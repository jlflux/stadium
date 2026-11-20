<?php
// ============================================================
//  index.php — front controller
//    /              -> landing / school directory
//    /{school-slug} -> that school's visiting-team guide
// ============================================================
require __DIR__ . '/config.php';

// ---- image helpers (use globals set once we know the school) ----
function up_url(string $file): string { return ($GLOBALS['UPL_URL'] ?? 'uploads') . '/' . rawurlencode($file); }
function up_has(string $file): bool   { return isset($GLOBALS['UPL_DIR']) && is_file($GLOBALS['UPL_DIR'] . '/' . $file); }
function parking_img(): string { return $GLOBALS['PARKING_IMG'] ?? 'parking.jpg'; }
function org_name(array $c): string {
    $o = trim($c['site']['org_name'] ?? '');
    return $o !== '' ? $o : trim($c['site']['school_name'] ?? '');
}

// Is a section worth rendering? (blank sections are hidden so new/partial
// schools look intentional, and the nav only lists what actually shows.)
function section_nonempty(string $sid, array $c): bool {
    switch ($sid) {
        case 'welcome':
            $w = $c['welcome'] ?? [];
            return trim(($w['heading'] ?? '') . ($w['p1'] ?? '') . ($w['p2'] ?? '') . ($w['quote'] ?? '')) !== '';
        case 'contacts':
            return !empty($c['contacts']) || !empty($c['admin_team']);
        case 'parking':
            $p = $c['parking'] ?? [];
            return trim($p['lede'] ?? '') !== '' || !empty($p['cards']) || up_has(parking_img());
        case 'policies':
            return !empty($c['policies']);
        case 'media':
            $m = $c['media'] ?? [];
            return trim($m['lede'] ?? '') !== '' || !empty($m['cards']) || !empty($m['broadcast_links']);
        case 'tickets':
            $t = $c['tickets'] ?? [];
            return trim($t['price'] ?? '') !== '' || !empty($t['items']);
        case 'directions':
            $d = $c['directions'] ?? [];
            return !empty($d['routes']) || trim($d['map_query'] ?? '') !== '';
        case 'school':
            return trim($c['school']['url'] ?? '') !== '';
        case 'gallery':
            return !empty($c['gallery']['photos']);
    }
    return true;
}

// ---- nav labels for reorderable sections (welcome has no nav entry) ----
$NAV_LABELS = [
    'contacts'   => 'Contacts',
    'parking'    => 'Parking & Arrival',
    'policies'   => 'Stadium Info',
    'media'      => 'Media & Broadcast',
    'tickets'    => 'Tickets',
    'directions' => 'Directions',
    'gallery'    => 'Photos',
];

// ============================================================
//  Section renderers
// ============================================================
function sec_welcome($c, $v, $alt) {
    $w = $c['welcome'] ?? [];
    ob_start(); ?>
    <section id="welcome" class="<?= $alt ?>">
      <div class="section-inner">
        <div class="welcome-grid">
          <div>
            <div class="kicker"><?= h_rich($w['kicker'] ?? '') ?></div>
            <h2><?= h_rich($w['heading'] ?? '') ?></h2>
            <p class="lede"><?= h_rich($w['p1'] ?? '') ?></p>
            <?php if (!empty($w['p2'])): ?><p style="color:#4A5568;max-width:680px"><?= h_rich($w['p2']) ?></p><?php endif; ?>
          </div>
          <div class="welcome-note">
            <?php if (up_has('pat.png')): ?><img src="<?= h(up_url('pat.png')) ?>?v=<?= $v ?>" alt="" style="width:130px;display:block;margin:0 auto 16px"><?php endif; ?>
            <?php if (!empty($w['quote'])): ?><p><em><?= h_rich($w['quote']) ?></em></p><?php endif; ?>
            <div class="sig">
              <strong><?= h_rich($w['sig_name'] ?? '') ?></strong>
              <span><?= h_rich($w['sig_title'] ?? '') ?></span>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php return ob_get_clean();
}

function sec_contacts($c, $v, $alt) {
    $org = org_name($c);
    ob_start(); ?>
    <section id="contacts" class="<?= $alt ?>">
      <div class="section-inner">
        <div class="kicker">Who To Call</div>
        <h2>Gameday Contacts</h2>
        <p class="lede">Save these before you travel — our athletic staff is available on gameday for anything you need.</p>
        <ul class="contact-list">
          <?php foreach (($c['contacts'] ?? []) as $ct): ?>
          <li class="contact-row">
            <div class="c-who">
              <span class="c-name"><?= h_rich($ct['name'] ?? '') ?></span>
              <span class="c-role"><?= h_rich($ct['role'] ?? '') ?></span>
            </div>
            <div class="c-reach">
              <?php if (!empty($ct['phone'])): ?><a href="<?= h(tel_href($ct['phone'])) ?>" class="c-phone"><?= h($ct['phone']) ?></a><?php endif; ?>
              <?php if (!empty($ct['email'])): ?><a href="mailto:<?= h($ct['email']) ?>" class="c-email"><?= h($ct['email']) ?></a><?php endif; ?>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php if (!empty($c['admin_team'])): ?>
        <div class="admin-list">
          <h3><?= h($org !== '' ? $org . ' Administrative Team' : 'Administrative Team') ?></h3>
          <ul>
            <?php foreach ($c['admin_team'] as $line): ?><li><?= h_rich($line) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php return ob_get_clean();
}

function sec_parking($c, $v, $alt) {
    $park = $c['parking'] ?? [];
    ob_start(); ?>
    <section id="parking" class="<?= $alt ?>">
      <div class="section-inner">
        <div class="kicker">Arrival &amp; Parking</div>
        <h2>Getting In</h2>
        <?php if (!empty($park['lede'])): ?><p class="lede"><?= h_rich($park['lede']) ?></p><?php endif; ?>
        <?php if (!empty($park['cards'])): ?>
        <div class="parking-grid">
          <?php foreach ($park['cards'] as $card): ?>
          <div class="p-card">
            <div class="p-head"><span class="dot"></span><?= h_rich($card['title'] ?? '') ?></div>
            <ul>
              <?php foreach (($card['items'] ?? []) as $item): ?><li><?= h_rich($item) ?></li><?php endforeach; ?>
            </ul>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (up_has(parking_img())): ?>
        <figure class="map-figure">
          <img src="<?= h(up_url(parking_img())) ?>?v=<?= $v ?>" alt="Gameday parking map">
          <?php if (!empty($park['caption'])): ?><figcaption><?= h_rich($park['caption']) ?></figcaption><?php endif; ?>
        </figure>
        <?php endif; ?>
      </div>
    </section>
    <?php return ob_get_clean();
}

function sec_policies($c, $v, $alt) {
    ob_start(); ?>
    <section id="policies" class="<?= $alt ?>">
      <div class="section-inner">
        <div class="kicker">Know Before You Go</div>
        <h2>Stadium Information</h2>
        <div class="policy-grid">
          <?php foreach (($c['policies'] ?? []) as $pol): ?>
          <div class="policy">
            <h3><?= h_rich($pol['title'] ?? '') ?></h3>
            <ul>
              <?php foreach (($pol['items'] ?? []) as $item): ?><li><?= h_rich($item) ?></li><?php endforeach; ?>
            </ul>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php return ob_get_clean();
}

