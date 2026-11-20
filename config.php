<?php
// ============================================================
//  Gameday Guides — multi-school platform configuration
// ============================================================
//
//  This file holds paths, platform settings, and shared helpers.
//  There are NO passwords here anymore — accounts live in
//  data/users.json (bcrypt-hashed) and are managed from the admin.
// ------------------------------------------------------------

// ---- Platform settings -------------------------------------
define('PLATFORM_NAME', 'VenueLink');
// Let schools create their own guide from the landing page. Set false to
// close public sign-ups (master admin can still create schools).
define('SIGNUP_ENABLED', true);
// Subtle growth link shown at the bottom of every public school page.
define('GROWTH_TEXT', 'Want a Gameday Guide like this for your school?');
define('GROWTH_URL',  '');   // blank => link to the landing page

// ---- Paths -------------------------------------------------
define('DATA_DIR',        __DIR__ . '/data');
define('SCHOOLS_DIR',     DATA_DIR . '/schools');
define('USERS_FILE',      DATA_DIR . '/users.json');
define('UPLOAD_BASE_DIR', __DIR__ . '/uploads');
define('DEFAULT_TEMPLATE', __DIR__ . '/default.php');

// ---- Base URL (so the app works at a domain root or in a subfolder) ----
$__script = $_SERVER['SCRIPT_NAME'] ?? '';
$__dir    = rtrim(str_replace('\\', '/', dirname($__script)), '/');
if ($__dir === '.' || $__dir === '/') $__dir = '';
define('BASE_URL', $__dir);                 // '' at root, '/sub' in a subfolder

// Reserved words that can never be a school slug (they are real paths/pages).
const RESERVED_SLUGS = ['admin', 'data', 'uploads', 'index', 'default',
                        'config', 'assets', 'api', 'css', 'js', 'img', 'static'];

// ------------------------------------------------------------
//  Slugs
// ------------------------------------------------------------
function valid_slug($slug): bool {
    $slug = (string)$slug;
    if (in_array($slug, RESERVED_SLUGS, true)) return false;
    // lowercase letters/digits, hyphens allowed inside, 1–40 chars
    return (bool)preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $slug);
}

