# MUBASA Campaign Site

Campaign website for **Ssendi Samuel MUBASA** — Deputy Chairperson, Makerere University Business School (MUBS), 2026–2028.

**Live URL:** [https://mubasa.ssendi.dev/](https://mubasa.ssendi.dev/)

## Stack

Static HTML, CSS, and JavaScript — no build step required. Designed for fast deployment on CWP/Afriezon shared hosting.

## Local preview

```bash
cd mubasa
python3 -m http.server 8080
# Open http://localhost:8080
```

## Deploy to VPS (CWP)

### 1. Push this repo to GitHub/GitLab

```bash
git remote add origin git@github.com:YOUR_USER/mubasa.git
git push -u origin main
```

### 2. On the server — first-time setup

```bash
ssh ssendi@YOUR_SERVER

# Clone the repo (outside public_html)
mkdir -p ~/repos && cd ~/repos
git clone git@github.com:YOUR_USER/mubasa.git
cd mubasa
chmod +x scripts/deploy.sh

# Deploy to public_html (or subdomain docroot)
REPO_DIR=$HOME/repos/mubasa DEPLOY_DIR=$HOME/public_html ./scripts/deploy.sh
```

If `mubasa.ssendi.dev` uses a separate docroot, set `DEPLOY_DIR` accordingly, e.g.:

```bash
DEPLOY_DIR=$HOME/public_html/mubasa.ssendi.dev ./scripts/deploy.sh
```

### 3. Subsequent deploys

```bash
cd ~/repos/mubasa && ./scripts/deploy.sh
```

Optional cron (every hour):

```cron
0 * * * * REPO_DIR=/home/ssendi/repos/mubasa DEPLOY_DIR=/home/ssendi/public_html /home/ssendi/repos/mubasa/scripts/deploy.sh >> /home/ssendi/logs/mubasa-deploy.log 2>&1
```

## Project structure

```
mubasa/
├── index.html          # Single-page campaign site
├── css/styles.css      # Flyer color theme
├── js/main.js          # Mobile nav + scroll effects
├── assets/images/      # Photos, logo, flyer
└── scripts/deploy.sh   # VPS pull + rsync deploy
```

## Brand colors (from campaign flyer)

| Pillar          | Color   | Hex       |
|-----------------|---------|-----------|
| Unity           | Yellow  | `#FFCC00` |
| Welfare         | Orange  | `#FF8C00` |
| Growth          | Coral   | `#FF4500` |
| Sustainability  | Red     | `#E31B23` |
| Background      | Blue    | `#003399` / `#001A57` |

## Updating manifesto content

Manifesto sections are in `index.html` under `#manifesto`. To sync from the full Word document:

1. Add `Ssendi Samuel MUBASA Manifesto Final Version.docx` to the repo root.
2. Update the manifesto `<ul>` items in `index.html` to match.
3. Optionally export a PDF to `assets/manifesto.pdf` and link it from the hero CTA.

## Contact

Update the email in `index.html` (`#contact`) with your preferred campaign address.