function sec_media($c, $v, $alt) {
    $m = $c['media'] ?? [];
    ob_start(); ?>
    <section id="media" class="<?= $alt ?>">
      <div class="section-inner">
        <div class="kicker"><?= h_rich($m['kicker'] ?? 'For Media & Broadcasts') ?></div>
        <h2><?= h_rich($m['heading'] ?? 'Media & Broadcast Information') ?></h2>
        <?php if (!empty($m['lede'])): ?><p class="lede"><?= h_rich($m['lede']) ?></p><?php endif; ?>
        <?php if (!empty($m['broadcast_links'])): ?>
        <div class="broadcast-band">
          <div class="bb-tag">📡 We're always live</div>
          <div class="bb-links">
            <?php foreach ($m['broadcast_links'] as $bl): if (empty($bl['label'])) continue; $u = trim($bl['url'] ?? ''); ?>
              <?php if ($u !== ''): ?>
              <a class="bb-link" href="<?= h($u) ?>" target="_blank" rel="noopener">
                <span class="bb-label"><?= h_rich($bl['label']) ?></span>
                <?php if (!empty($bl['sublabel'])): ?><span class="bb-sub"><?= h_rich($bl['sublabel']) ?></span><?php endif; ?>
                <span class="bb-arrow">→</span>
              </a>
              <?php else: ?>
              <span class="bb-link bb-disabled">
                <span class="bb-label"><?= h_rich($bl['label']) ?></span>
                <?php if (!empty($bl['sublabel'])): ?><span class="bb-sub"><?= h_rich($bl['sublabel']) ?></span><?php endif; ?>
              </span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($m['cards'])): ?>
        <div class="policy-grid" style="margin-top:24px">
          <?php foreach ($m['cards'] as $card): ?>
          <div class="policy">
            <h3><?= h_rich($card['title'] ?? '') ?></h3>
            <ul>
              <?php foreach (($card['items'] ?? []) as $item): ?><li><?= h_rich($item) ?></li><?php endforeach; ?>
            </ul>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php return ob_get_clean();
}

function sec_tickets($c, $v, $alt) {
    $tix = $c['tickets'] ?? [];
    $url = trim($tix['url'] ?? '');
    $tag = $url !== '' ? 'a' : 'div';
    $attrs = $url !== '' ? ' href="' . h($url) . '" target="_blank" rel="noopener"' : '';
    ob_start(); ?>
    <section id="tickets" class="<?= $alt ?>">
      <div class="section-inner">
        <div class="kicker">Tickets &amp; Entry</div>
        <h2>Getting Your Tickets</h2>
        <<?= $tag . $attrs ?> class="ticket-band<?= $url !== '' ? ' is-link' : '' ?>">
          <div class="ticket-price display"><?= h_rich($tix['price'] ?? '') ?><small><?= h_rich($tix['price_note'] ?? '') ?></small></div>
          <ul>
            <?php foreach (($tix['items'] ?? []) as $item): ?><li><?= h_rich($item) ?></li><?php endforeach; ?>
          </ul>
          <?php if ($url !== ''): ?><div class="ticket-cta"><?= h_rich($tix['cta'] ?? 'Buy tickets') ?> <span class="ticket-arrow">→</span></div><?php endif; ?>
        </<?= $tag ?>>
      </div>
    </section>
    <?php return ob_get_clean();
}

function sec_directions($c, $v, $alt) {
    $dir = $c['directions'] ?? [];
    $stadium = trim($c['site']['hero_em'] ?? '') ?: 'the Stadium';
    ob_start(); ?>
    <section id="directions" class="<?= $alt ?>">
      <div class="section-inner">
        <div class="kicker">Finding Us</div>
        <h2>Directions to <?= h($stadium) ?></h2>
        <?php if (!empty($dir['lede'])): ?><p class="lede"><?= h_rich($dir['lede']) ?></p><?php endif; ?>
        <div class="dir-grid">
          <div class="dir-col">
            <?php foreach (($dir['routes'] ?? []) as $route): ?>
            <div class="dir-card">
              <h3 class="display"><?= h_rich($route['title'] ?? '') ?></h3>
              <ol>
                <?php foreach (($route['steps'] ?? []) as $step): ?><li><?= h_rich($step) ?></li><?php endforeach; ?>
              </ol>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if (trim($dir['map_query'] ?? '') !== ''): ?>
          <div class="gmap">
            <iframe
              title="Map of <?= h($stadium) ?>"
              src="https://www.google.com/maps?q=<?= rawurlencode($dir['map_query']) ?>&output=embed"
              loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
            <div class="gmap-foot">
              <span><?= h_rich($dir['map_label'] ?? '') ?></span>
              <a href="https://www.google.com/maps/dir/?api=1&destination=<?= rawurlencode($dir['map_query']) ?>" target="_blank" rel="noopener">Get Directions →</a>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php return ob_get_clean();
}

function sec_school($c, $v, $alt) {
    $s = $c['school'] ?? [];
    $url = trim($s['url'] ?? '');
    ob_start(); ?>
    <section id="school" class="<?= $alt ?>">
      <div class="section-inner">
        <div class="school-band">
          <?php if (up_has('logo.png')): ?><img src="<?= h(up_url('logo.png')) ?>?v=<?= $v ?>" alt="" class="school-logo"><?php endif; ?>
          <div class="school-text">
            <div class="kicker" style="color:#fff;opacity:.85"><?= h_rich($s['kicker'] ?? 'Athletics') ?></div>
            <h2 style="color:#fff"><?= h_rich($s['heading'] ?? '') ?></h2>
            <p><?= h_rich($s['blurb'] ?? '') ?></p>
          </div>
          <?php if ($url !== ''): ?>
          <a class="school-btn" href="<?= h($url) ?>" target="_blank" rel="noopener"><?= h_rich($s['button_label'] ?: $url) ?> →</a>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php return ob_get_clean();
}

function sec_gallery($c, $v, $alt) {
    $gal = $c['gallery'] ?? [];
    $kick = trim($c['site']['hero_em'] ?? '') ?: trim($c['site']['school_name'] ?? '');
    ob_start(); ?>
    <section id="gallery" class="<?= $alt ?>">
      <div class="section-inner">
        <?php if ($kick !== ''): ?><div class="kicker"><?= h($kick) ?></div><?php endif; ?>
        <h2>Photos</h2>
        <?php if (!empty($gal['lede'])): ?><p class="lede"><?= h_rich($gal['lede']) ?></p><?php endif; ?>
        <div class="gallery-grid">
          <?php foreach (($gal['photos'] ?? []) as $photo): ?>
          <div class="slot"><img src="<?= h(up_url($photo)) ?>?v=<?= $v ?>" alt="" loading="lazy"></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php return ob_get_clean();
}

// ---- shared inner bodies (used by both single- and multi-venue) ----
function sec_parking_body(array $park, string $parkImg, $v): string {
    ob_start(); ?>
      <?php if (!empty($park['lede'])): ?><p class="lede"><?= h_rich($park['lede']) ?></p><?php endif; ?>
      <?php if (!empty($park['cards'])): ?>
      <div class="parking-grid">
        <?php foreach ($park['cards'] as $card): ?>
        <div class="p-card">
          <div class="p-head"><span class="dot"></span><?= h_rich($card['title'] ?? '') ?></div>
          <ul><?php foreach (($card['items'] ?? []) as $item): ?><li><?= h_rich($item) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if (up_has($parkImg)): ?>
      <figure class="map-figure">
        <img src="<?= h(up_url($parkImg)) ?>?v=<?= $v ?>" alt="Gameday parking map">
        <?php if (!empty($park['caption'])): ?><figcaption><?= h_rich($park['caption']) ?></figcaption><?php endif; ?>
      </figure>
      <?php endif; ?>
    <?php return ob_get_clean();
}

