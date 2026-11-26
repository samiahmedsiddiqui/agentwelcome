"""Paginated views of a list page (/blog/page/2.md) are served, stored, refreshed and verified one by one."""
import urllib.parse
from kit import *

reset_state()
admin = Client()
check("admin logged in", admin.login())
site = anon()
n = [0]
BLOG = "integ-pg-blog"


def bust():
    n[0] += 1
    return "?t=%d%d" % (int(time.time() * 1000), n[0])


def md(path):
    return site.get("/%s.md%s" % (path, bust()))


def paged_row(pid, paged):
    return jphp('global $wpdb; echo json_encode($wpdb->get_row("SELECT * FROM {$wpdb->prefix}agentwelcome_pages WHERE post_id=%d AND paged=%d", ARRAY_A));' % (pid, paged))


def titles(offset, count=2):
    return jphp("echo json_encode( wp_list_pluck( get_posts( array( 'numberposts' => %d, 'offset' => %d ) ), 'post_title' ) );" % (count, offset))


def verify(path):
    return admin.get("/wp-admin/admin.php?page=agentwelcome-verify&url=" + urllib.parse.quote(B + "/%s/" % path, safe=""))[2]


old = {k: wpcli("option", "get", k).strip() for k in ("show_on_front", "page_on_front", "page_for_posts", "posts_per_page")}
try:
    set_env(no_cron=True)
    home = create_page("Pg Home", "<p>Home.</p>", post_name="integ-pg-home")
    blog = create_page("Pg Blog", "<p>Blog.</p>", post_name=BLOG)
    other = create_page("Pg Other", "<p>Other.</p>", post_name="integ-pg-other")
    for i in range(1, 6):
        wpcli("post", "create", "--post_title=Pg Post %d" % i, "--post_name=integ-pg-post-%d" % i, "--post_status=publish", "--post_content=<p>Body of pg post %d.</p>" % i)
    wpcli("option", "update", "show_on_front", "page")
    wpcli("option", "update", "page_on_front", str(home))
    wpcli("option", "update", "page_for_posts", str(blog))
    wpcli("option", "update", "posts_per_page", "2")
    last = int(php("echo (int) ceil( wp_count_posts()->publish / 2 );"))
    check("setup: the list has at least three views", last >= 3, last)

    print("--- the first view is the page itself")
    code, h, body, _ = md(BLOG)
    check("page 1 is served", code == 200, code)
    first = titles(0)
    check("with the newest posts", all(t in body for t in first), first)

    print("--- the second view")
    code, h, body, _ = md(BLOG + "/page/2")
    second = titles(2)
    check("page/2.md is served as Markdown", code == 200 and "text/markdown" in h.get("content-type", ""), (code, h.get("content-type")))
    check("it holds the posts of the second view", all(t in body for t in second) and not any(t in body for t in first), (second, body[:300]))
    check("its front matter points to the view", "/%s/page/2/" % BLOG in body.split("---")[1], body[:300])
    r = paged_row(blog, 2)
    check("it is stored on its own, next to page 1", r is not None and r["state"] == "fresh" and r["source"] == "loopback" and paged_row(blog, 1) is not None, r)
    check("a request for it is a hit the second time", md(BLOG + "/page/2")[1].get("x-agentwelcome-cache") == "HIT")
    check("page 1 is not changed by it", all(t in md(BLOG)[2] for t in first))

    print("--- views that do not exist")
    for label, path in (("beyond the last", "%s/page/%d" % (BLOG, last + 1)), ("far beyond", "%s/page/9999" % BLOG), ("page 1 as a view", BLOG + "/page/1"), ("a page that is not a list", "integ-pg-other/page/2")):
        code, h, body, _ = md(path)
        check("%s gets the plain text 404, not a redirect" % label, code == 404 and "text/plain" in h.get("content-type", "") and "noindex" in h.get("x-robots-tag", ""), (code, h.get("location")))
    check("nothing is stored for them", paged_row(blog, last + 1) is None and paged_row(other, 2) is None)

    print("--- llms.txt")
    for i in range(2, last + 1):
        md(BLOG + "/page/%d" % i)
    llms = site.get("/llms.txt" + bust())[2]
    check("it lists the list page once and no view", "/%s.md" % BLOG in llms and "/page/" not in llms, [l for l in llms.splitlines() if "page" in l])

    print("--- Verify & Preview")
    page = verify(BLOG + "/page/2")
    check("it checks the view and finds it in sync", all(t in page for t in second) and "page/2.md" in page and "In sync" in page, "In sync" in page)
    check("a view beyond the last is not found", "No page was found" in verify(BLOG + "/page/%d" % (last + 1)))

    print("--- refresh")
    wpcli("post", "create", "--post_title=Pg Post 6", "--post_name=integ-pg-post-6", "--post_status=publish", "--post_content=<p>Body of pg post 6.</p>")
    php("do_action( 'agentwelcome_generate_post', %d, 2 );" % blog)
    code, h, body, _ = md(BLOG + "/page/2")
    check("a new post moves the posts of the view on once it is refreshed", all(t in body for t in titles(2)), titles(2))
    wpcli("post", "update", str(blog), "--post_content=<p>Blog changed.</p>")
    check("changing the list page marks every view out of date", paged_row(blog, 2)["state"] == "stale" and paged_row(blog, 1)["state"] == "stale")
    wpcli("cron", "event", "delete", "agentwelcome_generate_post")
    md(BLOG + "/page/2")
    events = jphp("echo json_encode( array_values( array_filter( _get_cron_array() ? array_merge( ...array_map( fn( $e ) => isset( $e['agentwelcome_generate_post'] ) ? array_map( fn( $x ) => $x['args'], array_values( $e['agentwelcome_generate_post'] ) ) : array(), _get_cron_array() ) ) : array() ) ) );")
    check("a refresh is queued for the view with its number", [blog, 2] in events, events)

    print("--- a page that lists posts itself")
    custom = create_page("Pg List", "[kit_list]", post_name="integ-pg-list")
    total = int(php("echo (int) ceil( wp_count_posts()->publish / 2 );"))
    code, h, body, _ = md("integ-pg-list/page/2")
    check("page/2.md is served before the page itself was ever requested", code == 200 and all(t in body for t in titles(2)), (code, body[:300]))
    check("the number of views comes from its links", wpcli("post", "meta", "get", str(custom), "_agentwelcome_page_views").strip() == str(total), total)
    check("the last view is served", md("integ-pg-list/page/%d" % total)[0] == 200)
    check("a view beyond it is not", md("integ-pg-list/page/%d" % (total + 1))[0] == 404 and paged_row(custom, total + 1) is None)
    check("Verify & Preview finds a view of it", "In sync" in verify("integ-pg-list/page/2"))
    wpcli("post", "update", str(custom), "--post_content=<p>No list now.</p>")
    md("integ-pg-list")
    php("do_action( 'agentwelcome_generate_post', %d );" % custom)
    check("the views are gone once the page no longer links to them", md("integ-pg-list/page/2")[0] == 404)

    print("--- the table of an earlier version")
    php("global $wpdb; $t = $wpdb->prefix . 'agentwelcome_pages'; $wpdb->query( \"DELETE FROM {$t} WHERE paged > 1\" ); $wpdb->query( \"ALTER TABLE {$t} DROP PRIMARY KEY, DROP COLUMN paged, ADD PRIMARY KEY (post_id)\" ); update_option( 'agentwelcome_db_version', '3' );")
    site.get("/")
    check("the version is updated", wpcli("option", "get", "agentwelcome_db_version").strip() == "4")
    check("copies keep their place as view 1", paged_row(blog, 1) is not None and md(BLOG)[0] == 200)
    check("and views can be stored again", md(BLOG + "/page/2")[0] == 200 and paged_row(blog, 2) is not None)
finally:
    for k, v in old.items():
        wpcli("option", "update", k, v)
    php('foreach ( get_posts( array( "post_type" => "post", "numberposts" => -1 ) ) as $p ) { if ( 0 === strpos( $p->post_name, "integ-pg-" ) ) { wp_delete_post( $p->ID, true ); } }')
    clear_env()

done("paged")
