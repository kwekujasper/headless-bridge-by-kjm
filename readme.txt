=== Headless Bridge by KJM ===
Contributors: kwekujasper
Tags: headless, rest-api, graphql, cors, redirect
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform WordPress into a secure, configurable headless CMS for any modern frontend framework.

== Description ==

**Headless Bridge by KJM** is a production-ready plugin that converts WordPress into a powerful headless CMS while preserving full access to the REST API, GraphQL, admin, AJAX, and cron endpoints.

= Key Features =

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

= Compatible Frameworks =

* Next.js
* Nuxt
* Astro
* SvelteKit
* Gatsby
* React (any host)
* Mobile applications

= Protected Endpoints (always allowed) =

`/wp-json/*`, `/graphql`, `/wp-admin/*`, `/wp-login.php`, `/wp-cron.php`, `/admin-ajax.php`, `/wp-content/*`, `/wp-includes/*`

== Installation ==

1. Upload the `headless-bridge-by-kjm` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins > Installed Plugins**.
3. Go to **Settings > Headless Bridge**.
4. Enter your **Frontend URL** (e.g. `https://yoursite.com`).
5. Enable **Headless Mode**.
6. Optionally configure CORS origins, disable features, and run a health check.

== Frequently Asked Questions ==

= Will this break my REST API? =

No. REST API endpoints (`/wp-json/*`) are always allowed through regardless of headless mode status.

= Does it work with WPGraphQL? =

Yes. The `/graphql` endpoint is preserved. The health checker will also verify GraphQL availability.

= Can I use this on a multisite? =

Not yet — multisite support is planned for a future release. Single-site only for now.

= What happens if my frontend goes down? =

Enable **Maintenance Mode** in General settings. Visitors will see a branded maintenance page instead of a redirect loop.

= Is XML-RPC affected? =

By default XML-RPC remains enabled (useful for Jetpack and mobile apps). You can optionally disable it under General settings. Note that when disabled, every XML-RPC method is removed (not just access to it) — clients like the WordPress mobile app will report methods such as `wp.getPosts` as nonexistent rather than access-denied.

= Which Image Optimization Strategy should I pick? =

