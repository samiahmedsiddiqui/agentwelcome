"""A normal page address returns Markdown when the client prefers it and the setting is on."""
from kit import *

reset_state()
site = anon()
n = [0]
MD = "text/markdown"


def get(path, accept=None, method=None):
    n[0] += 1
    h = {"Accept": accept} if accept else {}
    return site.get(path + "?t=%d%d" % (int(time.time() * 1000), n[0]), headers=h, method=method, follow=False)


page = create_page("Acc Page", "<p>Negotiated body.</p>", post_name="nc-acc-page")
hidden = create_page("Acc Hidden", "<p>Hidden.</p>", post_name="nc-acc-hidden")
wpcli("post", "meta", "update", str(hidden), "_agentwelcome_exclude", "1")
get("/nc-acc-page.md")

print("--- setting off")
c, h, body, _ = get("/nc-acc-page/", MD)
check("off: HTML", c == 200 and "<html" in body.lower() and h.get("content-type", "").startswith("text/html"), (c, h.get("content-type")))

wpcli("option", "update", "agentwelcome_settings", '{"markdown_by_accept":true}', "--format=json")

print("--- setting on")
c, h, body, _ = get("/nc-acc-page/", MD)
check("markdown for text/markdown", c == 200 and h.get("content-type", "").startswith(MD) and "Negotiated body." in body, (c, h.get("content-type")))
check("same body as the .md address", body == get("/nc-acc-page.md")[2])
check("Vary: Accept", "accept" in h.get("vary", "").lower(), h)
check("private, no-store", "no-store" in h.get("cache-control", "") and "private" in h.get("cache-control", "") and "public" not in h.get("cache-control", ""), h.get("cache-control"))
check("canonical link to the HTML page", "rel=\"canonical\"" in h.get("link", "") and "/nc-acc-page/" in h.get("link", ""), h.get("link"))
c, h, body, _ = get("/nc-acc-page/", MD, "HEAD")
check("HEAD: no body", c == 200 and body == "" and h.get("content-type", "").startswith(MD), (c, body[:30]))

print("--- clients that do not ask for Markdown")
for name, accept in (
    ("browser", "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"),
    ("star", "*/*"),
    ("text star", "text/*"),
    ("html first", "text/html, text/markdown;q=0.5"),
    ("none", None),
):
    c, h, body, _ = get("/nc-acc-page/", accept)
    check(name + ": HTML", c == 200 and h.get("content-type", "").startswith("text/html") and "<html" in body.lower(), (c, h.get("content-type")))

print("--- pages that are not served, and other requests")
c, h, body, _ = get("/nc-acc-hidden/", MD)
check("excluded page: HTML, not 404", c == 200 and h.get("content-type", "").startswith("text/html"), (c, h.get("content-type")))
c, h, body, _ = get("/nc-acc-nothing/", MD)
check("unknown address: the usual WordPress 404", c == 404 and "<html" in body.lower(), c)
c, h, body, _ = get("/?s=probe", MD)
check("search: HTML", c == 200 and h.get("content-type", "").startswith("text/html"), (c, h.get("content-type")))
c, h, body, _ = get("/wp-json/", MD)
check("REST: JSON", c == 200 and h.get("content-type", "").startswith("application/json"), (c, h.get("content-type")))

wpcli("option", "delete", "agentwelcome_settings")
done("accept header")
