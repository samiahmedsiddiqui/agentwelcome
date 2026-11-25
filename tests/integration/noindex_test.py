"""Noindex is read from the page source: the robots meta tag and the X-Robots-Tag header."""
import urllib.parse
from kit import *

reset_state()
admin = Client()
check("admin logged in", admin.login())
site = anon()
n = [0]
T = "global $wpdb; "


def bust():
    n[0] += 1
    return "?t=%d%d" % (int(time.time() * 1000), n[0])


def md(slug):
    return site.get("/%s.md%s" % (slug, bust()))


def listed(slug):
    return "/%s.md" % slug in site.get("/llms.txt" + bust())[2]


def in_full(slug):
    return "/%s/" % slug in site.get("/llms-full.txt" + bust())[2]


def gen(pid):
    php("do_action('agentwelcome_generate_post', %d);" % pid)


def flag(pid):
    r = row(pid)
    return None if r is None else r.get("noindex")


def left_out(slug):
    return md(slug)[0] != 200 and not listed(slug) and not in_full(slug)


def served(slug):
    return md(slug)[0] == 200 and listed(slug)


def save_main(**fields):
    page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    form = {"option_page": "agentwelcome_group", "action": "update", "_wpnonce": nonce_from(page), "_wp_http_referer": "/wp-admin/admin.php?page=agentwelcome"}
    form.update({"agentwelcome_settings[%s]" % k: v for k, v in fields.items()})
    return admin.req("/wp-admin/options.php", form, follow=False)


def verify(slug):
    return admin.get("/wp-admin/admin.php?page=agentwelcome-verify&url=" + urllib.parse.quote(B + "/%s/" % slug, safe=""))[2]


try:
    set_env(no_cron=True)

    print("--- a page that was never generated is not listed")
    new = create_page("Nx New", "<p>New page.</p>", post_name="integ-nx-new")
    check("not listed before it is generated", not listed("integ-nx-new") and not in_full("integ-nx-new"))
    check("the first request generates and serves it", md("integ-nx-new")[0] == 200)
    check("then it is listed, and the result is stored as not noindex", listed("integ-nx-new") and flag(new) == "0", flag(new))

    print("--- the robots meta tag")
    a = create_page("Nx A", "<p>A.</p>", post_name="integ-nx-a")
    md("integ-nx-a")
    check("setup: served", served("integ-nx-a"))
    wpcli("post", "meta", "update", str(a), "kit_robots", "noindex")
    gen(a)
    check("noindex in the meta tag leaves the page out of the .md, llms.txt and llms-full.txt", left_out("integ-nx-a"), md("integ-nx-a")[0])
    check("the result is stored", flag(a) == "1", flag(a))
    check("Verify says where it came from", "Found in the robots meta tag" in verify("integ-nx-a"))
    check("the editor box explains it", "says noindex" in admin.get("/wp-admin/post.php?post=%d&action=edit" % a)[2])
    for value, kept in (("noindex, nofollow", False), ("none", False), ("index, follow", True), ("max-image-preview:large", True)):
        wpcli("post", "meta", "update", str(a), "kit_robots", value)
        gen(a)
        check("meta content '%s': %s" % (value, "served" if kept else "left out"), served("integ-nx-a") if kept else left_out("integ-nx-a"), md("integ-nx-a")[0])
    wpcli("post", "meta", "delete", str(a), "kit_robots")
    gen(a)
    check("tag removed and refreshed: back in the list", served("integ-nx-a"))

    print("--- the X-Robots-Tag header")
    b = create_page("Nx B", "<p>B.</p>", post_name="integ-nx-b")
    md("integ-nx-b")
    wpcli("post", "meta", "update", str(b), "kit_robots_header", "noindex")
    gen(b)
    check("noindex in the header leaves the page out", left_out("integ-nx-b"), md("integ-nx-b")[0])
    check("Verify says it came from the header", "Found in the X-Robots-Tag header" in verify("integ-nx-b"))
    wpcli("post", "meta", "update", str(b), "kit_robots_header", "googlebot: noindex")
    gen(b)
    check("a directive for one crawler is ignored", served("integ-nx-b"))
    wpcli("post", "meta", "delete", str(b), "kit_robots_header")

    print("--- a whole post type")
    wpcli("option", "update", "kit_robots_type", "book")
    book = int(wpcli("post", "create", "--post_type=book", "--post_title=Nx Book", "--post_name=integ-nx-book", "--post_status=publish", "--post_content=<p>Book.</p>", "--porcelain").strip().splitlines()[-1])
    md("integ-nx-book")
    gen(book)
    check("a post type that says noindex is left out without any per-page setting", left_out("integ-nx-book"), md("integ-nx-book")[0])
    check("a page of another type is not affected", served("integ-nx-a"))
    wpcli("option", "delete", "kit_robots_type")
    gen(book)
    check("and returns when the post type is indexable again", served("integ-nx-book"))

    print("--- the setting")
    wpcli("post", "meta", "update", str(a), "kit_robots", "noindex")
    gen(a)
    check("setup: left out", left_out("integ-nx-a"))
    save_main(show_meta_box="1")
    check("with the setting off, the stored result does not hide the page", served("integ-nx-a") and in_full("integ-nx-a"))
    save_main(show_meta_box="1", exclude_noindex="1")
    check("with it on again, the page leaves at once", left_out("integ-nx-a"))

    print("--- results that are unknown")
    php(T + "$wpdb->query( \"UPDATE {$wpdb->prefix}agentwelcome_pages SET noindex = NULL WHERE post_id = %d\" );" % a)
    check("a copy from before the upgrade has no result and is served", served("integ-nx-a"), flag(a))
    wpcli("post", "meta", "delete", str(a), "kit_robots")
    set_env(no_cron=True, no_loopback=True)
    fb = create_page("Nx Fallback", "<p>Built from the post content.</p>", post_name="integ-nx-fb")
    gen(fb)
    check("a page built without the rendered page has no result and is served", flag(fb) is None and md("integ-nx-fb")[0] == 200, flag(fb))
    set_env(no_cron=True)

    print("--- the upgrade generates every page once")
    php("delete_option('agentwelcome_batch'); wp_unschedule_hook('agentwelcome_regenerate_batch');")
    gen(a)
    wpcli("option", "update", "agentwelcome_db_version", "1")
    site.get("/")
    check("the database version is updated", wpcli("option", "get", "agentwelcome_db_version").strip() == "2")
    check("the background job was started", php("echo get_option('agentwelcome_batch') === false ? 'no' : 'yes';") == "yes")
    check("and the request for it is cleared", php("echo get_option('agentwelcome_warmup') === false ? 'gone' : 'left';") == "gone")
    check("stored copies are marked out of date", row(a)["state"] == "stale", row(a))
    php("do_action('agentwelcome_regenerate_batch');")
    check("running the job refreshes them", row(a)["state"] == "fresh", row(a))

    print("--- the site asks search engines not to index it")
    wpcli("option", "update", "blog_public", "0")
    check("a notice explains why every page is left out", "Discourage search engines" in admin.get("/wp-admin/admin.php?page=agentwelcome")[2])
    wpcli("option", "update", "blog_public", "1")
    check("and goes when it is turned off", "Discourage search engines" not in admin.get("/wp-admin/admin.php?page=agentwelcome")[2])
finally:
    wpcli("option", "delete", "kit_robots_type")
    wpcli("option", "update", "blog_public", "1")
    clear_env()

done("noindex")
