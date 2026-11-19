WALDROP STADIUM — VISITING TEAM GUIDE
Public site + admin panel (PHP, no database, no dependencies)

WHAT'S IN HERE
  index.php          The public visiting team guide
  admin.php          Password-protected admin panel
  config.php         Settings — CHANGE THE PASSWORD HERE
  data/content.json  All the site's text (edited by the admin panel)
  uploads/           All images (logo, hero, parking diagram, gallery photos)

SETUP ON HOSTINGER
  1. Upload this whole folder's contents to the directory you want
     (public_html/visitors, a subdomain, wherever).
  2. Open config.php and change ADMIN_PASSWORD from the default.
  3. Make sure data/ and uploads/ are writable by PHP.
     On Hostinger shared hosting they usually are already; if saves fail,
     set both folders to permission 755 (or 775) in File Manager.
     The included .htaccess raises the PHP upload limit and also blocks
     direct web access to data/content.json. If your host ignores the
     .htaccess PHP limits, don't worry — the admin panel shrinks images
     in the browser first, so uploads arrive small enough anyway.
  4. Visit yourdomain.com/path/admin.php and sign in.

USING THE ADMIN
  - Everything on the page is editable: hero text, gameday clock times,
    the welcome letter, contacts (add/remove/reorder by editing rows —
    top row shows first on the site), parking bullets, stadium info
    cards, media & broadcast info, ticket info, directions, the
    Homewood Athletics website callout, and the footer.
  - HTML WORKS IN EVERY BOX. Type <strong>bold</strong>,
    <em>italics</em>, or <a href="https://...">a link</a> in any field
    and it renders on the site. Dangerous tags (scripts, etc.) are
    stripped automatically.
  - Bulleted lists are edited one-bullet-per-line in the textareas.

  REORDERING SECTIONS
  - The "Page Section Order" box at the top lets you drag sections into
    any order (or use the up/down arrows). Whatever order you set is the
    order they appear on the page. The hero image and gameday clock stay
    pinned at the top; the footer stays at the bottom.

  TICKETS (GoFan)
  - Paste your GoFan link into the "GoFan / ticket link" field and the
    whole red ticket box becomes one big clickable button. Leave it
    blank for a plain (non-clickable) box.

  MEDIA & BROADCAST
  - Add YouTube, NFHS Network, or any other broadcast links. Each shows
    as a bold button under a "We're always live" banner. Add the URL and
    the button turns red/active; leave the URL blank and it shows greyed
    out as "coming soon."

  HOMEWOOD ATHLETICS WEBSITE
  - Set the website URL once and it appears in three places: a red
    button in the top navigation, a callout section on the page, and a
    link in the footer.
  - The four fixed images (logo, welcome mark, hero photo, parking
    diagram) each have their own upload slot. New uploads replace the
    old image instantly.
  - IMAGE SIZE IS HANDLED FOR YOU. Upload photos straight off your
    phone — they're automatically shrunk in the browser before upload
    and again on the server, so you never have to resize anything.
    Logo and welcome-mark stay PNG (transparency preserved); hero,
    parking, and gallery photos are saved as optimized JPEGs.
  - Gallery photos: upload as many as you want; remove any with the
    Remove button under its thumbnail.
  - The Google Map pins whatever is in the "Google Maps search" field —
    no API key needed.

NOTES
  - No database required. Content lives in data/content.json — back that
    file (and uploads/) up occasionally and you can restore the whole
    site.
  - The admin URL is not linked anywhere on the public site.
  - To change the password later, edit ADMIN_PASSWORD in config.php.
