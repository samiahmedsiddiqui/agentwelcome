# Hooks

Filters and examples for changing what the plugin does from code.

`llms.txt` and `llms-full.txt` are kept for up to five minutes, so a filter that changes which pages they list or how they read can take that long to show.

| Filter | Purpose |
| --- | --- |
| `agentwelcome_is_eligible` | Exclude a post programmatically. Receives `$eligible` and the `WP_Post`. |
| `agentwelcome_is_restricted` | Whether a page that visitors cannot read is left out. Starts as `true`. Receives the result, the `WP_Post` and a reason code: `http_401`, `http_403` or `redirect`. Return `false` to include the page on purpose. |
| `agentwelcome_is_noindex` | Whether a page counts as noindex. Starts as what its robots meta tag and `X-Robots-Tag` header say. Receives the result and the `WP_Post`. |
| `agentwelcome_excluded_post_types` | Add or remove excluded post types in code. |
| `agentwelcome_show_meta_box` | Show or hide the editor box. Receives the setting and the post type. |
| `agentwelcome_resolve_post` | Map a request path to a post. |
| `agentwelcome_use_loopback` | Return `false` to skip fetching the page and use the post content. |
| `agentwelcome_loopback_timeout` | Seconds to wait for the page request (default 15). |
| `agentwelcome_files_capability` | Capability that is checked for the llms.txt page (default `agentwelcome_manage_files`). |
| `agentwelcome_max_concurrent_renders` | Pages that may be rendered at the same time for visitors (default 3). |
| `agentwelcome_strip_selectors` | CSS selectors for elements to remove. |
| `agentwelcome_markdown` | Final Markdown for a page. |
| `agentwelcome_description` | The description used in the metadata and `llms.txt`, after the editor box, the page's meta description and the excerpt were tried in that order. |
| `agentwelcome_check_site_address` | Return `false` to turn off the automatic pause when the site address changes. |
| `agentwelcome_compare_ignore` | Regular expressions for text that Verify & Preview and the scan leave out when comparing. |
| `agentwelcome_title_site_names` | Site names removed from page titles, as a list of strings. Defaults to the Site Title and the page's `og:site_name`. Receives the list and the `WP_Post`. |
| `agentwelcome_llms_txt` | Final `llms.txt` content. |
| `agentwelcome_llms_full_txt` | Final `llms-full.txt` content. |
| `agentwelcome_llms_full_max_bytes` | Size limit of `llms-full.txt` (default 5,000,000 bytes). |
| `agentwelcome_llms_max_items` | Entries per post type in `llms.txt` (default 500). |
| `agentwelcome_content_type` | Content type of the Markdown response. |
| `agentwelcome_answer_unserved_md` | Return `false` to stop the plugin from answering a `.md` address that is not served with its own 404, and leave it to WordPress. Receives the route and the requested path. |
| `agentwelcome_response_headers` | Response headers. |
| `agentwelcome_ai_crawlers` | User agents of the AI crawlers that Verify & Preview checks against robots.txt (GPTBot, ClaudeBot, PerplexityBot and others by default). |
| `agentwelcome_woocommerce_excluded_pages` | WooCommerce pages that are left out: `cart`, `checkout`, `myaccount` and `terms` by default. Return a shorter list to include one. |

### Excluding from code

```php
// Leave out one page.
add_filter( 'agentwelcome_is_eligible', function ( $eligible, $post ) {
	return 'private-docs' === $post->post_name ? false : $eligible;
}, 10, 2 );

// Leave out a whole post type.
add_filter( 'agentwelcome_excluded_post_types', function ( $types ) {
	return array_merge( $types, array( 'product' ) );
} );

// Hide the editor box for everyone.
add_filter( 'agentwelcome_show_meta_box', '__return_false' );
```

## Actions

| Action | Purpose |
| --- | --- |
| `agentwelcome_markdown_updated` | The stored Markdown of a page changed, or was removed because the page is no longer served. Receives the post ID and the Markdown address. It does not fire when a refresh produces the same Markdown. |
| `agentwelcome_cache_cleared` | Every stored copy was cleared. It fires once, with no arguments, not once per page. |

### Purging a host or CDN cache

Servers and CDNs may keep a `.md` address for a few minutes. Purge it when it changes:

```php
add_action( 'agentwelcome_markdown_updated', function ( $post_id, $url ) {
	my_cdn_purge( array( $url ) );
}, 10, 2 );

add_action( 'agentwelcome_cache_cleared', function () {
	my_cdn_purge_everything();
} );
```

The action runs while the page is being stored, so keep the callback fast or queue the purge. A refresh of the whole site fires it once for each page whose Markdown changed.

### WooCommerce

When WooCommerce is active, its cart, checkout, My account and terms pages are not listed in `llms.txt` and are not served as Markdown, because they are different for each visitor. The shop page and products are not affected. To include one of them:

```php
add_filter( 'agentwelcome_woocommerce_excluded_pages', function ( $pages ) {
	return array_diff( $pages, array( 'terms' ) );
} );
```
