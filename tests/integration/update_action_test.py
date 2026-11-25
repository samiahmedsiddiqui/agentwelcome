"""The update actions for host and CDN purges, and the WooCommerce pages that are left out."""
from kit import *

LOG = WP + "/wp-content/kit-actions.log"
reset_state()
site = anon()
n = [0]


def bust():
    n[0] += 1
    return "?t=%d%d" % (int(time.time() * 1000), n[0])


def md(slug):
    return site.get("/%s.md%s" % (slug, bust()))


def listed(slug):
    return "/%s.md" % slug in site.get("/llms.txt" + bust())[2]


def entries():
    try:
        return [json.loads(l) for l in open(LOG).read().splitlines() if l.strip()]
    except FileNotFoundError:
        return []


def reset_log():
    open(LOG, "w").close()


def job(pid):
    php("do_action('agentwelcome_generate_post', %d);" % pid)


def updated(pid):
    return [e for e in entries() if e[0] == "agentwelcome_markdown_updated" and e[1][0] == pid]


print("--- the Markdown changed")
pid = create_page("Upd Page", "<p>First body.</p>", post_name="upd-page")
reset_log()
check("page is served", md("upd-page")[0] == 200)
got = updated(pid)
check("first generation fires the action once", len(got) == 1, entries())
check("it passes the post ID and the Markdown address", got and got[0][1][1] == B + "/upd-page.md", got)
reset_log()
job(pid)
check("a refresh with the same Markdown does not fire it", updated(pid) == [], entries())
wpcli("post", "update", str(pid), "--post_content=<p>Second body, changed.</p>")
job(pid)
check("a refresh with new Markdown fires it", len(updated(pid)) == 1, entries())

print("--- the copy is removed")
reset_log()
wpcli("post", "meta", "update", str(pid), "_agentwelcome_exclude", "1")
job(pid)
check("hiding the page fires it, with the address that no longer serves", len(updated(pid)) == 1 and updated(pid)[0][1][1].endswith("/upd-page.md"), entries())
wpcli("post", "meta", "delete", str(pid), "_agentwelcome_exclude")

print("--- every copy is cleared")
md("upd-page")
reset_log()
wpcli("agentwelcome", "cache", "clear")
cleared = [e for e in entries() if e[0] == "agentwelcome_cache_cleared"]
check("clearing fires the cache action once", len(cleared) == 1, entries())
check("and not once for each page", [e for e in entries() if e[0] == "agentwelcome_markdown_updated"] == [], entries())

print("--- WooCommerce pages")
wpcli("option", "update", "kit_woocommerce", "1")
cart = create_page("Upd Cart", "<p>Your cart.</p>", post_name="upd-cart")
checkout = create_page("Upd Checkout", "<p>Checkout.</p>", post_name="upd-checkout")
shop = create_page("Upd Shop", "<p>The shop.</p>", post_name="upd-shop")
wpcli("option", "update", "woocommerce_cart_page_id", str(cart))
wpcli("option", "update", "woocommerce_checkout_page_id", str(checkout))
check("the cart page is not served", md("upd-cart")[0] != 200, md("upd-cart")[0])
check("the checkout page is not served", md("upd-checkout")[0] != 200)
check("the shop page is served", md("upd-shop")[0] == 200)
check("the cart and checkout are not listed, the shop is", not listed("upd-cart") and not listed("upd-checkout") and listed("upd-shop"))
wpcli("option", "update", "woocommerce_cart_page_id", "0")
check("a page that loses the role is served again", md("upd-cart")[0] == 200)
check("and listed again", listed("upd-cart"))
wpcli("option", "update", "woocommerce_checkout_page_id", str(checkout))
wpcli("option", "delete", "kit_woocommerce")
check("without WooCommerce nothing is left out", md("upd-checkout")[0] == 200)

wpcli("option", "delete", "woocommerce_cart_page_id")
wpcli("option", "delete", "woocommerce_checkout_page_id")
done("update actions")
