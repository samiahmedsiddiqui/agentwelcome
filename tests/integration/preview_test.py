"""Formatted view of the Markdown pages: opt-in by ?preview or by a setting, and never for agents."""
from kit import *

reset_state()
site = anon()
admin = Client()
check("logged in", admin.login())

NAV = {"Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8", "Sec-Fetch-Dest": "document", "Sec-Fetch-Mode": "navigate"}
content = ("<h2>Services</h2><p>Fish &amp;amp; chips with <strong>bold</strong> and a <a href=\"https://example.org/x\">link</a>.</p>"
           "<ul><li>one</li><li>two</li></ul><table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>"
           "<p>&lt;script&gt;alert(1)&lt;/script&gt; and &lt;/pre&gt;&lt;h1&gt;x&lt;/h1&gt;</p>")
pid = create_page("Preview A", content, post_name="prev-a")
other = None


def save(box):
    page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    form = {"option_page": "agentwelcome_group", "action": "update", "_wpnonce": nonce_from(page),
            "_wp_http_referer": "/wp-admin/admin.php?page=agentwelcome", "agentwelcome_settings[show_meta_box]": "1"}
    if box:
        form["agentwelcome_settings[browser_preview]"] = "1"
    admin.req("/wp-admin/options.php", form)
    return jphp("echo json_encode(get_option('agentwelcome_settings')['browser_preview'] ?? null);")


def stored():
    return jphp('global $wpdb; echo json_encode($wpdb->get_row("SELECT markdown, content_hash, generated_at FROM {$wpdb->prefix}agentwelcome_pages WHERE post_id=%d", ARRAY_A));' % pid)


