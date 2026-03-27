# kylekrukar.com

Personal portfolio site for Kyle Krukar — Founder & Maker, Denver CO.

## Features

- **Live Spotify integration** — displays currently playing track with album art background, dynamic text colors extracted from artwork with WCAG AA contrast compliance
- **Aurora animated background** — repeating gradient light-ray effect (blue/indigo/violet) shown when no music is playing
- **CRT/TV aesthetic** — SVG fractal noise grain, animated scanlines, radial vignette overlay
- **Responsive** — adapts from desktop to mobile with four breakpoints

## Stack

- Vanilla HTML / CSS / JS (no build step)
- AWS S3 + CloudFront (hosting & CDN)
- AWS Lambda + API Gateway (Spotify API proxy)
- GitHub Actions (CI/CD)

## Development

```bash
# Local dev with Spotify proxy
php -S localhost:3000 router.php

# Deploy to dev
bash deploy-dev.sh

# Promote dev to production
bash deploy-prod.sh
```

## Links

- **Production:** https://kylekrukar.com
- **Dev:** https://dev.kylekrukar.com
