<?php
// ============================================================
//  admin.php — multi-school admin
//    • School accounts edit only their own guide + images
//    • Master account creates schools and manages credentials
// ============================================================
require __DIR__ . '/config.php';
session_start();

$msg = ''; $err = '';

// ---------- logout ----------
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE_URL . '/admin.php');
    exit;
}

// ---------- login ----------
if (isset($_POST['do_login'])) {
    $u = verify_login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($u) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['username' => $u['username'], 'role' => $u['role'], 'slug' => $u['slug'] ?? null];
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: ' . BASE_URL . '/admin.php');
        exit;
    }
    $err = 'Incorrect username or password.';
}

// ---------- public self-serve signup (from the landing page) ----------
if (isset($_POST['do_signup'])) {
    $fail = function ($m) { $_SESSION['signup_err'] = $m; header('Location: ' . BASE_URL . '/?signup=1#signup'); exit; };
    if (!SIGNUP_ENABLED) $fail('Sign-ups are currently closed.');
    if (empty($_SESSION['csrf']) || !isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) $fail('Your session expired — please try again.');
    if (trim($_POST['website'] ?? '') !== '') $fail('Something went wrong.'); // honeypot
    $name  = trim($_POST['su_name'] ?? '');
    $slug  = slugify($_POST['su_slug'] ?? '') ?: slugify($name);
    $email = trim($_POST['su_email'] ?? '');
    $uname = $slug;
    $pass  = (string)($_POST['su_password'] ?? '');
    if ($name === '')            $fail('Enter your school name.');
    if (!valid_slug($slug))      $fail('Pick a web address using lowercase letters, numbers, and hyphens.');
    if (school_exists($slug))    $fail('That web address is already taken — try another.');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $fail('Enter a valid email address.');
    if (find_user($uname))       $fail('That name is already taken — try another web address.');
    if (strlen($pass) < 6)       $fail('Password must be at least 6 characters.');

    $tmpl = default_template();
    $tmpl['site']['school_name'] = $name;
    if (!save_content($slug, $tmpl)) $fail('Could not create your guide — please try again.');
    @mkdir(school_upload_dir($slug), 0775, true);
    $users = load_users();
    $users = upsert_user($users, [
        'username'      => $uname,
        'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
        'role'          => 'school',
        'slug'          => $slug,
        'email'         => $email,
    ]);
    save_users($users);

    session_regenerate_id(true);
    $_SESSION['user'] = ['username' => $uname, 'role' => 'school', 'slug' => $slug];
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    unset($_SESSION['signup_err']);
    header('Location: ' . BASE_URL . '/admin.php?welcome=1');
    exit;
}

$user      = $_SESSION['user'] ?? null;
$logged_in = (bool)$user;
$role      = $user['role'] ?? '';