function slugify($s): string {
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

// ------------------------------------------------------------
//  Per-school paths
// ------------------------------------------------------------
function school_data_file(string $slug): string { return SCHOOLS_DIR . '/' . $slug . '.json'; }
function school_upload_dir(string $slug): string { return UPLOAD_BASE_DIR . '/' . $slug; }
function school_upload_url(string $slug): string { return BASE_URL . '/uploads/' . $slug; }
function school_exists(string $slug): bool { return valid_slug($slug) && is_file(school_data_file($slug)); }
function school_url(string $slug): string { return (BASE_URL === '' ? '' : BASE_URL) . '/' . $slug; }

// ------------------------------------------------------------
//  Content load / save (per school)
// ------------------------------------------------------------
function load_content(string $slug): array {
    $json = @file_get_contents(school_data_file($slug));
    $data = $json ? json_decode($json, true) : null;
    return is_array($data) ? $data : [];
}

function save_content(string $slug, array $data): bool {
    if (!is_dir(SCHOOLS_DIR)) @mkdir(SCHOOLS_DIR, 0775, true);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return file_put_contents(school_data_file($slug), $json, LOCK_EX) !== false;
}

// Blank-school template (default.php returns an array). Creating a school
// clones this into data/schools/{slug}.json.
function default_template(): array {
    $tmpl = @include DEFAULT_TEMPLATE;
    return is_array($tmpl) ? $tmpl : [];
}

// List every school: [slug => site array], sorted by display name.
function list_schools(): array {
    $out = [];
    foreach (glob(SCHOOLS_DIR . '/*.json') ?: [] as $f) {
        $slug = basename($f, '.json');
        if (!valid_slug($slug)) continue;
        $c = load_content($slug);
        $out[$slug] = $c['site'] ?? [];
    }
    uasort($out, fn($a, $b) => strcasecmp($a['school_name'] ?? '', $b['school_name'] ?? ''));
    return $out;
}

// ------------------------------------------------------------
//  Users / accounts
// ------------------------------------------------------------
//  users.json shape:
//  { "users": [
//      {"username":"master","password_hash":"...","role":"master","slug":null},
//      {"username":"homewood","password_hash":"...","role":"school","slug":"homewood"}
//  ]}
function load_users(): array {
    $json = @file_get_contents(USERS_FILE);
    $data = $json ? json_decode($json, true) : null;
    if (!is_array($data) || !isset($data['users']) || !is_array($data['users'])) return ['users' => []];
    return $data;
}

function save_users(array $u): bool {
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
    $json = json_encode($u, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ok = file_put_contents(USERS_FILE, $json, LOCK_EX) !== false;
    @chmod(USERS_FILE, 0640);
    return $ok;
}

function find_user(string $username): ?array {
    foreach (load_users()['users'] as $u) {
        if (strcasecmp($u['username'] ?? '', $username) === 0) return $u;
    }
    return null;
}

// Verify a login. Returns the user record on success, null on failure.
function verify_login(string $username, string $password): ?array {
    $u = find_user($username);
    if (!$u || empty($u['password_hash'])) return null;
    if (!password_verify($password, $u['password_hash'])) return null;
    return $u;
}

// Add or replace a user record (matched by username). Returns updated users array.
function upsert_user(array $users, array $rec): array {
    $found = false;
    foreach ($users['users'] as &$u) {
        if (strcasecmp($u['username'] ?? '', $rec['username']) === 0) { $u = $rec; $found = true; break; }
    }
    unset($u);
    if (!$found) $users['users'][] = $rec;
    return $users;
}

// ------------------------------------------------------------
//  Output helpers
// ------------------------------------------------------------
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Allow a useful whitelist of HTML (bold, italics, links, lists, breaks…)
// while stripping anything dangerous. Admin content is trusted (login
// protected) but we still block scripts, event handlers, and javascript:
// URLs so a leaked password can't turn into stored XSS.
function h_rich($s): string {
    $s = (string)$s;
    $allowed = '<strong><b><em><i><u><s><br><a><span><small><sup><sub><ul><ol><li><p><mark>';
    $s = strip_tags($s, $allowed);
    $s = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $s);
    $s = preg_replace('/\b(href|src)\s*=\s*"\s*(?:javascript|data|vbscript):[^"]*"/i', '$1="#"', $s);
    $s = preg_replace("/\b(href|src)\s*=\s*'\s*(?:javascript|data|vbscript):[^']*'/i", '$1="#"', $s);
    $s = preg_replace('/\b(href|src)\s*=\s*(?:javascript|data|vbscript):[^\s>]*/i', '$1="#"', $s);
    $s = preg_replace_callback('/<a\b([^>]*)>/i', function ($m) {
        $attrs = $m[1];
        if (stripos($attrs, 'target=') === false) $attrs .= ' target="_blank"';
        if (stripos($attrs, 'rel=') === false)    $attrs .= ' rel="noopener"';
        return '<a' . $attrs . '>';
    }, $s);
    return $s;
}

function tel_href(string $phone): string {
    return 'tel:' . preg_replace('/\D+/', '', $phone);
}

// ------------------------------------------------------------
//  Per-school brand colors
// ------------------------------------------------------------
function valid_hex($s): bool { return (bool)preg_match('/^#?[0-9a-fA-F]{6}$/', (string)$s); }
function norm_hex($s, string $fallback): string {
    $s = trim((string)$s);
    return valid_hex($s) ? ('#' . strtoupper(ltrim($s, '#'))) : $fallback;
}
function _hex_rgb(string $hex): array {
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}
function _rgb_hex($r, $g, $b): string {
    $c = fn($v) => max(0, min(255, (int)round($v)));
    return sprintf('#%02X%02X%02X', $c($r), $c($g), $c($b));
}
// amount < 0 darkens, > 0 lightens (toward white), range roughly -1..1
function shade(string $hex, float $amount): string {
    [$r, $g, $b] = _hex_rgb($hex);
    if ($amount < 0) { $f = 1 + $amount; return _rgb_hex($r * $f, $g * $f, $b * $f); }
    return _rgb_hex($r + (255 - $r) * $amount, $g + (255 - $g) * $amount, $b + (255 - $b) * $amount);
}

// The four CSS color values a page renders with, from a school's theme.
// The canonical Homewood palette (navy + red) always renders its exact
// original shades — whether its theme is unset or explicitly set — so it
// never drifts. Any other palette derives its deep/bright shades.
function theme_colors(array $c): array {
    $t = is_array($c['theme'] ?? null) ? $c['theme'] : [];
    $primary = norm_hex($t['primary'] ?? '', '#101E3C');  // Homewood navy fallback
    $accent  = norm_hex($t['accent'] ?? '', '#B22234');   // Homewood red fallback
    if (strcasecmp($primary, '#101E3C') === 0 && strcasecmp($accent, '#B22234') === 0) {
        return ['navy' => '#101E3C', 'navy_deep' => '#0A1428', 'red' => '#B22234', 'red_bright' => '#E04553'];
    }
    return ['navy' => $primary, 'navy_deep' => shade($primary, -0.34), 'red' => $accent, 'red_bright' => shade($accent, 0.20)];
}

// Lines <-> array helpers for the admin textareas
function lines_to_array(string $text): array {
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $lines = array_map('trim', $lines);
    return array_values(array_filter($lines, fn($l) => $l !== ''));
}
function array_to_lines(array $arr): string {
    return implode("\n", $arr);
}

// ------------------------------------------------------------
//  Build the content array from posted admin-form fields.
//  Pure transformation (no I/O) so it can be unit-tested.
// ------------------------------------------------------------
function apply_content_post(array $P, array $c): array {
    $c['site'] = [
        'school_name'  => trim($P['school_name'] ?? ''),
        'org_name'     => trim($P['org_name'] ?? ''),
        'tagline'      => trim($P['tagline'] ?? ''),
        'eyebrow'      => trim($P['eyebrow'] ?? ''),
        'hero_top'     => trim($P['hero_top'] ?? ''),
        'hero_em'      => trim($P['hero_em'] ?? ''),
        'hero_sub'     => trim($P['hero_sub'] ?? ''),
        'address'      => trim($P['address'] ?? ''),
        'address_note' => trim($P['address_note'] ?? ''),
    ];
    $c['timeline']['label'] = trim($P['tl_label'] ?? '');
    $stops = [];
    foreach (($P['tl_time'] ?? []) as $i => $time) {
        $time = trim($time); $what = trim($P['tl_what'][$i] ?? '');
        if ($time !== '' || $what !== '') $stops[] = ['time' => $time, 'what' => $what];
    }
    $c['timeline']['stops'] = $stops;

    $c['welcome'] = [
        'kicker' => trim($P['w_kicker'] ?? ''), 'heading' => trim($P['w_heading'] ?? ''),
        'p1' => trim($P['w_p1'] ?? ''), 'p2' => trim($P['w_p2'] ?? ''),
        'quote' => trim($P['w_quote'] ?? ''),
        'sig_name' => trim($P['w_sig_name'] ?? ''), 'sig_title' => trim($P['w_sig_title'] ?? ''),
    ];

    $contacts = [];
    foreach (($P['ct_name'] ?? []) as $i => $name) {
        $name = trim($name);
        if ($name === '') continue;
        $contacts[] = [
            'role'  => trim($P['ct_role'][$i] ?? ''),
            'name'  => $name,
            'phone' => trim($P['ct_phone'][$i] ?? ''),
            'email' => trim($P['ct_email'][$i] ?? ''),
        ];
    }
    $c['contacts'] = $contacts;
    $c['admin_team'] = lines_to_array($P['admin_team'] ?? '');

    $c['parking']['lede'] = trim($P['park_lede'] ?? '');
    $c['parking']['caption'] = trim($P['park_caption'] ?? '');
    $pcards = [];
    foreach (($P['park_title'] ?? []) as $i => $title) {
        $title = trim($title);
        $items = lines_to_array($P['park_items'][$i] ?? '');
        if ($title === '' && !$items) continue;
        $pcards[] = ['title' => $title, 'items' => $items];
    }
    $c['parking']['cards'] = $pcards;

    $pols = [];
    foreach (($P['pol_title'] ?? []) as $i => $title) {
        $title = trim($title);
        $items = lines_to_array($P['pol_items'][$i] ?? '');
        if ($title === '' && !$items) continue;
        $pols[] = ['title' => $title, 'items' => $items];
    }
    $c['policies'] = $pols;

    $c['tickets'] = [
        'price' => trim($P['tix_price'] ?? ''),
        'price_note' => trim($P['tix_note'] ?? ''),
        'items' => lines_to_array($P['tix_items'] ?? ''),
        'url' => trim($P['tix_url'] ?? ''),
        'cta' => trim($P['tix_cta'] ?? ''),
    ];

    $c['school'] = [
        'nav_label'    => trim($P['sch_nav'] ?? ''),
        'url'          => trim($P['sch_url'] ?? ''),
        'kicker'       => trim($P['sch_kicker'] ?? ''),
        'heading'      => trim($P['sch_heading'] ?? ''),
        'blurb'        => trim($P['sch_blurb'] ?? ''),
        'button_label' => trim($P['sch_button'] ?? ''),
    ];

    $c['media']['kicker']  = trim($P['med_kicker'] ?? '');
    $c['media']['heading'] = trim($P['med_heading'] ?? '');
    $c['media']['lede']    = trim($P['med_lede'] ?? '');
    $mcards = [];
    foreach (($P['med_title'] ?? []) as $i => $title) {
        $title = trim($title);
        $items = lines_to_array($P['med_items'][$i] ?? '');
        if ($title === '' && !$items) continue;
        $mcards[] = ['title' => $title, 'items' => $items];
    }
    $c['media']['cards'] = $mcards;
    $blinks = [];
    foreach (($P['bl_label'] ?? []) as $i => $label) {
        $label = trim($label);
        $url = trim($P['bl_url'][$i] ?? '');
        $sub = trim($P['bl_sub'][$i] ?? '');
        if ($label === '' && $url === '') continue;
        $blinks[] = ['label' => $label, 'sublabel' => $sub, 'url' => $url];
    }
    $c['media']['broadcast_links'] = $blinks;

    $known = ['welcome','contacts','parking','policies','media','tickets','directions','school','gallery'];
    $submitted = array_filter(array_map('trim', explode(',', $P['section_order'] ?? '')));
    $ordered = array_values(array_intersect($submitted, $known));
    foreach ($known as $k) if (!in_array($k, $ordered, true)) $ordered[] = $k;
    if ($ordered) $c['section_order'] = $ordered;

    $c['directions']['lede'] = trim($P['dir_lede'] ?? '');
    $c['directions']['map_query'] = trim($P['dir_map_query'] ?? '');
    $c['directions']['map_label'] = trim($P['dir_map_label'] ?? '');
    $routes = [];
    foreach (($P['dir_title'] ?? []) as $i => $title) {
        $title = trim($title);
        $steps = lines_to_array($P['dir_steps'][$i] ?? '');
        if ($title === '' && !$steps) continue;
        $routes[] = ['title' => $title, 'steps' => $steps];
    }
    $c['directions']['routes'] = $routes;

    $c['gallery']['lede'] = trim($P['gal_lede'] ?? '');
    $c['footer'] = [
        'office_phone'  => trim($P['f_phone'] ?? ''),
        'contact_name'  => trim($P['f_name'] ?? ''),
        'contact_email' => trim($P['f_email'] ?? ''),
    ];

    // Brand colors (two per school; deep/bright shades are derived at render)
    $c['theme'] = [
        'primary' => norm_hex($P['theme_primary'] ?? '', '#101E3C'),
        'accent'  => norm_hex($P['theme_accent'] ?? '', '#B22234'),
    ];
    return $c;
}