function sec_directions_body(array $dir, string $name, $v): string {
    $mapq = trim($dir['map_query'] ?? '');
    ob_start(); ?>
      <?php if (!empty($dir['lede'])): ?><p class="lede"><?= h_rich($dir['lede']) ?></p><?php endif; ?>
      <div class="dir-grid">
        <div class="dir-col">
          <?php foreach (($dir['routes'] ?? []) as $route): ?>
          <div class="dir-card">
            <h3 class="display"><?= h_rich($route['title'] ?? '') ?></h3>
            <ol><?php foreach (($route['steps'] ?? []) as $step): ?><li><?= h_rich($step) ?></li><?php endforeach; ?></ol>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($mapq !== ''): ?>
        <div class="gmap">
          <iframe title="Map of <?= h($name) ?>" src="https://www.google.com/maps?q=<?= rawurlencode($mapq) ?>&output=embed" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
          <div class="gmap-foot">
            <span><?= h_rich($dir['map_label'] ?? '') ?></span>
            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= rawurlencode($mapq) ?>" target="_blank" rel="noopener">Get Directions &rarr;</a>
          </div>
        </div>
        <?php endif; ?>
      </div>
    <?php return ob_get_clean();
}

// ---- multi-venue: one swappable pane per venue inside a single section shell ----
function sec_parking_multi(array $venues, $v, string $alt): string {
    ob_start(); ?>
    <section id="parking" class="<?= $alt ?> venue-section">
      <div class="section-inner">
        <div class="kicker">Arrival &amp; Parking</div>
        <h2>Getting In</h2>
        <?php foreach ($venues as $k => $vn):
            $park = $vn['parking'] ?? [];
            if (empty($park['lede']) && empty($park['cards']) && !up_has($vn['parking_img'])) continue; ?>
        <div class="venue-pane" data-venue="<?= h($vn['id']) ?>"<?= $k === 0 ? '' : ' hidden' ?>>
          <div class="pane-venue"><?= h_rich($vn['name']) ?></div>
          <?= sec_parking_body($park, $vn['parking_img'], $v) ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php return ob_get_clean();
}

function sec_directions_multi(array $venues, $v, string $alt): string {
    ob_start(); ?>
    <section id="directions" class="<?= $alt ?> venue-section">
      <div class="section-inner">
        <div class="kicker">Finding Us</div>
        <?php foreach ($venues as $k => $vn):
            if (empty($vn['directions']['routes']) && trim($vn['map_query'] ?? '') === '') continue;
            $dir = $vn['directions'] ?? [];
            $dir['map_query'] = $vn['map_query'] ?? '';
            $dir['map_label'] = $vn['map_label'] ?? '';
            $nm = $vn['name'] ?: 'the venue'; ?>
        <div class="venue-pane" data-venue="<?= h($vn['id']) ?>"<?= $k === 0 ? '' : ' hidden' ?>>
          <h2>Directions to <?= h($nm) ?></h2>
          <?= sec_directions_body($dir, $nm, $v) ?>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php return ob_get_clean();
}

// ---- multi-venue gameday clock (one swappable pane per venue) ----
function multi_clock(array $venues): string {
    ob_start(); ?>
    <div class="timeline venue-section" aria-label="Gameday timeline">
      <?php foreach ($venues as $k => $vn):
          $tl = $vn['timeline'] ?? [];
          if (empty($tl['stops'])) continue; ?>
      <div class="timeline-inner venue-pane" data-venue="<?= h($vn['id']) ?>"<?= $k === 0 ? '' : ' hidden' ?>>
        <?php if (!empty($tl['label'])): ?><div class="timeline-label"><?= h_rich($tl['label']) ?></div><?php endif; ?>
        <div class="tl-track">
          <?php foreach ($tl['stops'] as $stop): ?>
          <div class="tl-stop">
            <div class="tl-time display"><?= h_rich($stop['time'] ?? '') ?></div>
            <div class="tl-what"><?= h_rich($stop['what'] ?? '') ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php return ob_get_clean();
}

$RENDERERS = [
    'welcome' => 'sec_welcome', 'contacts' => 'sec_contacts', 'parking' => 'sec_parking',
    'policies' => 'sec_policies', 'media' => 'sec_media', 'tickets' => 'sec_tickets',
    'directions' => 'sec_directions', 'school' => 'sec_school', 'gallery' => 'sec_gallery',
];

// ============================================================
//  Routing
// ============================================================
function route_slug(): string {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $uri = urldecode($uri);
    if (BASE_URL !== '' && strncmp($uri, BASE_URL, strlen(BASE_URL)) === 0) {
        $uri = substr($uri, strlen(BASE_URL));
    }
    $uri = ltrim($uri, '/');
    if ($uri === '') return '';
    $first = explode('/', $uri)[0];
    if ($first === 'index.php') return '';
    return strtolower($first);
}

$slug = route_slug();

if ($slug === '') {
    render_landing();
    exit;
}
if (!school_exists($slug)) {
    http_response_code(404);
    render_not_found($slug);
    exit;
}

// ---- A real school: set up its data + image context ----
$GLOBALS['UPL_URL'] = school_upload_url($slug);
$GLOBALS['UPL_DIR'] = school_upload_dir($slug);
$c = load_content($slug);

// ---- venues ----
$venues = school_venues($c);
$multi  = count($venues) > 1;
$primary = $venues[0];
$GLOBALS['PARKING_IMG'] = $primary['parking_img'];
if (!$multi) {
    // single venue: fold its data back into $c so the classic renderers work unchanged
    $c['parking']    = $primary['parking'];
    $c['timeline']   = $primary['timeline'];
    $c['directions'] = [
        'lede' => $primary['directions']['lede'], 'routes' => $primary['directions']['routes'],
        'map_query' => $primary['map_query'], 'map_label' => $primary['map_label'],
    ];
    $c['site']['address'] = $primary['address'];
    $c['site']['address_note'] = $primary['address_note'];
    if (empty($c['site']['hero_em'])) $c['site']['hero_em'] = $primary['name'];
}

$site = $c['site'] ?? []; $tl = $c['timeline'] ?? [];
$foot = $c['footer'] ?? []; $school = $c['school'] ?? [];
$org = org_name($c);
$v = @filemtime(school_data_file($slug)) ?: time();
$TC = theme_colors($c);   // per-school brand colors
$order = $c['section_order'] ?? ['welcome','contacts','parking','policies','media','tickets','directions','school','gallery'];

// Does ANY venue have parking / directions / a clock? (multi-venue gating)
$anyPark = $anyDir = $anyClock = false;
foreach ($venues as $vn) {
    if (!empty($vn['parking']['lede']) || !empty($vn['parking']['cards']) || up_has($vn['parking_img'])) $anyPark = true;
    if (!empty($vn['directions']['routes']) || trim($vn['map_query'] ?? '') !== '') $anyDir = true;
    if (!empty($vn['timeline']['stops'])) $anyClock = true;
}

// Nav: the usual single-page section list. Parking & Directions stay as
// one link each; their content swaps with the chosen sport/venue.
$nav = [];
foreach ($order as $sid) {
    if (empty($NAV_LABELS[$sid])) continue;
    if ($multi && $sid === 'parking')    { if ($anyPark) $nav[$sid] = $NAV_LABELS[$sid]; continue; }
    if ($multi && $sid === 'directions') { if ($anyDir)  $nav[$sid] = $NAV_LABELS[$sid]; continue; }
    if (section_nonempty($sid, $c)) $nav[$sid] = $NAV_LABELS[$sid];
}

