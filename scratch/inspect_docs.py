import os, re
from html.parser import HTMLParser

class DocParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.headings = []
        self.paragraphs = []
        self.current_tag = None
        self.current_text = []

    def handle_starttag(self, tag, attrs):
        if tag in ["h1", "h2", "h3", "h4", "p", "li"]:
            self.current_tag = tag
            self.current_text = []

    def handle_endtag(self, tag):
        if self.current_tag == tag:
            text = " ".join("".join(self.current_text).split())
            if text:
                if tag in ["h1", "h2", "h3", "h4"]:
                    self.headings.append((tag, text))
                elif tag in ["p", "li"]:
                    self.paragraphs.append((self.headings[-1][1] if self.headings else "Intro", text))
            self.current_tag = None
            self.current_text = []

    def handle_data(self, data):
        if self.current_tag:
            self.current_text.append(data)

doc_files = {
    "Overview": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/103/content.md",
    "Chat Agent": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/113/content.md",
    "Sales Agent": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/124/content.md",
    "Lead Agent": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/126/content.md",
    "Lead Capture": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/128/content.md",
    "Lead Engagement": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/130/content.md",
    "Support Agent": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/132/content.md",
    "Knowledge Base": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/134/content.md",
    "Knowledge Sync API": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/136/content.md",
    "Offerings": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/138/content.md",
    "Find What I Sell": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/140/content.md",
    "Chat Widget": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/142/content.md",
    "Social Channels": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/144/content.md",
    "Server APIs": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/146/content.md",
    "Live Checkout": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/148/content.md",
    "Commerce API": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/150/content.md",
    "Security": "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/152/content.md",
}

for name, path in doc_files.items():
    if not os.path.exists(path):
        print(f"File not found: {path}")
        continue
    with open(path, "r", encoding="utf-8") as f:
        html = f.read()
    p = DocParser()
    p.feed(html)
    print(f"==================================================")
    print(f"DOC TOPIC: {name}")
    print(f"==================================================")
    # filter out navigation headings (like 'Agents', 'Your data', 'Channels', etc.)
    real_headings = [h for h in p.headings if h[1] not in ["Agents", "Your data", "Channels", "APIs", "Security", "Docs", "Getting started", "No matching topics"]]
    for tag, text in real_headings[:10]:
        print(f"  [{tag}] {text}")
    print("\nSample Paragraphs:")
    for h, para in p.paragraphs[:6]:
        if len(para) > 30 and not para.startswith("Central AI is") and not para.startswith("The AI assistant"):
            print(f"  * ({h}): {para[:140]}...")
    print()