* **Native** (default) — use this if your frontend is on Vercel, or on a plain Node.js server; it works out of the box on both.
* **Sharp** — a self-hosted resizer that only works if your frontend runs on a real Node.js server (a VPS, or Vercel's serverless functions). It does not work on edge/Workers runtimes (e.g. Cloudflare Workers).
* **Free proxy (wsrv.nl)** — works on any frontend host, including Cloudflare Workers, and is the easiest way to avoid Cloudflare Images' free-tier transformation quota.
* **Unoptimized** — serves original files with no resizing; works everywhere, at the cost of larger page weight.

== Screenshots ==

1. General Settings — toggle headless mode and set frontend URL.
2. API & CORS — configure allowed origins.
3. Features — disable RSS, search, comments, and archives.
4. Health Checker — live status of all endpoints.
5. Tools — export, import, flush, and reset.

== Changelog ==

= 1.2.4 =
* Fixed: text domain now matches the plugin slug (`headless-bridge-by-kjm`) everywhere — required for WordPress.org Plugin Check to pass, since translations are looked up by slug.
* Fixed: settings page slug and its admin-page hook/links are now consistent with the plugin slug (previously a leftover `headless-bridge` reference in a couple of spots).
* Fixed: removed the now-unnecessary `load_plugin_textdomain()` call — WordPress.org auto-loads translations for hosted plugins.
* Fixed: the frontend redirect now uses `wp_safe_redirect()` (with the configured frontend host allow-listed) instead of `wp_redirect()`.
* Fixed: the reset-settings password field is now unslashed and sanitized before use.
* Fixed: `templates/maintenance.php` now guards against direct file access.
* Updated: "Tested up to" bumped to 7.0; trimmed the 1.2.0 upgrade notice to fit the WordPress.org length limit; reduced tags to 5.

= 1.2.3 =
* Security: a wildcard (`*`) CORS origin no longer reflects the requester's Origin header alongside `Access-Control-Allow-Credentials: true` — that combination let any site ride a logged-in visitor's cookies cross-origin. Wildcard now sends a literal `*` with credentials omitted, matching what the setting is actually meant to allow (public, unauthenticated access).
* Security: webhook secrets are now encrypted at rest (AES-256-CBC, keyed from WordPress's own auth salt) instead of stored as plaintext in the database. Existing secrets keep working and are transparently re-encrypted the next time that webhook is saved.
* Security: Import Settings now validates and sanitizes imported webhooks the same way the webhook builder does (valid URL, known trigger keys, a template that renders to JSON) instead of writing the uploaded file's contents directly — a malicious "settings backup" could otherwise plant an arbitrary webhook.
* Fixed: unpublishing/trashing a post sent the wrong (WordPress-mangled, `__trashed`-suffixed) slug to webhooks, breaking frontend revalidation for that route.
* Fixed: a webhook's payload template was only validated against its first selected trigger; it's now validated against every selected trigger so a template that only works for some of them is caught at save time instead of failing silently later.
* Fixed: Health tab checks (WP REST API, GraphQL, Frontend Reachability) no longer disable TLS certificate verification on outbound requests.
* Fixed: uninstalling the plugin now also removes the `headlessbridge_image_strategy` option.

= 1.2.2 =
* Fixed the admin page's JavaScript never initializing — a typo in the localized script's object name (`headless-bridgeAdmin` instead of `headlessbridgeAdmin`) made WordPress emit invalid JavaScript, so the Health tab's "Run Check"/"Clear Cache" buttons and the entire Webhooks tab (add/edit/delete/test/generate secret) silently failed with no working AJAX URL or nonce.

= 1.2.1 =
* Fixed settings on one tab (e.g. SEO or CORS) being silently wiped when saving another tab — most visibly, Headless Mode and the Frontend URL turning back off after saving any other settings tab. All settings tabs previously shared one Settings API group, and WordPress's options.php resets every option in a group to empty on each save unless it's part of the tab actually submitted. Each tab (General, SEO, Features, API & CORS) now has its own settings group so saving one tab no longer touches another's values.

= 1.2.0 =
* Renamed the plugin from "HeadlessWP by KJM" to "Headless Bridge by KJM" (new slug `headless-bridge`, new text domain, new option/hook prefixes) ahead of submitting to the WordPress.org Plugin Directory, to avoid the trademark restriction on "WordPress"/"WP" in a product name. Sites already running the plugin under its old name have their settings migrated automatically on activation.
* Added an Image Optimization Strategy setting (General tab) — choose between your frontend platform's native image optimizer, a self-hosted Node.js resizer ("Sharp"), the free wsrv.nl proxy, or unoptimized passthrough. Exposed to the frontend over GraphQL as `generalSettings.imageStrategy`.
* Added a "Quick Setup: Revalidate My Frontend" button to the Webhook Builder — checks every available trigger automatically so a non-technical user only has to fill in the frontend URL and secret.
* Reset Settings now requires re-entering your account password in a confirmation modal before it takes effect, in addition to the existing nonce check — an irreversible action needed a stronger confirmation than a nonce alone provides.
* Fixed a critical error ("There has been a critical error") that could occur when saving *any* settings tab other than the one containing the CORS origins or Image Optimization Strategy fields — a type-safety bug in those two fields' sanitizers crashed on the `null` value WordPress passes for fields absent from the submitting tab's form.
* Normalized webhook IDs to lowercase and tightened validation in the webhook list retrieval method.

= 1.1.0 =
* Added a native, no-code webhook builder — attach multiple triggers (post/page/product publish, update, or trash; category create/edit/delete; author profile updates; approved comments; site settings changes) to any outbound webhook, with a JSON payload template, a masked-secret field with a generator, and a "Send Test Webhook" tool. Replaces the need for a separate webhooks plugin.
* New "Webhooks" settings tab.

= 1.0.1 =
* Updated plugin contributor to Kweku Jasper Media.

= 1.0.0 =
* Initial release.
* Headless mode with slug-preserving redirects.
* CORS header management.
* Health checker (REST API, GraphQL, frontend, CORS, plugin).
* Feature toggles (RSS, search, comments, archives).
* Maintenance mode.
* Settings export / import / reset.
* Security headers.
* Translation-ready (POT file included).

== Upgrade Notice ==

= 1.2.4 =
Settings page URL slug changed from `headless-bridge` to `headless-bridge-by-kjm` (matches the plugin's text domain now) — update any bookmarks to Settings > Headless Bridge. No settings data is affected.

= 1.2.0 =
Plugin renamed to "Headless Bridge by KJM" — settings carry over automatically, but re-check Settings > Headless Bridge after upgrading since the URL slug changed. Also fixes a settings-save critical error and adds an Image Optimization Strategy setting.

= 1.1.0 =
New: a native webhook builder (Settings > Headless Bridge > Webhooks) that can replace a separate webhooks plugin for triggering frontend ISR revalidation and other automations.

= 1.0.0 =
Initial release.
