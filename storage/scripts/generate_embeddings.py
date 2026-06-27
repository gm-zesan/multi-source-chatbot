"""
Multi-Source Chatbot — Embedding Generator
============================================

Generates 384-dimension vector embeddings for databases, tables, and aliases
using sentence-transformers (all-MiniLM-L6-v2) and stores them directly
into the Laravel `embeddings` table.

Features:
  - Reads source configuration and registry tables directly from MySQL
  - Batch processes texts for maximum throughput
  - Caches embeddings in Redis to avoid regenerating identical texts
  - Progress tracking with detailed logging
  - Supports incremental (skip existing) and full refresh modes
  - Scoped generation per source (--source flag)

Dependencies:
    pip install sentence-transformers torch numpy mysql-connector-python redis

Environment Variables (.env):
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=chatbot_core
    DB_USERNAME=root
    DB_PASSWORD=
    REDIS_HOST=127.0.0.1
    REDIS_PORT=6379
    REDIS_PASSWORD=
    CHATBOT_EMBEDDING_MODEL=sentence-transformers/all-MiniLM-L6-v2

Usage:
    # Full generation (incremental - skips existing)
    python generate_embeddings.py

    # Force regenerate everything
    python generate_embeddings.py --refresh

    # Regenerate only db_01
    python generate_embeddings.py --source db_01

    # Regenerate only tables
    python generate_embeddings.py --type table

    # Dry run (show what would be generated)
    python generate_embeddings.py --dry-run
"""

import json
import sys
import os
import time
import argparse
import logging
from datetime import datetime
from typing import Optional

# ─── Logging Setup ───────────────────────────────────────────────────────────

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger("embeddings")


# ─── Configuration ───────────────────────────────────────────────────────────

class Config:
    """Read configuration from environment variables with sensible defaults."""

    DB_HOST = os.getenv("DB_HOST", "127.0.0.1")
    DB_PORT = int(os.getenv("DB_PORT", "3306"))
    DB_DATABASE = os.getenv("DB_DATABASE", "chatbot_core")
    DB_USERNAME = os.getenv("DB_USERNAME", "root")
    DB_PASSWORD = os.getenv("DB_PASSWORD", "")

    REDIS_HOST = os.getenv("REDIS_HOST", "127.0.0.1")
    REDIS_PORT = int(os.getenv("REDIS_PORT", "6379"))
    REDIS_PASSWORD = os.getenv("REDIS_PASSWORD", "")
    REDIS_DB = int(os.getenv("REDIS_DB", "0"))

    MODEL_NAME = os.getenv(
        "CHATBOT_EMBEDDING_MODEL",
        "sentence-transformers/all-MiniLM-L6-v2",
    )
    BATCH_SIZE = int(os.getenv("CHATBOT_BATCH_SIZE", "64"))
    CACHE_TTL = int(os.getenv("CHATBOT_VECTOR_CACHE_TTL", "86400"))
    CACHE_PREFIX = os.getenv("CHATBOT_CACHE_PREFIX", "embedding:")


# ─── Database Layer ──────────────────────────────────────────────────────────

