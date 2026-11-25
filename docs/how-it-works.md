# How AgentWelcome works

The addresses it serves, how a page becomes Markdown, and which pages are left out.

## URLs

| Page | Markdown URL |
| --- | --- |
| `/about/` | `/about.md` |
| `/services/web/` | `/services/web.md` |
| Homepage (static front page) | `/index.md` |
| Site index | `/llms.txt` |
| All pages in one file (opt-in) | `/llms-full.txt` |

`/about/index.html.md`, `/about/index.md` and `/index.html.md` are accepted as aliases. The homepage URL defaults to `/index.md`; choose `/index.html.md` instead under AgentWelcome > Settings > Content > Homepage. Both keep working, and the chosen one is the one listed in `llms.txt` and the page head. If a real page has the slug `index`, `/index.html.md` is used. Markdown responses send `Content-Type: text/markdown`, a canonical `Link` header to the HTML page, `X-Robots-Tag: noindex`, and support `ETag` and `If-Modified-Since`. Each page also gets `<link rel="alternate" type="text/markdown">` in its head.

## Custom Permalinks

The description in the metadata block and in `llms.txt` comes from the first of these that has text: the description typed in the AgentWelcome box on the edit screen, the `<meta name="description">` in the head of the rendered page (so whatever your SEO plugin or theme prints is used, template variables included), then the excerpt. The page content is never used. The meta description is read each time the page is generated and saved with the page as the post meta `_agentwelcome_page_description`. `llms.txt` and `llms-full.txt` are built from the database and use that saved value, and a page that has not been generated yet uses its excerpt until it is. Verify & Preview shows which of the three was used. Use the `agentwelcome_description` filter to change the result.

Works with default WordPress permalinks and with the [Custom Permalinks](https://wordpress.org/plugins/custom-permalinks/) plugin. A page with a custom URL gets its Markdown at that URL plus `.md` (`/company/about-us.md`), `llms.txt` and the discovery tag use it, and `.md` requests for the original WordPress URL (`/about.md`) are redirected to it. URL patterns in the exclusion list match the custom path.

## How it works

1. A request for `/about.md` is caught early (on `parse_request`), so no rewrite rules are added and nothing needs flushing.
2. The post is resolved from the path and checked for eligibility: published, not password protected, post type enabled, not excluded.
3. The page is fetched from its own public URL as a logged-out visitor, so theme templates, ACF output and page builders are included. If that fails, the post content is used instead.
4. The main content is selected (`main`, `[role="main"]`, `#content`, `#main`, `.site-content`, `article`, then `body`). Navigation, sidebars, forms, scripts, hidden elements and site-level headers and footers are removed. Headers and footers inside an `article`, `main` or `section` are kept. The result is converted to Markdown with a metadata header.

## Markdown by Accept header

Off by default. Turn on **Return Markdown when a client asks for it** under AgentWelcome > Settings > Content > Accept header. A GET or HEAD request for a normal page address (no `.md`) whose `Accept` header ranks `text/markdown` above `text/html` then gets the same stored Markdown as the `.md` address. The `q` values are read, so `text/html, text/markdown;q=0.5`, `*/*`, `text/*` and a missing header still get the HTML page, and so does a tie.

The answer sends `Vary: Accept`, `Cache-Control: private, no-store` (not the public 5 minute header of the `.md` address) and a canonical `Link` header to the HTML page, because many CDNs and page caches ignore `Vary`. It is only used for singular pages that are served as Markdown. Pages that are excluded, not served or not stored yet, and requests for the admin, REST, AJAX, feeds, previews and searches, are answered by WordPress as usual, with no 404.

## noindex pages

By default, a page that says noindex is left out of `llms.txt`, `llms-full.txt` and the `.md` files (the `.md` URL then gets the 404 described below). A page says noindex when the robots meta tag in its `<head>` or its `X-Robots-Tag` header has `noindex` or `none`. The plugin reads this from the page it already fetches, so it works whatever sets it: an SEO plugin on one page or on a whole post type, the theme, or the server. Directives for one named crawler, such as `googlebot: noindex`, are ignored.

The result is stored with each generated page, so a change shows after the page has been refreshed, which happens a few seconds after it is saved. `llms.txt` and `llms-full.txt` list only pages that have been generated, because until then it is not known whether a page says noindex. After an install or an update the plugin generates every page once in the background, so the list fills in over a few minutes, longer on a large site. A page built from the post content only, because the rendered page could not be read, is treated as not noindex.

Untick **Leave out pages set to noindex** under AgentWelcome > Settings > Content > Exclusions to include them again. Verify & Preview and the editor box say when this is the reason a page is not served. WordPress's "Discourage search engines" setting makes every page noindex, and a notice says so. The `agentwelcome_is_noindex` filter can change the result for a page.

## Pages that visitors cannot read

Pages are read as a logged-out visitor, but `llms.txt` is built from the database. A page that a membership or access-control plugin hides from visitors could therefore leak its title, link and description, so it is left out of `llms.txt`, `llms-full.txt` and the `.md` files (the `.md` URL gets the 404 described below). A page counts as restricted when the request for its address gets HTTP 403, or HTTP 401 that does not ask for HTTP Basic credentials, or is redirected to a different path, such as a login page. A trailing slash, http to https, www and non-www, the query string and the plugin's own `agentwelcome_render` argument do not count as a different path. A 401 that asks for HTTP Basic credentials is a protected site, handled by the HTTP Basic authentication setting.

A restricted page is never built from its post content. The result is stored with the page, like noindex, so it shows after the page has been refreshed, and Verify & Preview and the editor box give the reason, for example HTTP 403 or redirected to `/login/`. A page that cannot be checked, because it was built without the rendered page, is served.

Untick **Leave out pages that visitors cannot read** under AgentWelcome > Settings > Content > Exclusions to include such pages again; every page is then refreshed in the background. The `agentwelcome_is_restricted` filter includes a single page or a reason on purpose.

## Addresses that are not served

A `.md` address that is not served gets a plain text `404 Not found` from the plugin itself, with `Cache-Control: no-store` and `X-Robots-Tag: noindex`. This covers pages that are hidden, excluded, noindex, restricted, password protected or not published, and addresses that match no page. The plugin answers before WordPress runs its own redirects, so another plugin or a host rule cannot turn the request into a redirect to the homepage. It is not the theme's 404 page, and it never redirects to the HTML page. A `.md` path under the wrong address, such as a page's old permalink, is still redirected with a 301 to the right Markdown address. To leave these requests to WordPress instead, return `false` from the `agentwelcome_answer_unserved_md` filter.
