from html.parser import HTMLParser

class TopicDumper(HTMLParser):
    def __init__(self):
        super().__init__()
        self.text = []
        self.record = False
    def handle_starttag(self, tag, attrs):
        if tag in ["h1", "h2", "h3", "h4", "p", "li", "pre", "code"]:
            self.record = True
    def handle_endtag(self, tag):
        if tag in ["h1", "h2", "h3", "h4", "p", "li", "pre", "code"]:
            self.record = False
    def handle_data(self, data):
        d = data.strip()
        if d and self.record:
            self.text.append(d)

def dump_file(title, path):
    print("=" * 60)
    print(title)
    print("=" * 60)
    with open(path) as f:
        html = f.read()
    p = TopicDumper()
    p.feed(html)
    content = " \n".join(p.text)
    idx = content.find("Docs \n")
    if idx != -1:
        content = content[idx:]
    print(content[:3500])
    print("\n...\n")

dump_file("LEAD ENGAGEMENT", "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/130/content.md")
dump_file("SUPPORT AGENT", "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/132/content.md")
dump_file("SECURITY & KEYS", "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/152/content.md")
dump_file("KNOWLEDGE BASE", "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/134/content.md")
dump_file("SERVER APIS", "/Users/zesan/.gemini/antigravity-ide/brain/c11edfcb-f50d-4903-a35c-689fac81cdb3/.system_generated/steps/146/content.md")