class Database:
    """Handle MySQL connections and queries for the chatbot_core database."""

    def __init__(self, config: Config):
        self.config = config
        self.connection = None

    def connect(self):
        """Establish MySQL connection with error handling."""
        try:
            import mysql.connector
            self.connection = mysql.connector.connect(
                host=self.config.DB_HOST,
                port=self.config.DB_PORT,
                database=self.config.DB_DATABASE,
                user=self.config.DB_USERNAME,
                password=self.config.DB_PASSWORD,
                charset="utf8mb4",
                collation="utf8mb4_unicode_ci",
            )
            logger.info(
                "Connected to MySQL: %s@%s:%s/%s",
                self.config.DB_USERNAME,
                self.config.DB_HOST,
                self.config.DB_PORT,
                self.config.DB_DATABASE,
            )
        except ImportError:
            logger.error(
                "mysql-connector-python not installed. "
                "Run: pip install mysql-connector-python"
            )
            sys.exit(1)
        except Exception as e:
            logger.error("MySQL connection failed: %s", e)
            sys.exit(1)

    def close(self):
        """Close the database connection."""
        if self.connection and self.connection.is_connected():
            self.connection.close()
            logger.info("MySQL connection closed.")

    def fetch_sources(self) -> list[dict]:
        """Fetch distinct source IDs with their unique table names.

        Groups rows by table_name to handle split-alias storage
        (one row per alias instead of comma-separated).

        Returns list of dicts with keys: source_id, tables (unique list).
        """
        cursor = self.connection.cursor(dictionary=True)
        try:
            cursor.execute("""
                SELECT DISTINCT source_id
                FROM source_tables
                ORDER BY source_id
            """)
            rows = cursor.fetchall()
            sources = []
            for row in rows:
                sid = row["source_id"]
                # Get unique table names for this source
                cursor.execute(
                    "SELECT DISTINCT table_name FROM source_tables WHERE source_id = %s ORDER BY table_name",
                    (sid,),
                )
                tables = list(dict.fromkeys(r["table_name"] for r in cursor.fetchall()))
                sources.append({
                    "source_id": sid,
                    "tables": tables,
                })
            return sources
        finally:
            cursor.close()

    def fetch_tables(self, source_id: Optional[str] = None) -> list[dict]:
        """Fetch tables with aggregated aliases (handles split-alias storage).

        The source_tables table may store aliases as one row per alias
        (e.g., 4 rows for customers, each with a different alias).
        This method groups by table_name and merges all aliases.

        Returns list of dicts with keys: table_name, source_id, alias (merged).
        """
        cursor = self.connection.cursor(dictionary=True)
        try:
            if source_id:
                cursor.execute(
                    """
                    SELECT table_name, source_id,
                           GROUP_CONCAT(DISTINCT alias ORDER BY alias SEPARATOR ',') AS alias
                    FROM source_tables
                    WHERE source_id = %s
                    GROUP BY table_name, source_id
                    ORDER BY table_name
                    """,
                    (source_id,),
                )
            else:
                cursor.execute("""
                    SELECT table_name, source_id,
                           GROUP_CONCAT(DISTINCT alias ORDER BY alias SEPARATOR ',') AS alias
                    FROM source_tables
                    GROUP BY table_name, source_id
                    ORDER BY source_id, table_name
                """)
            return cursor.fetchall()
        finally:
            cursor.close()

    def fetch_columns(self, table_name: str) -> list[str]:
        """Fetch column names for a given table from source_table_columns."""
        cursor = self.connection.cursor(dictionary=True)
        try:
            cursor.execute(
                "SELECT column_name FROM source_table_columns WHERE table_name = %s",
                (table_name,),
            )
            return [row["column_name"] for row in cursor.fetchall()]
        finally:
            cursor.close()

    def embedding_exists(self, entity_type: str, entity_key: str) -> bool:
        """Check if an embedding already exists for this entity."""
        cursor = self.connection.cursor()
        try:
            cursor.execute(
                "SELECT COUNT(*) FROM embeddings WHERE entity_type = %s AND entity_key = %s",
                (entity_type, entity_key),
            )
            return cursor.fetchone()[0] > 0
        finally:
            cursor.close()

    def save_embedding(
        self,
        entity_type: str,
        entity_name: str,
        entity_key: str,
        source_id: Optional[str],
        embedding: list,
        metadata: dict,
    ):
        """Insert or update an embedding record."""
        cursor = self.connection.cursor()
        try:
            now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            cursor.execute(
                """
                INSERT INTO embeddings
                    (entity_type, entity_name, entity_key, source_id,
                     embedding, metadata, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    embedding = VALUES(embedding),
                    metadata = VALUES(metadata),
                    updated_at = VALUES(updated_at)
                """,
                (
                    entity_type,
                    entity_name,
                    entity_key,
                    source_id,
                    json.dumps(embedding),
                    json.dumps(metadata) if metadata else None,
                    now,
                    now,
                ),
            )
            self.connection.commit()
        finally:
            cursor.close()

    def count_embeddings(self, entity_type: Optional[str] = None) -> int:
        """Count embeddings, optionally filtered by type."""
        cursor = self.connection.cursor()
        try:
            if entity_type:
                cursor.execute(
                    "SELECT COUNT(*) FROM embeddings WHERE entity_type = %s",
                    (entity_type,),
                )
            else:
                cursor.execute("SELECT COUNT(*) FROM embeddings")
            return cursor.fetchone()[0]
        finally:
            cursor.close()

    def delete_embeddings(self, entity_type: Optional[str] = None,
                          source_id: Optional[str] = None):
        """Delete embeddings, optionally filtered."""
        cursor = self.connection.cursor()
        try:
            conditions = []
            params = []
            if entity_type:
                conditions.append("entity_type = %s")
                params.append(entity_type)
            if source_id:
                conditions.append("source_id = %s")
                params.append(source_id)

            if conditions:
                sql = "DELETE FROM embeddings WHERE " + " AND ".join(conditions)
                cursor.execute(sql, tuple(params))
            else:
                cursor.execute("DELETE FROM embeddings")

            self.connection.commit()
            logger.info("Deleted %s embedding records.", cursor.rowcount)
        finally:
            cursor.close()


