"""Pages that visitors cannot read (HTTP 401/403, or a redirect to another path) are left out of the .md address, llms.txt and llms-full.txt."""
import urllib.parse
from kit import *

reset_state()
admin = Client()
check("admin logged in", admin.login())
site = anon()
n = [0]
LOGIN = "/integ-rs-login/"


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


def restricted(pid):
    r = row(pid)
    return None if r is None else r.get("restricted")


def left_out(slug):
    return md(slug)[0] != 200 and not listed(slug) and not in_full(slug)


def served(slug):
    return md(slug)[0] == 200 and listed(slug)


def run_batch():
    php("for ( $i = 0; $i < 60 && false !== get_option( 'agentwelcome_batch' ); $i++ ) { do_action( 'agentwelcome_regenerate_batch' ); }")


def save_main(**fields):
    page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    form = {"option_page": "agentwelcome_group", "action": "update", "_wpnonce": nonce_from(page), "_wp_http_referer": "/wp-admin/admin.php?page=agentwelcome"}
    form.update({"agentwelcome_settings[%s]" % k: v for k, v in fields.items()})
    return admin.req("/wp-admin/options.php", form, follow=False)


def verify(slug):
    return admin.get("/wp-admin/admin.php?page=agentwelcome-verify&url=" + urllib.parse.quote(B + "/%s/" % slug, safe=""))[2]


def enable_full():
    page = admin.get("/wp-admin/admin.php?page=agentwelcome-llms")[2]
    form = {"option_page": "agentwelcome_llms_group", "action": "update", "_wpnonce": nonce_from(page), "_wp_http_referer": "/wp-admin/admin.php?page=agentwelcome-llms",
            "agentwelcome_llms[mode]": "automatic", "agentwelcome_llms[custom]": "", "agentwelcome_llms[full]": "1"}
    return admin.req("/wp-admin/options.php", form, follow=False)


def restrict(pid, mode):
    wpcli("post", "meta", "update", str(pid), "kit_restrict", mode)
    gen(pid)


