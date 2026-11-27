"""A multilingual site with language directories, with WPML stood in for by mu-plugins/kit-wpml.php."""
import urllib.parse
from kit import *

reset_state()
site = anon()
n = [0]
SETUP = r"""
$make = function ( $slug, $title, $body, $lang = 'en', $trid = 0, $type = 'page' ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => '<p>' . $body . '</p>' ) );
	if ( 'en' !== $lang ) {
		update_post_meta( $id, 'kit_lang', $lang );
	}
	if ( $trid ) {
		update_post_meta( $id, 'kit_trid', $trid );
	}
	return $id;
};
$ids = array( 'home' => $make( 'integ-wpml-home', 'Wpml Home', 'English home body.' ) );
$ids['home_ar'] = $make( 'integ-wpml-home-ar', 'الرئيسية', 'نص الصفحة الرئيسية.', 'ar', $ids['home'] );
$ids['home_zh'] = $make( 'integ-wpml-home-zh', '首页', '中文首页正文。', 'zh-hans', $ids['home'] );
$ids['about'] = $make( 'integ-wpml-about', 'Wpml About', 'English about body.' );
$ids['about_ar'] = $make( 'integ-wpml-من-نحن', 'من نحن', 'نص صفحة من نحن.', 'ar', $ids['about'] );
$ids['about_zh'] = $make( 'integ-wpml-about-zh', '关于我们', '中文关于正文。', 'zh-hans', $ids['about'] );
$ids['only_en'] = $make( 'integ-wpml-only-en', 'Only English', 'Not translated.' );
$ids['only_ar'] = $make( 'integ-wpml-only-ar', 'عربي فقط', 'صفحة عربية فقط.', 'ar' );
$ids['blog'] = $make( 'integ-wpml-blog', 'Wpml Blog', 'Blog.' );
$ids['blog_ar'] = $make( 'integ-wpml-blog-ar', 'المدونة', 'مدونة.', 'ar', $ids['blog'] );
for ( $i = 1; $i <= 3; $i++ ) {
	$ids[ 'post' . $i ] = $make( 'integ-wpml-post-' . $i, 'Wpml Post ' . $i, 'Post body ' . $i, 'en', 0, 'post' );
	$ids[ 'post_ar' . $i ] = $make( 'integ-wpml-post-ar-' . $i, 'مقال ' . $i, 'نص المقال ' . $i, 'ar', $ids[ 'post' . $i ], 'post' );
}
$term = wp_insert_term( 'integ-wpml-hidden', 'category' )['term_id'];
$term_ar = wp_insert_term( 'integ-wpml-hidden-ar', 'category' )['term_id'];
update_term_meta( $term_ar, 'kit_lang', 'ar' );
update_term_meta( $term_ar, 'kit_trid', $term );
wp_set_post_categories( $ids['post_ar1'], array( $term_ar ) );
$ids['term'] = $term;
echo json_encode( $ids );
"""
CLEANUP = r"""
global $wpdb;
foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE 'Wpml %' OR post_name LIKE 'integ-wpml-%'" ) as $id ) {
	wp_delete_post( (int) $id, true );
}
foreach ( array( 'integ-wpml-hidden', 'integ-wpml-hidden-ar' ) as $slug ) {
	$term = get_term_by( 'slug', $slug, 'category' );
	if ( $term ) {
		wp_delete_term( $term->term_id, 'category' );
	}
}
"""


def bust():
    n[0] += 1
    return "?t=%d%d" % (int(time.time() * 1000), n[0])


def get(path, **k):
    return site.get(urllib.parse.quote(path, safe="/%") + bust(), **k)


def url(path):
    return B + urllib.parse.quote(path, safe="/%").lower()


def is_md(code, h):
    return code == 200 and "text/markdown" in h.get("content-type", "")


def plain_404(code, h):
    return code == 404 and "text/plain" in h.get("content-type", "")


