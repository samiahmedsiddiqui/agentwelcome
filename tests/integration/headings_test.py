"""Elements to write as headings: saving, the Markdown it gives, the admin text, and the Verify override."""
from kit import *

reset_state()
admin = Client()
check("logged in", admin.login())
site = anon()
SETTINGS = "/wp-admin/admin.php?page=agentwelcome"
RULES = ".egs-title => h3\n.hero-title, .big-title =>H2\n.no-level\n.bad => h9\np:hover => h3"


def save(headings):
    page = admin.get(SETTINGS)[2]
    form = {"option_page": "agentwelcome_group", "action": "update", "_wpnonce": nonce_from(page),
            "_wp_http_referer": SETTINGS, "agentwelcome_settings[show_meta_box]": "1",
            "agentwelcome_settings[heading_selectors]": headings}
    body = admin.req("/wp-admin/options.php", form)[2]
    stored = jphp("echo json_encode(get_option('agentwelcome_settings')['heading_selectors'] ?? null);")
    return stored, body


def markdown(slug):
    return site.get(f"/{slug}.md?t={time.time()}")


print("--- the setting")
page = admin.get(SETTINGS)[2]
check("field and help text are on the settings page", "Elements to write as headings" in page and "How headings are chosen" in page and "Every rule needs a level" in page)
stored, body = save(RULES)
check("valid rules are saved with their levels", stored == ".egs-title => h3\n.hero-title => h2\n.big-title => h2", stored)
check("lines without a level, or a bad one, are reported and dropped", "no heading level" in body and ".no-level" in body and ".bad =&gt; h9" in body and "p:hover" in body, body[:200])
page = admin.get(SETTINGS)[2]
check("the saved rules show in the field", ".egs-title =&gt; h3" in page)

print("--- the Markdown")
content = ('<div class="egs-title">Why us</div><p>Body text.</p>'
           '<h5 class="egs-title">Real heading</h5>'
           '<div class="hero-title"><a href="/about/">Linked title</a></div>'
           '<p>Read <span class="egs-title">this</span> now.</p>'
           '<div class="egs-title"><p>One</p><p>Two</p></div>')
pid = create_page("Heading probe", content, post_name="heading-probe")
c, h, md, _ = markdown("heading-probe")
check("page is served", c == 200)
check("title becomes an h3", "### Why us" in md, md)
check("a real heading is not changed", "##### Real heading" in md and "### Real heading" not in md.replace("##### Real heading", ""), md)
check("linked title keeps its link, at the rule's level", "## [Linked title](" in md and "/about/)" in md, md)
check("a span inside a sentence is left alone", "Read this now." in md, md)
check("an element with blocks is skipped", "One" in md and "Two" in md and "### One" not in md, md)

print("--- changing the rules marks stored pages stale")
check("stored copy is fresh", row(pid) is not None and row(pid)["state"] == "fresh")
save(".egs-title => h4")
check("stored copy is marked stale", row(pid)["state"] == "stale", str(row(pid)))

print("--- the Verify override")
page = admin.get("/wp-admin/admin.php?page=agentwelcome-verify")[2]
check("Verify offers the field", "agentwelcome_verify_headings" in page)
form = {"_wpnonce": nonce_from(page), "agentwelcome_verify_url": B + "/heading-probe/", "agentwelcome_verify_headings": ".egs-title => h6"}
res = admin.req("/wp-admin/admin.php?page=agentwelcome-verify", form)[2]
check("check runs with the rule", "applied to this check only" in res and "###### Why us" in res, res[:200])
check("nothing was saved by the check", jphp("echo json_encode(get_option('agentwelcome_settings')['heading_selectors']);") == ".egs-title => h4")

done("headings")
