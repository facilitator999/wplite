# WPlite

A featherweight, multi-tenant publishing platform by [AQNTech](https://aqntech.com) — blogging that feels like Instagram. No database, no plugins, no build step. Just PHP, flat files, and good typography.

**Live demo:** https://aqntech.com/wplite/

## Why

AI made heavyweight CMSes redundant for personal publishing. Founders, MPs, and public figures need a face, a feed, and their writing — not a plugin ecosystem. WPlite is the whole platform in ~15 files.

## Features

- **Instagram-style public site** — logo + social icons, portrait & blurb hero, auto-sliding featured carousel, 3-column endless-scroll grid, elegant editorial typography (serif display, drop caps, warm paper palette)
- **Multi-tenant** — one engine serves unlimited client sites; each client is a folder under `sites/{slug}/` holding only their `config.php`, markdown content, and uploads
- **Three ways to reach a site** — client's own domain (domain mode), `/s/{slug}/` previews on the engine host (path mode), or the unprefixed fallback site (root mode)
- **Admin panel** — warm claude-style theme; post CRUD with image upload, page editor, settings (name, blurb, accent colour, socials, password). One password per site, no user accounts
- **Markdown content** — posts are front-matter markdown files; editable by the admin panel, any text editor, or an AI agent
- **Security** — bcrypt passwords, per-site session + CSRF isolation, file-based per-IP login throttle with exponential backoff, GD re-encoding of every upload (kills payloads, strips EXIF), strict slug validation, atomic writes
- **Engine-level credit page** — every tenant site links "Powered by WPlite" to a built-in page that cannot be deleted by tenants

## File map

```
index.php        front controller: routes home / post / page / feed.json / uploads / admin
lib.php          engine: site resolution, content repo, auth, CSRF, throttle, image pipeline
admin/index.php  the whole admin (login, posts, pages, settings)
templates/       layout, home, post, page, credit
assets/          style.css (public theme), admin.css (admin theme), app.js (carousel + endless scroll)
sites/_template/ scaffold for new client sites
seed.php         CLI demo-content generator:  php seed.php [site-slug]
deploy/          wplite-router.php — WordPress mu-plugin for nginx hosts (see below)
```

## Creating a client site

```bash
cp -a sites/_template sites/acme
php -r "echo password_hash('THEIR-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"  # paste into config
# edit sites/acme/config.php: site_name, blurb, socials, accent, admin_hash
```

Preview immediately at `https://your-host/wplite/s/acme/` (admin at `.../s/acme/admin/`). To go live on their domain, point it at the install (docroot = this folder) and add it to `'domains' => ['acme.com']` in their config.

## Deployment

- **Apache / LiteSpeed:** the included `.htaccess` files handle pretty URLs and lock down `sites/` — nothing else needed.
- **nginx (no .htaccess):** if WPlite lives in a subfolder of a WordPress docroot, copy `deploy/wplite-router.php` into `wp-content/mu-plugins/` — nginx falls unmatched URLs through to WordPress and the mu-plugin hands `/wplite/*` to the engine. On a dedicated nginx vhost, use a standard `try_files $uri $uri/ /index.php?$args;` instead.

## Local development

```bash
php seed.php                                   # generate demo site content
php -S localhost:8090 -t . index.php           # pretty URLs work via the router shim
```

---

Built by [AQNTech](https://aqntech.com) · © 2026
