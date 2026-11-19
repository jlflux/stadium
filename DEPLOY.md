# Auto-deploy to Hostinger

Every push to `main` deploys the **code** to your live site automatically.
Your live **content, images, and logins are never touched** by a deploy —
those live only on the server and are managed through the admin panel.

Pick **one** method. Method A (Hostinger's own Git) is simplest and needs no
credentials in GitHub. Method B (the GitHub Action) is already in the repo as
an alternative.

---

## Method A — Hostinger built-in Git (recommended)

Hostinger pulls the repo into your site folder, and a webhook makes it
re-pull on every push.

1. **Seed the site once:** upload `stadium-deploy.zip` to the site folder and
   extract it, so `data/` and `uploads/` exist on the server. (A pull only
   updates code; it never creates or overwrites these.)
2. In hPanel open **Advanced → GIT** (or Website → manage → Advanced → GIT).
3. **Create a repository:**
   - Repository: `git@github.com:jlflux/stadium.git`
   - Branch: `main`
   - Directory: the site's folder (e.g. `public_html` or `public_html/guides`)
4. **Give it access to the private repo:** Hostinger shows an SSH key — copy it,
   then in GitHub go to the repo → **Settings → Deploy keys → Add deploy key**,
   paste it (read-only is fine).
5. **Turn on auto-deploy:** in the Hostinger GIT page, copy the **webhook URL**,
   then in GitHub go to the repo → **Settings → Webhooks → Add webhook**, paste
   the URL, set content type `application/json`, and save.

Done — every push now auto-pulls to your site. You can also hit **Deploy** on the
Hostinger GIT page to pull on demand.

---

## Method B — GitHub Action over FTP (alternative, already set up)

### One-time setup

### 1. Seed the site once (first time only)
Upload `stadium-deploy.zip` to your Hostinger folder and extract it. This puts
the starting data (`data/users.json`, `data/schools/homewood.json`) and images
(`uploads/homewood/`) on the server. After this, those files stay on the server
and are never overwritten by a deploy.

### 2. Get your FTP details (Hostinger hPanel → Files → FTP Accounts)
You need: FTP host, username, password, and the folder path your site lives in.

### 3. Add them to GitHub (repo → Settings → Secrets and variables → Actions)
Create these four **Secrets**:

| Secret name       | Example value                              |
|-------------------|--------------------------------------------|
| `FTP_HOST`        | `ftp.yourdomain.com` (or the IP hPanel shows) |
| `FTP_USERNAME`    | your FTP account username                   |
| `FTP_PASSWORD`    | your FTP account password                   |
| `FTP_SERVER_DIR`  | `/public_html/guides/` (must end with `/`)  |

Then, on the **Variables** tab, add one variable to switch deploys on:

| Variable name     | Value   |
|-------------------|---------|
| `DEPLOY_ENABLED`  | `true`  |

### 4. Done
From now on, every change we push here deploys automatically. You can also
trigger a deploy by hand from the repo's **Actions** tab → "Deploy to
Hostinger" → "Run workflow". Watch runs (green = deployed) in that tab.

## What deploys vs. what doesn't

- **Deployed (code):** `index.php`, `admin.php`, `config.php`, `default.php`,
  `.htaccess`, templates/samples.
- **Never deployed (your live data):** `data/users.json`, `data/schools/*.json`,
  and everything in `uploads/`. Edit those through the admin — a deploy leaves
  them alone.

Because content is excluded, bulk content changes we make *here* won't auto-push
(by design, so your admin edits are never clobbered). Day-to-day, edit content
in the admin; we push code. Per-school content is not tracked in git at all —
it lives on the server and is owned by the admin. (Homewood's migrated copy is
recoverable from git history and from `stadium-deploy.zip`.)

## Notes
- If `ftps` fails to connect, change `protocol: ftps` to `protocol: ftp` in
  `.github/workflows/deploy.yml`.
- Private repos include plenty of free Actions minutes for this (~1 min/deploy).