# ─── Cache Layer (Redis) ─────────────────────────────────────────────────────

class EmbeddingCache:
    """Redis cache for generated embeddings to avoid recomputing identical texts."""

    def __init__(self, config: Config):
        self.config = config
        self.client = None

    def connect(self):
        """Connect to Redis with fallback to no-op if unavailable."""
        try:
            import redis
            self.client = redis.Redis(
                host=self.config.REDIS_HOST,
                port=self.config.REDIS_PORT,
                password=self.config.REDIS_PASSWORD or None,
                db=self.config.REDIS_DB,
                decode_responses=True,
                socket_connect_timeout=2,
            )
            self.client.ping()
            logger.info("Connected to Redis: %s:%s", self.config.REDIS_HOST, self.config.REDIS_PORT)
        except ImportError:
            logger.warning("redis-py not installed. Caching disabled.")
            self.client = None
        except Exception as e:
            logger.warning("Redis unavailable, proceeding without cache: %s", e)
            self.client = None

    def get(self, text: str) -> Optional[list]:
        """Get cached embedding for text. Returns None if not found."""
        if not self.client:
            return None
        key = self.config.CACHE_PREFIX + self._hash(text)
        try:
            data = self.client.get(key)
            if data:
                return json.loads(data)
        except Exception:
            pass
        return None

    def set(self, text: str, embedding: list):
        """Cache an embedding vector with TTL."""
        if not self.client:
            return
        key = self.config.CACHE_PREFIX + self._hash(text)
        try:
            self.client.setex(key, self.config.CACHE_TTL, json.dumps(embedding))
        except Exception:
            pass

    def get_batch(self, texts: list[str]) -> dict:
        """Get cached embeddings for multiple texts.

        Returns dict mapping text index to cached embedding.
        """
        if not self.client:
            return {}
        results = {}
        for i, text in enumerate(texts):
            cached = self.get(text)
            if cached is not None:
                results[i] = cached
        return results

    def set_batch(self, texts: list[str], embeddings: list[list]):
        """Cache multiple embeddings at once."""
        for text, emb in zip(texts, embeddings):
            self.set(text, emb)

    def clear(self):
        """Clear all cached embeddings with the configured prefix."""
        if not self.client:
            return
        try:
            cursor = 0
            deleted = 0
            while True:
                cursor, keys = self.client.scan(
                    cursor, match=self.config.CACHE_PREFIX + "*", count=100
                )
                if keys:
                    self.client.delete(*keys)
                    deleted += len(keys)
                if cursor == 0:
                    break
            logger.info("Cleared %s cached embeddings from Redis.", deleted)
        except Exception as e:
            logger.warning("Failed to clear Redis cache: %s", e)

    def _hash(self, text: str) -> str:
        """Create a consistent hash key for a text."""
        import hashlib
        return hashlib.md5(text.encode("utf-8")).hexdigest()


