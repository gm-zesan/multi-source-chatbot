import os, re

base_dir = "docs/product-audit/central-ai"

patterns = {
    "BANT Qualification": r"\bBANT\b|multi-turn qualification|qualification interview|lead score \(0–100\)",
    "K=4 / 0.72 similarity": r"K\s*=\s*4|0\.72|Top-K Chunks\*\*:\s*4|~400 chars, 80 overlap",
    "MFS / TrxID in Chat": r"TrxID|bKash / Nagad TrxID|personal or merchant MFS number",
    "Lead Engagement Channels": r"Lead Engagement.*(?:WhatsApp|Instagram Direct|Meta Messenger)|(?:WhatsApp|Messenger).*Lead Engagement",
    "Old Quotas (1k/5k/20k)": r"1,000 knowledge records|5,000 knowledge records|20,000 knowledge records",
    "Auto Lead Capture All Plans": r"Auto Lead Capture.*All Plans|MOD-04.*All Plans",
    "False [OBSERVED] tags": r"`\[OBSERVED\]`",
}

for root, _, files in os.walk(base_dir):
    for f in sorted(files):
        if not f.endswith(".md"):
            continue
        rel = os.path.relpath(os.path.join(root, f), base_dir)
        full_path = os.path.join(root, f)
        with open(full_path, "r", encoding="utf-8") as file:
            content = file.read()
        
        findings = []
        for pat_name, pat in patterns.items():
            matches = list(re.finditer(pat, content, re.IGNORECASE))
            if matches:
                # get line numbers
                lines = []
                for m in matches:
                    line_no = content[:m.start()].count("\n") + 1
                    match_text = m.group(0)
                    lines.append((line_no, match_text))
                findings.append((pat_name, lines))
        
        if findings:
            print(f"=== {rel} ===")
            for pat_name, lines in findings:
                print(f"  [{pat_name}]: {len(lines)} occurrences")
                for lno, txt in lines[:5]:
                    # extract line content
                    line_content = content.splitlines()[lno-1].strip()
                    print(f"    Line {lno}: {line_content[:100]}")
            print()
