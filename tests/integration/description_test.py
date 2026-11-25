"""Descriptions: editor override, then the meta description of the rendered page, then the excerpt, never the page content."""
from kit import *

reset_state()
set_env(no_cron=True, meta_desc=True)
site = anon()
LONG = "<p>This long body text must never appear in llms.txt. " + "More body words. " * 12 + "</p>"
BLOCKS = '<!-- wp:paragraph --><p>Block paragraph that must stay out.</p><!-- /wp:paragraph --><!-- wp:heading {"level":2} --><h2>Block heading</h2><!-- /wp:heading -->'


def mk(title, content=LONG, excerpt=None, **meta):
    args = ["post", "create", "--post_type=page", "--post_title=" + title, "--post_status=publish", "--post_content=" + content, "--porcelain"]
    if excerpt is not None:
        args.append("--post_excerpt=" + excerpt)
    pid = int(wpcli(*args).strip().splitlines()[-1])
    for k, v in meta.items():
        wpcli("post", "meta", "update", str(pid), k, v)
    return pid


def slug(title):
    return title.lower().replace(" ", "-")


def entry(title):
    for line in site.get("/llms.txt?t=%d" % time.time_ns())[2].splitlines():
        if line.startswith("- [") and ("/" + slug(title) + ".md)") in line:
            return line
    return None


def page_description(pid):
    return php("echo get_post_meta( %d, '_agentwelcome_page_description', true );" % pid)


ids = {}
for title, kwargs in {
    "Desc Override": dict(excerpt="Excerpt text.", kit_meta_desc="Meta text.", _agentwelcome_description="Editor text."),
    "Desc Meta": dict(excerpt="Excerpt text.", kit_meta_desc="Meta text &amp; more."),
    "Desc Entities": dict(kit_meta_desc="Tom & Jerry's \"show\""),
    "Desc Excerpt": dict(excerpt="Hand written excerpt."),
    "Desc None": dict(),
    "Desc Html": dict(excerpt="<b>Bold</b> [gallery] and &amp; more."),
    "Desc Saved Seo": dict(excerpt="Excerpt text.", _yoast_wpseo_metadesc="Saved Yoast text."),
    "Desc Late": dict(excerpt="Late excerpt.", kit_meta_desc="Late meta."),
}.items():
    ids[title] = mk(title, **kwargs)
ids["Desc Blocks"] = mk("Desc Blocks", content=BLOCKS)

try:
    print("--- a page without a stored copy uses the excerpt in llms.txt")
    check("excerpt before the page is generated", entry("Desc Meta").endswith(": Excerpt text."), entry("Desc Meta"))

    print("--- front matter and llms.txt follow the order")
    for title in ids:
        site.get("/" + slug(title) + ".md")
    c, h, body, _ = site.get("/desc-override.md")
    check("front matter takes the editor description", 'description: "Editor text."' in body, body[:300])
    check("llms.txt takes the editor description", entry("Desc Override").endswith(": Editor text."), entry("Desc Override"))
    c, h, body, _ = site.get("/desc-meta.md")
    check("front matter takes the meta description of the page", 'description: "Meta text & more."' in body, body[:300])
    check("llms.txt takes the stored meta description", entry("Desc Meta").endswith(": Meta text & more."), entry("Desc Meta"))
    check("the meta description is stored as post meta", page_description(ids["Desc Meta"]) == "Meta text & more.", page_description(ids["Desc Meta"]))
    check("entities in the tag are decoded", entry("Desc Entities").endswith(": Tom & Jerry's \"show\""), entry("Desc Entities"))
    check("the excerpt is the last source", entry("Desc Excerpt").endswith(": Hand written excerpt."), entry("Desc Excerpt"))
    check("HTML, shortcodes and entities in the excerpt are cleaned", entry("Desc Html").endswith(": Bold and & more."), entry("Desc Html"))
    check("a page without a meta description stores none", page_description(ids["Desc Excerpt"]) == "")
    check("a saved SEO plugin description is not read when the page prints none", entry("Desc Saved Seo").endswith(": Excerpt text."), entry("Desc Saved Seo"))

    print("--- the stored description follows the page")
    wpcli("post", "meta", "update", str(ids["Desc Meta"]), "kit_meta_desc", "Changed meta text.")
    wpcli("agentwelcome", "cache", "regenerate", str(ids["Desc Meta"]))
    check("regenerating stores the new meta description", entry("Desc Meta").endswith(": Changed meta text."), entry("Desc Meta"))
    wpcli("post", "meta", "delete", str(ids["Desc Meta"]), "kit_meta_desc")
    wpcli("agentwelcome", "cache", "regenerate", str(ids["Desc Meta"]))
    check("a page that stops printing one goes back to the excerpt", entry("Desc Meta").endswith(": Excerpt text.") and page_description(ids["Desc Meta"]) == "", entry("Desc Meta"))
    check("a page with a meta description has it stored", page_description(ids["Desc Late"]) == "Late meta.")
    wpcli("post", "update", str(ids["Desc Late"]), "--post_status=draft")
    check("removing the copy removes the stored description", page_description(ids["Desc Late"]) == "")

    print("--- never the page content")
    check("no excerpt and no meta description gives no description", entry("Desc None").endswith("(http://127.0.0.1:8899/desc-none.md)"), entry("Desc None"))
    check("block content gives no description", entry("Desc Blocks").endswith("(http://127.0.0.1:8899/desc-blocks.md)"), entry("Desc Blocks"))
    llms = site.get("/llms.txt")[2]
    check("body text and block markup never appear in llms.txt", "must never appear" not in llms and "Block paragraph" not in llms and "wp:paragraph" not in llms)
    for name in ("desc-none", "desc-blocks"):
        c, h, body, _ = site.get("/" + name + ".md")
        head = body.split("---")[1] if body.startswith("---") else body[:300]
        check(name + ": no description line, nothing taken from the page body", c == 200 and "description:" not in head and "must never appear" not in head and "wp:" not in head, head)

finally:
    for i in ids.values():
        wpcli("post", "delete", str(i), "--force")
    reset_state()
done("DESCRIPTION")
