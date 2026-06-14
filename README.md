# MUBASA Campaign Site — Ssendi Samuel for Deputy Chairperson

Campaign website for **Ssendi Samuel**, candidate for **Deputy Chairperson** of the Makerere University Business School Academic Staff Association (MUBASA), 2026–2028.

**Live URL:** [https://mubasa.ssendi.dev/](https://mubasa.ssendi.dev/)

## Tech stack

| Layer | Technology |
|-------|------------|
| Frontend | **HTML5**, **CSS3**, **JavaScript** (vanilla — no React, no build step) |
| Feedback API | **PHP** + **MySQL** (runs on your CWP/Afriezon VPS) |
| Hosting | Static files + PHP on Apache/CWP |

No npm, no frameworks — easy to deploy and maintain on shared hosting.

## View locally

```bash
cd /Users/samuel/Development/Projects/mubasa
python3 -m http.server 8080
```

Open **http://localhost:8080** in your browser.

> The feedback form needs PHP on the server. Locally you can browse the full site; form submissions work after deploy once `api/config.php` is set up.

For local PHP testing:

```bash
php -S localhost:8080
```

## Policy Hub & Policy Assistant

The **Policy Hub** section explains how the manifesto is grounded in:

- MUBS HR Manual 2024 (downloadable on site)
- MUBS Strategic Plan 2025–2030
- FASPU–PUNTSEF Collective Agreements
- Universities and Other Tertiary Institutions Act, Cap 210
- Public Service Standing Orders 2021

The **Policy Assistant** uses Claude (when configured on the server) plus the HR Manual and policy summaries. Without an API key it falls back to keyword search.

### Enable Claude on the VPS (server only)

```bash
cp api/config.example.php api/config.php
nano api/config.php
```

Add your Anthropic API key from [console.anthropic.com](https://console.anthropic.com) — **never commit `config.php` or paste keys in chat**.

```php
'anthropic_api_key' => 'sk-ant-...',
'anthropic_model' => 'claude-haiku-4-5-20251001',
```

Redeploy after saving. Haiku keeps costs low for a campaign site.

Knowledge base files:

- `data/policies-index.json` — curated summaries and manifesto alignment
- `data/hr-manual-chunks.json` — searchable HR Manual excerpts

## Member feedback form

The **Your Voice** section lets MUBASA members share what they expect from the manifesto. Submissions are stored in MySQL and optionally emailed to you.

### Server setup (one time)

1. Copy `api/config.example.php` → `api/config.php`
2. Fill in your CWP MySQL credentials (database `ssendi_mubasa`)
3. Deploy the site — the PHP script creates the `feedback` table automatically

View submissions in phpMyAdmin: `SELECT * FROM feedback ORDER BY created_at DESC;`

## Deploy to VPS

```bash
# On server — first time
mkdir -p ~/repos && cd ~/repos
git clone git@github.com:ssendisamuel/mubasa.git
cd mubasa
cp api/config.example.php api/config.php   # edit with DB password
chmod +x scripts/deploy.sh
./scripts/deploy.sh
```

Subsequent updates:

```bash
cd ~/repos/mubasa && ./scripts/deploy.sh
```

## Brand colors (campaign flyer)

| Pillar | Hex |
|--------|-----|
| Unity | `#FFCC00` |
| Welfare | `#FF8C00` |
| Growth | `#FF4500` |
| Sustainability | `#E31B23` |
| Background | `#003399` / `#001A57` |

## Contact

- **Email:** sssendi@mubs.ac.ug
- **Phone:** 0779 265 701
