import time
import os
import glob
import multiprocessing as mp
import resource
from sentence_transformers import SentenceTransformer

def worker_task(worker_id: int, queries: list[str], barrier, results_queue):
    hub_path = os.path.expanduser('~/.cache/huggingface/hub/models--sentence-transformers--paraphrase-multilingual-mpnet-base-v2/snapshots/*')
    snapshots = glob.glob(hub_path)
    
    t0 = time.time()
    model = SentenceTransformer(snapshots[0])
    load_time = time.time() - t0
    model.encode('warmup')
    
    # Wait for all workers to be loaded and warmed up before encoding together
    barrier.wait()
    
    t_start = time.time()
    for q in queries:
        model.encode(q, normalize_embeddings=True)
    encode_time = (time.time() - t_start) * 1000
    
    rss = resource.getrusage(resource.RUSAGE_SELF).ru_maxrss / (1024 * 1024)
    results_queue.put({
        'worker_id': worker_id,
        'load_time_s': load_time,
        'encode_time_ms': encode_time,
        'rss_mb': rss,
        'device': str(model.device),
    })

def run_benchmark():
    print("=== PyTorch MPS Multi-Worker Contention Benchmark ===")
    queries = [
        'ডেলিভারি চার্জ কত?',
        'রিটার্ন পলিসি কি?',
        'ক্যাশ অন ডেলিভারি আছে?',
        'ঢাকার বাইরে ডেলিভারি কত দিন লাগে?',
        'প্রোডাক্ট এক্সচেঞ্জ কিভাবে করব?',
    ]
    
    # 1 Worker Test
    print("\n--- Testing Single Worker ---")
    b1 = mp.Barrier(1)
    q1 = mp.Queue()
    p1 = mp.Process(target=worker_task, args=(1, queries, b1, q1))
    p1.start()
    p1.join()
    r1 = q1.get()
    print(f"Single Worker: Device={r1['device']}, Load={r1['load_time_s']:.2f}s, RSS={r1['rss_mb']:.2f}MB, 5 Queries={r1['encode_time_ms']:.2f}ms (Avg={r1['encode_time_ms']/5:.2f}ms/query)")
    
    # 2 Workers Concurrent MPS Test
    print("\n--- Testing 2 Concurrent Workers (Simultaneous MPS Execution) ---")
    b2 = mp.Barrier(2)
    q2 = mp.Queue()
    t_start_2w = time.time()
    w1 = mp.Process(target=worker_task, args=(1, queries, b2, q2))
    w2 = mp.Process(target=worker_task, args=(2, queries, b2, q2))
    w1.start()
    w2.start()
    w1.join()
    w2.join()
    wall_2w = (time.time() - t_start_2w) * 1000
    
    res_w1 = q2.get()
    res_w2 = q2.get()
    print(f"Worker 1: Load={res_w1['load_time_s']:.2f}s, RSS={res_w1['rss_mb']:.2f}MB, 5 Queries={res_w1['encode_time_ms']:.2f}ms")
    print(f"Worker 2: Load={res_w2['load_time_s']:.2f}s, RSS={res_w2['rss_mb']:.2f}MB, 5 Queries={res_w2['encode_time_ms']:.2f}ms")
    print(f"Total Memory for 2 Workers: {res_w1['rss_mb'] + res_w2['rss_mb']:.2f} MB")
    print(f"Concurrent Wall Clock: {max(res_w1['encode_time_ms'], res_w2['encode_time_ms']):.2f} ms")
    
    # Contention Analysis
    single_avg = r1['encode_time_ms'] / 5
    concurrent_avg = max(res_w1['encode_time_ms'], res_w2['encode_time_ms']) / 5
    slowdown_ratio = concurrent_avg / single_avg if single_avg > 0 else 1.0
    print(f"\nMPS Contention Factor: {slowdown_ratio:.2f}x (Single: {single_avg:.2f}ms vs Dual: {concurrent_avg:.2f}ms per query)")
    print("=====================================================")

if __name__ == '__main__':
    mp.set_start_method('spawn', force=True)
    run_benchmark()
