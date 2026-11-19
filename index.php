<?php
require __DIR__ . '/config.php';
$c = load_content();
$site = $c['site'] ?? []; $tl = $c['timeline'] ?? [];
$foot = $c['footer'] ?? []; $school = $c['school'] ?? [];
$v = @filemtime(DATA_FILE) ?: time(); // cache-buster for images

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
$order = $c['section_order'] ?? ['welcome','contacts','parking','policies','media','tickets','directions','school','gallery'];

// ---- section renderers ----
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
            <p style="color:#4A5568;max-width:680px"><?= h_rich($w['p2'] ?? '') ?></p>
          </div>
          <div class="welcome-note">
            <img src="<?= UPLOAD_URL ?>/pat.png?v=<?= $v ?>" alt="Patriot Pat" style="width:130px;display:block;margin:0 auto 16px">
            <p><em><?= h_rich($w['quote'] ?? '') ?></em></p>
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
          <h3>Homewood High School Administrative Team</h3>
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
        <p class="lede"><?= h_rich($park['lede'] ?? '') ?></p>
        <div class="parking-grid">
          <?php foreach (($park['cards'] ?? []) as $card): ?>
          <div class="p-card">
            <div class="p-head"><span class="dot"></span><?= h_rich($card['title'] ?? '') ?></div>
            <ul>
              <?php foreach (($card['items'] ?? []) as $item): ?><li><?= h_rich($item) ?></li><?php endforeach; ?>
            </ul>
          </div>
          <?php endforeach; ?>
        </div>
        <figure class="map-figure">
          <img src="<?= UPLOAD_URL ?>/parking.jpg?v=<?= $v ?>" alt="Aerial diagram of Waldrop Stadium showing gameday parking locations">
          <figcaption><?= h_rich($park['caption'] ?? '') ?></figcaption>
        </figure>
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
    ob_start(); ?>
    <section id="directions" class="<?= $alt ?>">
      <div class="section-inner">
        <div class="kicker">Finding Us</div>
        <h2>Directions to Waldrop Stadium</h2>
        <p class="lede"><?= h_rich($dir['lede'] ?? '') ?></p>
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
          <div class="gmap">
            <iframe
              title="Map of Waldrop Stadium"
              src="https://www.google.com/maps?q=<?= rawurlencode($dir['map_query'] ?? '') ?>&output=embed"
              loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
            <div class="gmap-foot">
              <span><?= h_rich($dir['map_label'] ?? '') ?></span>
              <a href="https://www.google.com/maps/dir/?api=1&destination=<?= rawurlencode($dir['map_query'] ?? '') ?>" target="_blank" rel="noopener">Get Directions →</a>
            </div>
          </div>
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
          <img src="<?= UPLOAD_URL ?>/logo.png?v=<?= $v ?>" alt="" class="school-logo">
          <div class="school-text">
            <div class="kicker" style="color:#fff;opacity:.85"><?= h_rich($s['kicker'] ?? 'Homewood Athletics') ?></div>
            <h2 style="color:#fff"><?= h_rich($s['heading'] ?? '') ?></h2>
            <p><?= h_rich($s['blurb'] ?? '') ?></p>
          </div>
          <?php if ($url !== ''): ?>
          <a class="school-btn" href="<?= h($url) ?>" target="_blank" rel="noopener"><?= h_rich($s['button_label'] ?? $url) ?> →</a>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php return ob_get_clean();
}

function sec_gallery($c, $v, $alt) {
    $gal = $c['gallery'] ?? [];
    ob_start(); ?>
    <section id="gallery" class="<?= $alt ?>">
      <div class="section-inner">
        <div class="kicker">Waldrop Stadium</div>
        <h2>Photos</h2>
        <p class="lede"><?= h_rich($gal['lede'] ?? '') ?></p>
        <div class="gallery-grid">
          <?php foreach (($gal['photos'] ?? []) as $photo): ?>
          <div class="slot"><img src="<?= UPLOAD_URL . '/' . h($photo) ?>?v=<?= $v ?>" alt="Waldrop Stadium photo" loading="lazy"></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php return ob_get_clean();
}