# ─── Embedding Engine ────────────────────────────────────────────────────────

class EmbeddingEngine:
    """Core embedding generation using sentence-transformers."""

    def __init__(self, config: Config, cache: EmbeddingCache):
        self.config = config
        self.cache = cache
        self.model = None

    def load_model(self):
        """Lazy-load the sentence-transformers model."""
        if self.model is not None:
            return
        try:
            from sentence_transformers import SentenceTransformer
            logger.info("Loading model: %s ...", self.config.MODEL_NAME)
            t0 = time.time()
            self.model = SentenceTransformer(self.config.MODEL_NAME)
            elapsed = time.time() - t0
            logger.info("Model loaded in %.2fs (384 dimensions).", elapsed)
        except ImportError:
            logger.error(
                "sentence-transformers not installed. "
                "Run: pip install sentence-transformers torch numpy"
            )
            sys.exit(1)

    def embed(self, text: str) -> list:
        """Generate embedding for a single text, checking cache first."""
        # Check cache
        cached = self.cache.get(text)
        if cached is not None:
            return cached

        # Generate
        self.load_model()
        vector = self.model.encode(text, normalize_embeddings=True).tolist()

        # Cache
        self.cache.set(text, vector)

        return vector

    def embed_batch(self, texts: list[str]) -> list[list]:
        """Generate embeddings for multiple texts in batch, with caching."""
        if not texts:
            return []

        # Check cache for each text
        cached = self.cache.get_batch(texts)
        results = [None] * len(texts)
        uncached_indices = []
        uncached_texts = []

        for i, text in enumerate(texts):
            if i in cached:
                results[i] = cached[i]
            else:
                uncached_indices.append(i)
                uncached_texts.append(text)

        # Generate embeddings for uncached texts
        if uncached_texts:
            self.load_model()
            t0 = time.time()
            vectors = self.model.encode(
                uncached_texts,
                normalize_embeddings=True,
                batch_size=self.config.BATCH_SIZE,
                show_progress_bar=True,
            ).tolist()
            elapsed = time.time() - t0
            logger.debug("Batch encoded %d texts in %.2fs.", len(uncached_texts), elapsed)

            # Store results and cache
            for idx, vector in zip(uncached_indices, vectors):
                results[idx] = vector
                self.cache.set(texts[idx], vector)

        return results


# ─── Entity Builders ─────────────────────────────────────────────────────────

class EntityBuilder:
    """Builds semantic text descriptions for each entity type."""

    @staticmethod
    def build_database_text(source_id: str, tables: list[str],
                            columns_by_table: dict[str, list[str]]) -> str:
        """Build descriptive text for a database source."""
        parts = [f"Database {source_id}"]
        if tables:
            parts.append(f"contains tables: {', '.join(tables)}")
        # Add columns from all tables
        all_columns = set()
        for cols in columns_by_table.values():
            all_columns.update(cols)
        if all_columns:
            parts.append(f"with columns: {', '.join(sorted(all_columns))}")
        return ". ".join(parts) + "."

    @staticmethod
    def build_table_text(table_name: str, columns: list[str],
                         aliases: list[str]) -> str:
        """Build descriptive text for a table."""
        parts = [f"Table {table_name}"]
        if aliases:
            parts.append(f"also known as: {', '.join(aliases)}")
        if columns:
            parts.append(f"has columns: {', '.join(columns)}")
        return ". ".join(parts) + "."

    @staticmethod
    def build_alias_text(alias: str, table_name: str, source_id: str) -> str:
        """Build descriptive text for a table alias."""
        return (
            f"{alias} refers to the {table_name} table "
            f"in {source_id}. It contains data about {table_name}."
        )


