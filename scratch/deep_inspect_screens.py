import json

with open("scratch/live_inspected_screens.json") as f:
    data = json.load(f)

screens_to_inspect = [
    "/chat/dashboard/",
    "/chat/chatbot/settings/",
    "/sales/",
    "/sales/offerings/new/",
    "/lead-capture/dashboard/",
    "/lead-engagement/settings/",
    "/chat/widget/",
    "/integrations/meta/",
    "/support/tickets/",
    "/accounts/company/update/",
    "/accounts/team/",
    "/accounts/security/api-keys/",
    "/billing/usage/",
    "/chat/api/docs/"
]

for route in screens_to_inspect:
    info = data.get(route)
    if not info:
        continue
    print("=" * 70)
    print(f"ROUTE: {route} | Title: {info.get('title')}")
    print("=" * 70)
    print("Headings:", info.get("headings"))
    forms = info.get("forms", [])
    print(f"Forms ({len(forms)}):")
    for idx, form in enumerate(forms):
        action = form.get("action")
        method = form.get("method")
        inputs = [inp.get("name") + f" ({inp.get('type')})" for inp in form.get("inputs", []) if inp.get("name") and inp.get("name") != "csrfmiddlewaretoken"]
        selects = [sel.get("name") + f" [options: {len(sel.get('options', []))}]" for sel in form.get("selects", []) if sel.get("name")]
        if inputs or selects:
            print(f"  Form #{idx+1} [method={method}, action={action}]:")
            if inputs:
                print(f"    Inputs: {', '.join(inputs)}")
            if selects:
                print(f"    Selects: {', '.join(selects)}")
    tables = info.get("tables", [])
    if tables:
        print(f"Tables ({len(tables)}):")
        for t in tables[:3]:
            print(f"  Headers: {t.get('headers')}")
            if t.get("rows_sample"):
                print(f"  Sample row: {t.get('rows_sample')[0]}")
    text_sample = info.get("text_sample", "")
    print("Sample Text:", text_sample[:250])
    print()