try:
    set_env(no_cron=True)
    check("llms-full.txt is turned on for the test", enable_full()[0] == 302 and site.get("/llms-full.txt" + bust())[0] == 200)
    login = create_page("Rs Login", "<p>Please log in.</p>", post_name="integ-rs-login")
    ok = create_page("Rs Open", "<p>Open page.</p>", post_name="integ-rs-open")
    forbidden = create_page("Rs Forbidden", "<p>Secret members text.</p>", post_name="integ-rs-403")
    unauth = create_page("Rs Unauthorized", "<p>Secret text.</p>", post_name="integ-rs-401")
    gated = create_page("Rs Gated", "<p>Secret gated text.</p>", post_name="integ-rs-gated")
    slash = create_page("Rs Slash", "<p>Slash text.</p>", post_name="integ-rs-slash")
    for slug in ("integ-rs-login", "integ-rs-open", "integ-rs-403", "integ-rs-401", "integ-rs-gated", "integ-rs-slash"):
        md(slug)

    print("--- a page that answers visitors with 403")
    check("setup: served and listed", served("integ-rs-403"))
    restrict(forbidden, "403")
    check("it is left out of the .md address, llms.txt and llms-full.txt", left_out("integ-rs-403"), md("integ-rs-403")[0])
    check("the .md address answers with the plain text 404", md("integ-rs-403")[0] == 404 and "text/plain" in md("integ-rs-403")[1].get("content-type", ""), md("integ-rs-403")[:2])
    r = row(forbidden)
    check("the reason is stored and no text is kept", r["restricted"] == "http_403" and r["markdown"] == "", r)
    check("the post content is not used as a fallback", r["source"] == "restricted", r["source"])
    check("Verify says visitors cannot read it, with the status", "visitors cannot read it" in verify("integ-rs-403") and "HTTP 403" in verify("integ-rs-403"))
    check("the editor box says so too", "visitors cannot read it" in admin.get("/wp-admin/post.php?post=%d&action=edit" % forbidden)[2])
    check("a readable page next to it is not affected", served("integ-rs-open") and restricted(ok) == "")

    print("--- a page that answers with 401 and no Basic challenge")
    restrict(unauth, "401")
    check("it is left out", left_out("integ-rs-401"))
    check("with its reason", restricted(unauth) == "http_401", restricted(unauth))

    print("--- a page that redirects to a login page")
    restrict(gated, "login")
    check("it is left out of all three", left_out("integ-rs-gated"))
    check("with the path stored", restricted(gated) == "redirect:" + LOGIN, restricted(gated))
    check("Verify names the path", "redirected to /integ-rs-login" in verify("integ-rs-gated"))
    check("the login page itself is listed", served("integ-rs-login"))

    print("--- a harmless redirect is not restricted")
    restrict(slash, "slash")
    check("the page stays served and listed", served("integ-rs-slash"), md("integ-rs-slash")[0])
    check("it is stored as readable", restricted(slash) == "", restricted(slash))

    print("--- the page is readable again")
    wpcli("post", "meta", "delete", str(forbidden), "kit_restrict")
    gen(forbidden)
    check("it comes back in all three", served("integ-rs-403") and in_full("integ-rs-403"))

    print("--- the setting")
    save_main(show_meta_box="1", exclude_noindex="1")
    run_batch()
    check("with the setting off, a 401 page is served as before", md("integ-rs-401")[0] == 200 and listed("integ-rs-401"), md("integ-rs-401")[0])
    check("a redirected page is served as before", md("integ-rs-gated")[0] == 200 and listed("integ-rs-gated"))
    check("nothing is stored as restricted", restricted(gated) in ("", None) and restricted(unauth) in ("", None), (restricted(gated), restricted(unauth)))
    save_main(show_meta_box="1", exclude_noindex="1", exclude_restricted="1")
    run_batch()
    check("with it on again, they leave", left_out("integ-rs-gated") and left_out("integ-rs-401"))

    print("--- the filter")
    wpcli("option", "update", "kit_restricted_include", "redirect")
    gen(gated)
    gen(unauth)
    check("a filter can include a page, by reason", served("integ-rs-gated") and left_out("integ-rs-401"))
    wpcli("option", "update", "kit_restricted_include", "1")
    gen(unauth)
    check("or every page", md("integ-rs-401")[0] == 200 and restricted(unauth) in ("", None), restricted(unauth))
    wpcli("option", "delete", "kit_restricted_include")
    gen(gated)
    gen(unauth)
    check("without it they are left out again", left_out("integ-rs-gated") and left_out("integ-rs-401"))

    print("--- the upgrade from database version 2")
    php("delete_option('agentwelcome_batch'); wp_unschedule_hook('agentwelcome_regenerate_batch'); global $wpdb; $wpdb->query( \"ALTER TABLE {$wpdb->prefix}agentwelcome_pages DROP COLUMN restricted\" ); update_option('agentwelcome_db_version', '2');")
    site.get("/")
    check("the database version is updated", wpcli("option", "get", "agentwelcome_db_version").strip() == "3")
    check("the column exists again, unknown for the old copies", row(ok) is not None and "restricted" in row(ok) and row(ok)["restricted"] is None, row(ok))
    check("the copies are marked out of date and the background job started", row(ok)["state"] == "stale" and php("echo get_option('agentwelcome_batch') === false ? 'no' : 'yes';") == "yes")
    check("an unknown result is served", md("integ-rs-open")[0] == 200)
    run_batch()
    check("running the job fills in the results", restricted(gated) == "redirect:" + LOGIN and restricted(ok) == "", (restricted(gated), restricted(ok)))
    check("and the restricted pages are left out", left_out("integ-rs-gated") and served("integ-rs-403"))
finally:
    clear_env()

done("restricted")
