<p align="center"><img src="https://raw.githubusercontent.com/samiahmedsiddiqui/agentwelcome/main/.github/assets/banner.png" alt="AgentWelcome" width="100%"></p>

Serves an llms.txt index and a clean Markdown version of every page, so AI agents can read your content.

[![Version](https://img.shields.io/wordpress/plugin/v/agentwelcome)](https://wordpress.org/plugins/agentwelcome/)
[![Tests](https://github.com/samiahmedsiddiqui/agentwelcome/actions/workflows/phpunit-tests.yml/badge.svg)](https://github.com/samiahmedsiddiqui/agentwelcome/actions/workflows/phpunit-tests.yml)
[![Licence](https://img.shields.io/github/license/samiahmedsiddiqui/agentwelcome)](LICENSE)
[![Downloads](https://img.shields.io/wordpress/plugin/dt/agentwelcome)](https://wordpress.org/plugins/agentwelcome/)

The Markdown is built from the rendered page, so ACF fields, Gutenberg blocks, Elementor layouts and classic editor content are all included, whatever the theme.

## Features

- `/llms.txt`, a Markdown index of your pages. Write your own text instead, or put it above the generated list.
- Optional `/llms-full.txt`, the Markdown of every page in one file.
- Optional comment lines in `robots.txt` that point to these files.
- A `.md` version of every page, for example `/about.md`.
- All public post types enabled by default, with a setting to exclude any of them.
- Hide individual pages from the editor, or exclude by ID, URL pattern or taxonomy term. Pages whose source says noindex, and pages that visitors cannot read (HTTP 401 or 403, or a redirect to a login page), are left out by default.
- Per-page description for `llms.txt`. When empty it uses the meta description of the rendered page (as printed by your SEO plugin or theme), then the excerpt. The page content is never used, so a page without any of these is listed without a description.
- Verify & Preview: checks that the stored Markdown matches the page, shows a rendered preview and the changes, and updates it in one click.
- Check all pages: a background scan that lists every stored page that has drifted from its live page.
- Works behind HTTP Basic authentication.
- Generated Markdown is stored and refreshed when a page is saved, so agents get a fast answer.
- Runs on production by default. Staging, development and local are opt-in, and a URL allow-list and a `wp-config.php` kill switch keep copied sites quiet.

## Requirements

- PHP 8.0+ with the DOM extension, WordPress 6.0+.
- **Pretty permalinks.** Plain permalinks (`?p=123`) are not supported. Nothing is served for them and no Markdown is generated for URLs that contain a query string.

## Install

From the [WordPress.org plugin directory](https://wordpress.org/plugins/agentwelcome/): in the admin go to Plugins, Add New, and search for AgentWelcome. Questions and help go to the [support forum](https://wordpress.org/support/plugin/agentwelcome/).

Composer:

```bash
composer require wpackagist-plugin/agentwelcome
```

Or copy the plugin folder to `wp-content/plugins/agentwelcome` and activate it.

## Usage

- `/llms.txt` lists your pages for AI agents. `/llms-full.txt` is optional and holds every page in one file.
- Every page has a Markdown version at its address plus `.md`, for example `/about.md`. The homepage is `/index.md`.
- Open Verify & Preview in the admin menu to check a page against its stored Markdown.

## Options

Settings are under AgentWelcome in the admin menu: which post types and pages to leave out, the content area, elements to remove or write as headings, the `llms.txt` mode, the environments it runs in, and HTTP Basic credentials. See [Settings and pages](docs/settings.md).

## Documentation

- [How it works](docs/how-it-works.md): the addresses it serves, how a page becomes Markdown, Custom Permalinks and noindex pages.
- [Settings and pages](docs/settings.md): the Settings, llms.txt and Verify & Preview pages, and who can manage the llms.txt files.
- [Environments](docs/environments.md): where the plugin runs, allowed URLs, and what happens when a site is copied or moved.
- [Stored Markdown](docs/stored-markdown.md): how the Markdown is stored and refreshed, WP-CLI commands, and uninstall.
- [Hooks](docs/hooks.md): filters and code examples.
- [Development](docs/development.md): checks, tests and how the plugin is developed.

## Development

Developed with AI coding tools and maintained by Sami Ahmed Siddiqui. Every change is reviewed by the author and checked with automated tests, PHPCS, PHPStan and Plugin Check before release. Setup and commands are in [docs/development.md](docs/development.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