try:
    print("--- the plain Markdown is what it always was")
    c, raw_h, raw, _ = site.get("/prev-a.md")
    check("200 text/markdown starting with the metadata block", c == 200 and raw_h["content-type"] == "text/markdown; charset=utf-8" and raw.startswith("---\n"), raw[:80])
    check("public cache header and no Vary", raw_h["cache-control"] == "public, max-age=300" and "vary" not in raw_h)
    check("noindex, nosniff and a canonical link", raw_h["x-robots-tag"] == "noindex" and raw_h["x-content-type-options"] == "nosniff" and 'rel="canonical"' in raw_h["link"])
    before = stored()
    c, h, body, _ = site.get("/prev-a.md", headers=NAV)
    check("a browser's request gets the same bytes while the setting is off", body == raw and h["content-type"] == raw_h["content-type"] and h["etag"] == raw_h["etag"] and "vary" not in h)

    print("--- ?preview gives the formatted page")
    c, ph, page, _ = site.get("/prev-a.md?preview")
    check("200 text/html", c == 200 and ph["content-type"] == "text/html; charset=utf-8", (c, ph.get("content-type")))
    check("the render target, the source block and the script are there", 'id="agentwelcome-rendered"' in page and '<pre id="agentwelcome-markdown"' in page and page.count("<script") == 1)
    check("the metadata block is shown as escaped text", "title: &quot;Preview A&quot;" in page, page[:600])
    check("an entity written in the Markdown stays literal text", "Fish &amp;amp; chips" in page or "Fish &amp;amp;amp; chips" in page, page[page.find("Fish") - 20:page.find("Fish") + 60])
    check("HTML written in the page text is escaped, no tag is created", "<script>alert" not in page and "<h1>x</h1>" not in page and page.count("</pre>") == 1)
    check("it links to the page and to the plain Markdown", 'href="http://127.0.0.1:8899/prev-a/"' in page and 'href="http://127.0.0.1:8899/prev-a.md"' in page and "?raw" not in page)
    check("it is not indexed", '<meta name="robots" content="noindex, nofollow">' in page and ph["x-robots-tag"] == "noindex, nofollow")
    check("shared caches may not keep it", ph["cache-control"] == "private, no-cache")
    csp = ph.get("content-security-policy", "")
    check("the policy allows nothing but the two plugin files", csp.startswith("default-src 'none'; script-src http://127.0.0.1:8899/wp-content/plugins/agentwelcome/assets/js/markdown.js; style-src http://127.0.0.1:8899/wp-content/plugins/agentwelcome/assets/css/preview.css;") and "frame-ancestors 'none'" in csp, csp)
    check("framing, referrers and sniffing are blocked", ph["x-frame-options"] == "DENY" and ph["referrer-policy"] == "no-referrer" and ph["x-content-type-options"] == "nosniff")
    check("it has a canonical link to the page", ph["link"] == '<http://127.0.0.1:8899/prev-a/>; rel="canonical"', ph.get("link"))
    check("it is no longer than a small page", len(page) < len(raw) + 4000)
    script = re.search(r'<script src="([^"]+)"', page).group(1)
    style = re.search(r'<link rel="stylesheet" href="([^"]+)"', page).group(1)
    sc = site.get(script.replace(B, ""))
    st = site.get(style.replace(B, ""))
    check("the script and the stylesheet load, with a version", sc[0] == 200 and st[0] == 200 and "ver=" in script and "ver=" in style and "agentwelcomeRenderMarkdown" in sc[2] and ".agentwelcome-rendered" in st[2], (sc[0], st[0], script, style))
    c, h2, again, _ = site.get("/prev-a.md?preview=1")
    check("the formatted page has its own ETag and answers a conditional request", h2["etag"] != raw_h["etag"] and site.get("/prev-a.md?preview", headers={"If-None-Match": ph["etag"]})[0] == 304)
    c, hh, hbody, _ = site.req("/prev-a.md?preview", method="HEAD")
    check("HEAD gives the headers and no body", c == 200 and hh["content-type"].startswith("text/html") and hbody == "")
    check("?preview=0 and ?raw give the plain Markdown", site.get("/prev-a.md?preview=0")[2] == raw and site.get("/prev-a.md?raw")[2] == raw and site.get("/prev-a.md?preview&raw")[2] == raw)
    c, h, body, _ = site.get("/prev-a.md")
    check("asking for the page did not change the plain Markdown or its headers", body == raw and h["etag"] == raw_h["etag"] and h["cache-control"] == raw_h["cache-control"] and h["content-type"] == raw_h["content-type"])
    check("the stored copy is untouched", stored() == before, (stored(), before))
    check("an agent that sends Accept text/html alone still gets the plain Markdown", site.get("/prev-a.md", headers={"Accept": "text/html"})[2] == raw)

    print("--- other addresses are not affected")
    c, h, body, _ = site.get("/llms.txt?preview")
    check("llms.txt stays plain text", c == 200 and h["content-type"].startswith("text/plain") and "<html" not in body)
    check("an unknown page is still a 404", site.get("/no-such-page.md?preview")[0] == 404)
    wpcli("post", "meta", "update", str(pid), "_agentwelcome_exclude", "1")
    check("a page that is left out is still a 404", site.get("/prev-a.md?preview")[0] == 404)
    wpcli("post", "meta", "delete", str(pid), "_agentwelcome_exclude")
    check("and is served again when included", site.get("/prev-a.md?preview")[0] == 200)

    print("--- the setting")
    page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    check("it is a checkbox that is off by default", 'name="agentwelcome_settings[browser_preview]"' in page and re.search(r'name="agentwelcome_settings\[browser_preview\]"[^>]*checked', page) is None)
    check("saving it on is kept", save(True) is True)
    page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    check("and shown checked", re.search(r'name="agentwelcome_settings\[browser_preview\]"[^>]*checked', page) is not None)

    print("--- with the setting on, a browser navigation gets the formatted page")
    c, h, body, _ = site.get("/prev-a.md", headers=NAV)
    check("html for a page navigation", c == 200 and h["content-type"].startswith("text/html") and 'id="agentwelcome-rendered"' in body, (c, h.get("content-type")))
    check("its raw link carries ?raw=1 so a person can still reach the Markdown", 'href="http://127.0.0.1:8899/prev-a.md?raw=1"' in body)
    check("it varies on the request headers and is private", all(x in h["vary"] for x in ("Accept", "Sec-Fetch-Dest", "Sec-Fetch-Mode")) and h["cache-control"] == "private, no-cache", h.get("vary"))
    c, h, body, _ = site.get("/prev-a.md?raw=1", headers=NAV)
    check("?raw=1 gives the Markdown even to a browser", body == raw and h["content-type"].startswith("text/markdown"))
    for name, hdr in (("no browser headers", {}), ("Accept text/html only", {"Accept": "text/html"}), ("curl-style Accept", {"Accept": "*/*"}), ("a script fetch from a page", {"Accept": "*/*", "Sec-Fetch-Dest": "empty", "Sec-Fetch-Mode": "cors"}),
                      ("an embedded frame", dict(NAV, **{"Sec-Fetch-Dest": "iframe"})), ("navigation without text/html", dict(NAV, Accept="text/markdown"))):
        c, h, body, _ = site.get("/prev-a.md", headers=hdr)
        check("agents still get the Markdown: " + name, c == 200 and body == raw and h["content-type"] == raw_h["content-type"] and h["etag"] == raw_h["etag"], (c, h.get("content-type")))
    c, h, body, _ = site.get("/prev-a.md")
    check("the plain response now also says it varies, and nothing else about it changed", body == raw and "Accept" in h["vary"] and h["cache-control"] == raw_h["cache-control"] and h["etag"] == raw_h["etag"] and h["link"] == raw_h["link"])
    check("llms.txt and llms-full.txt do not vary", "vary" not in site.get("/llms.txt", headers=NAV)[1] and site.get("/llms.txt", headers=NAV)[2] == site.get("/llms.txt")[2])
    check("the stored copy is still untouched", stored() == before)

    print("--- the same renderer is used by the admin page")
    wpcli("agentwelcome", "cache", "clear")
    vp = admin.get("/wp-admin/admin.php?page=agentwelcome-verify&url=" + urllib.parse.quote(B + "/prev-a/", safe=""))[2]
    check("Verify & Preview loads the shared renderer before its own script", "assets/js/markdown.js" in vp and vp.find("assets/js/markdown.js") < vp.find("assets/js/verify.js"))
finally:
    wpcli("option", "delete", "agentwelcome_settings")
    wpcli("post", "delete", str(pid), "--force")
    reset_state()
done("PREVIEW")
