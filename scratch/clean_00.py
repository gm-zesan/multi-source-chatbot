import re

with open("docs/product-audit/central-ai/00_EXECUTIVE_SUMMARY.md", "r", encoding="utf-8") as f:
    text = f.read()

# Match the content between content_00 = """ and """
match = re.search(r'content_00\s*=\s*"""(.*?)"""', text, re.DOTALL)
if match:
    clean_content = match.group(1).strip() + "\n"
    with open("docs/product-audit/central-ai/00_EXECUTIVE_SUMMARY.md", "w", encoding="utf-8") as f:
        f.write(clean_content)
    print("Cleaned 00_EXECUTIVE_SUMMARY.md successfully")
else:
    print("Could not find content_00 pattern")