// ---------- CSRF ----------
if ($logged_in && empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok(): bool { return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf']); }

// ---------- image upload helper (scoped to a school's upload dir) ----------
define('MAX_IMG_WIDTH', 2200);
define('JPEG_QUALITY', 85);

function handle_upload(string $field, string $uploadDir, ?string $target_name = null): array {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    $f = $_FILES[$field];

    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        $lim = ini_get('upload_max_filesize');
        return [null, "That file is larger than the server's upload limit ($lim). If this keeps happening, raise upload_max_filesize / post_max_size in .htaccess — see README."];
    }
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, 'Upload failed (error ' . $f['error'] . ').'];

    $info = @getimagesize($f['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => '.jpg', IMAGETYPE_PNG => '.png', IMAGETYPE_WEBP => '.webp'];
    if (!$info || !isset($allowed[$info[2]])) return [null, 'That file isn\'t a JPG, PNG, or WEBP image.'];
    $type = $info[2];

    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

    $keep_png = ($target_name === 'logo.png' || $target_name === 'pat.png');
    $out_ext  = $keep_png ? '.png' : '.jpg';

    if ($target_name) {
        $dest = $uploadDir . '/' . $target_name;
    } else {
        $name = 'photo-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . $out_ext;
        $dest = $uploadDir . '/' . $name;
    }

    if (!function_exists('imagecreatetruecolor')) {
        if ($f['size'] > 8 * 1024 * 1024) return [null, 'Image is over 8 MB and the server can\'t resize it automatically — please shrink it first.'];
        if (!move_uploaded_file($f['tmp_name'], $dest)) return [null, 'Could not save the file — check that the uploads folder is writable.'];
        return [basename($dest), null];
    }

    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($f['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($f['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($f['tmp_name']),
        default        => false,
    };
    if (!$src) return [null, 'That image couldn\'t be read — try re-saving it as a JPG or PNG.'];

    $sw = imagesx($src); $sh = imagesy($src);
    $scale = min(1, MAX_IMG_WIDTH / $sw);
    $dw = max(1, (int)round($sw * $scale));
    $dh = max(1, (int)round($sh * $scale));

    $dst = imagecreatetruecolor($dw, $dh);
    if ($keep_png) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefilledrectangle($dst, 0, 0, $dw, $dh, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    } else {
        imagefilledrectangle($dst, 0, 0, $dw, $dh, imagecolorallocate($dst, 255, 255, 255));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
    imagedestroy($src);

    $ok = $keep_png ? imagepng($dst, $dest, 6) : imagejpeg($dst, $dest, JPEG_QUALITY);
    imagedestroy($dst);

    if (!$ok) return [null, 'Could not save the resized image — check that the uploads folder is writable.'];
    return [basename($dest), null];
}

// Resolve which school this request is allowed to edit.
//  - school role: always its own slug (posted slug is ignored for safety)
//  - master role: the posted/queried slug, if it exists
function target_slug(array $user): ?string {
    if (($user['role'] ?? '') === 'school') return $user['slug'] ?? null;
    $cand = $_POST['school'] ?? $_GET['edit'] ?? '';
    return ($cand !== '' && school_exists($cand)) ? $cand : null;
}

// ============================================================
//  POST actions (only when logged in)
// ============================================================
if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['do_login'])) {
    if (!csrf_ok()) {
        $err = 'Session expired — please try that again.';
    }

    // ---- MASTER: create a school ----
    elseif (isset($_POST['create_school']) && $role === 'master') {
        $name = trim($_POST['new_name'] ?? '');
        $slug = slugify($_POST['new_slug'] ?? '') ?: slugify($name);
        $uname = trim($_POST['new_username'] ?? '') ?: $slug;
        $pass = (string)($_POST['new_password'] ?? '');
        if ($name === '')            $err = 'Enter a school name.';
        elseif (!valid_slug($slug))  $err = 'That slug isn\'t valid. Use lowercase letters, numbers, and hyphens (e.g. "vestavia").';
        elseif (school_exists($slug))$err = 'A school with that slug already exists.';
        elseif (find_user($uname))   $err = 'That username is already taken.';
        elseif (strlen($pass) < 6)   $err = 'Password must be at least 6 characters.';
        else {
            $tmpl = default_template();
            $tmpl['site']['school_name'] = $name;
            if (save_content($slug, $tmpl)) {
                @mkdir(school_upload_dir($slug), 0775, true);
                $users = load_users();
                $users = upsert_user($users, [
                    'username'      => $uname,
                    'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                    'role'          => 'school',
                    'slug'          => $slug,
                ]);
                save_users($users);
                header('Location: ' . BASE_URL . '/admin.php?edit=' . urlencode($slug) . '&created=1');
                exit;
            }
            $err = 'Could not create the school data file — check that data/schools is writable.';
        }
    }

    // ---- MASTER: reset a school's credentials ----
    elseif (isset($_POST['reset_creds']) && $role === 'master') {
        $slug = (string)($_POST['school'] ?? '');
        $newUser = trim($_POST['rc_username'] ?? '');
        $newPass = (string)($_POST['rc_password'] ?? '');
        if (!school_exists($slug)) { $err = 'Unknown school.'; }
        else {
            $users = load_users();
            $target = null;
            foreach ($users['users'] as $uu) if (($uu['role'] ?? '') === 'school' && ($uu['slug'] ?? '') === $slug) { $target = $uu; break; }
            if (!$target) { $err = 'No account found for that school.'; }
            elseif ($newUser !== '' && strcasecmp($newUser, $target['username']) !== 0 && find_user($newUser)) { $err = 'That username is already taken.'; }
            elseif ($newPass !== '' && strlen($newPass) < 6) { $err = 'Password must be at least 6 characters.'; }
            else {
                $old = $target['username'];
                if ($newUser !== '') $target['username'] = $newUser;
                if ($newPass !== '') $target['password_hash'] = password_hash($newPass, PASSWORD_DEFAULT);
                // replace by old username
                foreach ($users['users'] as &$uu) if (strcasecmp($uu['username'] ?? '', $old) === 0) { $uu = $target; break; }
                unset($uu);
                save_users($users);
                $msg = 'Credentials updated for ' . $slug . '.';
            }
        }
    }

    // ---- MASTER: delete a school ----
    elseif (isset($_POST['delete_school']) && $role === 'master') {
        $slug = (string)($_POST['school'] ?? '');
        $confirm = trim($_POST['confirm_slug'] ?? '');
        if (!school_exists($slug)) { $err = 'Unknown school.'; }
        elseif ($confirm !== $slug) { $err = 'Type the exact slug to confirm deletion.'; }
        else {
            @unlink(school_data_file($slug));
            $dir = school_upload_dir($slug);
            if (is_dir($dir)) { foreach (glob($dir . '/*') ?: [] as $f) @unlink($f); @rmdir($dir); }
            $users = load_users();
            $users['users'] = array_values(array_filter($users['users'],
                fn($uu) => !(($uu['role'] ?? '') === 'school' && ($uu['slug'] ?? '') === $slug)));
            save_users($users);
            header('Location: ' . BASE_URL . '/admin.php?deleted=' . urlencode($slug));
            exit;
        }
    }

    // ---- MASTER: feature / unfeature a school on the landing page ----
    elseif (isset($_POST['toggle_feature']) && $role === 'master') {
        $slug = (string)($_POST['school'] ?? '');
        if (!school_exists($slug)) { $err = 'Unknown school.'; }
        else {
            $sc = load_content($slug);
            $sc['featured'] = empty($sc['featured']);
            save_content($slug, $sc);
            $msg = ($sc['featured'] ? 'Added ' : 'Removed ') . $slug . ($sc['featured'] ? ' to the featured list.' : ' from the featured list.');
        }
    }

    // ---- ANY ROLE: change own password ----
    elseif (isset($_POST['change_password'])) {
        $cur = (string)($_POST['cur_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        if (!verify_login($user['username'], $cur)) { $err = 'Current password is incorrect.'; }
        elseif (strlen($new) < 6) { $err = 'New password must be at least 6 characters.'; }
        else {
            $users = load_users();
            foreach ($users['users'] as &$uu) if (strcasecmp($uu['username'] ?? '', $user['username']) === 0) {
                $uu['password_hash'] = password_hash($new, PASSWORD_DEFAULT); break;
            }
            unset($uu);
            save_users($users);
            $msg = 'Your password has been changed.';
        }
    }

    // ---- content / image / gallery edits (scoped to a school) ----
    else {
        $slug = target_slug($user);
        if ($slug === null) {
            $err = 'No school selected, or you don\'t have access to it.';
        } else {
            $UPLDIR = school_upload_dir($slug);
            if (isset($_POST['save_content'])) {
                $c = apply_content_post($_POST, load_content($slug));
                $msg = save_content($slug, $c) ? 'All changes saved. The site is updated.' : 'Could not write the content file — check that data/schools is writable.';
            } elseif (isset($_POST['save_images'])) {
                $vens0 = school_venues(load_content($slug));
                $vix0  = (int)($_POST['venue_index'] ?? 0);
                if ($vix0 < 0 || $vix0 >= count($vens0)) $vix0 = 0;
                $pimg0 = $vens0[$vix0]['parking_img'] ?: 'parking.jpg';
                $done = [];
                foreach (['logo' => 'logo.png', 'pat' => 'pat.png', 'hero' => 'hero.jpg', 'parking' => $pimg0] as $field => $fname) {
                    [$saved, $e] = handle_upload($field, $UPLDIR, $fname);
                    if ($e) { $err = $e; break; }
                    if ($saved) $done[] = $fname;
                }
                if (!$err) $msg = $done ? ('Updated: ' . implode(', ', $done)) : 'No new images selected.';
            } elseif (isset($_POST['gallery_add'])) {
                [$saved, $e] = handle_upload('gallery_photo', $UPLDIR);
                if ($e) $err = $e;
                elseif ($saved) {
                    $c = load_content($slug);
                    $c['gallery']['photos'][] = $saved;
                    save_content($slug, $c);
                    $msg = 'Photo added to the gallery.';
                } else $err = 'Choose a photo first.';
            } elseif (isset($_POST['gallery_remove'])) {
                $photo = basename((string)$_POST['gallery_remove']);
                $c = load_content($slug);
                $photos = $c['gallery']['photos'] ?? [];
                $idx = array_search($photo, $photos, true);
                if ($idx !== false) {
                    array_splice($photos, $idx, 1);
                    $c['gallery']['photos'] = $photos;
                    save_content($slug, $c);
                    if (!in_array($photo, ['hero.jpg', 'parking.jpg', 'logo.png', 'pat.png'], true) && !in_array($photo, $photos, true)) {
                        @unlink($UPLDIR . '/' . $photo);
                    }
                    $msg = 'Photo removed from the gallery.';
                }
            } elseif (isset($_POST['add_venue'])) {
                $c = load_content($slug);
                $vens = school_venues($c);
                $vens[] = normalize_venue(
                    ['name' => 'New Venue', 'id' => 'venue-' . substr(md5(uniqid('', true)), 0, 6)],
                    count($vens)
                );
                $c['venues'] = array_values(array_map('normalize_venue', $vens, array_keys($vens)));
                save_content($slug, $c);
                $msg = 'Venue added — fill in its name, sports, and details below.';
                $_GET['venue'] = (string)(count($c['venues']) - 1);
            } elseif (isset($_POST['remove_venue'])) {
                $c = load_content($slug);
                $vens = school_venues($c);
                $rix = (int)$_POST['remove_venue'];
                if (count($vens) > 1 && isset($vens[$rix])) {
                    array_splice($vens, $rix, 1);
                    $c['venues'] = array_values(array_map('normalize_venue', $vens, array_keys($vens)));
                    save_content($slug, $c);
                    $msg = 'Venue removed.';
                    $_GET['venue'] = (string)max(0, $rix - 1);
                } else {
                    $err = 'A school needs at least one venue.';
                }
            }
        }
    }
}

// one-time flash messages via query (after redirects)
if (isset($_GET['created'])) $msg = 'School created. You\'re now editing it below.';
if (isset($_GET['welcome'])) $msg = 'Welcome to ' . PLATFORM_NAME . '! Your guide is live — fill it in below, then set your brand colors. Your username is your web address.';
if (isset($_GET['deleted'])) $msg = 'Deleted "' . $_GET['deleted'] . '".';

// ---------- decide the view ----------
$editing_slug = null;
if ($logged_in) {
    if ($role === 'school') $editing_slug = $user['slug'] ?? null;
    elseif ($role === 'master') {
        $cand = $_GET['edit'] ?? $_POST['school'] ?? '';
        if ($cand !== '' && school_exists($cand)) $editing_slug = $cand;
    }
}
$c = $editing_slug ? load_content($editing_slug) : [];
$UPL = $editing_slug ? school_upload_url($editing_slug) : '';
$v = $editing_slug ? (@filemtime(school_data_file($editing_slug)) ?: time()) : time();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin · <?= h(PLATFORM_NAME) ?></title>
<meta name="robots" content="noindex">
<link rel="icon" href="<?= BASE_URL ?>/assets/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--bg:#121316;--panel:#1A1D22;--panel2:#15171B;--line:#2A2F37;--text:#E7EAEF;--muted:#98A2B0;--accent:#38B6FF;--accent-ink:#06121D;--danger:#FF5C62}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);line-height:1.55}
a{color:var(--accent)}
.bar{position:sticky;top:0;z-index:40;background:#0C0D0F;color:#fff;border-bottom:3px solid var(--accent)}
.bar-inner{max-width:960px;margin:0 auto;padding:12px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.bar strong{font-weight:900;text-transform:uppercase;letter-spacing:.05em;font-size:15px}
.bar .pill{background:var(--accent);color:var(--accent-ink);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;padding:3px 9px;border-radius:999px}
.bar a{color:#AEB6C2;text-decoration:none;font-size:13px;font-weight:600}
.bar a:hover{color:#fff}
.bar .right{margin-left:auto;display:flex;gap:16px;align-items:center}
main{max-width:960px;margin:0 auto;padding:26px 20px 80px}
h1{font-weight:900;text-transform:uppercase;font-size:24px;color:#fff;margin-bottom:4px}
.sub{color:var(--muted);font-size:14px;margin-bottom:22px}
.flash{border-radius:10px;padding:12px 16px;font-weight:600;font-size:14px;margin-bottom:18px}
.flash.ok{background:rgba(56,182,255,.12);color:#9ED8FF;border:1px solid rgba(56,182,255,.35)}
.flash.bad{background:rgba(255,92,98,.12);color:#FFB0B3;border:1px solid rgba(255,92,98,.4)}
fieldset{border:1px solid var(--line);background:var(--panel);border-radius:14px;padding:20px 22px;margin-bottom:22px}
legend{font-weight:900;text-transform:uppercase;font-size:13px;letter-spacing:.1em;color:var(--accent);padding:0 8px}
label{display:block;font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:14px 0 4px}
label:first-of-type{margin-top:0}
input[type=text],input[type=email],input[type=password],textarea{width:100%;border:1px solid var(--line);border-radius:8px;padding:9px 12px;font:inherit;font-size:14.5px;background:var(--panel2);color:var(--text)}
input::placeholder,textarea::placeholder{color:#5C6673}
input:focus,textarea:focus{outline:2px solid var(--accent);border-color:var(--accent)}
textarea{min-height:84px;resize:vertical}
.hint{font-size:12.5px;color:#7A8492;margin-top:4px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 16px}
@media(max-width:680px){.grid2,.grid3{grid-template-columns:1fr}}
.row{display:grid;grid-template-columns:1.1fr 1.1fr 1fr 1.4fr auto;gap:8px;align-items:end;padding:10px;border:1px solid var(--line);border-radius:10px;margin-bottom:8px;background:var(--panel2)}
.row label{margin:0 0 3px;font-size:11px}
.row .rm{background:none;border:1px solid var(--line);color:var(--danger);border-radius:8px;width:34px;height:38px;font-size:16px;cursor:pointer}
.row .rm:hover{background:rgba(255,92,98,.14)}
@media(max-width:820px){.row{grid-template-columns:1fr 1fr;grid-auto-rows:auto}.row .rm{width:100%}}
.btn{display:inline-block;background:#262B33;color:#fff;border:0;border-radius:9px;padding:11px 20px;font:inherit;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none}
.btn:hover{background:#30363F}
.btn.red{background:var(--accent);color:var(--accent-ink)}.btn.red:hover{background:#5BC6FF}
.btn.ghost{background:none;color:var(--accent);border:1.5px solid var(--accent)}
.btn.ghost:hover{background:rgba(56,182,255,.1)}
.btn.danger{background:var(--danger);color:#1A0506}.btn.danger:hover{background:#FF7A7F}
.btn.sm{padding:7px 13px;font-size:12.5px}
.feat-btn{background:transparent;border:1.5px solid var(--line);color:var(--muted)}
.feat-btn:hover{background:transparent;border-color:var(--accent);color:var(--accent)}
.feat-btn.on{background:var(--accent);border-color:var(--accent);color:var(--accent-ink)}
.feat-btn.on:hover{background:#5BC6FF;color:var(--accent-ink)}
.btn.linkish-danger{background:none;color:var(--danger);border:0;padding:6px 2px;font-size:13px;text-decoration:underline;text-underline-offset:3px}
.btn.linkish-danger:hover{background:none;color:#FF7A7F}
.venuebar .vtabs{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch}
.vtab{display:flex;flex-direction:column;gap:2px;min-width:140px;background:var(--panel2);border:1.5px solid var(--line);border-radius:11px;padding:11px 15px;text-decoration:none;color:var(--text);transition:border-color .12s,background .12s}
.vtab:hover{border-color:var(--accent)}
.vtab.on{border-color:var(--accent);background:rgba(56,182,255,.12)}
.vtab .vt-name{font-weight:800;font-size:14.5px;color:#fff}
.vtab.on .vt-name{color:var(--accent)}
.vtab .vt-sports{font-size:11.5px;color:var(--muted);line-height:1.3}
.vtab-add{display:flex;align-items:center}
.vtab-add .btn{padding:11px 16px;border-style:dashed}
.savebar{position:sticky;bottom:0;background:linear-gradient(180deg,transparent,var(--bg) 30%);padding:16px 0 8px;text-align:right}
.order-list{list-style:none;display:grid;gap:8px}
.order-item{display:flex;align-items:center;gap:12px;background:var(--panel2);border:1px solid var(--line);border-radius:10px;padding:11px 14px;cursor:default}
.order-item.dragging{opacity:.5;border-color:var(--accent)}
.order-item.dragover{border-color:var(--accent);box-shadow:0 0 0 2px rgba(56,182,255,.25)}
.drag-handle{cursor:grab;color:#6B7280;font-size:18px;line-height:1;user-select:none;flex-shrink:0}
.drag-handle:active{cursor:grabbing}
.order-name{font-weight:700;color:var(--text);flex:1}
.order-btns{display:flex;gap:4px}
.order-btns button{width:34px;height:32px;border:1px solid var(--line);background:#20242B;border-radius:7px;font-size:15px;cursor:pointer;color:var(--text)}
.order-btns button:hover{background:#2A2F37}
code{background:#20242B;color:#CBD2DB;padding:1px 6px;border-radius:4px;font-size:12.5px}
.colorrow{display:flex;gap:10px;align-items:center}
.colorrow input[type=color]{width:46px;height:40px;border:1px solid var(--line);border-radius:8px;background:var(--panel2);padding:2px;cursor:pointer;flex-shrink:0}
.colorrow input[type=text]{flex:1}
.imgslots{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.imgslot{border:1px solid var(--line);border-radius:10px;padding:12px;background:var(--panel2);text-align:center}
.imgslot img{max-width:100%;height:110px;object-fit:contain;display:block;margin:0 auto 8px;background:#EEF1F4;border-radius:6px}
.imgslot .name{font-weight:700;font-size:13px;margin-bottom:6px;color:var(--text)}
.imgslot input[type=file]{font-size:12px;width:100%;color:var(--muted)}
.gal{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-top:12px}
.gal .g{border:1px solid var(--line);border-radius:10px;overflow:hidden;background:var(--panel2)}
.gal img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block}
.gal button{width:100%;border:0;background:rgba(255,92,98,.14);color:#FFB0B3;font:inherit;font-size:12.5px;font-weight:700;padding:7px;cursor:pointer}
.gal button:hover{background:rgba(255,92,98,.26)}
.login-box{max-width:380px;margin:80px auto;background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:30px;border-top:5px solid var(--accent)}
.login-box h1{margin-bottom:14px}
.login-brand{display:flex;align-items:center;gap:10px;margin-bottom:18px}
.login-brand img{width:30px;height:34px}
.login-brand span{font-weight:900;font-size:20px;color:#fff;letter-spacing:-.01em}
.login-brand span i{color:var(--accent);font-style:normal}
.school-table{width:100%;border-collapse:collapse;margin-top:6px}
.school-table th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:6px 10px;border-bottom:2px solid var(--line)}
.school-table td{padding:10px;border-bottom:1px solid var(--line);font-size:14px;vertical-align:middle}
.school-table .nm{font-weight:800;color:#fff}
.school-table .sl{color:var(--muted);font-size:12.5px}
.school-table .acts{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.danger{border-color:rgba(255,92,98,.4)!important;background:rgba(255,92,98,.06)}
.danger legend{color:var(--danger)}
details.mgr{border:1px solid var(--line);border-radius:10px;padding:0;margin-bottom:10px;background:var(--panel2)}
details.mgr>summary{cursor:pointer;padding:12px 14px;font-weight:700;color:var(--text);font-size:14px;list-style:none}
details.mgr>summary::-webkit-details-marker{display:none}
details.mgr>summary:hover{color:var(--accent)}
details.mgr .body{padding:0 14px 14px}
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<main>
  <div class="login-box">
    <div class="login-brand"><img src="<?= BASE_URL ?>/assets/venuelink-mark.svg" alt=""><span>Venue<i>Link</i></span></div>
    <h1>Admin Sign-In</h1>
    <?php if ($err): ?><div class="flash bad"><?= h($err) ?></div><?php endif; ?>
    <form method="post">
      <label for="un">Username</label>
      <input type="text" id="un" name="username" autofocus autocomplete="username">
      <label for="pw">Password</label>
      <input type="password" id="pw" name="password" autocomplete="current-password">
      <div style="margin-top:16px"><button class="btn red" name="do_login" value="1">Sign in</button></div>
    </form>
    <p class="hint" style="margin-top:14px">Each school has its own login. Accounts are managed by the master admin.</p>
  </div>
</main>
</body></html>
<?php exit; endif; ?>

<div class="bar">
  <div class="bar-inner">
    <strong><?= h(PLATFORM_NAME) ?> · Admin</strong>
    <span class="pill"><?= $role === 'master' ? 'Master' : 'School' ?></span>
    <?php if ($editing_slug): ?><span style="color:#AEB9CF;font-size:13px">editing <code style="background:rgba(255,255,255,.12);color:#fff"><?= h($editing_slug) ?></code></span><?php endif; ?>
    <div class="right">
      <?php if ($role === 'master' && $editing_slug): ?><a href="<?= h(BASE_URL . '/admin.php') ?>">← All schools</a><?php endif; ?>
      <?php if ($editing_slug): ?><a href="<?= h(school_url($editing_slug)) ?>" target="_blank" rel="noopener">View site ↗</a><?php endif; ?>
      <a href="<?= h(BASE_URL . '/admin.php?logout=1') ?>">Sign out</a>
    </div>
  </div>
</div>

<main>
  <?php if ($msg): ?><div class="flash ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash bad"><?= h($err) ?></div><?php endif; ?>

<?php
// ============================================================
//  MASTER DASHBOARD  (master, no school selected)
// ============================================================
if ($role === 'master' && !$editing_slug):
    $schools = list_schools();
?>
  <h1>Master Dashboard</h1>
  <p class="sub">Create schools, manage their logins, and jump into any school's guide to edit it. Use <strong>★ Feature</strong> to put the best-looking guides on the public landing page — new sign-ups stay off it until you feature them.</p>

  <fieldset>
    <legend>Schools (<?= count($schools) ?>)</legend>
    <?php if ($schools): ?>
    <table class="school-table">
      <thead><tr><th>School</th><th>Front page</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($schools as $sl => $st): $feat = !empty($st['_featured']); ?>
        <tr>
          <td><span class="nm"><?= h($st['school_name'] ?? $sl) ?></span><br><span class="sl"><a href="<?= h(school_url($sl)) ?>" target="_blank" rel="noopener"><?= h(rtrim(BASE_URL, '/') . '/' . $sl) ?></a></span></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
              <input type="hidden" name="school" value="<?= h($sl) ?>">
              <button class="btn sm feat-btn<?= $feat ? ' on' : '' ?>" name="toggle_feature" value="1" title="<?= $feat ? 'Showing on the landing page — click to remove' : 'Not on the landing page — click to feature' ?>"><?= $feat ? '★ Featured' : '☆ Feature' ?></button>
            </form>
          </td>
          <td><div class="acts">
            <a class="btn sm" href="<?= h(BASE_URL . '/admin.php?edit=' . urlencode($sl)) ?>">Edit guide</a>
          </div></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p class="hint">No schools yet. Create the first one below.</p>
    <?php endif; ?>
  </fieldset>

  <fieldset>
    <legend>Create a School</legend>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <div class="grid2">
        <div><label>School name</label><input type="text" name="new_name" id="new_name" placeholder="Vestavia Hills High School"></div>
        <div><label>URL slug</label><input type="text" name="new_slug" id="new_slug" placeholder="vestavia">
          <p class="hint">The address will be <code><?= h(rtrim(BASE_URL,'/')) ?>/<span id="slugPreview">vestavia</span></code>. Lowercase letters, numbers, hyphens.</p>
        </div>
        <div><label>Login username</label><input type="text" name="new_username" placeholder="defaults to the slug"></div>
        <div><label>Login password</label><input type="text" name="new_password" placeholder="at least 6 characters"></div>
      </div>
      <div style="margin-top:16px"><button class="btn red" name="create_school" value="1">Create school</button></div>
      <p class="hint">A new guide is cloned from <code>default.php</code> and an upload folder is created automatically.</p>
    </form>
  </fieldset>

  <?php if ($schools): ?>
  <fieldset>
    <legend>Manage Credentials</legend>
    <?php foreach ($schools as $sl => $st): ?>
    <details class="mgr">
      <summary><?= h($st['school_name'] ?? $sl) ?> <span class="sl" style="color:#8A93A6">· <?= h($sl) ?></span></summary>
      <div class="body">
        <form method="post" style="margin-bottom:14px">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <input type="hidden" name="school" value="<?= h($sl) ?>">
          <div class="grid2">
            <div><label>New username (optional)</label><input type="text" name="rc_username" placeholder="leave blank to keep"></div>
            <div><label>New password (optional)</label><input type="text" name="rc_password" placeholder="leave blank to keep"></div>
          </div>
          <div style="margin-top:12px"><button class="btn sm" name="reset_creds" value="1">Update credentials</button></div>
        </form>
        <form method="post" class="danger" style="border:1px solid #F2C6CB;border-radius:10px;padding:12px;background:#FFF7F8" onsubmit="return confirm('Permanently delete <?= h($sl) ?> and all its data and images?')">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <input type="hidden" name="school" value="<?= h($sl) ?>">
          <label style="color:#8E1A29">Delete this school — type <code><?= h($sl) ?></code> to confirm</label>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <input type="text" name="confirm_slug" placeholder="<?= h($sl) ?>" style="max-width:220px">
            <button class="btn danger sm" name="delete_school" value="1">Delete</button>
          </div>
        </form>
      </div>
    </details>
    <?php endforeach; ?>
  </fieldset>
  <?php endif; ?>

  <fieldset>
    <legend>Your Master Password</legend>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <div class="grid2">
        <div><label>Current password</label><input type="password" name="cur_password" autocomplete="current-password"></div>
        <div><label>New password</label><input type="password" name="new_password" autocomplete="new-password"></div>
      </div>
      <div style="margin-top:12px"><button class="btn" name="change_password" value="1">Change password</button></div>
    </form>
  </fieldset>

<?php
// ============================================================
//  SCHOOL EDITOR  (school role, or master editing a slug)
// ============================================================
else:
    $SEC_NAMES = [
        'welcome' => 'Welcome Letter', 'contacts' => 'Gameday Contacts',
        'parking' => 'Arrival & Parking', 'policies' => 'Stadium Information',
        'media' => 'Media & Broadcast', 'tickets' => 'Tickets',
        'directions' => 'Directions & Map', 'school' => 'Athletics Website',
        'gallery' => 'Photos',
    ];
    $venues = school_venues($c);
    $vi = isset($_GET['venue']) ? (int)$_GET['venue']
        : (isset($_POST['venue_index']) ? (int)$_POST['venue_index'] : 0);
    if ($vi < 0 || $vi >= count($venues)) $vi = 0;
    $vn     = $venues[$vi];
    $multi  = count($venues) > 1;
?>
  <h1>Edit the Guide</h1>
  <p class="sub">Editing <strong><?= h($c['site']['school_name'] ?? $editing_slug) ?></strong>. Change anything below and hit <strong>Save all changes</strong>. Images and gallery photos save instantly with their own buttons.</p>

  <!-- ============ VENUES ============ -->
  <fieldset id="venues" class="venuebar">
    <legend>Venues</legend>
    <p class="hint" style="margin:-4px 0 14px">
      A venue is a place your teams host — a stadium, a gym, a field. Visitors pick their sport on your page and get <em>that</em> venue's address, gameday clock, parking, and directions.
      <?php if ($multi): ?>You have <strong><?= count($venues) ?> venues</strong>. Pick one to edit it, and <strong>save before switching</strong> — unsaved changes don't carry over.<?php else: ?>Add a second venue if some sports play somewhere else (a separate stadium, a different campus).<?php endif; ?>
    </p>
    <div class="vtabs">
      <?php foreach ($venues as $k => $ven):
          $qs = ($role === 'master' ? 'edit=' . urlencode((string)$editing_slug) . '&amp;' : '') . 'venue=' . $k;
          $sp = $ven['sports'] ?? []; ?>
        <a class="vtab<?= $k === $vi ? ' on' : '' ?>" href="?<?= $qs ?>#venues">
          <span class="vt-name"><?= h($ven['name'] ?: ('Venue ' . ($k + 1))) ?></span>
          <?php if ($sp): ?><span class="vt-sports"><?= h(implode(' · ', array_slice($sp, 0, 3)) . (count($sp) > 3 ? ' +' . (count($sp) - 3) : '')) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <form method="post" class="vtab-add">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <input type="hidden" name="school" value="<?= h($editing_slug) ?>">
        <button class="btn ghost" name="add_venue" value="1">+ Add a venue</button>
      </form>
    </div>
    <?php if ($multi): ?>
    <form method="post" style="margin-top:14px" onsubmit="return confirm('Remove the venue &quot;<?= h($vn['name'] ?: 'this venue') ?>&quot;? Visitors will no longer see it. This can\'t be undone.')">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="school" value="<?= h($editing_slug) ?>">
      <button class="btn linkish-danger" name="remove_venue" value="<?= $vi ?>">Remove “<?= h($vn['name'] ?: 'this venue') ?>”</button>
    </form>
    <?php endif; ?>
  </fieldset>

  <p class="sub" style="margin:-8px 0 22px;font-size:13px">You're editing venue <strong style="color:var(--accent)"><?= h($vn['name'] ?: ('Venue ' . ($vi + 1))) ?></strong>. The venue name, sports, address, gameday clock, parking, and directions below all belong to it. Everything else (welcome letter, contacts, tickets, media, photos, colors) is shared across the whole school.</p>

  <!-- ============ IMAGES ============ -->
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <input type="hidden" name="school" value="<?= h($editing_slug) ?>">
    <input type="hidden" name="venue_index" value="<?= $vi ?>">
    <fieldset>
      <legend>Site Images</legend>
      <div class="imgslots">
        <div class="imgslot">
          <img src="<?= h($UPL) ?>/logo.png?v=<?= $v ?>" alt="Current logo" onerror="this.style.visibility='hidden'">
          <div class="name">Logo (header &amp; footer)</div>
          <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="imgslot">
          <img src="<?= h($UPL) ?>/pat.png?v=<?= $v ?>" alt="Current welcome mark" onerror="this.style.visibility='hidden'">
          <div class="name">Welcome-card mark</div>
          <input type="file" name="pat" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="imgslot">
          <img src="<?= h($UPL) ?>/hero.jpg?v=<?= $v ?>" alt="Current hero photo" onerror="this.style.visibility='hidden'">
          <div class="name">Hero photo (top of page)</div>
          <input type="file" name="hero" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="imgslot">
          <img src="<?= h($UPL) ?>/<?= h($vn['parking_img']) ?>?v=<?= $v ?>" alt="Current parking diagram" onerror="this.style.visibility='hidden'">
          <div class="name">Parking diagram<?php if ($multi): ?> — <?= h($vn['name'] ?: ('Venue ' . ($vi + 1))) ?><?php endif; ?></div>
          <input type="file" name="parking" accept="image/png,image/jpeg,image/webp">
        </div>
      </div>
      <div style="margin-top:14px"><button class="btn" name="save_images" value="1">Upload selected images</button></div>
      <p class="hint">JPG, PNG, or WEBP — big phone photos are fine, they're resized and optimized automatically. New uploads replace the current image immediately.</p>
    </fieldset>
  </form>

  <!-- ============ GALLERY ============ -->
  <fieldset>
    <legend>Photo Gallery</legend>
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="school" value="<?= h($editing_slug) ?>">
      <input type="file" name="gallery_photo" accept="image/png,image/jpeg,image/webp">
      <button class="btn" name="gallery_add" value="1">Add photo</button>
    </form>
    <div class="gal">
      <?php foreach (($c['gallery']['photos'] ?? []) as $photo): ?>
      <div class="g">
        <img src="<?= h($UPL) ?>/<?= h($photo) ?>?v=<?= $v ?>" alt="">
        <form method="post" onsubmit="return confirm('Remove this photo from the gallery?')">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <input type="hidden" name="school" value="<?= h($editing_slug) ?>">
          <button name="gallery_remove" value="<?= h($photo) ?>">Remove</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <!-- ============ MAIN CONTENT FORM ============ -->
  <form method="post" id="contentform">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <input type="hidden" name="school" value="<?= h($editing_slug) ?>">
    <input type="hidden" name="venue_index" value="<?= $vi ?>">
    <input type="hidden" name="section_order" id="section_order" value="<?= h(implode(',', $c['section_order'] ?? [])) ?>">

    <fieldset style="border-color:rgba(56,182,255,.45)">
      <legend>This Venue<?php if ($multi): ?> · <?= h($vn['name'] ?: ('Venue ' . ($vi + 1))) ?><?php endif; ?></legend>
      <div class="grid2">
        <div><label>Venue name</label><input type="text" name="ven_name" value="<?= h($vn['name'] ?? '') ?>" placeholder="e.g. Waldrop Stadium"></div>
        <div><label>Sports played here <span style="text-transform:none;font-weight:500;color:var(--muted)">(comma-separated)</span></label><input type="text" name="ven_sports" value="<?= h(implode(', ', $vn['sports'] ?? [])) ?>" placeholder="Football, Flag Football, Track &amp; Field, Soccer"></div>
        <div><label>Address</label><input type="text" name="address" value="<?= h($vn['address'] ?? '') ?>"></div>
        <div><label>Address note <span style="text-transform:none;font-weight:500;color:var(--muted)">(small text after address)</span></label><input type="text" name="address_note" value="<?= h($vn['address_note'] ?? '') ?>"></div>
      </div>
      <p class="hint">When a school has more than one venue, the sports here become the "Which sport are you here for?" buttons that send visitors to this venue. With a single venue, sports are optional and no picker shows.</p>
    </fieldset>

    <fieldset>
      <legend>Page Section Order</legend>
      <p class="hint" style="margin-bottom:12px">Drag the handles to reorder how sections appear on the page — or use the ↑ ↓ buttons. (The hero and gameday clock always stay at the top; the footer stays at the bottom.)</p>
      <ul id="section-order-list" class="order-list">
        <?php foreach (($c['section_order'] ?? array_keys($SEC_NAMES)) as $sid):
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
        <div><label>Full org name (footer &amp; admin list)</label><input type="text" name="org_name" value="<?= h($c['site']['org_name'] ?? '') ?>" placeholder="e.g. Homewood High School"></div>
        <div><label>Tagline (under school name)</label><input type="text" name="tagline" value="<?= h($c['site']['tagline'] ?? '') ?>"></div>
        <div><label>Red badge text (hero)</label><input type="text" name="eyebrow" value="<?= h($c['site']['eyebrow'] ?? '') ?>"></div>
        <div><label>Headline line 1</label><input type="text" name="hero_top" value="<?= h($c['site']['hero_top'] ?? '') ?>"></div>
        <div><label>Headline line 2 (red) — your stadium name</label><input type="text" name="hero_em" value="<?= h($c['site']['hero_em'] ?? '') ?>"></div>
      </div>
      <label>Hero subtitle</label><textarea name="hero_sub"><?= h($c['site']['hero_sub'] ?? '') ?></textarea>
    </fieldset>

    <?php $tp = $c['theme']['primary'] ?? '#101E3C'; $ta = $c['theme']['accent'] ?? '#B22234'; ?>
    <fieldset>
      <legend>Brand Colors</legend>
      <p class="hint" style="margin:-4px 0 14px">Two colors drive the whole page — a dark base and an accent. The darker header/footer shades and hover states are generated automatically. (Homewood's navy &amp; red render exactly as before.)</p>
      <div class="grid2">
        <div>
          <label>Primary (dark base)</label>
          <div class="colorrow">
            <input type="color" class="swatch" data-for="tp" value="<?= h(strtolower($tp)) ?>">
            <input type="text" name="theme_primary" id="tp" value="<?= h($tp) ?>" placeholder="#101E3C">
          </div>
        </div>
        <div>
          <label>Accent</label>
          <div class="colorrow">
            <input type="color" class="swatch" data-for="ta" value="<?= h(strtolower($ta)) ?>">
            <input type="text" name="theme_accent" id="ta" value="<?= h($ta) ?>" placeholder="#B22234">
          </div>
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Gameday Clock<?php if ($multi): ?> · <?= h($vn['name'] ?: ('Venue ' . ($vi + 1))) ?><?php endif; ?></legend>
      <label>Strip label</label><input type="text" name="tl_label" value="<?= h($vn['timeline']['label'] ?? '') ?>">
      <?php
        $stops = $vn['timeline']['stops'] ?? [];
        if (count($stops) < 3) $stops[] = ['time'=>'','what'=>''];
        foreach ($stops as $i => $stop): ?>
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
      <legend>Arrival &amp; Parking<?php if ($multi): ?> · <?= h($vn['name'] ?: ('Venue ' . ($vi + 1))) ?><?php endif; ?></legend>
      <label>Intro sentence</label><textarea name="park_lede"><?= h($vn['parking']['lede'] ?? '') ?></textarea>
      <div class="grid3">
        <?php
          $pcards = $vn['parking']['cards'] ?? [];
          $pcards[] = ['title'=>'','items'=>[]];
          foreach ($pcards as $card): ?>
        <div>
          <label>Card title</label><input type="text" name="park_title[]" value="<?= h($card['title'] ?? '') ?>">
          <label>Bullets (one per line)</label>
          <textarea name="park_items[]" rows="7"><?= h(array_to_lines($card['items'] ?? [])) ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <label>Parking diagram caption</label><textarea name="park_caption" rows="2"><?= h($vn['parking']['caption'] ?? '') ?></textarea>
      <p class="hint">You can use &lt;strong&gt;bold&lt;/strong&gt; inside bullets, e.g. &lt;strong&gt;Recommended:&lt;/strong&gt;</p>
    </fieldset>

    <fieldset>
      <legend>Stadium Information Cards</legend>
      <div class="grid2">
        <?php
          $pols = $c['policies'] ?? [];
          $pols[] = ['title'=>'','items'=>[]];
          foreach ($pols as $pol): ?>
        <div style="margin-bottom:12px">
          <label>Card title</label><input type="text" name="pol_title[]" value="<?= h($pol['title'] ?? '') ?>">
          <label>Bullets (one per line)</label>
          <textarea name="pol_items[]" rows="5"><?= h(array_to_lines($pol['items'] ?? [])) ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="hint">Clear a card's title and bullets to remove it. Fill the spare one to add a card.</p>
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
          <p class="hint">Paste your ticket link and the whole red ticket box becomes a clickable button.</p>
        </div>
        <div><label>Button text</label><input type="text" name="tix_cta" value="<?= h($c['tickets']['cta'] ?? '') ?>" placeholder="Buy tickets on GoFan"></div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Directions &amp; Map<?php if ($multi): ?> · <?= h($vn['name'] ?: ('Venue ' . ($vi + 1))) ?><?php endif; ?></legend>
      <label>Intro sentence</label><textarea name="dir_lede" rows="2"><?= h($vn['directions']['lede'] ?? '') ?></textarea>
      <div class="grid2">
        <?php
          $routes = $vn['directions']['routes'] ?? [];
          $routes[] = ['title'=>'','steps'=>[]];
          foreach ($routes as $route): ?>
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
          <input type="text" name="dir_map_query" value="<?= h($vn['map_query'] ?? '') ?>">
          <p class="hint">What the embedded map pins — usually the venue name and address.</p>
        </div>
        <div><label>Label under the map</label><input type="text" name="dir_map_label" value="<?= h($vn['map_label'] ?? '') ?>"></div>
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
        if (count($blinks) < 3) $blinks[] = ['label'=>'','sublabel'=>'','url'=>''];
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
          $mcards[] = ['title'=>'','items'=>[]];
          foreach ($mcards as $card): ?>
        <div style="margin-bottom:12px">
          <label>Card title</label><input type="text" name="med_title[]" value="<?= h($card['title'] ?? '') ?>">
          <label>Bullets (one per line)</label>
          <textarea name="med_items[]" rows="4"><?= h(array_to_lines($card['items'] ?? [])) ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="hint">Clear a card's title and bullets to remove it. Bullets accept HTML.</p>
    </fieldset>

    <fieldset>
      <legend>Athletics Website</legend>
      <div class="grid2">
        <div><label>Website URL</label><input type="text" name="sch_url" value="<?= h($c['school']['url'] ?? '') ?>" placeholder="https://yourathletics.com"></div>
        <div><label>Top-nav button text</label><input type="text" name="sch_nav" value="<?= h($c['school']['nav_label'] ?? '') ?>" placeholder="Our Athletics"></div>
        <div><label>Section kicker</label><input type="text" name="sch_kicker" value="<?= h($c['school']['kicker'] ?? '') ?>"></div>
        <div><label>Section heading</label><input type="text" name="sch_heading" value="<?= h($c['school']['heading'] ?? '') ?>"></div>
      </div>
      <label>Blurb</label><textarea name="sch_blurb" rows="2"><?= h($c['school']['blurb'] ?? '') ?></textarea>
      <label>Big button text</label><input type="text" name="sch_button" value="<?= h($c['school']['button_label'] ?? '') ?>" placeholder="yourathletics.com">
      <p class="hint">The URL appears as a red button in the top navigation, a callout section, and a footer link.</p>
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

  <?php if ($role === 'school'): ?>
  <fieldset>
    <legend>Your Password</legend>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <div class="grid2">
        <div><label>Current password</label><input type="password" name="cur_password" autocomplete="current-password"></div>
        <div><label>New password</label><input type="password" name="new_password" autocomplete="new-password"></div>
      </div>
      <div style="margin-top:12px"><button class="btn" name="change_password" value="1">Change password</button></div>
    </form>
  </fieldset>
  <?php endif; ?>

<?php endif; // end view switch ?>
</main>

<script>
function addContact(){
  const wrap = document.getElementById('contacts');
  if(!wrap) return;
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

/* brand-color swatch <-> hex text sync */
(function(){
  document.querySelectorAll('.colorrow .swatch').forEach(function(sw){
    var txt = document.getElementById(sw.dataset.for);
    if(!txt) return;
    sw.addEventListener('input', function(){ txt.value = sw.value.toUpperCase(); });
    txt.addEventListener('input', function(){ if(/^#?[0-9a-fA-F]{6}$/.test(txt.value.trim())) sw.value = '#' + txt.value.trim().replace(/^#/,'').toLowerCase(); });
  });
})();

/* live slug preview on the create-school form */
(function(){
  const name = document.getElementById('new_name');
  const slug = document.getElementById('new_slug');
  const prev = document.getElementById('slugPreview');
  if(!slug || !prev) return;
  function slugify(s){ return s.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,''); }
  function upd(){ prev.textContent = (slug.value ? slugify(slug.value) : (name && name.value ? slugify(name.value) : 'your-school')); }
  if(name) name.addEventListener('input', upd);
  slug.addEventListener('input', upd); upd();
})();

/* section reordering */
(function(){
  const list = document.getElementById('section-order-list');
  const field = document.getElementById('section_order');
  if(!list || !field) return;
  function sync(){ field.value = [...list.querySelectorAll('.order-item')].map(li => li.dataset.id).join(','); }
  list.addEventListener('click', (e) => {
    const li = e.target.closest('.order-item'); if(!li) return;
    if(e.target.classList.contains('ord-up') && li.previousElementSibling){ li.parentNode.insertBefore(li, li.previousElementSibling); sync(); }
    else if(e.target.classList.contains('ord-down') && li.nextElementSibling){ li.parentNode.insertBefore(li.nextElementSibling, li); sync(); }
  });
  let dragged = null;
  list.addEventListener('dragstart', (e) => { const li = e.target.closest('.order-item'); if(!li) return; dragged = li; li.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
  list.addEventListener('dragend', () => { if(dragged) dragged.classList.remove('dragging'); list.querySelectorAll('.dragover').forEach(el => el.classList.remove('dragover')); dragged = null; sync(); });
  list.addEventListener('dragover', (e) => {
    e.preventDefault(); const li = e.target.closest('.order-item'); if(!li || li === dragged) return;
    const rect = li.getBoundingClientRect(); const after = (e.clientY - rect.top) > rect.height / 2;
    list.querySelectorAll('.dragover').forEach(el => el.classList.remove('dragover')); li.classList.add('dragover');
    list.insertBefore(dragged, after ? li.nextElementSibling : li);
  });
  list.addEventListener('drop', (e) => e.preventDefault());
  sync();
})();

/* client-side image pre-shrink */
(function(){
  const MAX_W = 2200, JPEG_Q = 0.85;
  const KEEP_PNG = new Set(['logo','pat']);
  function shrink(file, keepPng){
    return new Promise((resolve) => {
      if(!file || !file.type.startsWith('image/')) return resolve(file);
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        URL.revokeObjectURL(url);
        const scale = Math.min(1, MAX_W / img.width);
        if(scale === 1 && file.size < 1.5*1024*1024 && !keepPng){ return resolve(file); }
        const w = Math.max(1, Math.round(img.width*scale));
        const h = Math.max(1, Math.round(img.height*scale));
        const cv = document.createElement('canvas'); cv.width = w; cv.height = h;
        const ctx = cv.getContext('2d');
        if(!keepPng){ ctx.fillStyle = '#fff'; ctx.fillRect(0,0,w,h); }
        ctx.drawImage(img, 0, 0, w, h);
        const outType = keepPng ? 'image/png' : 'image/jpeg';
        const outName = file.name.replace(/\.[^.]+$/, '') + (keepPng ? '.png' : '.jpg');
        cv.toBlob((blob) => { if(!blob) return resolve(file); resolve(new File([blob], outName, { type: outType })); }, outType, keepPng ? undefined : JPEG_Q);
      };
      img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }
  document.querySelectorAll('form').forEach((form) => {
    const fileInputs = form.querySelectorAll('input[type=file]');
    if(!fileInputs.length) return;
    let busy = false;
    form.addEventListener('submit', async (e) => {
      if(busy) return;
      const hasFiles = [...fileInputs].some(inp => inp.files && inp.files.length);
      if(!hasFiles) return;
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
