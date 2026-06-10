<?php
// WPlite site configuration — NEW CLIENT TEMPLATE.
//
// To create a client site:
//   1. cp -a sites/_template sites/{slug}
//      slug: lowercase letters/numbers/hyphens, 2-30 chars.
//      Reserved (never use): s, admin, assets, sites, content, uploads, templates, www
//   2. Set admin_hash below — generate with:
//      php -r "echo password_hash('THE-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
//      (empty hash = admin locked, nobody can log in)
//   3. Preview at https://aqntech.com/wplight/s/{slug}/  (admin: .../s/{slug}/admin/)
//   4. Go live: add the client's domain as a cPanel addon domain with docroot
//      public_html/wplight, then list it in 'domains' below (www is implied).
return array (
  'site_name' => 'New Client',
  'blurb' => '',
  'portrait' => '',
  'logo' => '',
  'accent' => '#c2185b',
  'socials' =>
  array (
    'instagram' => '',
    'x' => '',
    'facebook' => '',
    'youtube' => '',
    'email' => '',
  ),
  'domains' => array (),
  'batch_size' => 12,
  'admin_hash' => '',
);