AR = "/ar/integ-wpml-%D9%85%D9%86-%D9%86%D8%AD%D9%86".lower()
old = {k: wpcli("option", "get", k).strip() for k in ("show_on_front", "page_on_front", "page_for_posts", "posts_per_page", "blogdescription")}
try:
    set_env(no_cron=True)
    php(CLEANUP)
    wpcli("option", "update", "kit_wpml", "filter")
    ids = jphp(SETUP)
    wpcli("option", "update", "show_on_front", "page")
    wpcli("option", "update", "page_on_front", str(ids["home"]))
    wpcli("option", "update", "page_for_posts", str(ids["blog"]))
    wpcli("option", "update", "posts_per_page", "2")
    wpcli("option", "update", "blogdescription", "Site tagline")

    for mode in ("filter", "rewrite"):
        wpcli("option", "update", "kit_wpml", mode)
        php('global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}agentwelcome_pages"); delete_transient("agentwelcome_out_llms");')
        print("--- pages (%s)" % mode)
        code, h, body, _ = get(AR + ".md")
        check("%s: a translated page is served as Markdown" % mode, is_md(code, h), (code, h.get("location"), body[:200]))
        check("%s: it holds the translation" % mode, "نص صفحة من نحن" in body and "English about" not in body, body[:300])
        check("%s: its front matter has its own address and language" % mode, 'url: "%s/"' % url(AR) in body and 'language: "ar"' in body, body[:300])
        check("%s: the canonical header is the translated page" % mode, "<%s/>" % url(AR) in h.get("link", ""), h.get("link"))
        code, h, body, _ = get("/zh-hans/integ-wpml-about-zh.md")
        check("%s: the Chinese page too" % mode, is_md(code, h) and "中文关于正文" in body and 'language: "zh-CN"' in body, (code, body[:300]))
        code, h, body, _ = get("/integ-wpml-about.md")
        check("%s: the English page stays at the root" % mode, is_md(code, h) and "English about body" in body and 'language: "en-US"' in body, (code, body[:300]))
        for path, text in (("/ar/index.md", "نص الصفحة الرئيسية"), ("/ar/index.html.md", "نص الصفحة الرئيسية"), ("/zh-hans/index.md", "中文首页正文"), ("/index.md", "English home body")):
            code, h, body, _ = get(path)
            check("%s: %s is that language's home page" % (mode, path), is_md(code, h) and text in body, (code, h.get("location"), body[:200]))

        print("--- discovery tag (%s)" % mode)
        for path, md in (("/ar/", url("/ar/index.md")), ("/zh-hans/", url("/zh-hans/index.md")), ("/", url("/index.md")), (AR + "/", url(AR + ".md"))):
            code, h, html, _ = get(path)
            check("%s: %s names its Markdown address" % (mode, path), 'type="text/markdown" href="%s"' % md in html, (code, re.findall(r'type="text/markdown" href="[^"]*"', html)))

        print("--- no translation (%s)" % mode)
        for path in ("/ar/integ-wpml-only-en.md", "/zh-hans/integ-wpml-only-en.md", "/integ-wpml-only-ar.md", "/ar/no-such-page.md"):
            code, h, body, _ = get(path, follow=False)
            check("%s: %s is a 404, not another language" % (mode, path), plain_404(code, h), (code, h.get("location"), body[:200]))
        check("%s: a page that exists only in Arabic is served there" % mode, is_md(*get("/ar/integ-wpml-only-ar.md")[:2]))
        code, h, _, _ = get("/ar/integ-wpml-about.md", follow=False)
        check("%s: the English slug under /ar/ redirects to the Arabic Markdown" % mode, code == 301 and h.get("location") == url(AR + ".md"), (code, h.get("location")))

    wpcli("option", "update", "kit_wpml", "filter")

    print("--- background generation")
    php('global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}agentwelcome_pages");')
    warm()
    for key, lang, text in (("about_ar", "ar", "نص صفحة من نحن"), ("home_zh", "zh-CN", "中文首页正文"), ("about", "en-US", "English about body")):
        r = row(ids[key])
        check("cron generates %s from its own rendered page" % key, r is not None and r["source"] == "loopback" and text in r["markdown"] and 'language: "%s"' % lang in r["markdown"], r and r["markdown"][:300])
    addresses = jphp("$u = new AgentWelcome\\Http\\Urls(); echo json_encode( array( $u->markdown_url( get_post( %d ) ), $u->markdown_url( get_post( %d ) ), $u->markdown_url( get_post( %d ) ) ) );" % (ids["home_ar"], ids["home_zh"], ids["about_ar"]))
    check("addresses are right outside their language", addresses == [url("/ar/index.md"), url("/zh-hans/index.md"), url(AR + ".md")], addresses)

    print("--- llms.txt")
    php('delete_transient("agentwelcome_out_llms");')
    code, h, llms, _ = get("/llms.txt")
    lines = llms.splitlines()
    ar_at = lines.index("## العربية") if "## العربية" in lines else -1
    zh_at = lines.index("## 简体中文") if "## 简体中文" in lines else -1
    first, arabic, chinese = lines[:ar_at], lines[ar_at:zh_at], lines[zh_at:]
    check("it has a part for each other language, after the default one", code == 200 and 0 < ar_at < zh_at, llms[:600])
    check("the heading and tagline are the default language's", lines[:3] == ["# AgentWelcome Test", "", "> Site tagline"], lines[:4])
    check("each part has its own tagline", "> وصف الموقع" in arabic and "> 网站描述" in chinese, arabic[:6])
    check("the default part lists English addresses only", any("(%s)" % url("/integ-wpml-about.md") in l for l in first) and not any("/ar/" in l or "/zh-hans/" in l for l in first), first)
    check("the Arabic part lists the Arabic home and pages", all(any("(%s)" % u in l for l in arabic) for u in (url("/ar/index.md"), url(AR + ".md"), url("/ar/integ-wpml-only-ar.md"))), arabic)
    check("an untranslated page is not listed under another language", "/ar/integ-wpml-only-en" not in llms and "/zh-hans/integ-wpml-only-en" not in llms)
    links = re.findall(r"\]\((http[^)]+)\)", llms)
    check("every address is listed once", len(links) == len(set(links)), [l for l in links if links.count(l) > 1])
    check("every listed address is served", all(is_md(*site.get(l[len(B):])[:2]) for l in links), [l for l in links if not is_md(*site.get(l[len(B):])[:2])])
    code, h, _, _ = get("/ar/llms.txt", follow=False)
    check("/ar/llms.txt redirects to the one at the root", code == 301 and h.get("location") == B + "/llms.txt", (code, h.get("location")))
    php('delete_transient("agentwelcome_out_llms");')
    wpcli("option", "update", "kit_wpml", "rewrite")
    get("/ar/llms.txt")
    check("a file built for an Arabic request is not kept", get("/llms.txt")[2] == llms, get("/llms.txt")[2][:300])
    wpcli("option", "update", "kit_wpml", "filter")

    print("--- llms-full.txt")
    wpcli("option", "update", "agentwelcome_llms", '{"full":true}', "--format=json")
    full = get("/llms-full.txt")[2]
    sources = re.findall(r"^Source: (\S+)$", full, re.M)
    check("each page once, with the address of its language", len(sources) == len(set(sources)) and all(u in sources for u in (url("/"), url("/ar/"), url("/zh-hans/"), url(AR + "/"))), sources)
    wpcli("option", "delete", "agentwelcome_llms")

    print("--- paginated views")
    code, h, body, _ = get("/ar/integ-wpml-blog-ar/page/2.md")
    check("page/2.md is served per language", is_md(code, h) and "مقال" in body and "Wpml Post" not in body, (code, body[:400]))
    check("a view beyond the language's posts is a 404", plain_404(*get("/ar/integ-wpml-blog-ar/page/3.md", follow=False)[:2]))

    print("--- a post that WPML leaves at the default language address")
    wpcli("option", "update", "kit_wpml_unprefixed", "1")
    php('global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}agentwelcome_pages");')
    code, h, body, _ = get("/ar/integ-wpml-post-ar-2.md")
    check("its Markdown, front matter and canonical use the language directory", is_md(code, h) and 'url: "%s"' % url("/ar/integ-wpml-post-ar-2/") in body and "/ar/integ-wpml-post-ar-2/>" in h.get("link", ""), (code, body[:300]))
    html = get("/ar/integ-wpml-post-ar-2/")[2]
    check("so does its discovery tag", 'type="text/markdown" href="%s"' % url("/ar/integ-wpml-post-ar-2.md") in html, re.findall(r'type="text/markdown" href="[^"]*"', html))
    wpcli("option", "delete", "kit_wpml_unprefixed")

    print("--- exclusions")
    wpcli("option", "update", "agentwelcome_settings", json.dumps({"excluded_terms": [ids["term"]]}), "--format=json")
    check("excluding a term also leaves out the posts of its translations", plain_404(*get("/ar/integ-wpml-post-ar-1.md", follow=False)[:2]) and is_md(*get("/ar/integ-wpml-post-ar-2.md")[:2]))
    wpcli("option", "update", "agentwelcome_settings", '{"excluded_posts":"integ-wpml-only-ar","markdown_by_accept":true}', "--format=json")
    check("URL patterns ignore the language directory", plain_404(*get("/ar/integ-wpml-only-ar.md", follow=False)[:2]))
    code, h, body, _ = get(AR + "/", headers={"Accept": "text/markdown"})
    check("the Accept header works under a language", is_md(code, h) and "نص صفحة من نحن" in body, (code, h.get("content-type")))
    wpcli("option", "delete", "agentwelcome_settings")

    print("--- admin")
    admin = Client()
    check("admin logged in", admin.login())
    page = admin.get("/wp-admin/admin.php?page=agentwelcome-verify&url=" + urllib.parse.quote(url(AR + "/"), safe=""))[2]
    check("Verify & Preview finds a translated page by its address", url(AR + ".md") in page and "In sync" in page, "No page was found" in page)

    print("--- debug headers")
    check("none by default", not any(k.startswith("x-agentwelcome-debug") for k in get(AR + ".md")[1]))
    set_env(no_cron=True, debug=True)
    h = get(AR + ".md")[1]
    check("they say the language, path and post", h.get("x-agentwelcome-debug-language") == "ar" and h.get("x-agentwelcome-debug-path") == AR[4:] + ".md" and h.get("x-agentwelcome-debug-post") == str(ids["about_ar"]), {k: v for k, v in h.items() if "debug" in k})
    code, h, _, _ = get("/ar/integ-wpml-only-en.md", follow=False)
    check("a 404 carries them too", code == 404 and h.get("x-agentwelcome-debug-language") == "ar", {k: v for k, v in h.items() if "debug" in k})
    set_env(no_cron=True)

    print("--- without WPML")
    wpcli("option", "delete", "kit_wpml")
    check("a language directory means nothing", get("/ar/index.md", follow=False)[0] != 200)
    check("and llms.txt has no language parts", "## العربية" not in get("/llms.txt")[2])
finally:
    wpcli("option", "delete", "kit_wpml")
    wpcli("option", "delete", "kit_wpml_unprefixed")
    php(CLEANUP)
    for k, v in old.items():
        wpcli("option", "update", k, v)
    wpcli("option", "delete", "agentwelcome_settings")
    wpcli("option", "delete", "agentwelcome_llms")
    clear_env()

done("wpml")
