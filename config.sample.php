<?php
// ============================================================
//  Waldrop Stadium Visiting Team Guide — configuration
// ============================================================

// CHANGE THIS before going live. This is the password for /admin.php
define('ADMIN_PASSWORD', 'change-me-before-going-live');

define('DATA_FILE',   __DIR__ . '/data/content.json');
define('UPLOAD_DIR',  __DIR__ . '/uploads');
define('UPLOAD_URL',  'uploads');

// ------------------------------------------------------------

function load_content(): array {
    $json = @file_get_contents(DATA_FILE);
    $data = $json ? json_decode($json, true) : null;
    return is_array($data) ? $data : [];
}

function save_content(array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return file_put_contents(DATA_FILE, $json, LOCK_EX) !== false;
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Allow a useful whitelist of HTML (bold, italics, links, lists, breaks…)
// while stripping anything dangerous. Admin content is trusted (password
// protected) but we still block scripts, event handlers, and javascript: URLs
// so a leaked password can't turn into stored XSS.
function h_rich($s): string {
    $s = (string)$s;
    // keep only these tags; strip_tags removes everything else
    $allowed = '<strong><b><em><i><u><s><br><a><span><small><sup><sub><ul><ol><li><p><mark>';
    $s = strip_tags($s, $allowed);
    // remove on* event handlers (onclick, onload, …)
    $s = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $s);
    // neutralize javascript:/data:/vbscript: in href or src (quoted or bare)
    $s = preg_replace('/\b(href|src)\s*=\s*"\s*(?:javascript|data|vbscript):[^"]*"/i', '$1="#"', $s);
    $s = preg_replace("/\b(href|src)\s*=\s*'\s*(?:javascript|data|vbscript):[^']*'/i", '$1="#"', $s);
    $s = preg_replace('/\b(href|src)\s*=\s*(?:javascript|data|vbscript):[^\s>]*/i', '$1="#"', $s);
    // add target/rel to links that don't set them, so outbound links open safely in a new tab
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

// Lines <-> array helpers for the admin textareas
function lines_to_array(string $text): array {
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $lines = array_map('trim', $lines);
    return array_values(array_filter($lines, fn($l) => $l !== ''));
}
function array_to_lines(array $arr): string {
    return implode("\n", $arr);
}

// Build the content array from posted admin-form fields. Pure transformation
// (no I/O) so it can be unit-tested independently of the HTTP layer.
function apply_content_post(array $P, array $c): array {
    $c['site'] = [
        'school_name'  => trim($P['school_name'] ?? ''),
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

    // School website section
    $c['school'] = [
        'nav_label'    => trim($P['sch_nav'] ?? ''),
        'url'          => trim($P['sch_url'] ?? ''),
        'kicker'       => trim($P['sch_kicker'] ?? ''),
        'heading'      => trim($P['sch_heading'] ?? ''),
        'blurb'        => trim($P['sch_blurb'] ?? ''),
        'button_label' => trim($P['sch_button'] ?? ''),
    ];

    // Media & Broadcast section
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

    // Section order (from the drag-and-drop reorder control)
    $known = ['welcome','contacts','parking','policies','media','tickets','directions','school','gallery'];
    $submitted = array_filter(array_map('trim', explode(',', $P['section_order'] ?? '')));
    $ordered = array_values(array_intersect($submitted, $known));   // keep only known ids, in submitted order
    foreach ($known as $k) if (!in_array($k, $ordered, true)) $ordered[] = $k; // append any missing
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
    return $c;
}

function require_admin(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['is_admin'])) {
        header('Location: admin.php');
        exit;
    }
}
