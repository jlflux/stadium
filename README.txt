GAMEDAY GUIDES — multi-school visiting-team guide platform
PHP, no database, no build step. Runs on shared hosting (e.g. Hostinger).

WHAT'S HERE
  index.php            Front controller: landing page + each school's guide
  admin.php            Login-protected admin (school accounts + master admin)
  config.php           Paths, platform settings, shared helpers (no secrets)
  default.php          Blank-school template, cloned when a school is created
  .htaccess            Pretty-URL routing + upload limits
  data/
    schools/{slug}.json  One file per school (the site content)
    users.json           Accounts + bcrypt password hashes (NOT in git)
    users.sample.json    Template for users.json
    .htaccess            Blocks direct web access to the JSON files
  uploads/
    {slug}/...           Each school's images (logo, hero, parking, gallery)

HOW ROUTING WORKS
  /                 -> landing page / directory of all schools
  /{school-slug}    -> that school's guide (e.g. /homewood, /vestavia)
  /admin            -> admin login

SETUP ON HOSTINGER
  1. Upload everything to the web root (or a subfolder — both work).
  2. Create data/users.json from data/users.sample.json. Generate each
     password hash with PHP, e.g.:
         php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
     Paste the output into the "password_hash" field for each account.
     (Or just sign in as master and set credentials from the dashboard.)
  3. Make data/, data/schools/, and uploads/ writable by PHP
     (755 or 775 in File Manager if saves fail).
  4. Go to /admin and sign in.

ACCOUNTS
  - Master admin: create schools, set/reset each school's login, delete a
    school, and jump into any school's guide to edit it.
  - School admin: edits only its own guide and images — nothing else.
  Creating a school clones default.php into data/schools/{slug}.json,
  makes uploads/{slug}/, and adds a hashed login.

USING THE ADMIN (per school)
  - Every section is editable: hero, gameday clock, welcome letter,
    contacts, parking, stadium info, media & broadcast, tickets,
    directions, athletics-website callout, gallery, footer.
  - HTML works in every text box (bold, italics, links); scripts are
    stripped automatically.
  - Drag the "Page Section Order" list to reorder sections on the page.
  - Paste a GoFan link to turn the ticket box into a button.
  - Upload images straight from a phone — they're resized automatically.
  - Change your own password at the bottom of the editor.

NOTES
  - No database. Content is flat-file JSON under data/schools/.
  - Blank/partial schools hide empty sections and missing images, so a new
    school never looks broken.
  - Every public school page has a subtle "Want a Gameday Guide like this
    for your school?" link to the landing page (configurable in config.php).