$page_title = implode(' · ', array_filter([$site['tagline'] ?? '', $site['hero_em'] ?? '', $org]));
$growth_url = GROWTH_URL !== '' ? GROWTH_URL : (BASE_URL . '/');
$has_hero   = up_has('hero.jpg');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($page_title !== '' ? $page_title : 'Visiting Team Guide') ?></title>
<link rel="icon" href="<?= BASE_URL ?>/assets/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/favicon-32.png">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--navy:<?= $TC['navy'] ?>;--navy-deep:<?= $TC['navy_deep'] ?>;--red:<?= $TC['red'] ?>;--red-bright:<?= $TC['red_bright'] ?>;--chalk:#F7F5F0;--line:#8A93A6;--card:#FFFFFF;--ink:#1B2437;--max:1080px}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:76px}
@media (prefers-reduced-motion: reduce){html{scroll-behavior:auto}*,*::before,*::after{animation:none!important;transition:none!important}}
body{font-family:'Inter',system-ui,sans-serif;background:var(--chalk);color:var(--ink);line-height:1.6}
.display{font-family:'Inter',sans-serif;font-weight:900;text-transform:uppercase;letter-spacing:.02em}
.topbar{position:sticky;top:0;z-index:50;background:var(--navy-deep);border-bottom:3px solid var(--red)}
.topbar-inner{max-width:var(--max);margin:0 auto;display:flex;align-items:center;gap:16px;padding:10px 20px}
.crest{width:52px;height:44px;border-radius:8px;background:#fff;padding:4px;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
.crest img{width:100%;height:100%;object-fit:contain}
.crest-initial{font-weight:900;color:var(--navy);font-size:22px;line-height:1}
.topbar-title{color:#fff;font-size:14px;line-height:1.25}
.topbar-title strong{display:block;font-weight:900;text-transform:uppercase;font-size:15px;letter-spacing:.04em}
.topnav{margin-left:auto;position:relative;display:flex;align-items:center;gap:10px}
.nav-site{display:inline-flex;align-items:center;gap:6px;background:var(--red);color:#fff;text-decoration:none;font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:9px 14px;border-radius:7px;white-space:nowrap}
.nav-site:hover{background:var(--red-bright);color:#fff}
.menu-btn{display:inline-flex;align-items:center;gap:9px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.22);color:#fff;font:inherit;font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:9px 15px;border-radius:7px;cursor:pointer}
.menu-btn:hover{background:rgba(255,255,255,.16)}
.menu-btn .bars{display:inline-flex;flex-direction:column;gap:3px}
.menu-btn .bars span{width:16px;height:2px;background:#fff;border-radius:2px}
.menu-panel{position:absolute;top:calc(100% + 10px);right:0;min-width:230px;background:#fff;border-radius:12px;box-shadow:0 14px 40px rgba(10,20,40,.28);padding:8px;opacity:0;visibility:hidden;transform:translateY(-6px);transition:opacity .16s,transform .16s,visibility .16s;z-index:60}
.menu-panel.open{opacity:1;visibility:visible;transform:translateY(0)}
.menu-panel a{display:block;color:var(--navy);text-decoration:none;font-size:14px;font-weight:600;padding:10px 14px;border-radius:8px}
.menu-panel a:hover,.menu-panel a:focus-visible{background:#F1EEE6;color:var(--red)}
.menu-panel .divider{height:1px;background:#E7E3D9;margin:6px 8px}
.menu-panel a.site{color:#fff;background:var(--red);margin-top:2px}
.menu-panel a.site:hover{background:var(--red-bright);color:#fff}
a:focus-visible,button:focus-visible{outline:3px solid #fff;outline-offset:2px}
@media(max-width:520px){.topbar-title{display:none}.nav-site span.lbl{display:none}}
.hero{position:relative;background:var(--navy);color:#fff;overflow:hidden}
.hero-img{width:100%;height:min(62vh,560px);object-fit:cover;display:block;opacity:.55}
.hero-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(10,20,40,.15) 0%,rgba(10,20,40,.55) 55%,var(--navy-deep) 100%)}
.hero-content{position:absolute;left:0;right:0;bottom:0;padding:0 20px 44px}
.hero-content-inner{max-width:var(--max);margin:0 auto}
/* plain hero (no image): content sits in normal flow over navy */
.hero.plain{padding:64px 20px 44px}
.hero.plain .hero-content{position:static;padding:0}
.eyebrow{display:inline-block;background:var(--red);color:#fff;font-weight:900;text-transform:uppercase;font-size:13px;letter-spacing:.14em;padding:6px 14px;border-radius:4px;margin-bottom:14px}
.hero h1{font-size:clamp(34px,6vw,64px);line-height:1.02;color:#fff}
.hero h1 em{font-style:normal;color:var(--red-bright)}
.hero-sub{margin-top:12px;font-size:clamp(15px,2vw,18px);color:#D9E0EE;max-width:640px}
.hero-addr{margin-top:16px;display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:8px 18px;font-weight:600;font-size:14px;color:#fff;flex-wrap:wrap}
.hero-addr .note{color:#FF9AA5;font-weight:700}
.timeline{background:var(--navy-deep);padding:26px 20px 34px}
.timeline-inner{max-width:var(--max);margin:0 auto}
.timeline-label{color:#fff;font-weight:900;text-transform:uppercase;font-size:12px;letter-spacing:.18em;margin-bottom:14px}
.tl-track{display:grid;grid-template-columns:repeat(3,1fr);gap:2px;border-radius:10px;overflow:hidden}
.tl-stop{background:#16264A;padding:16px 18px;position:relative}
.tl-stop::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--red)}
.tl-time{font-weight:900;font-size:24px;color:#fff;line-height:1.1}
.tl-what{color:#AEB9CF;font-size:13px;margin-top:4px;line-height:1.4}
@media(max-width:680px){.tl-track{grid-template-columns:1fr}}
section{padding:64px 20px}
.section-inner{max-width:var(--max);margin:0 auto}
.kicker{color:var(--red);font-weight:900;text-transform:uppercase;font-size:12.5px;letter-spacing:.16em;margin-bottom:8px}
h2{font-weight:900;text-transform:uppercase;font-size:clamp(24px,3.4vw,34px);color:var(--navy);line-height:1.1;margin-bottom:10px}
.lede{color:#4A5568;max-width:680px;margin-bottom:32px;font-size:16px}
.alt{background:#EDEAE2}
.welcome-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:40px;align-items:start}
.welcome-note{background:var(--card);border-radius:14px;padding:28px 30px;border-top:5px solid var(--red);box-shadow:0 2px 14px rgba(16,30,60,.08)}
.welcome-note p{margin-bottom:14px;color:#37415A}
.sig strong{display:block;font-size:17px;color:var(--navy)}
.sig span{color:#6B7488;font-size:14px}
@media(max-width:820px){.welcome-grid{grid-template-columns:1fr}}
/* contact list — one row each, room for full emails */
.contact-list{list-style:none;display:grid;gap:8px}
.contact-row{background:var(--card);border:1px solid #E3E0D7;border-left:4px solid var(--red);border-radius:10px;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;box-shadow:0 1px 5px rgba(16,30,60,.04)}
.c-who{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;min-width:0}
.c-name{font-weight:700;color:var(--navy);font-size:16px}
.c-role{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--red)}
.c-reach{display:flex;align-items:center;gap:8px 18px;flex-wrap:wrap;justify-content:flex-end}
.c-reach a{text-decoration:none;font-size:14px;font-weight:600;white-space:nowrap}
.c-phone{color:var(--navy)}
.c-email{color:#37415A}
.c-reach a:hover{color:var(--red)}
@media(max-width:560px){.contact-row{align-items:flex-start}.c-reach{justify-content:flex-start}}
.admin-list{margin-top:26px;background:var(--navy);border-radius:12px;padding:20px 24px;color:#D9E0EE}
.admin-list h3{font-weight:900;text-transform:uppercase;font-size:14px;letter-spacing:.1em;color:#fff;margin-bottom:12px}
.admin-list ul{list-style:none;display:grid;gap:2px}
.admin-list li{font-size:14.5px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.09)}
.admin-list li:last-child{border-bottom:0}
.parking-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:34px}
.p-card{background:var(--card);border-radius:12px;overflow:hidden;border:1px solid #E3E0D7;display:flex;flex-direction:column}
.p-head{background:var(--navy);color:#fff;padding:12px 18px;font-weight:900;text-transform:uppercase;font-size:14px;letter-spacing:.08em;display:flex;align-items:center;gap:10px}
.p-head .dot{width:10px;height:10px;border-radius:50%;background:#fff;flex-shrink:0}
.p-card ul{list-style:none;padding:16px 18px;display:grid;gap:9px;font-size:14.5px;color:#37415A}
.p-card li{padding-left:18px;position:relative}
.p-card li::before{content:"";position:absolute;left:0;top:8px;width:8px;height:8px;border-radius:2px;background:var(--red)}
@media(max-width:820px){.parking-grid{grid-template-columns:1fr}}
.map-figure{background:var(--card);border-radius:14px;overflow:hidden;border:1px solid #E3E0D7;box-shadow:0 2px 14px rgba(16,30,60,.08)}
.map-figure img{width:100%;display:block}
.map-figure figcaption{padding:14px 20px;font-size:13.5px;color:#6B7488;border-top:1px solid #EEE;background:#FCFBF8}
.policy-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:16px}
.policy{background:var(--card);border-radius:12px;padding:20px 22px;border:1px solid #E3E0D7}
.policy h3{font-weight:900;text-transform:uppercase;font-size:15px;letter-spacing:.06em;color:var(--navy);margin-bottom:10px;padding-bottom:8px;border-bottom:2px solid var(--red)}
.policy ul{list-style:none;display:grid;gap:8px;font-size:14px;color:#37415A}
.policy li{padding-left:16px;position:relative}
.policy li::before{content:"";position:absolute;left:0;top:8px;width:6px;height:6px;border-radius:50%;background:var(--line)}
.policy a{color:var(--red);font-weight:600}
.broadcast-band{background:var(--navy);border-radius:14px;padding:22px 24px;display:flex;align-items:center;gap:22px;flex-wrap:wrap;box-shadow:0 4px 18px rgba(16,30,60,.16)}
.bb-tag{color:#fff;font-weight:900;text-transform:uppercase;letter-spacing:.08em;font-size:14px;flex-shrink:0}
.bb-links{display:flex;gap:12px;flex-wrap:wrap;flex:1;justify-content:flex-end}
.bb-link{display:flex;flex-direction:column;background:var(--red);color:#fff;border-radius:10px;padding:12px 18px;text-decoration:none;min-width:190px;position:relative;transition:background .15s}
.bb-link.is-arrow:hover,a.bb-link:hover{background:var(--red-bright)}
.bb-label{font-weight:800;font-size:15px}
.bb-sub{font-size:12px;opacity:.85;margin-top:2px}
.bb-arrow{position:absolute;right:16px;top:50%;transform:translateY(-50%);font-size:18px;font-weight:700}
.bb-disabled{background:#4A5568;opacity:.75}
@media(max-width:720px){.bb-links{justify-content:flex-start}.bb-link{flex:1}}
.ticket-band{background:var(--red);border-radius:16px;color:#fff;padding:34px 36px;display:grid;grid-template-columns:auto 1fr;gap:34px;align-items:center;box-shadow:0 6px 24px rgba(178,34,52,.28);position:relative}
a.ticket-band{text-decoration:none}
a.ticket-band.is-link{transition:transform .15s,box-shadow .15s}
a.ticket-band.is-link:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(178,34,52,.38)}
.ticket-price{font-weight:900;font-size:clamp(48px,7vw,72px);line-height:1}
.ticket-price small{display:block;font-size:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;opacity:.85}
.ticket-band ul{list-style:none;display:grid;gap:8px;font-size:15px}
.ticket-band li{padding-left:20px;position:relative}
.ticket-band li::before{content:"✓";position:absolute;left:0;font-weight:700}
.ticket-cta{grid-column:1/-1;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.4);border-radius:10px;padding:12px 18px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;font-size:15px;display:flex;justify-content:space-between;align-items:center}
@media(max-width:680px){.ticket-band{grid-template-columns:1fr}}
.dir-grid{display:grid;grid-template-columns:1fr 1.2fr;gap:24px;align-items:stretch}
.dir-col{display:grid;gap:16px;align-content:start}
.dir-card{background:var(--card);border-radius:12px;padding:20px 22px;border:1px solid #E3E0D7}
.dir-card h3{font-weight:900;text-transform:uppercase;font-size:15px;color:var(--navy);margin-bottom:12px}
.dir-card ol{padding-left:20px;display:grid;gap:8px;font-size:14.5px;color:#37415A}
.gmap{border-radius:14px;overflow:hidden;border:1px solid #E3E0D7;box-shadow:0 2px 14px rgba(16,30,60,.08);min-height:420px;display:flex;flex-direction:column}
.gmap iframe{border:0;width:100%;flex:1;min-height:380px}
.gmap-foot{background:var(--navy);color:#fff;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;font-size:14px}
.gmap-foot a{color:#fff;text-decoration:underline;font-weight:700}
@media(max-width:900px){.dir-grid{grid-template-columns:1fr}}
.school-band{background:var(--navy);border-radius:16px;padding:30px 34px;display:flex;align-items:center;gap:26px;flex-wrap:wrap;box-shadow:0 4px 18px rgba(16,30,60,.16)}
.school-logo{width:76px;flex-shrink:0}
.school-text{flex:1;min-width:240px}
.school-text h2{margin:2px 0 8px}
.school-text p{color:#C9D2E4;font-size:15px;max-width:560px}
.school-btn{background:var(--red);color:#fff;text-decoration:none;font-weight:800;padding:14px 26px;border-radius:10px;font-size:15px;white-space:nowrap;transition:background .15s}
.school-btn:hover{background:var(--red-bright)}
.gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px}
.slot{aspect-ratio:4/3;border-radius:12px;overflow:hidden;background:var(--card);border:1px solid #E3E0D7}
.slot img{width:100%;height:100%;object-fit:cover;display:block}
/* multi-venue: small sport switcher + swappable panes */
.venue-pane[hidden]{display:none}
.hero-addr .ha-venue{font-weight:800}
.venue-switch{background:var(--navy);color:#fff;border-bottom:1px solid rgba(255,255,255,.12)}
.vs-inner{max-width:1120px;margin:0 auto;padding:12px 24px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.vs-lead{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#AEBAD0;white-space:nowrap}
.vs-chips{display:flex;flex-wrap:wrap;gap:6px}
.vs-chip{background:rgba(255,255,255,.08);color:#E7EDF6;border:1px solid rgba(255,255,255,.16);border-radius:999px;padding:6px 14px;font:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:background .12s,color .12s,border-color .12s}
.vs-chip:hover{background:rgba(255,255,255,.16)}
.vs-chip.on{background:var(--red);color:#fff;border-color:var(--red)}
.vs-at{font-size:13px;color:#AEBAD0;white-space:nowrap;margin-left:auto}
.vs-at .vs-venue{color:#fff}
.pane-venue{font-weight:900;text-transform:uppercase;letter-spacing:.06em;font-size:13px;color:var(--red);margin-bottom:14px}
@media(max-width:640px){.vs-at{margin-left:0;width:100%}}
footer{background:var(--navy-deep);color:#AEB9CF;padding:40px 20px 24px;border-top:4px solid var(--red)}
.footer-inner{max-width:var(--max);margin:0 auto;display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap;font-size:14px}
.footer-inner strong{color:#fff;font-weight:900;text-transform:uppercase;display:block;margin-bottom:6px;letter-spacing:.06em}
footer a{color:#fff;text-decoration:underline}
/* subtle growth line */
.growth{background:var(--navy-deep);text-align:center;padding:4px 20px 30px}
.growth a{color:#8A93A6;font-size:13px;text-decoration:none;border-bottom:1px dotted rgba(138,147,166,.6);padding-bottom:1px;transition:color .15s}
.growth a:hover{color:#fff;border-color:#fff}
</style>
</head>
<body>

<nav class="topbar" aria-label="Main">
  <div class="topbar-inner">
    <div class="crest"><?php if (up_has('logo.png')): ?><img src="<?= h(up_url('logo.png')) ?>?v=<?= $v ?>" alt="<?= h(($site['school_name'] ?? '') . ' logo') ?>"><?php else: ?><span class="crest-initial"><?= h(strtoupper(mb_substr(trim($site['school_name'] ?? '?'), 0, 1)) ?: '?') ?></span><?php endif; ?></div>
    <div class="topbar-title"><strong><?= h($site['school_name'] ?? '') ?></strong><?= h($site['tagline'] ?? '') ?></div>
    <div class="topnav">
      <?php if (!empty($school['url'])): ?>
        <a class="nav-site" href="<?= h($school['url']) ?>" target="_blank" rel="noopener"><span class="lbl"><?= h($school['nav_label'] ?: 'Athletics Site') ?></span> ↗</a>
      <?php endif; ?>
      <?php if ($nav): ?>
      <button class="menu-btn" id="menuBtn" aria-expanded="false" aria-controls="menuPanel" aria-haspopup="true">
        <span class="bars" aria-hidden="true"><span></span><span></span><span></span></span> Menu
      </button>
      <div class="menu-panel" id="menuPanel" role="menu" aria-label="Sections">
        <?php foreach ($nav as $sid => $label): ?>
          <a href="#<?= h($sid) ?>" role="menuitem"><?= h($label) ?></a>
        <?php endforeach; ?>
        <?php if (!empty($school['url'])): ?>
          <div class="divider"></div>
          <a class="site" href="<?= h($school['url']) ?>" target="_blank" rel="noopener" role="menuitem"><?= h($school['nav_label'] ?: 'Athletics Site') ?> ↗</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</nav>

<header class="hero<?= $has_hero ? '' : ' plain' ?>">
  <?php if ($has_hero): ?>
  <img class="hero-img" src="<?= h(up_url('hero.jpg')) ?>?v=<?= $v ?>" alt="<?= h(($site['hero_em'] ?? 'Stadium')) ?>">
  <div class="hero-overlay"></div>
  <?php endif; ?>
  <div class="hero-content">
    <div class="hero-content-inner">
      <?php if (!empty($site['eyebrow'])): ?><span class="eyebrow"><?= h_rich($site['eyebrow']) ?></span><?php endif; ?>
      <h1 class="display"><?= h_rich($site['hero_top'] ?? '') ?><br><?php if ($multi): foreach ($venues as $k => $vn): ?><em class="venue-pane" data-venue="<?= h($vn['id']) ?>"<?= $k === 0 ? '' : ' hidden' ?>><?= h_rich($vn['name'] ?: ($site['hero_em'] ?? '')) ?></em><?php endforeach; else: ?><em><?= h_rich($site['hero_em'] ?? '') ?></em><?php endif; ?></h1>
      <?php if (!empty($site['hero_sub'])): ?><p class="hero-sub"><?= h_rich($site['hero_sub']) ?></p><?php endif; ?>
      <?php if (!$multi && !empty($site['address'])): ?>
      <div class="hero-addr">📍 <?= h_rich($site['address']) ?> <?php if (!empty($site['address_note'])): ?><span class="note">· <?= h_rich($site['address_note']) ?></span><?php endif; ?></div>
      <?php elseif ($multi): foreach ($venues as $k => $vn): if (empty($vn['address'])) continue; ?>
      <div class="hero-addr venue-pane" data-venue="<?= h($vn['id']) ?>"<?= $k === 0 ? '' : ' hidden' ?>>📍 <span class="ha-venue"><?= h_rich($vn['name']) ?></span> · <?= h_rich($vn['address']) ?><?php if (!empty($vn['address_note'])): ?> <span class="note">· <?= h_rich($vn['address_note']) ?></span><?php endif; ?></div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</header>

<?php if ($multi): ?>
<div class="venue-switch" id="venue-switch">
  <div class="vs-inner">
    <span class="vs-lead">I'm here for</span>
    <div class="vs-chips" role="tablist" aria-label="Choose your sport">
      <?php foreach ($venues as $vn):
          $sports = array_values(array_filter(array_map('trim', $vn['sports'] ?? []), fn($s) => $s !== ''));
          if (!$sports) $sports = [$vn['name'] ?: 'This venue'];
          foreach ($sports as $sport): ?>
      <button type="button" class="vs-chip" role="tab" data-venue="<?= h($vn['id']) ?>" data-sport="<?= h($sport) ?>"><?= h($sport) ?></button>
      <?php endforeach; endforeach; ?>
    </div>
    <span class="vs-at">at <strong class="vs-venue"></strong></span>
  </div>
</div>
<?php endif; ?>

<?php if ($multi && $anyClock): ?>
<?= multi_clock($venues) ?>
<?php elseif (!$multi && !empty($tl['stops'])): ?>
<div class="timeline" aria-label="Gameday timeline">
  <div class="timeline-inner">
    <?php if (!empty($tl['label'])): ?><div class="timeline-label"><?= h_rich($tl['label']) ?></div><?php endif; ?>
    <div class="tl-track">
      <?php foreach ($tl['stops'] as $stop): ?>
      <div class="tl-stop">
        <div class="tl-time display"><?= h_rich($stop['time'] ?? '') ?></div>
        <div class="tl-what"><?= h_rich($stop['what'] ?? '') ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
$i = 0;
foreach ($order as $sid) {
    $alt = ($i % 2 === 1) ? 'alt' : '';
    if ($multi && $sid === 'parking')    { if ($anyPark) { echo sec_parking_multi($venues, $v, $alt); $i++; } continue; }
    if ($multi && $sid === 'directions') { if ($anyDir)  { echo sec_directions_multi($venues, $v, $alt); $i++; } continue; }
    if (empty($RENDERERS[$sid]) || !section_nonempty($sid, $c)) continue;
    echo $RENDERERS[$sid]($c, $v, $alt); $i++;
}
?>

<footer>
  <div class="footer-inner">
    <div style="display:flex;gap:16px;align-items:flex-start">
      <?php if (up_has('logo.png')): ?><img src="<?= h(up_url('logo.png')) ?>?v=<?= $v ?>" alt="" style="width:72px;flex-shrink:0"><?php endif; ?>
      <div>
        <strong><?= h($org !== '' ? $org . ' Athletics' : 'Athletics') ?></strong>
<?= h_rich($site['hero_em'] ?? '') ?><?php if (!empty($site['address'])): ?> · <?= h_rich($site['address']) ?><?php endif; ?><br>
        <?php if (!empty($foot['office_phone'])): ?>School office: <a href="<?= h(tel_href($foot['office_phone'])) ?>"><?= h($foot['office_phone']) ?></a><?php endif; ?>
        <?php if (!empty($school['url'])): ?><br><a href="<?= h($school['url']) ?>" target="_blank" rel="noopener"><?= h($school['button_label'] ?: 'Athletics site') ?></a><?php endif; ?>
      </div>
    </div>
    <?php if (!empty($foot['contact_name']) || !empty($foot['contact_email'])): ?>
    <div>
      <strong>Questions?</strong>
      <?= h_rich($foot['contact_name'] ?? '') ?><?php if (!empty($foot['contact_email'])): ?><br><a href="mailto:<?= h($foot['contact_email']) ?>"><?= h($foot['contact_email']) ?></a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</footer>

<div class="growth"><a href="<?= h($growth_url) ?>"><?= h(GROWTH_TEXT) ?></a></div>

<script>
(function(){
  var btn = document.getElementById('menuBtn');
  var panel = document.getElementById('menuPanel');
  if(!btn || !panel) return;
  function close(){ panel.classList.remove('open'); btn.setAttribute('aria-expanded','false'); }
  function open(){ panel.classList.add('open'); btn.setAttribute('aria-expanded','true'); }
  btn.addEventListener('click', function(e){
    e.stopPropagation();
    panel.classList.contains('open') ? close() : open();
  });
  panel.addEventListener('click', function(e){ if(e.target.closest('a')) close(); });
  document.addEventListener('click', function(e){
    if(!panel.contains(e.target) && e.target !== btn) close();
  });
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape') close(); });
})();
</script>
<?php if ($multi): ?>
<script>
(function(){
  var VENUES = <?= json_encode(array_column(array_map(fn($x) => ['id' => $x['id'], 'name' => $x['name'] ?: 'Venue'], $venues), 'name', 'id'), JSON_UNESCAPED_UNICODE) ?>;
  var chips = Array.prototype.slice.call(document.querySelectorAll('.vs-chip'));
  var panes = Array.prototype.slice.call(document.querySelectorAll('.venue-pane'));
  var sections = Array.prototype.slice.call(document.querySelectorAll('.venue-section'));
  var label = document.querySelector('.vs-venue');
  if(!chips.length) return;

  function apply(id){
    panes.forEach(function(p){ p.hidden = (p.getAttribute('data-venue') !== id); });
    // hide a venue-specific section entirely if the chosen venue has no pane in it
    sections.forEach(function(s){
      var has = s.querySelector('.venue-pane[data-venue="'+ (window.CSS && CSS.escape ? CSS.escape(id) : id) +'"]');
      s.style.display = has ? '' : 'none';
    });
    if(label) label.textContent = VENUES[id] || '';
  }
  function select(chip){
    chips.forEach(function(c){ var on = (c === chip); c.classList.toggle('on', on); c.setAttribute('aria-selected', on ? 'true' : 'false'); });
    apply(chip.getAttribute('data-venue'));
  }
  chips.forEach(function(c){ c.addEventListener('click', function(){ select(c); }); });

  // preselect from ?sport= if present, else the first chip
  var want = (new URLSearchParams(location.search).get('sport') || '').trim().toLowerCase();
  var start = chips[0];
  if(want){
    var m = chips.find(function(c){ return (c.getAttribute('data-sport') || '').toLowerCase() === want; });
    if(m) start = m;
  }
  select(start);
})();
</script>
<?php endif; ?>
</body>
</html>
<?php
// ============================================================
//  Landing / directory  +  404
// ============================================================
function render_shell_head(string $title): string {
    ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?></title>
<link rel="icon" href="<?= BASE_URL ?>/assets/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/favicon-32.png">
<link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--navy:#121316;--navy-deep:#0C0D0F;--red:#38B6FF;--red-bright:#60C5FF;--chalk:#F7F5F0;--card:#fff;--ink:#1B2437;--max:1080px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',system-ui,sans-serif;background:var(--chalk);color:var(--ink);line-height:1.6}
.display{font-weight:900;text-transform:uppercase;letter-spacing:.02em}
.lx-top{background:var(--navy-deep);border-bottom:3px solid var(--red);padding:14px 20px}
.lx-top-in{max-width:var(--max);margin:0 auto;display:flex;align-items:center;gap:12px}
.lx-logo-mark{width:30px;height:34px;display:block;flex-shrink:0}
.lx-brand{color:#fff;font-weight:900;font-size:19px;letter-spacing:-.01em}
.lx-brand span{color:var(--red)}
.lx-hero{background:var(--navy);color:#fff;padding:72px 20px 64px;text-align:center}
.lx-hero-mark{display:block;margin:0 auto 18px}
.lx-hero .eyebrow{display:inline-block;background:var(--red);color:#fff;font-weight:900;text-transform:uppercase;font-size:12px;letter-spacing:.14em;padding:6px 14px;border-radius:4px;margin-bottom:18px}
.lx-hero h1{font-size:clamp(34px,6vw,60px);line-height:1.03;max-width:760px;margin:0 auto}
.lx-hero h1 em{font-style:normal;color:var(--red-bright)}
.lx-hero p{margin:16px auto 0;max-width:560px;color:#D9E0EE;font-size:clamp(15px,2vw,18px)}
.lx-wrap{max-width:var(--max);margin:0 auto;padding:56px 20px 72px}
.lx-kicker{color:var(--red);font-weight:900;text-transform:uppercase;font-size:12.5px;letter-spacing:.16em;margin-bottom:18px;text-align:center}
.lx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.lx-card{display:flex;align-items:center;gap:14px;background:var(--card);border:1px solid #E3E0D7;border-left:4px solid var(--red);border-radius:12px;padding:16px 18px;text-decoration:none;color:var(--ink);box-shadow:0 1px 6px rgba(16,30,60,.05);transition:transform .15s,box-shadow .15s}
.lx-card:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(16,30,60,.12)}
.lx-logo{width:46px;height:46px;border-radius:8px;background:#fff;border:1px solid #EEE;flex-shrink:0;display:flex;align-items:center;justify-content:center;overflow:hidden}
.lx-logo img{width:100%;height:100%;object-fit:contain}
.lx-logo span{font-weight:900;color:var(--navy);font-size:20px}
.lx-card .nm{font-weight:800;color:var(--navy);font-size:16px;line-height:1.2}
.lx-card .go{margin-left:auto;color:var(--red);font-weight:800;font-size:18px}
.lx-empty{text-align:center;color:#6B7488;background:var(--card);border:1px dashed #CFCABC;border-radius:12px;padding:40px 20px}
.lx-cta{margin-top:40px;background:var(--navy);border-radius:16px;padding:34px;text-align:center;color:#fff}
.lx-cta h2{font-weight:900;text-transform:uppercase;font-size:clamp(20px,3vw,28px);margin-bottom:8px}
.lx-cta p{color:#C9D2E4;max-width:520px;margin:0 auto 18px}
.lx-cta a{display:inline-block;background:var(--red);color:#fff;text-decoration:none;font-weight:800;padding:13px 26px;border-radius:10px}
.lx-cta a{color:#06121D}
.lx-cta a:hover{background:var(--red-bright)}
.lx-hero-btn{display:inline-block;margin-top:24px;background:var(--red);color:#06121D!important;text-decoration:none;font-weight:800;font-size:16px;padding:14px 28px;border-radius:11px;transition:background .15s}
.lx-hero-btn:hover{background:var(--red-bright)}
.su-form{max-width:640px;margin:0 auto;text-align:left}
.su-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 16px;margin-bottom:16px}
.su-form label{display:block;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#AEB6C2}
.su-form input{width:100%;margin-top:6px;border:1px solid #2E333B;background:#0E0F12;color:#fff;border-radius:9px;padding:11px 13px;font:inherit;font-size:15px}
.su-form input::placeholder{color:#5C6673}
.su-form input:focus{outline:2px solid var(--red);border-color:var(--red)}
.su-url{display:block;font-size:12px;color:#8A93A6;font-weight:600;text-transform:none;letter-spacing:0;margin-top:5px;overflow-wrap:anywhere}
.su-hp{position:absolute!important;left:-9999px;width:1px;height:1px;opacity:0}
.su-btn{background:var(--red);color:#06121D;border:0;border-radius:11px;padding:14px 28px;font:inherit;font-weight:800;font-size:15px;cursor:pointer}
.su-btn:hover{background:var(--red-bright)}
.su-fine{color:#8A93A6;font-size:12.5px;margin-top:12px}
.su-err{background:rgba(255,92,98,.16);color:#FFB3B6;border:1px solid rgba(255,92,98,.45);border-radius:10px;padding:10px 14px;font-size:14px;margin:0 auto 16px;max-width:640px}
@media(max-width:560px){.su-grid{grid-template-columns:1fr}}
.lx-foot{background:var(--navy-deep);color:#8A93A6;text-align:center;padding:26px 20px;font-size:13px}
a:focus-visible{outline:3px solid var(--red);outline-offset:2px}
</style>
</head>
<body>
<?php return ob_get_clean();
}

function render_landing(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    $csrf = $_SESSION['csrf'];
    $signup_err = $_SESSION['signup_err'] ?? ''; unset($_SESSION['signup_err']);
    $schools = list_schools();
    echo render_shell_head(PLATFORM_NAME);
    ?>
    <div class="lx-top"><div class="lx-top-in">
      <img class="lx-logo-mark" src="<?= BASE_URL ?>/assets/venuelink-mark.svg" alt=""><strong class="lx-brand">Venue<span>Link</span></strong>
    </div></div>
    <div class="lx-hero">
      <img class="lx-hero-mark" src="<?= BASE_URL ?>/assets/venuelink-mark.svg" alt="" width="66" height="72">
      <span class="eyebrow"><?= h(PLATFORM_NAME) ?></span>
      <h1 class="display">Clean, mobile-friendly <em>gameday guides</em> for visiting teams</h1>
      <p>Everything a visiting team, band, and fans need — contacts, parking, directions, tickets, and more — on one simple page per school.</p>
      <?php if (SIGNUP_ENABLED): ?><a href="#signup" class="lx-hero-btn">Create your school's guide →</a><?php endif; ?>
    </div>
    <div class="lx-wrap">
      <?php $featured = array_filter($schools, fn($st) => !empty($st['_featured'])); ?>
      <?php if ($featured): ?>
      <div class="lx-kicker">Featured Schools</div>
      <div class="lx-grid">
        <?php foreach ($featured as $sl => $st):
            $name = $st['school_name'] ?? $sl;
            $hasLogo = is_file(school_upload_dir($sl) . '/logo.png');
            $logoUrl = school_upload_url($sl) . '/logo.png';
        ?>
        <a class="lx-card" href="<?= h(school_url($sl)) ?>">
          <span class="lx-logo"><?php if ($hasLogo): ?><img src="<?= h($logoUrl) ?>" alt=""><?php else: ?><span><?= h(strtoupper(mb_substr(trim($name), 0, 1)) ?: '?') ?></span><?php endif; ?></span>
          <span class="nm"><?= h($name) ?></span>
          <span class="go">→</span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (SIGNUP_ENABLED): ?>
      <div class="lx-cta" id="signup">
        <h2>Create your school's guide</h2>
        <p>Free to set up, works on every phone, and ready in about a minute.</p>
        <?php if ($signup_err): ?><div class="su-err"><?= h($signup_err) ?></div><?php endif; ?>
        <form method="post" action="<?= h(BASE_URL) ?>/admin.php" class="su-form" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="text" name="website" class="su-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
          <div class="su-grid">
            <label>School name
              <input type="text" name="su_name" id="su_name" required maxlength="80" placeholder="Vestavia Hills High School">
            </label>
            <label>Web address
              <input type="text" name="su_slug" id="su_slug" maxlength="40" placeholder="vestavia">
              <span class="su-url"><?= h(rtrim(BASE_URL, '/')) ?>/<span id="su_prev">your-school</span></span>
            </label>
            <label>Your email
              <input type="email" name="su_email" required placeholder="you@school.org">
            </label>
            <label>Choose a password
              <input type="password" name="su_password" required minlength="6" placeholder="at least 6 characters">
            </label>
          </div>
          <button class="su-btn" name="do_signup" value="1">Create my guide →</button>
          <p class="su-fine">You'll go straight to your editor. Your login username is your web address — change your password anytime.</p>
        </form>
      </div>
      <?php else: ?>
      <div class="lx-cta">
        <h2>Want a guide for your school?</h2>
        <p>Get in touch and we'll set yours up.</p>
        <a href="mailto:hello@venuelink.io?subject=VenueLink%20Guide">Get your guide</a>
      </div>
      <?php endif; ?>
    </div>
    <div class="lx-foot"><?= h(PLATFORM_NAME) ?></div>
    <script>
    (function(){
      var name=document.getElementById('su_name'), slug=document.getElementById('su_slug'), prev=document.getElementById('su_prev');
      if(!prev) return;
      function sl(s){return s.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');}
      function upd(){ prev.textContent = (slug.value?sl(slug.value):(name&&name.value?sl(name.value):'your-school')) || 'your-school'; }
      if(name) name.addEventListener('input',upd); slug.addEventListener('input',upd); upd();
    })();
    </script>
    </body></html>
    <?php
}

function render_not_found(string $slug): void {
    echo render_shell_head('Not found · ' . PLATFORM_NAME);
    ?>
    <div class="lx-top"><div class="lx-top-in">
      <img class="lx-logo-mark" src="<?= BASE_URL ?>/assets/venuelink-mark.svg" alt=""><strong class="lx-brand">Venue<span>Link</span></strong>
    </div></div>
    <div class="lx-hero">
      <span class="eyebrow">404</span>
      <h1 class="display">We couldn't find <em><?= h($slug) ?></em></h1>
      <p>That school guide doesn't exist yet. Head back to the directory to see what's available.</p>
      <p style="margin-top:22px"><a href="<?= h(BASE_URL . '/') ?>" style="display:inline-block;background:var(--red);color:#fff;text-decoration:none;font-weight:800;padding:12px 24px;border-radius:10px">← All schools</a></p>
    </div>
    <div class="lx-foot"><?= h(PLATFORM_NAME) ?></div>
    </body></html>
    <?php
}
