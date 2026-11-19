<?php
require __DIR__ . '/config.php';
session_start();

$msg = ''; $err = '';

// ---------- auth ----------
if (isset($_POST['do_login'])) {
    if (hash_equals(ADMIN_PASSWORD, (string)($_POST['password'] ?? ''))) {
        $_SESSION['is_admin'] = true;
        session_regenerate_id(true);
        header('Location: admin.php'); exit;
    }
    $err = 'Incorrect password. Try again.';
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php'); exit;
}
$logged_in = !empty($_SESSION['is_admin']);

// ---------- CSRF ----------
if ($logged_in && empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok(): bool { return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']); }

$c = load_content();

// ---------- image upload helper ----------
// Uploaded images are automatically downscaled and compressed so big phone
// photos and screenshots "just work" without the user resizing first.
// Max width the site ever needs: hero/parking are full-bleed, logo/mark are small.
define('MAX_IMG_WIDTH', 2200);   // anything wider is scaled down to this
define('JPEG_QUALITY', 85);

function handle_upload(string $field, string $target_name = null): array {
    // returns [savedFilename|null, error|null]
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    $f = $_FILES[$field];

    // PHP itself blocks files above its ini limits before we can shrink them.
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        $lim = ini_get('upload_max_filesize');
        return [null, "That file is larger than the server's upload limit ($lim). If this keeps happening on very large photos, raise upload_max_filesize / post_max_size in php.ini or .htaccess — see README."];
    }
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'Upload failed (error ' . $f['error'] . ').'];

    $info = @getimagesize($f['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => '.jpg', IMAGETYPE_PNG => '.png', IMAGETYPE_WEBP => '.webp'];
    if (!$info || !isset($allowed[$info[2]])) return [null, 'That file isn\'t a JPG, PNG, or WEBP image.'];
    $type = $info[2];

    // Keep transparency for the logo & welcome mark (they're PNGs on a colored
    // background); everything else becomes an optimized JPEG.
    $keep_png = ($target_name === 'logo.png' || $target_name === 'pat.png');
    $out_ext  = $keep_png ? '.png' : '.jpg';

    if ($target_name) {
        // fixed-name slots keep the name the site markup expects (logo.png, hero.jpg, etc.)
        $dest = UPLOAD_DIR . '/' . $target_name;
    } else {
        $name = 'photo-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . $out_ext;
        $dest = UPLOAD_DIR . '/' . $name;
    }

    // If GD isn't available, fall back to saving the file as-is (with a size guard).
    if (!function_exists('imagecreatetruecolor')) {
        if ($f['size'] > 8 * 1024 * 1024) return [null, 'Image is over 8 MB and the server can\'t resize it automatically — please shrink it first.'];
        if (!move_uploaded_file($f['tmp_name'], $dest)) return [null, 'Could not save the file — check that the uploads folder is writable.'];
        return [basename($dest), null];
    }

    // Load the source into GD.
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($f['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($f['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($f['tmp_name']),
        default        => false,
    };
    if (!$src) return [null, 'That image couldn\'t be read — try re-saving it as a JPG or PNG.'];

    $sw = imagesx($src); $sh = imagesy($src);
    $scale = min(1, MAX_IMG_WIDTH / $sw);          // only ever shrink, never enlarge
    $dw = max(1, (int)round($sw * $scale));
    $dh = max(1, (int)round($sh * $scale));

    $dst = imagecreatetruecolor($dw, $dh);
    if ($keep_png) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $dw, $dh, $transparent);
    } else {
        // flatten any transparency onto white so JPEGs don't get black backgrounds
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $dw, $dh, $white);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);

    $ok = $keep_png
        ? imagepng($dst, $dest, 6)
        : imagejpeg($dst, $dest, JPEG_QUALITY);
    imagedestroy($dst);

    if (!$ok) return [null, 'Could not save the resized image — check that the uploads folder is writable.'];
    return [basename($dest), null];
}

