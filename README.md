# WPlite

A featherweight, multi-tenant publishing platform by [AQNTech](https://aqntech.com) — blogging that feels like Instagram. No database, no plugins, no build step. Just PHP, flat files, and good typography.

**Live demo:** https://aqntech.com/wplite/

## Why

AI made heavyweight CMSes redundant for personal publishing. Founders, MPs, and public figures need a face, a feed, and their writing — not a plugin ecosystem. WPlite is the whole platform in ~15 files.

## Features

- **Instagram-style public site** — logo + social icons, portrait & blurb hero, auto-sliding featured carousel, 3-column endless-scroll grid, elegant editorial typography (serif display, drop caps, warm paper palette)
- **Multi-tenant** — one engine serves unlimited client sites; each client is markdown content + uploads under `sites/{slug}/` plus a private config in `data/{slug}/`, created from the web installer at `/install`
- **Three ways to reach a site** — client's own domain (domain mode), `/s/{slug}/` previews on the engine host (path mode), or the unprefixed fallback site (root mode)
- **Admin panel** — warm claude-style theme; post CRUD with image upload, page editor, settings (name, blurb, accent colour, socials, password). One password per site, no user accounts
- **Markdown content** — posts are front-matter markdown files; editable by the admin panel, any text editor, or an AI agent
- **Security** — bcrypt passwords, per-site session + CSRF isolation, file-based per-IP login throttle with exponential backoff, GD re-encoding of every upload (kills payloads, strips EXIF), strict slug validation, atomic writes
- **Engine-level credit page** — every tenant site links "Powered by WPlite" to a built-in page that cannot be deleted by tenants

## File map

```
index.php        front controller: routes home / post / page / feed.json / uploads / admin / install
install.php      engine installer + master dashboard (list & create sites from the browser)
lib.php          engine core: site resolution, config, content repo, markdown
lib/security.php sessions, login throttle, CSRF, site-admin + master auth
lib/images.php   upload validation, GD re-encoding, thumbnails, upload serving
admin/           index.php (auth + dispatch), helpers.php, actions/{posts,pages,settings}.php
templates/       layout, home, post, page, credit
assets/          style.css (public theme), admin.css (admin theme), app.js (carousel + endless scroll)
data/            web-blocked engine data: master.php, {slug}/config.php, throttle/ (not in git)
sites/{slug}/    a tenant's public-ish half: content/ markdown + uploads/ images
sites/_template/ starter content copied into new sites
seed.php         CLI demo-content generator:  php seed.php [site-slug]
deploy/          wplite-router.php — WordPress mu-plugin for nginx hosts (see below)
```

Secrets and tenant configs live under `data/` (denied to the web on Apache; on
nginx-style hosts they are plain PHP files that execute to nothing, so never
store non-PHP secrets there).

## Creating a client site

Open `https://your-host/wplite/install`. On the first visit it asks you to set
the engine **master password**; after that it shows a dashboard of all sites
with a create form (slug, name, site admin password) — no CLI needed.

Preview immediately at `https://your-host/wplite/s/acme/` (admin at `.../s/acme/admin/`). To go live on their domain, point it at the install (docroot = this folder) and add it to `'domains' => ['acme.com']` in `data/acme/config.php`.

## Deployment

- **Apache / LiteSpeed:** the included `.htaccess` files handle pretty URLs and lock down `sites/` — nothing else needed.
- **nginx (no .htaccess):** if WPlite lives in a subfolder of a WordPress docroot, copy `deploy/wplite-router.php` into `wp-content/mu-plugins/` — nginx falls unmatched URLs through to WordPress and the mu-plugin hands `/wplite/*` to the engine. On a dedicated nginx vhost, use a standard `try_files $uri $uri/ /index.php?$args;` instead.

## Local development

```bash
php seed.php                                   # generate demo site content
php -S localhost:8090 -t . index.php           # pretty URLs work via the router shim
```

Then visit `http://localhost:8090/install` once to set the master password.

---

Built by [AQNTech](https://aqntech.com) · © 2026
