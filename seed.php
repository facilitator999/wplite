<?php
// Demo content seeder. CLI only: php seed.php [site-slug]   (default: demo)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

define('WPL_FORCE_SITE', preg_replace('/[^a-z0-9-]/', '', $argv[1] ?? 'demo'));
require __DIR__ . '/lib.php';

@mkdir(WPL_POSTS, 0755, true);
@mkdir(WPL_PAGES, 0755, true);
@mkdir(WPL_UPLOADS, 0755, true);
if (!is_file(WPL_SITE_DIR . '/config.php')) {
    copy(WPL_ROOT . '/sites/_template/config.php', WPL_SITE_DIR . '/config.php');
}
echo 'Seeding site: ' . WPL_SITE . "\n";

/** Write a diagonal two-color gradient JPEG. */
function gradient(string $path, int $w, int $h, array $c1, array $c2): void
{
    $im = imagecreatetruecolor($w, $h);
    $steps = 64;
    $band = (int)ceil(($w + $h) / $steps);
    for ($i = 0; $i < $steps; $i++) {
        $t = $i / ($steps - 1);
        $col = imagecolorallocate(
            $im,
            (int)($c1[0] + ($c2[0] - $c1[0]) * $t),
            (int)($c1[1] + ($c2[1] - $c1[1]) * $t),
            (int)($c1[2] + ($c2[2] - $c1[2]) * $t)
        );
        // diagonal bands: polygon strip from top-left toward bottom-right
        $d = $i * $band;
        imagefilledpolygon($im, [
            $d, 0, $d + $band * 2, 0, 0, $d + $band * 2, 0, $d,
        ], $col);
    }
    imagejpeg($im, $path, 82);
    imagedestroy($im);
}

$palettes = [
    [[194, 24, 91], [255, 160, 90]],
    [[33, 99, 175], [120, 220, 232]],
    [[46, 125, 50], [220, 231, 117]],
    [[106, 27, 154], [240, 98, 146]],
    [[230, 81, 0], [255, 213, 79]],
    [[0, 105, 92], [128, 203, 196]],
    [[69, 39, 160], [3, 169, 244]],
    [[183, 28, 28], [255, 138, 101]],
];

$posts = [
    ['slug' => 'why-i-left-wordpress', 'title' => 'Why I left WordPress behind', 'featured' => true, 'days' => 2,
     'body' => "I ran my site on WordPress for eight years. Plugins, updates, a database I never looked at — all to publish a few hundred words a week.\n\n## What I actually needed\n\n- A photo\n- A title\n- Some text\n- A link people can share\n\n> The best tool is the one you stop noticing.\n\nSo I rebuilt everything as flat files. Publishing now takes thirty seconds, and there is nothing left to hack or update."],
    ['slug' => 'a-week-in-westminster', 'title' => 'A week in Westminster', 'featured' => true, 'days' => 5,
     'body' => "Three committee sessions, two constituency surgeries, and one very long vote night.\n\n## Highlights\n\n- Raised the housing question at PMQs\n- Met campaigners on the town hall steps\n- Read every single letter you sent — keep them coming\n\nMore detail in next week's post."],
    ['slug' => 'building-in-public', 'title' => 'Building in public, month one', 'featured' => true, 'days' => 8,
     'body' => "Thirty days ago I announced the idea. Here is what happened since.\n\n## Numbers\n\n- 412 signups\n- 18 user interviews\n- 1 pivot (already!)\n\nThe pivot deserves its own post. Short version: people did not want the dashboard, they wanted the alerts."],
    ['slug' => 'the-founders-morning-routine', 'title' => "The founder's morning routine myth", 'days' => 12,
     'body' => "Cold showers and 5am alarms make good content and bad advice.\n\nWhat actually correlates with my productive days is embarrassingly simple: going to bed on time and knowing the first task before I sit down.\n\n*That's it. That's the routine.*"],
    ['slug' => 'surgery-notes-housing', 'title' => 'Surgery notes: housing, again', 'days' => 16,
     'body' => "Half of Saturday's cases were housing. Damp, delays, and a family of five in a one-bed flat.\n\nI have written to the council about all twelve cases and will publish their responses here.\n\n## How to reach me\n\nDetails on the contact page — no case is too small."],
    ['slug' => 'what-ai-changes-for-blogs', 'title' => 'What AI actually changes for blogging', 'days' => 20,
     'body' => "AI did not kill blogging. It killed *blogging infrastructure*.\n\nWhen a model can draft, edit, and publish for you, the value moves to the only thing it cannot fake: your name, your face, your judgement.\n\nWhich is why this site is just that — a face, a feed, and the writing."],
    ['slug' => 'reading-list-spring', 'title' => 'My spring reading list', 'days' => 25,
     'body' => "Five books, one sentence each.\n\n1. **The Mom Test** — stop asking people if your idea is good.\n2. **Chip War** — sand runs the world.\n3. **Politics On the Edge** — Westminster from the inside.\n4. **The Pathless Path** — careers are made up.\n5. **Deep Work** — you already know, you just do not do it."],
    ['slug' => 'hello-world', 'title' => 'Hello, world — the new site', 'days' => 30,
     'body' => "Welcome to the new site. No themes, no plugins, no cookie banner walls — just posts.\n\nScroll the grid like a feed, tap anything that catches your eye, and find the legal bits in the footer.\n\nIf you want one of these for yourself, get in touch."],
];