// ---------- save handlers ----------
if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['do_login'])) {
    if (!csrf_ok()) {
        $err = 'Session expired — please try that again.';
    }
    // -- main content save --
    elseif (isset($_POST['save_content'])) {
        $c = apply_content_post($_POST, $c);
        if (save_content($c)) $msg = 'All changes saved. The site is updated.';
        else $err = 'Could not write to data/content.json — check the folder is writable.';
    }
    // -- fixed image slots --
    elseif (isset($_POST['save_images'])) {
        $done = [];
        foreach (['logo' => 'logo.png', 'pat' => 'pat.png', 'hero' => 'hero.jpg', 'parking' => 'parking.jpg'] as $field => $fname) {
            [$saved, $e] = handle_upload($field, $fname);
            if ($e) { $err = $e; break; }
            if ($saved) $done[] = $fname;
        }
        if (!$err) $msg = $done ? ('Updated: ' . implode(', ', $done)) : 'No new images selected.';
    }
    // -- gallery add --
    elseif (isset($_POST['gallery_add'])) {
        [$saved, $e] = handle_upload('gallery_photo');
        if ($e) $err = $e;
        elseif ($saved) {
            $c['gallery']['photos'][] = $saved;
            save_content($c);
            $msg = 'Photo added to the gallery.';
        } else $err = 'Choose a photo first.';
    }
    // -- gallery remove --
    elseif (isset($_POST['gallery_remove'])) {
        $photo = basename((string)$_POST['gallery_remove']); // basename() blocks path tricks
        $photos = $c['gallery']['photos'] ?? [];
        $idx = array_search($photo, $photos, true);
        if ($idx !== false) {
            array_splice($photos, $idx, 1);
            $c['gallery']['photos'] = $photos;
            save_content($c);
            // only delete the file if it's an uploaded gallery photo, not a fixed slot
            if (!in_array($photo, ['hero.jpg', 'parking.jpg', 'logo.png', 'pat.png'], true) && !in_array($photo, $photos, true)) {
                @unlink(UPLOAD_DIR . '/' . $photo);
            }
            $msg = 'Photo removed from the gallery.';
        }
    }
    $c = load_content(); // re-read so the form shows what's on disk
}

