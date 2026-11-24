# Development

## Setup and checks

```bash
composer install
```

| Command | What it does |
| --- | --- |
| `composer lint` | Runs PHP_CodeSniffer with the WordPress Coding Standards and PHP 8.0+ compatibility checks. It also checks that every file, class, function, constant and class variable has a comment. |
| `composer format` | Fixes the violations PHP_CodeSniffer can fix automatically. |
| `composer analyse` | Runs PHPStan. |
| `composer test` | Runs the PHPUnit unit tests (no WordPress needed). |

Every pull request runs these checks plus [Plugin Check](https://github.com/WordPress/plugin-check), the end-to-end suites in `tests/integration` and the wp-env tests in `tests/wp-env`. See the README in each folder for how to run them. See [CONTRIBUTING.md](../CONTRIBUTING.md) for how to send a change.

## How this plugin is developed

Developed with AI coding tools and maintained by Sami Ahmed Siddiqui. Every change is reviewed by the author and checked with automated tests, PHPCS, PHPStan and Plugin Check before release.

## Releasing

Releases go to the WordPress.org plugin directory from GitHub, with `.github/workflows/deploy-wporg.yml`.

1. Set the same version in the plugin header and in the readme's `Stable tag`, and add its changelog entry.
2. Merge to `main`, then publish a GitHub release whose tag is that version, for example `1.0.0`.
3. The workflow checks that the tag, header and stable tag match, builds the plugin without the export-ignored files, commits `trunk`, the new tag and the `.wordpress-org` assets to the SVN repository, and attaches the zip to the release.

Set these once, in Settings, Environments, `wordpress-org`: the secrets `SVN_USERNAME` and `SVN_PASSWORD` (the WordPress.org account that owns the plugin) and, if you want an approval before each deploy, required reviewers. To try it without publishing, run the workflow by hand from the Actions tab. A manual run is a dry run unless you untick the option.