$RENDERERS = [
    'welcome' => 'sec_welcome', 'contacts' => 'sec_contacts', 'parking' => 'sec_parking',
    'policies' => 'sec_policies', 'media' => 'sec_media', 'tickets' => 'sec_tickets',
    'directions' => 'sec_directions', 'school' => 'sec_school', 'gallery' => 'sec_gallery',
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($site['tagline'] ?? 'Visiting Team Guide') ?> · Waldrop Stadium · Homewood High School</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--navy:#101E3C;--navy-deep:#0A1428;--red:#B22234;--red-bright:#E04553;--chalk:#F7F5F0;--line:#8A93A6;--card:#FFFFFF;--ink:#1B2437;--max:1080px}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:76px}
@media (prefers-reduced-motion: reduce){html{scroll-behavior:auto}*,*::before,*::after{animation:none!important;transition:none!important}}
body{font-family:'Inter',system-ui,sans-serif;background:var(--chalk);color:var(--ink);line-height:1.6}
.display{font-family:'Inter',sans-serif;font-weight:900;text-transform:uppercase;letter-spacing:.02em}
.topbar{position:sticky;top:0;z-index:50;background:var(--navy-deep);border-bottom:3px solid var(--red)}
.topbar-inner{max-width:var(--max);margin:0 auto;display:flex;align-items:center;gap:16px;padding:10px 20px}
.crest{width:52px;height:44px;border-radius:8px;background:#fff;padding:4px;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
.crest img{width:100%;height:100%;object-fit:contain}
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
/* media broadcast band */
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
/* school website band */
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
footer{background:var(--navy-deep);color:#AEB9CF;padding:40px 20px;border-top:4px solid var(--red)}
.footer-inner{max-width:var(--max);margin:0 auto;display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap;font-size:14px}
.footer-inner strong{color:#fff;font-weight:900;text-transform:uppercase;display:block;margin-bottom:6px;letter-spacing:.06em}
footer a{color:#fff;text-decoration:underline}
</style>
</head>
<body>

<nav class="topbar" aria-label="Main">
  <div class="topbar-inner">
    <div class="crest"><img src="<?= UPLOAD_URL ?>/logo.png?v=<?= $v ?>" alt="<?= h($site['school_name'] ?? 'Homewood Patriots') ?> logo"></div>
    <div class="topbar-title"><strong><?= h($site['school_name'] ?? '') ?></strong><?= h($site['tagline'] ?? '') ?></div>
    <div class="topnav">
      <?php if (!empty($school['url'])): ?>
        <a class="nav-site" href="<?= h($school['url']) ?>" target="_blank" rel="noopener"><span class="lbl"><?= h($school['nav_label'] ?? 'Athletics Site') ?></span> ↗</a>
      <?php endif; ?>
      <button class="menu-btn" id="menuBtn" aria-expanded="false" aria-controls="menuPanel" aria-haspopup="true">
        <span class="bars" aria-hidden="true"><span></span><span></span><span></span></span> Menu
      </button>
      <div class="menu-panel" id="menuPanel" role="menu" aria-label="Sections">
        <?php foreach ($order as $sid): if (!empty($NAV_LABELS[$sid])): ?>
          <a href="#<?= h($sid) ?>" role="menuitem"><?= h($NAV_LABELS[$sid]) ?></a>
        <?php endif; endforeach; ?>
        <?php if (!empty($school['url'])): ?>
          <div class="divider"></div>
          <a class="site" href="<?= h($school['url']) ?>" target="_blank" rel="noopener" role="menuitem"><?= h($school['nav_label'] ?? 'Athletics Site') ?> ↗</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<header class="hero">
  <img class="hero-img" src="<?= UPLOAD_URL ?>/hero.jpg?v=<?= $v ?>" alt="Aerial view of Waldrop Stadium">
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <div class="hero-content-inner">
      <span class="eyebrow"><?= h_rich($site['eyebrow'] ?? '') ?></span>
      <h1 class="display"><?= h_rich($site['hero_top'] ?? '') ?><br><em><?= h_rich($site['hero_em'] ?? '') ?></em></h1>
      <p class="hero-sub"><?= h_rich($site['hero_sub'] ?? '') ?></p>
      <div class="hero-addr">📍 <?= h_rich($site['address'] ?? '') ?> <?php if (!empty($site['address_note'])): ?><span class="note">· <?= h_rich($site['address_note']) ?></span><?php endif; ?></div>
    </div>
  </div>
</header>

<div class="timeline" aria-label="Gameday timeline">
  <div class="timeline-inner">
    <div class="timeline-label"><?= h_rich($tl['label'] ?? '') ?></div>
    <div class="tl-track">
      <?php foreach (($tl['stops'] ?? []) as $stop): ?>
      <div class="tl-stop">
        <div class="tl-time display"><?= h_rich($stop['time'] ?? '') ?></div>
        <div class="tl-what"><?= h_rich($stop['what'] ?? '') ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
$i = 0;
foreach ($order as $sid) {
    if (empty($RENDERERS[$sid])) continue;
    $alt = ($i % 2 === 1) ? 'alt' : '';
    echo $RENDERERS[$sid]($c, $v, $alt);
    $i++;
}
?>

<footer>
  <div class="footer-inner">
    <div style="display:flex;gap:16px;align-items:flex-start">
      <img src="<?= UPLOAD_URL ?>/logo.png?v=<?= $v ?>" alt="" style="width:72px;flex-shrink:0">
      <div>
        <strong>Homewood High School Athletics</strong>
        Waldrop Stadium · <?= h_rich($site['address'] ?? '') ?><br>
        School office: <a href="<?= h(tel_href($foot['office_phone'] ?? '')) ?>"><?= h($foot['office_phone'] ?? '') ?></a>
        <?php if (!empty($school['url'])): ?><br><a href="<?= h($school['url']) ?>" target="_blank" rel="noopener"><?= h($school['button_label'] ?? 'homewoodathletics.com') ?></a><?php endif; ?>
      </div>
    </div>
    <div>
      <strong>Questions?</strong>
      <?= h_rich($foot['contact_name'] ?? '') ?><br>
      <a href="mailto:<?= h($foot['contact_email'] ?? '') ?>"><?= h($foot['contact_email'] ?? '') ?></a>
    </div>
  </div>
</footer>

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
  // close when a link is chosen, when clicking outside, or on Escape
  panel.addEventListener('click', function(e){ if(e.target.closest('a')) close(); });
  document.addEventListener('click', function(e){
    if(!panel.contains(e.target) && e.target !== btn) close();
  });
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape') close(); });
})();
</script>
</body>
</html>