$v = @filemtime(DATA_FILE) ?: time();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin · Visiting Team Guide</title>
<meta name="robots" content="noindex">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--navy:#101E3C;--navy-deep:#0A1428;--red:#B22234;--chalk:#F7F5F0;--card:#fff;--ink:#1B2437;--line:#D8D4C8}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',system-ui,sans-serif;background:var(--chalk);color:var(--ink);line-height:1.55}
.bar{position:sticky;top:0;z-index:40;background:var(--navy-deep);color:#fff;border-bottom:3px solid var(--red)}
.bar-inner{max-width:960px;margin:0 auto;padding:12px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.bar strong{font-weight:900;text-transform:uppercase;letter-spacing:.05em;font-size:15px}
.bar a{color:#C9D2E4;text-decoration:none;font-size:13px;font-weight:600}
.bar a:hover{color:#fff}
.bar .right{margin-left:auto;display:flex;gap:16px}
main{max-width:960px;margin:0 auto;padding:26px 20px 80px}
h1{font-weight:900;text-transform:uppercase;font-size:24px;color:var(--navy);margin-bottom:4px}
.sub{color:#6B7488;font-size:14px;margin-bottom:22px}
.flash{border-radius:10px;padding:12px 16px;font-weight:600;font-size:14px;margin-bottom:18px}
.flash.ok{background:#E2F2E5;color:#1C6B33;border:1px solid #BEDFC6}
.flash.bad{background:#FBE7E9;color:#8E1A29;border:1px solid #F2C6CB}
fieldset{border:1px solid var(--line);background:var(--card);border-radius:14px;padding:20px 22px;margin-bottom:22px}
legend{font-weight:900;text-transform:uppercase;font-size:13px;letter-spacing:.1em;color:var(--red);padding:0 8px}
label{display:block;font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#5A6478;margin:14px 0 4px}
label:first-of-type{margin-top:0}
input[type=text],input[type=email],textarea,input[type=password]{width:100%;border:1px solid #CCC8BB;border-radius:8px;padding:9px 12px;font:inherit;font-size:14.5px;background:#FCFBF8}
input:focus,textarea:focus{outline:2px solid var(--navy);border-color:var(--navy)}
textarea{min-height:84px;resize:vertical}
.hint{font-size:12.5px;color:#8A93A6;margin-top:4px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 16px}
@media(max-width:680px){.grid2,.grid3{grid-template-columns:1fr}}
.row{display:grid;grid-template-columns:1.1fr 1.1fr 1fr 1.4fr auto;gap:8px;align-items:end;padding:10px;border:1px solid #EFECE2;border-radius:10px;margin-bottom:8px;background:#FCFBF8}
.row label{margin:0 0 3px;font-size:11px}
.row .rm{background:none;border:1px solid #CCC8BB;color:#8E1A29;border-radius:8px;width:34px;height:38px;font-size:16px;cursor:pointer}
.row .rm:hover{background:#FBE7E9}
@media(max-width:820px){.row{grid-template-columns:1fr 1fr;grid-auto-rows:auto}.row .rm{width:100%}}
.btn{display:inline-block;background:var(--navy);color:#fff;border:0;border-radius:9px;padding:11px 20px;font:inherit;font-weight:700;font-size:14px;cursor:pointer}
.btn:hover{background:#1A2C55}
.btn.red{background:var(--red)}.btn.red:hover{background:#8E1A29}
.btn.ghost{background:none;color:var(--navy);border:1.5px solid var(--navy)}
.btn.ghost:hover{background:#EDEAE2}
.savebar{position:sticky;bottom:0;background:linear-gradient(180deg,transparent,var(--chalk) 30%);padding:16px 0 8px;text-align:right}
.order-list{list-style:none;display:grid;gap:8px}
.order-item{display:flex;align-items:center;gap:12px;background:#FCFBF8;border:1px solid #E3E0D7;border-radius:10px;padding:11px 14px;cursor:default}
.order-item.dragging{opacity:.5;border-color:var(--navy)}
.order-item.dragover{border-color:var(--red);box-shadow:0 0 0 2px rgba(178,34,52,.2)}
.drag-handle{cursor:grab;color:#9AA2B1;font-size:18px;line-height:1;user-select:none;flex-shrink:0}
.drag-handle:active{cursor:grabbing}
.order-name{font-weight:700;color:var(--navy);flex:1}
.order-btns{display:flex;gap:4px}
.order-btns button{width:34px;height:32px;border:1px solid #CCC8BB;background:#fff;border-radius:7px;font-size:15px;cursor:pointer;color:var(--navy)}
.order-btns button:hover{background:#EDEAE2}
code{background:#EDEAE2;padding:1px 6px;border-radius:4px;font-size:12.5px}
.imgslots{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.imgslot{border:1px solid #EFECE2;border-radius:10px;padding:12px;background:#FCFBF8;text-align:center}
.imgslot img{max-width:100%;height:110px;object-fit:contain;display:block;margin:0 auto 8px;background:#fff;border-radius:6px}
.imgslot .name{font-weight:700;font-size:13px;margin-bottom:6px}
.imgslot input[type=file]{font-size:12px;width:100%}
.gal{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-top:12px}
.gal .g{border:1px solid #EFECE2;border-radius:10px;overflow:hidden;background:#fff}
.gal img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block}
.gal button{width:100%;border:0;background:#FBE7E9;color:#8E1A29;font:inherit;font-size:12.5px;font-weight:700;padding:7px;cursor:pointer}
.gal button:hover{background:#F2C6CB}
.login-box{max-width:380px;margin:80px auto;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:30px;border-top:5px solid var(--red)}
.login-box h1{margin-bottom:14px}
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<main>
  <div class="login-box">
    <h1>Admin Sign-In</h1>
    <?php if ($err): ?><div class="flash bad"><?= h($err) ?></div><?php endif; ?>
    <form method="post">
      <label for="pw">Password</label>
      <input type="password" id="pw" name="password" autofocus autocomplete="current-password">
      <div style="margin-top:16px"><button class="btn red" name="do_login" value="1">Sign in</button></div>
    </form>
    <p class="hint" style="margin-top:14px">The password is set in <code>config.php</code>.</p>
  </div>
</main>
</body></html>
<?php exit; endif; ?>

<div class="bar">
  <div class="bar-inner">
    <strong>Visiting Team Guide · Admin</strong>
    <div class="right">
      <a href="index.php" target="_blank" rel="noopener">View site ↗</a>
      <a href="admin.php?logout=1">Sign out</a>
    </div>
  </div>
</div>

<main>
  <h1>Edit the Guide</h1>
  <p class="sub">Change anything below and hit <strong>Save all changes</strong>. Images and gallery photos save instantly with their own buttons.</p>

  <?php if ($msg): ?><div class="flash ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash bad"><?= h($err) ?></div><?php endif; ?>

  <!-- ============ IMAGES ============ -->
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <fieldset>
      <legend>Site Images</legend>
      <div class="imgslots">
        <div class="imgslot">
          <img src="uploads/logo.png?v=<?= $v ?>" alt="Current logo">
          <div class="name">Logo (header &amp; footer)</div>
          <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="imgslot">
          <img src="uploads/pat.png?v=<?= $v ?>" alt="Current welcome mark">
          <div class="name">Welcome-card mark</div>
          <input type="file" name="pat" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="imgslot">
          <img src="uploads/hero.jpg?v=<?= $v ?>" alt="Current hero photo">
          <div class="name">Hero photo (top of page)</div>
          <input type="file" name="hero" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="imgslot">
          <img src="uploads/parking.jpg?v=<?= $v ?>" alt="Current parking diagram">
          <div class="name">Parking diagram</div>
          <input type="file" name="parking" accept="image/png,image/jpeg,image/webp">
        </div>
      </div>
      <div style="margin-top:14px"><button class="btn" name="save_images" value="1">Upload selected images</button></div>
      <p class="hint">JPG, PNG, or WEBP — big photos straight off your phone are fine, they're resized and optimized automatically. New uploads replace the current image immediately.</p>
    </fieldset>
  </form>

  <!-- ============ GALLERY ============ -->
  <fieldset>
    <legend>Photo Gallery</legend>
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="file" name="gallery_photo" accept="image/png,image/jpeg,image/webp">
      <button class="btn" name="gallery_add" value="1">Add photo</button>
    </form>
    <div class="gal">
      <?php foreach (($c['gallery']['photos'] ?? []) as $photo): ?>
      <div class="g">
        <img src="uploads/<?= h($photo) ?>?v=<?= $v ?>" alt="">
        <form method="post" onsubmit="return confirm('Remove this photo from the gallery?')">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <button name="gallery_remove" value="<?= h($photo) ?>">Remove</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <!-- ============ MAIN CONTENT FORM ============ -->
  <form method="post" id="contentform">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <input type="hidden" name="section_order" id="section_order" value="<?= h(implode(',', $c['section_order'] ?? [])) ?>">

    <fieldset>
      <legend>Page Section Order</legend>
      <p class="hint" style="margin-bottom:12px">Drag the handles to reorder how sections appear on the page — or use the ↑ ↓ buttons. (The hero and gameday clock always stay at the top; the footer stays at the bottom.)</p>
      <ul id="section-order-list" class="order-list">
        <?php
          $SEC_NAMES = [
            'welcome' => 'Welcome Letter', 'contacts' => 'Gameday Contacts',
            'parking' => 'Arrival & Parking', 'policies' => 'Stadium Information',
            'media' => 'Media & Broadcast', 'tickets' => 'Tickets',
            'directions' => 'Directions & Map', 'school' => 'Homewood Athletics Website',
            'gallery' => 'Photos',
          ];
          foreach (($c['section_order'] ?? array_keys($SEC_NAMES)) as $sid):
            if (empty($SEC_NAMES[$sid])) continue; ?>
          <li class="order-item" draggable="true" data-id="<?= h($sid) ?>">
            <span class="drag-handle" title="Drag to reorder">⠿</span>
            <span class="order-name"><?= h($SEC_NAMES[$sid]) ?></span>
            <span class="order-btns">
              <button type="button" class="ord-up" title="Move up" aria-label="Move up">↑</button>
              <button type="button" class="ord-down" title="Move down" aria-label="Move down">↓</button>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </fieldset>

    <fieldset>
      <legend>Header &amp; Hero</legend>
      <p class="hint" style="margin:-4px 0 14px">Tip: every text box on this page accepts HTML — use <code>&lt;strong&gt;bold&lt;/strong&gt;</code>, <code>&lt;em&gt;italics&lt;/em&gt;</code>, or <code>&lt;a href="https://..."&gt;a link&lt;/a&gt;</code> anywhere you like.</p>
      <div class="grid2">
        <div><label>School name (header)</label><input type="text" name="school_name" value="<?= h($c['site']['school_name'] ?? '') ?>"></div>
        <div><label>Tagline (under school name)</label><input type="text" name="tagline" value="<?= h($c['site']['tagline'] ?? '') ?>"></div>
        <div><label>Red badge text (hero)</label><input type="text" name="eyebrow" value="<?= h($c['site']['eyebrow'] ?? '') ?>"></div>
        <div><label>Headline line 1</label><input type="text" name="hero_top" value="<?= h($c['site']['hero_top'] ?? '') ?>"></div>
        <div><label>Headline line 2 (red)</label><input type="text" name="hero_em" value="<?= h($c['site']['hero_em'] ?? '') ?>"></div>
        <div><label>Stadium address</label><input type="text" name="address" value="<?= h($c['site']['address'] ?? '') ?>"></div>
      </div>
      <label>Hero subtitle</label><textarea name="hero_sub"><?= h($c['site']['hero_sub'] ?? '') ?></textarea>
      <label>Address note (small text after address)</label><input type="text" name="address_note" value="<?= h($c['site']['address_note'] ?? '') ?>">
    </fieldset>

    <fieldset>
      <legend>Gameday Clock</legend>
      <label>Strip label</label><input type="text" name="tl_label" value="<?= h($c['timeline']['label'] ?? '') ?>">
      <?php foreach (($c['timeline']['stops'] ?? []) as $i => $stop): ?>
      <div class="grid2" style="margin-top:10px">
        <div><label>Time <?= $i + 1 ?></label><input type="text" name="tl_time[]" value="<?= h($stop['time'] ?? '') ?>"></div>
        <div><label>Description</label><input type="text" name="tl_what[]" value="<?= h($stop['what'] ?? '') ?>"></div>
      </div>
      <?php endforeach; ?>
      <p class="hint">Leave a row's time and description blank to remove that stop.</p>
    </fieldset>

    <fieldset>
      <legend>Welcome Letter</legend>
      <div class="grid2">
        <div><label>Kicker (small red text)</label><input type="text" name="w_kicker" value="<?= h($c['welcome']['kicker'] ?? '') ?>"></div>
        <div><label>Heading</label><input type="text" name="w_heading" value="<?= h($c['welcome']['heading'] ?? '') ?>"></div>
      </div>
      <label>Paragraph 1</label><textarea name="w_p1"><?= h($c['welcome']['p1'] ?? '') ?></textarea>
      <label>Paragraph 2</label><textarea name="w_p2"><?= h($c['welcome']['p2'] ?? '') ?></textarea>
      <label>Quote (in the card)</label><input type="text" name="w_quote" value="<?= h($c['welcome']['quote'] ?? '') ?>">
      <div class="grid2">
        <div><label>Signature name</label><input type="text" name="w_sig_name" value="<?= h($c['welcome']['sig_name'] ?? '') ?>"></div>
        <div><label>Signature title</label><input type="text" name="w_sig_title" value="<?= h($c['welcome']['sig_title'] ?? '') ?>"></div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Gameday Contacts</legend>
      <p class="hint" style="margin-bottom:10px">Cards appear on the site in this order — top row here shows first. Leave the name blank to drop a contact.</p>
      <div id="contacts">
        <?php foreach (($c['contacts'] ?? []) as $ct): ?>
        <div class="row">
          <div><label>Role</label><input type="text" name="ct_role[]" value="<?= h($ct['role'] ?? '') ?>"></div>
          <div><label>Name</label><input type="text" name="ct_name[]" value="<?= h($ct['name'] ?? '') ?>"></div>
          <div><label>Phone</label><input type="text" name="ct_phone[]" value="<?= h($ct['phone'] ?? '') ?>"></div>
          <div><label>Email (optional)</label><input type="text" name="ct_email[]" value="<?= h($ct['email'] ?? '') ?>"></div>
          <button type="button" class="rm" title="Remove contact" onclick="this.closest('.row').remove()">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn ghost" onclick="addContact()">+ Add contact</button>
      <label style="margin-top:20px">Administrative team (one person per line)</label>
      <textarea name="admin_team" rows="6"><?= h(array_to_lines($c['admin_team'] ?? [])) ?></textarea>
    </fieldset>

    <fieldset>
      <legend>Arrival &amp; Parking</legend>
      <label>Intro sentence</label><textarea name="park_lede"><?= h($c['parking']['lede'] ?? '') ?></textarea>
      <div class="grid3">
        <?php foreach (($c['parking']['cards'] ?? []) as $card): ?>
        <div>
          <label>Card title</label><input type="text" name="park_title[]" value="<?= h($card['title'] ?? '') ?>">
          <label>Bullets (one per line)</label>
          <textarea name="park_items[]" rows="7"><?= h(array_to_lines($card['items'] ?? [])) ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <label>Parking diagram caption</label><textarea name="park_caption" rows="2"><?= h($c['parking']['caption'] ?? '') ?></textarea>
      <p class="hint">You can use &lt;strong&gt;bold&lt;/strong&gt; inside bullets, e.g. &lt;strong&gt;Recommended:&lt;/strong&gt;</p>
    </fieldset>

    <fieldset>
      <legend>Stadium Information Cards</legend>
      <div class="grid2">
        <?php foreach (($c['policies'] ?? []) as $pol): ?>
        <div style="margin-bottom:12px">
          <label>Card title</label><input type="text" name="pol_title[]" value="<?= h($pol['title'] ?? '') ?>">
          <label>Bullets (one per line)</label>
          <textarea name="pol_items[]" rows="5"><?= h(array_to_lines($pol['items'] ?? [])) ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="hint">Clear a card's title and bullets to remove it. To add a card, fill in the spare one below.</p>
      <div class="grid2">
        <div>
          <label>New card title</label><input type="text" name="pol_title[]" value="">
          <label>New card bullets</label><textarea name="pol_items[]" rows="3"></textarea>
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Tickets</legend>
      <div class="grid2">
        <div><label>Price</label><input type="text" name="tix_price" value="<?= h($c['tickets']['price'] ?? '') ?>"></div>
        <div><label>Price note</label><input type="text" name="tix_note" value="<?= h($c['tickets']['price_note'] ?? '') ?>"></div>
      </div>
      <label>Bullets (one per line)</label>
      <textarea name="tix_items" rows="5"><?= h(array_to_lines($c['tickets']['items'] ?? [])) ?></textarea>
      <div class="grid2">
        <div>
          <label>GoFan / ticket link (optional)</label>
          <input type="text" name="tix_url" value="<?= h($c['tickets']['url'] ?? '') ?>" placeholder="https://gofan.co/app/school/...">
          <p class="hint">Paste your GoFan link here and the whole red ticket box becomes a clickable button. Leave blank for a plain box.</p>
        </div>
        <div><label>Button text</label><input type="text" name="tix_cta" value="<?= h($c['tickets']['cta'] ?? '') ?>" placeholder="Buy tickets on GoFan"></div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Directions &amp; Map</legend>
      <label>Intro sentence</label><textarea name="dir_lede" rows="2"><?= h($c['directions']['lede'] ?? '') ?></textarea>
      <div class="grid2">
        <?php foreach (($c['directions']['routes'] ?? []) as $route): ?>
        <div>
          <label>Route title</label><input type="text" name="dir_title[]" value="<?= h($route['title'] ?? '') ?>">
          <label>Steps (one per line)</label>
          <textarea name="dir_steps[]" rows="6"><?= h(array_to_lines($route['steps'] ?? [])) ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="grid2">
        <div>
          <label>Google Maps search</label>
          <input type="text" name="dir_map_query" value="<?= h($c['directions']['map_query'] ?? '') ?>">
          <p class="hint">What the embedded map pins — usually the stadium name and address.</p>
        </div>
        <div><label>Label under the map</label><input type="text" name="dir_map_label" value="<?= h($c['directions']['map_label'] ?? '') ?>"></div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Media &amp; Broadcast Information</legend>
      <div class="grid2">
        <div><label>Kicker (small red text)</label><input type="text" name="med_kicker" value="<?= h($c['media']['kicker'] ?? '') ?>"></div>
        <div><label>Heading</label><input type="text" name="med_heading" value="<?= h($c['media']['heading'] ?? '') ?>"></div>
      </div>
      <label>Intro sentence</label><textarea name="med_lede" rows="2"><?= h($c['media']['lede'] ?? '') ?></textarea>

      <label style="margin-top:18px">Broadcast links (YouTube, NFHS Network, etc.)</label>
      <p class="hint" style="margin-bottom:8px">These show as bold buttons under a "We're always live" banner. Add the URL and they light up; leave a URL blank to show it as "coming soon" grey.</p>
      <?php
        $blinks = $c['media']['broadcast_links'] ?? [];
        if (count($blinks) < 3) $blinks[] = ['label'=>'','sublabel'=>'','url'=>''];  // spare row to add one
        foreach ($blinks as $bl): ?>
      <div class="row" style="grid-template-columns:1.1fr 1.3fr 1.6fr;">
        <div><label>Button label</label><input type="text" name="bl_label[]" value="<?= h($bl['label'] ?? '') ?>" placeholder="Watch on YouTube"></div>
        <div><label>Small text under it</label><input type="text" name="bl_sub[]" value="<?= h($bl['sublabel'] ?? '') ?>" placeholder="Live every home game"></div>
        <div><label>Link URL</label><input type="text" name="bl_url[]" value="<?= h($bl['url'] ?? '') ?>" placeholder="https://..."></div>
      </div>
      <?php endforeach; ?>

      <label style="margin-top:18px">Info cards</label>
      <div class="grid2">
        <?php
          $mcards = $c['media']['cards'] ?? [];
          $mcards[] = ['title'=>'','items'=>[]]; // spare card to add one
          foreach ($mcards as $card): ?>
        <div style="margin-bottom:12px">
          <label>Card title</label><input type="text" name="med_title[]" value="<?= h($card['title'] ?? '') ?>">
          <label>Bullets (one per line)</label>
          <textarea name="med_items[]" rows="4"><?= h(array_to_lines($card['items'] ?? [])) ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="hint">Clear a card's title and bullets to remove it. Bullets accept HTML — e.g. &lt;a href="..."&gt;link text&lt;/a&gt;.</p>
    </fieldset>

    <fieldset>
      <legend>Homewood Athletics Website</legend>
      <div class="grid2">
        <div><label>Website URL</label><input type="text" name="sch_url" value="<?= h($c['school']['url'] ?? '') ?>" placeholder="https://homewoodathletics.com"></div>
        <div><label>Top-nav button text</label><input type="text" name="sch_nav" value="<?= h($c['school']['nav_label'] ?? '') ?>" placeholder="Homewood Athletics"></div>
        <div><label>Section kicker</label><input type="text" name="sch_kicker" value="<?= h($c['school']['kicker'] ?? '') ?>"></div>
        <div><label>Section heading</label><input type="text" name="sch_heading" value="<?= h($c['school']['heading'] ?? '') ?>"></div>
      </div>
      <label>Blurb</label><textarea name="sch_blurb" rows="2"><?= h($c['school']['blurb'] ?? '') ?></textarea>
      <label>Big button text</label><input type="text" name="sch_button" value="<?= h($c['school']['button_label'] ?? '') ?>" placeholder="homewoodathletics.com">
      <p class="hint">The URL appears as a red button in the top navigation, a callout section on the page, and a link in the footer.</p>
    </fieldset>

    <fieldset>
      <legend>Gallery &amp; Footer</legend>
      <label>Gallery intro sentence</label><input type="text" name="gal_lede" value="<?= h($c['gallery']['lede'] ?? '') ?>">
      <div class="grid3">
        <div><label>Footer office phone</label><input type="text" name="f_phone" value="<?= h($c['footer']['office_phone'] ?? '') ?>"></div>
        <div><label>Footer contact name</label><input type="text" name="f_name" value="<?= h($c['footer']['contact_name'] ?? '') ?>"></div>
        <div><label>Footer contact email</label><input type="text" name="f_email" value="<?= h($c['footer']['contact_email'] ?? '') ?>"></div>
      </div>
    </fieldset>

    <div class="savebar">
      <button class="btn red" name="save_content" value="1" style="padding:14px 34px;font-size:15px">Save all changes</button>
    </div>
  </form>
</main>

<script>
function addContact(){
  const wrap = document.getElementById('contacts');
  const div = document.createElement('div');
  div.className = 'row';
  div.innerHTML = `
    <div><label>Role</label><input type="text" name="ct_role[]" value=""></div>
    <div><label>Name</label><input type="text" name="ct_name[]" value=""></div>
    <div><label>Phone</label><input type="text" name="ct_phone[]" value=""></div>
    <div><label>Email (optional)</label><input type="text" name="ct_email[]" value=""></div>
    <button type="button" class="rm" title="Remove contact" onclick="this.closest('.row').remove()">✕</button>`;
  wrap.appendChild(div);
  div.querySelector('input').focus();
}

/* --------------------------------------------------------------
   Section reordering — drag handles + up/down buttons.
   Keeps the hidden #section_order field in sync so the order saves
   with "Save all changes".
----------------------------------------------------------------- */
(function(){
  const list = document.getElementById('section-order-list');
  const field = document.getElementById('section_order');
  if(!list || !field) return;

  function sync(){
    field.value = [...list.querySelectorAll('.order-item')].map(li => li.dataset.id).join(',');
  }

  // --- up/down buttons ---
  list.addEventListener('click', (e) => {
    const li = e.target.closest('.order-item'); if(!li) return;
    if(e.target.classList.contains('ord-up') && li.previousElementSibling){
      li.parentNode.insertBefore(li, li.previousElementSibling); sync();
    } else if(e.target.classList.contains('ord-down') && li.nextElementSibling){
      li.parentNode.insertBefore(li.nextElementSibling, li); sync();
    }
  });

  // --- drag and drop ---
  let dragged = null;
  list.addEventListener('dragstart', (e) => {
    const li = e.target.closest('.order-item'); if(!li) return;
    dragged = li; li.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  });
  list.addEventListener('dragend', (e) => {
    if(dragged) dragged.classList.remove('dragging');
    list.querySelectorAll('.dragover').forEach(el => el.classList.remove('dragover'));
    dragged = null; sync();
  });
  list.addEventListener('dragover', (e) => {
    e.preventDefault();
    const li = e.target.closest('.order-item');
    if(!li || li === dragged) return;
    const rect = li.getBoundingClientRect();
    const after = (e.clientY - rect.top) > rect.height / 2;
    list.querySelectorAll('.dragover').forEach(el => el.classList.remove('dragover'));
    li.classList.add('dragover');
    list.insertBefore(dragged, after ? li.nextElementSibling : li);
  });
  list.addEventListener('drop', (e) => e.preventDefault());

  sync(); // set initial value
})();

/* --------------------------------------------------------------
   Client-side image pre-shrink.
   Before any image is uploaded, downscale it in the browser to at
   most MAX_W pixels wide and re-encode it. This means even a 12 MB
   phone photo arrives at the server as a few hundred KB, so it never
   trips the host's upload_max_filesize limit. The server-side resize
   still runs as a safety net for anything that slips through.
   Logo & welcome-mark inputs are left as PNG to keep transparency.
----------------------------------------------------------------- */
(function(){
  const MAX_W = 2200, JPEG_Q = 0.85;
  const KEEP_PNG = new Set(['logo','pat']);      // input name= that must stay PNG

  function shrink(file, keepPng){
    return new Promise((resolve) => {
      if(!file || !file.type.startsWith('image/')) return resolve(file);
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        URL.revokeObjectURL(url);
        const scale = Math.min(1, MAX_W / img.width);
        // nothing to do if it's already small and a normal jpeg
        if(scale === 1 && file.size < 1.5*1024*1024 && !keepPng){ return resolve(file); }
        const w = Math.max(1, Math.round(img.width*scale));
        const h = Math.max(1, Math.round(img.height*scale));
        const cv = document.createElement('canvas');
        cv.width = w; cv.height = h;
        const ctx = cv.getContext('2d');
        if(!keepPng){ ctx.fillStyle = '#fff'; ctx.fillRect(0,0,w,h); } // flatten to white for jpeg
        ctx.drawImage(img, 0, 0, w, h);
        const outType = keepPng ? 'image/png' : 'image/jpeg';
        const outName = file.name.replace(/\.[^.]+$/, '') + (keepPng ? '.png' : '.jpg');
        cv.toBlob((blob) => {
          if(!blob) return resolve(file);           // fallback: send original
          resolve(new File([blob], outName, { type: outType }));
        }, outType, keepPng ? undefined : JPEG_Q);
      };
      img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  // Intercept submit on every form that has a file input, swap in shrunk files.
  document.querySelectorAll('form').forEach((form) => {
    const fileInputs = form.querySelectorAll('input[type=file]');
    if(!fileInputs.length) return;
    let busy = false;
    form.addEventListener('submit', async (e) => {
      if(busy) return;                              // already processed, let it through
      const hasFiles = [...fileInputs].some(inp => inp.files && inp.files.length);
      if(!hasFiles) return;                          // text-only submit (e.g. remove button)
      e.preventDefault();
      const submitter = e.submitter;
      for(const inp of fileInputs){
        if(!inp.files || !inp.files.length) continue;
        const keepPng = KEEP_PNG.has(inp.name);
        const dt = new DataTransfer();
        for(const f of inp.files){ dt.items.add(await shrink(f, keepPng)); }
        inp.files = dt.files;
      }
      busy = true;
      // preserve which button was clicked (save_images vs gallery_add)
      if(submitter && submitter.name){
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = submitter.name; hidden.value = submitter.value || '1';
        form.appendChild(hidden);
      }
      form.submit();
    });
  });
})();
</script>
</body>
</html>