echo "Seeding demo content...\n";

foreach ($posts as $i => $p) {
    $id = bin2hex(random_bytes(6));
    [$c1, $c2] = $palettes[$i % count($palettes)];
    gradient(WPL_UPLOADS . "/img_$id.jpg", 1600, 1000, $c1, $c2);
    gradient(WPL_UPLOADS . "/thumb_$id.jpg", 640, 640, $c1, $c2);
    $meta = [
        'title' => $p['title'],
        'date' => date('Y-m-d', strtotime("-{$p['days']} days")),
        'image' => "img_$id.jpg",
        'featured' => !empty($p['featured']),
    ];
    wpl_write(WPL_POSTS . '/' . $p['slug'] . '.md', wpl_serialize($meta, $p['body']));
    echo "  post: {$p['slug']}\n";
}

$pages = [
    'about' => ['About', "I'm **Jane Founder** — this is my corner of the web.\n\nI write about building products, the odd political diary entry (for the MP demo flavour), and whatever else survives the drafts folder.\n\nThis site runs on WPlite: no database, no plugins, just files."],
    'contact' => ['Contact', "The fastest way to reach me is email: [hello@example.com](mailto:hello@example.com)\n\nYou can also find me on the social links at the top of every page.\n\nI read everything, even if I cannot reply to it all."],
    'privacy' => ['Privacy', "This site keeps things simple.\n\n- No analytics trackers\n- No advertising cookies\n- One functional session cookie, used only if you log in to the admin area\n\nIf you email me, I keep your message for as long as the conversation needs and nothing more."],
];

foreach ($pages as $slug => [$title, $body]) {
    wpl_write(WPL_PAGES . "/$slug.md", wpl_serialize(['title' => $title], $body));
    echo "  page: $slug\n";
}

// Placeholder portrait (square gradient with initial) and simple SVG logo.
$im = imagecreatetruecolor(640, 640);
for ($y = 0; $y < 640; $y++) {
    $t = $y / 639;
    imageline($im, 0, $y, 640, $y, imagecolorallocate($im,
        (int)(194 - 100 * $t), (int)(24 + 60 * $t), (int)(91 + 80 * $t)));
}
$white = imagecolorallocate($im, 255, 255, 255);
imagestring($im, 5, 300, 305, 'J', $white);
imagejpeg($im, WPL_UPLOADS . '/portrait.jpg', 85);
imagedestroy($im);
echo "  image: portrait.jpg\n";

file_put_contents(WPL_UPLOADS . '/logo.svg',
    '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="32" viewBox="0 0 120 32">'
    . '<text x="0" y="24" font-family="Georgia,serif" font-size="24" font-weight="bold" fill="#222">Jane F.</text></svg>');
echo "  image: logo.svg\n";

echo "Done. " . count($posts) . " posts, " . count($pages) . " pages.\n";
