# Headless Bridge by KJM

Transform WordPress into a secure, configurable headless CMS for any modern frontend framework (Next.js, Nuxt, Astro, SvelteKit, and more).

Author: **Kweku Jasper Media** ([@kwekujasper](https://github.com/kwekujasper))
License: [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

> This is the public source repository. See [`readme.txt`](readme.txt) for the full WordPress.org-format plugin description, installation steps, FAQ, and changelog.

## Key Features

* **Headless Mode** — Redirect all frontend traffic to your external frontend (Next.js, Nuxt, Astro, SvelteKit, Gatsby, etc.)
* **Slug Preservation** — `/my-post` on WordPress redirects to `yourfrontend.com/my-post`
* **SEO Protection** — `X-Robots-Tag: noindex, nofollow` header + optional robots.txt override
* **CORS Management** — Configure allowed origins with fine-grained `Access-Control-*` headers
* **Feature Toggles** — Disable RSS, search, comments, author archives, date archives
* **Maintenance Mode** — Show a branded maintenance page when the frontend is unavailable
* **Health Checker** — Dashboard widget that verifies REST API, GraphQL, frontend reachability, and CORS configuration
* **Webhook Builder** — No-code webhooks for post/page/product publish, updates, category and author changes, comments, and site settings — notify your frontend's ISR revalidation endpoint, Slack, Zapier, or anything else
* **Image Optimization Strategy** — Choose how your frontend serves images, exposed over GraphQL as `generalSettings.imageStrategy`
* **Settings Import/Export** — Back up and restore your configuration as JSON
* **Security Headers** — `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`

## Compatible Frameworks

Next.js, Nuxt, Astro, SvelteKit, Gatsby, React (any host), and mobile applications.

## Installation

1. Upload the `headless-bridge-by-kjm` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins > Installed Plugins**.
3. Go to **Settings > Headless Bridge**.
4. Enter your **Frontend URL** and enable **Headless Mode**.

See [`readme.txt`](readme.txt) for full documentation.

## License

GPLv2 or later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

Copyright (C) Kweku Jasper Media
