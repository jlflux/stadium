# Auto-deploy to Hostinger

Every push to `main` deploys the **code** to your live site automatically.
Your live **content, images, and logins are never touched** by a deploy —
those live only on the server and are managed through the admin panel.

## One-time setup

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
in the admin; we push code. `data/schools/homewood.json` stays tracked in git as
a backup, it just isn't sent to the server by the deploy.

## Notes
- If `ftps` fails to connect, change `protocol: ftps` to `protocol: ftp` in
  `.github/workflows/deploy.yml`.
- Private repos include plenty of free Actions minutes for this (~1 min/deploy).
- Alternative: Hostinger's built-in **Git** feature (hPanel → Advanced → GIT)
  can pull the repo on a webhook instead. It's simpler but pulls *everything*
  tracked, so you'd want to keep content out of git to avoid overwriting live
  edits. The FTP Action above is preferred because it protects your data by
  excluding it.
