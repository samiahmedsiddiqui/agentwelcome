"""A .md address that is not served gets the plugin's own plain text 404, even when another plugin redirects 404s."""
from kit import *

reset_state()
wpcli("option", "delete", "kit_redirect_404")
wpcli("option", "delete", "kit_answer_off")
site = anon()
n = [0]


def bust():
    n[0] += 1
    return "?t=%d%d" % (int(time.time() * 1000), n[0])


def get(path, **k):
    return site.get(path + bust(), follow=False, **k)


def is_plain_404(r, name):
    c, h, body, _ = r
    check(name + ": status 404", c == 404, c)
    check(name + ": plain text, not the theme page", h.get("content-type", "").startswith("text/plain") and "<html" not in body.lower() and "Not found" in body, (h.get("content-type"), body[:80]))
    check(name + ": not cacheable and noindex", "no-store" in h.get("cache-control", "") and h.get("x-robots-tag") == "noindex", h)


print("--- an address that matches no page")
is_plain_404(get("/nc-nf-nothing.md"), "unknown address")
is_plain_404(get("/12354.md"), "number address")

print("--- pages that are not served")
hidden = create_page("Nf Hidden", "<p>Hidden.</p>", post_name="nc-nf-hidden")
draft = create_page("Nf Draft", "<p>Draft.</p>", post_name="nc-nf-draft", post_status="draft")
protected = create_page("Nf Locked", "<p>Locked.</p>", post_name="nc-nf-locked", post_password="secret")
shown = create_page("Nf Shown", "<p>Shown.</p>", post_name="nc-nf-shown")
check("a served page is unaffected", get("/nc-nf-shown.md")[0] == 200)
wpcli("post", "meta", "update", str(hidden), "_agentwelcome_exclude", "1")
is_plain_404(get("/nc-nf-hidden.md"), "hidden page")
is_plain_404(get("/nc-nf-draft.md"), "draft")
is_plain_404(get("/nc-nf-locked.md"), "password protected page")

print("--- HEAD and the other files")
c, h, body, _ = site.get("/nc-nf-hidden.md" + bust(), follow=False, method="HEAD")
check("HEAD gets the 404 without a body", c == 404 and body == "", (c, body[:40]))
check("llms.txt and a served page are still served", site.get("/llms.txt" + bust())[0] == 200 and get("/nc-nf-shown.md")[0] == 200)
check("the real page is not touched", site.get("/nc-nf-hidden/")[0] == 200)

print("--- another plugin redirects every 404 to the homepage")
wpcli("option", "update", "kit_redirect_404", "1")
r = site.get("/this-is-not-a-page/" + bust(), follow=False)
check("the other plugin does redirect a normal 404 (test setup)", r[0] == 301, r[0])
is_plain_404(get("/nc-nf-nothing.md"), "unknown address with that plugin")
is_plain_404(get("/nc-nf-hidden.md"), "hidden page with that plugin")
wpcli("option", "update", "kit_answer_off", "1")
r = get("/nc-nf-nothing.md")
check("with the filter off the request is left to the other plugin", r[0] == 301, r[0])
wpcli("option", "delete", "kit_answer_off")
wpcli("option", "delete", "kit_redirect_404")

done("not found")
