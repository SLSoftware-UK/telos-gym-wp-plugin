# TelosGym Schedule

WordPress plugin: embed a TelosGym studio's live class schedule via the `[telos_schedule]` shortcode or the "TelosGym Schedule" block.

`readme.txt` is the canonical listing copy (WordPress.org parses it directly) — this file is only a pointer plus developer build notes.

## How it renders

Both the shortcode and the block call the same PHP function, `Telos_Gym_Schedule_Render::render()` (`includes/class-render.php`), which outputs a `<div data-telos-schedule data-key="..." data-days="...">` and enqueues `public-schedule-widget.js` from the `telos-gym` backend (`TELOS_GYM_API_BASE`, defined in `telos-gym-schedule.php`). The visitor's own browser fetches the schedule directly — this plugin and its WordPress host never see the schedule data.

## Development

```bash
npm install
npm run build          # builds src/ -> build/ via @wordpress/scripts
npx @wordpress/env start   # local WordPress at http://localhost:8888 (admin/password)
npx @wordpress/env stop
```

No PHP/JS automated test suite in v1 — verify manually via `wp-env` (see `docs/superpowers/plans/2026-09-11-telos-gym-wp-plugin.md`, Task 8). **Never run a real plugin delete/uninstall action against this checkout via wp-env** — `.wp-env.json` bind-mounts this repo's own root as the plugin directory, so `wp plugin uninstall` (or wp-admin's "Delete" action) removes it from disk for real. See Task 8, Step 6 for the safe way to verify `uninstall.php`.

PHP syntax checks run via Docker's `php:7.4-cli` image (no PHP CLI installed on the Windows dev machine this plugin was built on):

```bash
docker run --rm -v "$(pwd):/app" -w /app php:7.4-cli php -l telos-gym-schedule.php
```