# ─── Generator ───────────────────────────────────────────────────────────────

class EmbeddingGenerator:
    """Orchestrates the full embedding generation process."""

    def __init__(self, db: Database, engine: EmbeddingEngine, config: Config,
                 refresh: bool = False, source_filter: Optional[str] = None,
                 type_filter: Optional[str] = None, dry_run: bool = False):
        self.db = db
        self.engine = engine
        self.config = config
        self.refresh = refresh
        self.source_filter = source_filter
        self.type_filter = type_filter
        self.dry_run = dry_run
        self.stats = {"generated": 0, "skipped": 0, "errors": 0}

    def run(self) -> dict:
        """Execute the full generation pipeline."""
        start_time = time.time()
        logger.info("=" * 60)
        logger.info("Embedding Generator Started")
        logger.info("  Refresh: %s", self.refresh)
        logger.info("  Source filter: %s", self.source_filter or "all")
        logger.info("  Type filter: %s", self.type_filter or "all")
        logger.info("  Dry run: %s", self.dry_run)
        logger.info("=" * 60)

        # If refreshing, clear old embeddings first
        if self.refresh and not self.dry_run:
            self.db.delete_embeddings(
                entity_type=self.type_filter,
                source_id=self.source_filter,
            )

        # Generate each entity type
        if not self.type_filter or self.type_filter == "database":
            self._generate_databases()

        if not self.type_filter or self.type_filter == "table":
            self._generate_tables()

        if not self.type_filter or self.type_filter == "alias":
            self._generate_aliases()

        elapsed = time.time() - start_time
        logger.info("=" * 60)
        logger.info(
            "Finished: %d generated, %d skipped, %d errors in %.2fs",
            self.stats["generated"],
            self.stats["skipped"],
            self.stats["errors"],
            elapsed,
        )
        logger.info("Total embeddings in DB: %d", self.db.count_embeddings())
        logger.info("=" * 60)

        return self.stats

    def _generate_databases(self):
        """Generate embeddings for each database source."""
        logger.info("\n--- Database Embeddings ---")
        sources = self.db.fetch_sources()

        # Filter by source if specified
        if self.source_filter:
            sources = [s for s in sources if s["source_id"] == self.source_filter]

        if not sources:
            logger.warning("No database sources found.")
            return

        # Pre-fetch columns for all tables
        for source in sources:
            columns_by_table = {}
            for table_name in source["tables"]:
                columns_by_table[table_name] = self.db.fetch_columns(table_name)

            text = EntityBuilder.build_database_text(
                source["source_id"], source["tables"], columns_by_table
            )

            entity_key = source["source_id"]

            # Check if already exists
            if not self.refresh and self.db.embedding_exists("database", entity_key):
                logger.info("  ⏭  [database] %s (already exists)", entity_key)
                self.stats["skipped"] += 1
                continue

            if self.dry_run:
                logger.info("  🔄 [DRY RUN] Would generate embedding for: %s", entity_key)
                logger.debug("    Text: %s", text[:200])
                self.stats["generated"] += 1
                continue

            try:
                logger.info("  🔄 [database] %s ...", entity_key)
                vector = self.engine.embed(text)
                self.db.save_embedding(
                    entity_type="database",
                    entity_name=source["source_id"],
                    entity_key=entity_key,
                    source_id=source["source_id"],
                    embedding=vector,
                    metadata={
                        "tables": source["tables"],
                        "generated_by": "generate_embeddings.py",
                        "generated_at": datetime.now().isoformat(),
                    },
                )
                self.stats["generated"] += 1
                logger.info("    ✅ %s (%d dimensions)", entity_key, len(vector))
            except Exception as e:
                logger.error("    ❌ Failed to generate embedding for %s: %s", entity_key, e)
                self.stats["errors"] += 1

    def _generate_tables(self):
        """Generate embeddings for each registered table."""
        logger.info("\n--- Table Embeddings ---")
        tables = self.db.fetch_tables(self.source_filter)

        if not tables:
            logger.warning("No tables found.")
            return

        # Build batch data
        batch_data = []
        for table in tables:
            columns = self.db.fetch_columns(table["table_name"])
            aliases = [
                a.strip() for a in (table.get("alias") or "").split(",") if a.strip()
            ]
            text = EntityBuilder.build_table_text(
                table["table_name"], columns, aliases
            )
            batch_data.append({
                "table": table,
                "columns": columns,
                "aliases": aliases,
                "text": text,
            })

        # Process in batches
        total = len(batch_data)
        for i in range(0, total, self.config.BATCH_SIZE):
            batch = batch_data[i:i + self.config.BATCH_SIZE]

            for item in batch:
                entity_key = item["table"]["table_name"]

                if not self.refresh and self.db.embedding_exists("table", entity_key):
                    logger.info("  ⏭  [table] %s (already exists)", entity_key)
                    self.stats["skipped"] += 1
                    continue

                if self.dry_run:
                    logger.info("  🔄 [DRY RUN] Would generate embedding for: %s", entity_key)
                    self.stats["generated"] += 1
                    continue

            # Generate embeddings for uncached items in this batch
            uncached = [item for item in batch
                        if self.refresh
                        or not self.db.embedding_exists("table", item["table"]["table_name"])]

            if not uncached:
                continue

            if self.dry_run:
                continue

            texts = [item["text"] for item in uncached]
            try:
                vectors = self.engine.embed_batch(texts)
                for item, vector in zip(uncached, vectors):
                    entity_key = item["table"]["table_name"]
                    self.db.save_embedding(
                        entity_type="table",
                        entity_name=item["table"]["table_name"],
                        entity_key=entity_key,
                        source_id=item["table"]["source_id"],
                        embedding=vector,
                        metadata={
                            "columns": item["columns"],
                            "aliases": item["aliases"],
                            "generated_by": "generate_embeddings.py",
                            "generated_at": datetime.now().isoformat(),
                        },
                    )
                    self.stats["generated"] += 1
                    logger.info("    ✅ [table] %s (%d dimensions)", entity_key, len(vector))
            except Exception as e:
                logger.error("    ❌ Batch table embedding failed: %s", e)
                self.stats["errors"] += len(uncached)

    def _generate_aliases(self):
        """Generate embeddings for table aliases."""
        logger.info("\n--- Alias Embeddings ---")
        tables = self.db.fetch_tables(self.source_filter)

        alias_data = []
        for table in tables:
            aliases = [
                a.strip() for a in (table.get("alias") or "").split(",") if a.strip()
            ]
            for alias in aliases:
                alias_key = f"{table['table_name']}:alias:{alias}"
                text = EntityBuilder.build_alias_text(
                    alias, table["table_name"], table["source_id"]
                )
                alias_data.append({
                    "alias": alias,
                    "table_name": table["table_name"],
                    "source_id": table["source_id"],
                    "alias_key": alias_key,
                    "text": text,
                })

        if not alias_data:
            logger.info("  No aliases found.")
            return

        # Process in batches
        for i in range(0, len(alias_data), self.config.BATCH_SIZE):
            batch = alias_data[i:i + self.config.BATCH_SIZE]

            for item in batch:
                if not self.refresh and self.db.embedding_exists("alias", item["alias_key"]):
                    logger.info("  ⏭  [alias] %s → %s (already exists)",
                                item["alias"], item["table_name"])
                    self.stats["skipped"] += 1
                    continue

                if self.dry_run:
                    logger.info("  🔄 [DRY RUN] Would generate embedding for alias: %s → %s",
                                item["alias"], item["table_name"])
                    self.stats["generated"] += 1
                    continue

            # Generate for uncached items
            uncached = [item for item in batch
                        if self.refresh
                        or not self.db.embedding_exists("alias", item["alias_key"])]

            if not uncached or self.dry_run:
                continue

            texts = [item["text"] for item in uncached]
            try:
                vectors = self.engine.embed_batch(texts)
                for item, vector in zip(uncached, vectors):
                    self.db.save_embedding(
                        entity_type="alias",
                        entity_name=item["alias"],
                        entity_key=item["alias_key"],
                        source_id=item["source_id"],
                        embedding=vector,
                        metadata={
                            "alias": item["alias"],
                            "canonical_table": item["table_name"],
                            "generated_by": "generate_embeddings.py",
                            "generated_at": datetime.now().isoformat(),
                        },
                    )
                    self.stats["generated"] += 1
                    logger.info("    ✅ [alias] %s → %s (%d dimensions)",
                                item["alias"], item["table_name"], len(vector))
            except Exception as e:
                logger.error("    ❌ Batch alias embedding failed: %s", e)
                self.stats["errors"] += len(uncached)


