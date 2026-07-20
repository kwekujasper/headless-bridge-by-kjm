# Headless Bridge by KJM

Transform WordPress into a secure, configurable headless CMS for any modern frontend framework (Next.js, Nuxt, Astro, SvelteKit, and more).

**Author:** Kweku Jasper Media ([@kwekujasper](https://github.com/kwekujasper))
**Requires at least:** WordPress 6.5 · **Requires PHP:** 8.1 · **Stable tag:** 1.2.4
**License:** [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

> This is the source repository. [`readme.txt`](readme.txt) is the canonical WordPress.org-format listing; this file is a GitHub-friendly overview of the same plugin.

## Description

**Headless Bridge by KJM** is a production-ready plugin that converts WordPress into a powerful headless CMS while preserving full access to the REST API, GraphQL, admin, AJAX, and cron endpoints. Point it at your frontend, turn on Headless Mode, and every normal front-end visitor request gets redirected there — while `/wp-json/*`, `/graphql`, `/wp-admin/*`, and the other operational endpoints keep working normally.

## Key Features

* **Headless Mode** — Redirect all frontend traffic to your external frontend (Next.js, Nuxt, Astro, SvelteKit, Gatsby, etc.)
* **Slug Preservation** — `/my-post` on WordPress redirects to `yourfrontend.com/my-post`
* **SEO Protection** — `X-Robots-Tag: noindex, nofollow` header + optional robots.txt override
* **CORS Management** — Configure allowed origins with fine-grained `Access-Control-*` headers
* **Feature Toggles** — Disable RSS, search, comments, author archives, date archives
* **Maintenance Mode** — Show a branded maintenance page when the frontend is unavailable
* **Health Checker** — Dashboard widget that verifies REST API, GraphQL, frontend reachability, and CORS configuration
* **Webhook Builder** — No-code webhooks for post/page/product publish, updates, category and author changes, comments, and site settings — notify your frontend's ISR revalidation endpoint, Slack, Zapier, or anything else, no separate webhooks plugin required. A one-click "Quick Setup" fills in every trigger so you only need to supply your frontend URL and a secret
* **Image Optimization Strategy** — Choose how your frontend serves images (exposed over GraphQL as `generalSettings.imageStrategy`): the hosting platform's native optimizer, a self-hosted Node.js resizer, the free wsrv.nl proxy, or unoptimized passthrough — pick whichever fits your frontend's hosting platform
* **Settings Import/Export** — Back up and restore your configuration as JSON
* **Security Headers** — `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`

## Compatible Frameworks

Next.js · Nuxt · Astro · SvelteKit · Gatsby · React (any host) · Mobile applications

## Protected Endpoints (always allowed)

`/wp-json/*`, `/graphql`, `/wp-admin/*`, `/wp-login.php`, `/wp-cron.php`, `/admin-ajax.php`, `/wp-content/*`, `/wp-includes/*`

## Installation

1. Upload the `headless-bridge-by-kjm` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins > Installed Plugins**.
3. Go to **Settings > Headless Bridge**.
4. Enter your **Frontend URL** (e.g. `https://yoursite.com`).
5. Enable **Headless Mode**.
6. Optionally configure CORS origins, disable features, and run a health check.

## Frequently Asked Questions

**Will this break my REST API?**
No. REST API endpoints (`/wp-json/*`) are always allowed through regardless of headless mode status.

**Does it work with WPGraphQL?**
Yes. The `/graphql` endpoint is preserved. The health checker will also verify GraphQL availability.

**Can I use this on a multisite?**
Not yet — multisite support is planned for a future release. Single-site only for now.

**What happens if my frontend goes down?**
Enable **Maintenance Mode** in General settings. Visitors will see a branded maintenance page instead of a redirect loop.

**Is XML-RPC affected?**
By default XML-RPC remains enabled (useful for Jetpack and mobile apps). You can optionally disable it under General settings. Note that when disabled, every XML-RPC method is removed (not just access to it) — clients like the WordPress mobile app will report methods such as `wp.getPosts` as nonexistent rather than access-denied.

**Which Image Optimization Strategy should I pick?**
* **Native** (default) — use this if your frontend is on Vercel, or on a plain Node.js server; it works out of the box on both.
* **Sharp** — a self-hosted resizer that only works if your frontend runs on a real Node.js server (a VPS, or Vercel's serverless functions). It does not work on edge/Workers runtimes (e.g. Cloudflare Workers).
* **Free proxy (wsrv.nl)** — works on any frontend host, including Cloudflare Workers, and is the easiest way to avoid Cloudflare Images' free-tier transformation quota.
* **Unoptimized** — serves original files with no resizing; works everywhere, at the cost of larger page weight.

## Screenshots

1. General Settings — toggle headless mode and set frontend URL.
2. API & CORS — configure allowed origins.
3. Features — disable RSS, search, comments, and archives.
4. Health Checker — live status of all endpoints.
5. Tools — export, import, flush, and reset.

## Development

* `includes/` — one class per subsystem (`Settings`, `Redirects`, `Api`, `Security`, `Cors`, `Health`, `Webhooks`, `Admin`, `Graphql`), wired up in `includes/class-plugin.php`.
* `templates/` — admin page markup, included by `class-admin.php` and `class-health.php`.
* `tests/` — PHPUnit tests; run with `phpunit` after setting up `WP_TESTS_DIR` (see `tests/bootstrap.php`).
* `.github/workflows/release-zip.yml` — builds an installable plugin zip on every `vX.Y.Z` tag push, excluding everything in `.distignore`.
* `.github/workflows/deploy-svn.yml` — syncs tagged releases to the WordPress.org SVN repository once the plugin has been approved there.

Cut a release with:

```bash
git tag v1.2.4 && git push --tags
```

## Changelog

### 1.2.4
* Fixed: text domain now matches the plugin slug (`headless-bridge-by-kjm`) everywhere — required for WordPress.org Plugin Check to pass, since translations are looked up by slug.
* Fixed: settings page slug and its admin-page hook/links are now consistent with the plugin slug.
* Fixed: removed the now-unnecessary `load_plugin_textdomain()` call — WordPress.org auto-loads translations for hosted plugins.
* Fixed: the frontend redirect now uses `wp_safe_redirect()` (with the configured frontend host allow-listed) instead of `wp_redirect()`.
* Fixed: the reset-settings password field is now unslashed and sanitized before use.
* Fixed: `templates/maintenance.php` now guards against direct file access.
* Fixed: `headlessbridge_post_path_prefix` is now removed on uninstall along with the plugin's other options.
* Updated: "Tested up to" bumped to 7.0; trimmed the 1.2.0 upgrade notice to fit the WordPress.org length limit; reduced tags to 5.

### 1.2.3
* Security: a wildcard (`*`) CORS origin no longer reflects the requester's Origin header alongside `Access-Control-Allow-Credentials: true` — that combination let any site ride a logged-in visitor's cookies cross-origin. Wildcard now sends a literal `*` with credentials omitted.
* Security: webhook secrets are now encrypted at rest (AES-256-CBC, keyed from WordPress's own auth salt) instead of stored as plaintext. Existing secrets keep working and are transparently re-encrypted the next time that webhook is saved.
* Security: Import Settings now validates and sanitizes imported webhooks the same way the webhook builder does, instead of writing the uploaded file's contents directly.
* Fixed: unpublishing/trashing a post sent the wrong (WordPress-mangled, `__trashed`-suffixed) slug to webhooks, breaking frontend revalidation for that route.
* Fixed: a webhook's payload template is now validated against every selected trigger, not just the first.
* Fixed: Health tab checks no longer disable TLS certificate verification on outbound requests.
* Fixed: uninstalling the plugin now also removes the `headlessbridge_image_strategy` option.

### 1.2.2
* Fixed the admin page's JavaScript never initializing — a typo in the localized script's object name broke the Health tab and the entire Webhooks tab.

### 1.2.1
* Fixed settings on one tab being silently wiped when saving another tab. Each settings tab now has its own Settings API group.

### 1.2.0
* Renamed the plugin from "HeadlessWP by KJM" to "Headless Bridge by KJM" ahead of submitting to the WordPress.org Plugin Directory, to avoid the trademark restriction on "WordPress"/"WP" in a product name. Existing installs migrate settings automatically on activation.
* Added an Image Optimization Strategy setting, exposed over GraphQL as `generalSettings.imageStrategy`.
* Added a "Quick Setup: Revalidate My Frontend" button to the Webhook Builder.
* Reset Settings now requires re-entering your account password in a confirmation modal.
* Fixed a critical error that could occur when saving any settings tab other than the one containing the CORS origins or Image Optimization Strategy fields.

### 1.1.0
* Added a native, no-code webhook builder with a JSON payload template, masked secrets, and a "Send Test Webhook" tool.

### 1.0.1
* Updated plugin contributor to Kweku Jasper Media.

### 1.0.0
* Initial release — headless mode, CORS management, health checker, feature toggles, maintenance mode, settings import/export, security headers, translation-ready.

Full changelog and per-version upgrade notices: see [`readme.txt`](readme.txt).

## License

GPLv2 or later — see [license text](https://www.gnu.org/licenses/gpl-2.0.html).

Copyright (C) Kweku Jasper Media
