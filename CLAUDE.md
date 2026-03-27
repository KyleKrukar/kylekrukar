# CLAUDE.md — kylekrukar.com

## Project Overview

Personal portfolio site for **Kyle Krukar** — Founder & Maker based in Denver, CO. Currently at [By the Pixel](https://www.bythepixel.com). This is an actively evolving single-page site with a live Spotify integration and cinematic visual design.

**Production:** https://kylekrukar.com

## Architecture

### Frontend (Static Site)
- Single `index.html` with inline JavaScript — no build step, no framework
- `styles/default.css` — all styles in one file
- Google Fonts: Open Sans (400, 600)
- Responsive breakpoints: 900px, 700px, 460px, 420px

### Visual Layers (back to front)
1. **Aurora background** (`z-index: -3`) — animated repeating gradient in blue/indigo/violet, inspired by X Developer Console. Shows as default when Spotify is not playing.
2. **Album art backgrounds** (`z-index: -2, -1`) — two layers for crossfading. Blurred, saturated, scaled 1.3x. Shown when Spotify is playing.
3. **Content** (`z-index: 0`) — profile image, name, title, links, now-playing widget
4. **TV effects** (`z-index: 1`) — SVG fractal noise grain, CSS scanlines, radial vignette overlay

### Spotify "Now Playing" Integration
- Polls Lambda API every 15 seconds
- Extracts dominant color from album art via canvas (64x64 sample)
- Adjusts text color dynamically with WCAG AA contrast enforcement
- Crossfades between album art backgrounds on track change
- Clears to aurora background when music stops/pauses (detected on next poll)
- Now-playing widget: fixed bottom-right on desktop, static on mobile

### Color System
- `--album-text` and `--album-text-hover` CSS custom properties set dynamically from album art
- Falls back to `#fff` when no music is playing
- HSL manipulation with luminance-based contrast checking against the blurred background

## Infrastructure

### Hosting
- AWS S3 + CloudFront (prod and dev buckets/distributions configured separately)
- **Region:** us-east-2 (Ohio)
- See deploy scripts for bucket names and distribution IDs

### Lambda Functions (in `lambda/`, deployed separately)
- **`now-playing/index.mjs`** — Spotify currently-playing API proxy with OAuth token caching. Env vars: `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`, `SPOTIFY_REFRESH_TOKEN`, `ALLOWED_ORIGINS`
- **`github-activity/index.mjs`** — GitHub contribution calendar via GraphQL (last 30 days). Env vars: `GITHUB_TOKEN`, `GITHUB_USERNAME`, `ALLOWED_ORIGIN`
- **API Gateway:** endpoint configured in `index.html` inline script

## Deployment

### Dev → Prod Workflow
```
# 1. Deploy local changes to dev for testing
bash deploy-dev.sh    # syncs to dev bucket, invalidates dev CDN

# 2. Test at dev URL

# 3. Promote dev to production
bash deploy-prod.sh   # syncs dev bucket → prod bucket, invalidates prod CDN
```

### Other Scripts
- `deploy.sh` — direct local-to-prod deploy (bypasses dev)
- `pull-prod.sh` — sync production files down to local
- `router.php` — local PHP dev server with Spotify API proxy (`php -S localhost:3000 router.php`)

### CI/CD
- `.github/workflows/deploy.yml` — on push to `main`, syncs to S3 and invalidates CloudFront (prod)
- This means `git push` to main also deploys to production directly

### File Exclusions (all deploy scripts)
`.git`, `.github`, `.claude`, `lambda/`, `deploy*.sh`, `pull-prod.sh`, `router.php`, `.gitignore`, `.DS_Store`, `.env`

## Local Development
```bash
# Requires PHP 7+ (for router.php Spotify proxy)
php -S localhost:3000 router.php

# Or just open the file directly (API calls go to Lambda)
open index.html
```

## Key Design Decisions
- No build tools or frameworks — everything is vanilla HTML/CSS/JS for simplicity and speed
- Inline JS in index.html to keep it as a single deployable unit
- TV grain/scanlines give a cinematic CRT aesthetic
- Album art drives the entire color palette when music plays
- Aurora background provides ambient visual interest when idle