# ─── Main Entry Point ────────────────────────────────────────────────────────

def parse_args() -> argparse.Namespace:
    """Parse command-line arguments."""
    parser = argparse.ArgumentParser(
        description="Generate embeddings for Multi-Source Chatbot",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Generate/update all embeddings (from Laravel command)
  python generate_embeddings.py

  # Single text embedding (called by VectorEmbeddingService)
  python generate_embeddings.py "show customers"

  # Regenerate everything
  python generate_embeddings.py --refresh

  # Scoped generation
  python generate_embeddings.py --source db_01
  python generate_embeddings.py --type table
  python generate_embeddings.py --dry-run
  python generate_embeddings.py --refresh --source db_02
        """,
    )
    parser.add_argument(
        "text", nargs="?", default=None,
        help="Single text to embed (used by VectorEmbeddingService for on-the-fly queries).",
    )
    parser.add_argument(
        "--refresh", action="store_true",
        help="Force regenerate all embeddings, replacing existing ones.",
    )
    parser.add_argument(
        "--source", type=str, default=None,
        help="Only process this specific source (e.g., db_01).",
    )
    parser.add_argument(
        "--type", type=str, default=None,
        choices=["database", "table", "alias"],
        help="Only process this entity type.",
    )
    parser.add_argument(
        "--dry-run", action="store_true",
        help="Show what would be generated without actually doing it.",
    )
    parser.add_argument(
        "--batch-size", type=int, default=None,
        help="Batch size for embedding generation (default: 64).",
    )
    return parser.parse_args()


def embed_single_text(text: str, engine: EmbeddingEngine):
    """Embed a single text and print the result as JSON to stdout.

    Used by VectorEmbeddingService for on-the-fly query embedding.
    """
    vector = engine.embed(text)
    print(json.dumps(vector))


def main():
    """Main entry point."""
    args = parse_args()

    # Configuration
    config = Config()
    if args.batch_size:
        config.BATCH_SIZE = args.batch_size

    # Cache layer
    cache = EmbeddingCache(config)
    cache.connect()

    # Embedding engine
    engine = EmbeddingEngine(config, cache)

    # ── Single-text mode (called by VectorEmbeddingService) ──
    if args.text and not args.refresh and not args.source and not args.type:
        embed_single_text(args.text, engine)
        return

    # Database layer (only needed for batch generation)
    db = Database(config)
    db.connect()

    # Clear Redis cache if refreshing
    if args.refresh:
        logger.info("Clearing embedding cache in Redis...")
        cache.clear()

    try:
        # Run generator
        generator = EmbeddingGenerator(
            db=db,
            engine=engine,
            config=config,
            refresh=args.refresh,
            source_filter=args.source,
            type_filter=args.type,
            dry_run=args.dry_run,
        )
        stats = generator.run()

        # Exit with appropriate code
        if stats["errors"] > 0:
            sys.exit(1)
        sys.exit(0)

    except KeyboardInterrupt:
        logger.info("\nInterrupted by user.")
        sys.exit(130)
    except Exception as e:
        logger.error("Fatal error: %s", e, exc_info=True)
        sys.exit(1)
    finally:
        db.close()


if __name__ == "__main__":
    main()
