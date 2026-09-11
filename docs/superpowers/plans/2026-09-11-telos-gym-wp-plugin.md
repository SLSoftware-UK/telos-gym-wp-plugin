# TelosGym Schedule WordPress Plugin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a WordPress.org-listed plugin (`telos-gym-schedule`) that lets any WordPress site embed a studio's live TelosGym class schedule via a `[telos_schedule]` shortcode and a matching Gutenberg block, both rendering client-side against the existing public schedule API with zero `telos-gym` backend changes.

**Architecture:** Pure client-side consumer, matching the JS widget's security model exactly. The plugin stores one Origin-pinned embed key (`ScheduleEmbedKey`) per site in `wp_options`, and both the shortcode and the block funnel through one shared PHP render function that outputs `<div data-telos-schedule data-key="..." data-days="...">` and enqueues the existing `public-schedule-widget.js` from the `telos-gym` backend. The visitor's own browser then calls `GET /api/public/v1/schedule/` directly — Origin-pinning (`ScheduleEmbedKeyAuthentication`) stays the real access control; nothing server-side on the WordPress host ever holds or forwards the schedule data.

**Tech Stack:** PHP 7.4+ (no framework — plain WordPress Plugin API: Settings API, Shortcode API, Block Editor via `block.json` + `@wordpress/scripts`), vanilla JS for the admin settings UX, `wp-env` (Docker) for manual verification. No PHP or JS automated test runner — matches this project's existing convention for frontend-only/scale-appropriate repos (see `telos-gym`'s `public-schedule-widget.js`, which is also manually verified only).

**Spec:** This plan implements the briefing "WordPress plugin for public class-schedule embed (WordPress.org listed)" (provided directly in conversation, 2026-09-11) plus ground-truth details pulled from the already-merged `telos-gym` code it depends on:
- `backend/public_api/static/public-schedule-widget.js` (embed contract, CSS custom properties, endpoint/header shape)
- `backend/public_api/views.py` (`PublicScheduleView` — days clamp, error envelope, CORS)
- `frontend/src/pages/SettingsPage.jsx` (`ExternalEmbedsCard`, `embedSnippet()` — existing "External embeds" convention this plugin extends)
- `llms.txt` / `ROADMAP.md` public-schedule-API and widget entries

## Incident and reconstruction (2026-09-11)

**This plan document and the entire repo it describes were destroyed once already**, during the first pass at Task 8, Step 6. The implementer ran `npx @wordpress/env run cli wp plugin uninstall telos-gym-wp-plugin --deactivate` to verify `uninstall.php`'s cleanup. WP-CLI's `plugin uninstall` deletes the plugin directory from disk by default (needs `--no-delete` to skip). `.wp-env.json` (Task 1) bind-mounts the entire repo root as the WP plugin directory, so the delete happened directly on the host working tree — `.git` (all commit history, never pushed to any remote), every source file, `node_modules/`, `build/`, `languages/`, and this plan document were all deleted in one shot. Confirmed independently by the controller session afterward (`git status` → not a git repo, directory empty). Nothing had ever been pushed to GitHub, so there was no exposure — only lost local work, since fully recovered by reconstruction from this plan's own verbatim code (every task's independent review had already confirmed byte-for-byte matches between the brief and the implementation, so nothing of substance was actually lost — only git history/timestamps and generated build artifacts, both inherently regenerable).

**Root cause:** not implementer error — the implementer followed Task 8 Step 6's own stated intent ("fully delete/uninstall it ... confirm `uninstall.php` removed it with no orphaned row") using a reasonable, direct method. The landmine was structural: mounting a live git working tree as the exact directory WordPress will genuinely delete during an uninstall test. Task 8's Step 6 below has been rewritten to eliminate this landmine — it no longer performs any real delete-from-disk action against the working tree.

**How reconstruction worked:** the controller session had, in its own conversation context, a full verbatim `Read` of this plan document (taken just before Task 1 was first dispatched) plus a record of the one subsequent edit made to it (adding `settings_errors( self::OPTION_NAME )` to Task 3's `render_page()`, per that task's fix round). Reconstruction replayed both. Every hand-written source file was then recreated directly from this plan's code blocks (already independently verified byte-for-byte by five separate task reviews the first time through) rather than re-dispatching and re-reviewing implementer subagents for already-verified content — the generated artifacts (`build/`, `package-lock.json`, the `.pot` file, screenshots) were regenerated fresh by re-running the same commands (`npm install && npm run build`, `wp i18n make-pot`, new screenshots), which is normal and expected regardless of the incident.

## Global Constraints

- License: GPLv2 or later (WordPress.org hard requirement) — full license text shipped as `LICENSE`.
- `Requires PHP: 7.4`, `Requires at least: 6.5` in the plugin header — **re-verify both against current WordPress.org minimums in Task 8** (the brief flags these move).
- Text domain: `telos-gym-schedule` everywhere (header, `load_plugin_textdomain`, every `__()`/`_e()` call).
- No copy of `public-schedule-widget.js` is ever bundled in this repo — always enqueued from `TELOS_GYM_API_BASE . '/static/public-schedule-widget.js'`.
- No server-side (`wp_remote_get`) fetch of schedule data anywhere in the live embed path — the shortcode/block only ever emit markup + enqueue the widget script; the browser does the real API call itself, matching `ScheduleEmbedKeyAuthentication`'s Origin-pinning model.
- `days` is clamped client-defensively to 1–30 before being written into `data-days`, but the API (`PublicScheduleView.get`, `backend/public_api/views.py:56`) remains the authoritative clamp — don't duplicate its exact logic beyond "don't emit something obviously wrong."
- One embed key per site install, stored in a single `telos_gym_schedule_options` option — no multi-key/multi-studio support in v1.
- `TELOS_GYM_API_BASE` = `https://studioos-production-c31b.up.railway.app` (current `telos-gym` production backend — see `llms.txt` "Stripe — live mode" section; no custom API domain is configured yet).
- Widget embed contract (do not deviate — this is the live, merged contract): `<div data-telos-schedule data-key="KEY" data-days="N"></div>` + `<script src="{TELOS_GYM_API_BASE}/static/public-schedule-widget.js">`. Reskin surface is exactly five CSS custom properties: `--telos-accent`, `--telos-font`, `--telos-bg`, `--telos-text`, `--telos-radius`.
- Every user-facing string wrapped in `__()`/`_e()`; `.pot` generated via `wp i18n make-pot` in Task 6.
- No automated test runner — every task's verification step is either a syntax/lint check run inside `wp-env` (this Windows machine has no local `php` CLI — same class of local-tooling gap already documented for `telos-gym`'s WeasyPrint issue) or a manual behavioural check, called out explicitly per task.
- **Never run a real delete/uninstall action against this repo's own working tree via wp-env** — `.wp-env.json` bind-mounts the repo root live; `wp plugin uninstall` (and wp-admin's plugin "Delete" action, which does the same thing) removes the plugin directory from disk by default, which on this mount means the git repository itself. See "Incident and reconstruction" above and the rewritten Task 8 Step 6.

## Design note — resolving one contradiction in the brief

The Decisions table says key validation is "client-triggered (admin-side, via `fetch` from the settings page's own JS, not PHP)," while §2/the file structure also call for a nonce-protected `wp_ajax_*` PHP endpoint ("thin wrapper"). Implemented as: **the real validation HTTP call to `telos-gym`'s API is always a direct `fetch()` from `admin.js`** (Decision honored — no server-side proxy of the schedule request). The PHP AJAX endpoint (`class-key-validator.php`) does **only one narrow thing**: when the admin clicks "Test connection" without retyping a key (the field shows a masked placeholder, not the real stored value, once saved), `admin.js` needs the real key value to `fetch` with — the AJAX endpoint (nonce + `manage_options`-checked) is what safely hands that stored value back to the page's own JS for that one call. It never itself contacts `telos-gym`. This is what "thin wrapper" means here.

## File Structure

```
telos-gym-wp-plugin/
├── telos-gym-schedule.php          # Task 1 — headers, constants, requires, hooks
├── uninstall.php                   # Task 1 — deletes telos_gym_schedule_options
├── LICENSE                         # Task 1 — GPLv2 full text
├── readme.txt                      # Task 7 — WordPress.org listing
├── README.md                       # Task 7 — GitHub pointer + dev build notes
├── .gitignore                      # Task 1
├── .wp-env.json                    # Task 1 — wp-env config for manual testing
├── package.json                    # Task 5 — @wordpress/scripts build
├── includes/
│   ├── class-render.php            # Task 2 — shared render function
│   ├── class-shortcode.php         # Task 2 — [telos_schedule]
│   ├── class-settings-page.php     # Task 3 — Settings → TelosGym Schedule
│   ├── class-key-validator.php     # Task 4 — nonce-protected AJAX "reveal key for test"
│   └── class-block.php             # Task 5 — Gutenberg block PHP registration
├── assets/
│   ├── admin.css                   # Task 3
│   └── admin.js                    # Task 4 — settings page + test-connection UX
├── src/
│   ├── block.json                  # Task 5 — block metadata (source)
│   └── index.js                    # Task 5 — editor UI (Inspector controls, static preview)
├── build/                          # Task 5 — generated by `npm run build`, committed
└── languages/
    └── telos-gym-schedule.pot      # Task 6
```

---

### Task 1: Plugin scaffold, headers, activation/uninstall, LICENSE

**Files:**
- Create: `telos-gym-schedule.php`
- Create: `uninstall.php`
- Create: `LICENSE`
- Create: `.gitignore`
- Create: `.wp-env.json`
- Create: `README.md` (stub — full dev notes land in Task 7)

**Interfaces:**
- Produces: constants `TELOS_GYM_SCHEDULE_VERSION`, `TELOS_GYM_SCHEDULE_FILE`, `TELOS_GYM_SCHEDULE_DIR`, `TELOS_GYM_SCHEDULE_URL`, `TELOS_GYM_API_BASE` — every later task's PHP file relies on these being defined before it loads.
- Produces: option key `telos_gym_schedule_options` (empty array `[]` as the activation default) — Tasks 2–4 read/write this.

- [x] **Step 1: Write the main plugin file**

```php
<?php
/**
 * Plugin Name:       TelosGym Schedule
 * Plugin URI:        https://telosgym.com
 * Description:       Display your TelosGym class schedule on your WordPress site with a shortcode or block.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            TelosGym
 * Author URI:        https://telosgym.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       telos-gym-schedule
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TELOS_GYM_SCHEDULE_VERSION', '1.0.0' );
define( 'TELOS_GYM_SCHEDULE_FILE', __FILE__ );
define( 'TELOS_GYM_SCHEDULE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TELOS_GYM_SCHEDULE_URL', plugin_dir_url( __FILE__ ) );
// telos-gym's production backend. No custom API domain is configured yet —
// see that repo's llms.txt "Stripe — live mode" section for the current host.
define( 'TELOS_GYM_API_BASE', 'https://studioos-production-c31b.up.railway.app' );

register_activation_hook( __FILE__, 'telos_gym_schedule_activate' );

function telos_gym_schedule_activate() {
	if ( false === get_option( 'telos_gym_schedule_options' ) ) {
		add_option( 'telos_gym_schedule_options', array() );
	}
}

add_action( 'plugins_loaded', 'telos_gym_schedule_load_textdomain' );

function telos_gym_schedule_load_textdomain() {
	load_plugin_textdomain( 'telos-gym-schedule', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

require_once TELOS_GYM_SCHEDULE_DIR . 'includes/class-render.php';
require_once TELOS_GYM_SCHEDULE_DIR . 'includes/class-shortcode.php';
require_once TELOS_GYM_SCHEDULE_DIR . 'includes/class-settings-page.php';
require_once TELOS_GYM_SCHEDULE_DIR . 'includes/class-key-validator.php';
require_once TELOS_GYM_SCHEDULE_DIR . 'includes/class-block.php';

Telos_Gym_Schedule_Shortcode::init();
Telos_Gym_Schedule_Settings_Page::init();
Telos_Gym_Schedule_Key_Validator::init();
Telos_Gym_Schedule_Block::init();
```

Note (reconstruction): the first time through, this file was built incrementally (Task 1 left the requires/inits as a placeholder comment, and Tasks 2–5 each added their own require_once/init line as they created their class file — see the original per-task Steps below, preserved for reference). Since reconstruction recreates every file from already-verified content in one pass, there is no incremental-activation hazard this time — all five requires/inits are written directly, matching the fully-wired state Task 5 originally left behind.

- [x] **Step 2: Write `uninstall.php`**

```php
<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'telos_gym_schedule_options' );
```

- [x] **Step 3: Add the GPLv2-or-later license text**

Fetch the canonical text and save it verbatim:

```bash
curl -s https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt -o LICENSE
```

- [x] **Step 4: Write `.gitignore`**

```gitignore
node_modules/
.wp-env-cache/
.DS_Store
```

Note: `build/` is intentionally **not** ignored — WordPress.org's SVN mirror serves this repo as-is with no build step, so the compiled block JS/CSS from Task 5 must be committed.

- [x] **Step 5: Add `.wp-env.json` for local manual testing (Docker-based, used from Task 8 onward)**

```json
{
	"core": null,
	"phpVersion": "7.4",
	"plugins": [ "." ],
	"config": {
		"WP_DEBUG": true
	}
}
```

- [x] **Step 6: Write a stub `README.md`**

(Superseded by Task 7's full rewrite — see Task 7.)

- [x] **Step 7: Stage and verify no PHP file has a fatal syntax error, using the Docker `php:7.4-cli` image (no local PHP CLI on this Windows machine)**

Run: `docker run --rm -v "$(pwd):/app" -w /app php:7.4-cli php -l telos-gym-schedule.php && docker run --rm -v "$(pwd):/app" -w /app php:7.4-cli php -l uninstall.php`
Expected: `No syntax errors detected` for both files.

- [x] **Step 8: Commit**

```bash
git add telos-gym-schedule.php uninstall.php LICENSE .gitignore .wp-env.json README.md docs/
git commit -m "feat: scaffold TelosGym Schedule plugin"
```

---

### Task 2: Shared render function + shortcode

**Files:**
- Create: `includes/class-render.php`
- Create: `includes/class-shortcode.php`

**Interfaces:**
- Consumes: `TELOS_GYM_SCHEDULE_DIR`/`TELOS_GYM_API_BASE` constants (Task 1). Reads option `telos_gym_schedule_options` (keys `embed_key`, `accent_color`, `font_stack` — written by Task 3, but must degrade gracefully before Task 3 exists, since this task is implemented first).
- Produces: `Telos_Gym_Schedule_Render::render( $days = 14, $accent_override = '' ): string` — Task 5 (block) calls this too, so this is the one place embed markup is built.
- Produces: shortcode `[telos_schedule days="14"]`.

- [x] **Step 1: Write `includes/class-render.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Telos_Gym_Schedule_Render {

	/**
	 * Builds the <div data-telos-schedule> embed markup and enqueues the
	 * widget script. Shared by the shortcode and the block so there is
	 * exactly one place that builds this markup.
	 */
	public static function render( $days = 14, $accent_override = '' ) {
		$options = get_option( 'telos_gym_schedule_options', array() );
		$key     = isset( $options['embed_key'] ) ? $options['embed_key'] : '';

		if ( empty( $key ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p>' . esc_html__( 'TelosGym Schedule: no embed key configured yet. Add one under Settings → TelosGym Schedule.', 'telos-gym-schedule' ) . '</p>';
			}
			return '';
		}

		wp_enqueue_script(
			'telos-gym-schedule-widget',
			trailingslashit( TELOS_GYM_API_BASE ) . 'static/public-schedule-widget.js',
			array(),
			null,
			true
		);

		$days = absint( $days );
		if ( $days < 1 || $days > 30 ) {
			$days = 14;
		}

		$style  = '';
		$accent = sanitize_hex_color( $accent_override ? $accent_override : ( $options['accent_color'] ?? '' ) );
		if ( $accent ) {
			$style .= '--telos-accent:' . $accent . ';';
		}
		if ( ! empty( $options['font_stack'] ) && 'theme' !== $options['font_stack'] ) {
			$style .= '--telos-font:' . self::font_stack_value( $options['font_stack'] ) . ';';
		}

		return sprintf(
			'<div data-telos-schedule data-key="%1$s" data-days="%2$d"%3$s></div>',
			esc_attr( $key ),
			$days,
			$style ? ' style="' . esc_attr( $style ) . '"' : ''
		);
	}

	/**
	 * 'theme' omits the --telos-font override entirely so the widget's
	 * built-in default inherits page CSS. 'system' is the only other
	 * choice in v1 (Settings page dropdown) and writes an explicit stack.
	 */
	public static function font_stack_value( $choice ) {
		if ( 'system' === $choice ) {
			return '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif';
		}
		return '';
	}
}
```

- [x] **Step 2: Write `includes/class-shortcode.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Telos_Gym_Schedule_Shortcode {

	public static function init() {
		add_shortcode( 'telos_schedule', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array( 'days' => 14 ),
			$atts,
			'telos_schedule'
		);

		return Telos_Gym_Schedule_Render::render( $atts['days'] );
	}
}
```

- [x] **Step 3: Syntax-check and commit**

```bash
docker run --rm -v "$(pwd):/app" -w /app php:7.4-cli php -l includes/class-render.php
docker run --rm -v "$(pwd):/app" -w /app php:7.4-cli php -l includes/class-shortcode.php
git add includes/class-render.php includes/class-shortcode.php
git commit -m "feat: add shared render function and [telos_schedule] shortcode"
```

---

### Task 3: Settings page (`Settings → TelosGym Schedule`)

**Files:**
- Create: `includes/class-settings-page.php`
- Create: `assets/admin.css`

**Interfaces:**
- Consumes: nothing new from earlier tasks beyond the plugin constants.
- Produces: option `telos_gym_schedule_options = ['embed_key' => string, 'accent_color' => string, 'font_stack' => 'theme'|'system']`, which `Telos_Gym_Schedule_Render::render()` (Task 2) already reads.
- Produces: page hook suffix `settings_page_telos-gym-schedule`, which Task 4's `admin_enqueue_scripts` check depends on.

- [x] **Step 1: Write `includes/class-settings-page.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Telos_Gym_Schedule_Settings_Page {

	const OPTION_NAME = 'telos_gym_schedule_options';
	const PAGE_SLUG    = 'telos-gym-schedule';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	public static function add_menu() {
		add_options_page(
			__( 'TelosGym Schedule', 'telos-gym-schedule' ),
			__( 'TelosGym Schedule', 'telos-gym-schedule' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting( self::PAGE_SLUG, self::OPTION_NAME, array(
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => array(),
		) );
	}

	/**
	 * The embed-key field shows a masked placeholder once a key is saved
	 * (matches the "shown once, masked after" convention on telos-gym's own
	 * Settings → External embeds card) and is submitted blank when the
	 * admin hasn't retyped it — so a blank submission here means "keep the
	 * existing key," not "clear it."
	 */
	public static function sanitize( $input ) {
		$existing = get_option( self::OPTION_NAME, array() );
		$output   = array();

		$new_key = isset( $input['embed_key'] ) ? trim( $input['embed_key'] ) : '';
		if ( '' !== $new_key ) {
			$output['embed_key'] = preg_match( '/^[A-Za-z0-9_-]{20,64}$/', $new_key )
				? $new_key
				: '';
			if ( '' === $output['embed_key'] ) {
				add_settings_error( self::OPTION_NAME, 'invalid_key', __( 'That doesn\'t look like a valid TelosGym embed key — it was not saved.', 'telos-gym-schedule' ) );
				$output['embed_key'] = $existing['embed_key'] ?? '';
			}
		} else {
			$output['embed_key'] = $existing['embed_key'] ?? '';
		}

		$output['accent_color'] = ! empty( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '';
		$output['font_stack']   = ( isset( $input['font_stack'] ) && 'system' === $input['font_stack'] ) ? 'system' : 'theme';

		return $output;
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'telos-gym-schedule-admin', TELOS_GYM_SCHEDULE_URL . 'assets/admin.css', array(), TELOS_GYM_SCHEDULE_VERSION );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'telos-gym-schedule-admin', TELOS_GYM_SCHEDULE_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), TELOS_GYM_SCHEDULE_VERSION, true );
		wp_localize_script( 'telos-gym-schedule-admin', 'telosGymScheduleAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'telos_gym_schedule_admin' ),
			'apiBase' => TELOS_GYM_API_BASE,
			'strings' => array(
				'testing'  => __( 'Testing…', 'telos-gym-schedule' ),
				'ok'       => __( 'Connected', 'telos-gym-schedule' ),
				'fail'     => __( 'Invalid key or origin mismatch', 'telos-gym-schedule' ),
				'noKey'    => __( 'Enter a key first', 'telos-gym-schedule' ),
			),
		) );
	}

	public static function render_page() {
		$options    = get_option( self::OPTION_NAME, array() );
		$has_key    = ! empty( $options['embed_key'] );
		$accent     = $options['accent_color'] ?? '';
		$font_stack = $options['font_stack'] ?? 'theme';
		?>
		<div class="wrap telos-gym-schedule-settings">
			<h1><?php esc_html_e( 'TelosGym Schedule', 'telos-gym-schedule' ); ?></h1>
			<?php settings_errors( self::OPTION_NAME ); ?>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE_SLUG ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="telos_gym_schedule_embed_key"><?php esc_html_e( 'Embed key', 'telos-gym-schedule' ); ?></label></th>
						<td>
							<input
								type="password"
								id="telos_gym_schedule_embed_key"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[embed_key]"
								class="regular-text"
								autocomplete="off"
								placeholder="<?php echo $has_key ? esc_attr__( '•••••••• (saved — leave blank to keep)', 'telos-gym-schedule' ) : esc_attr__( 'Paste the key from TelosGym Settings → External embeds', 'telos-gym-schedule' ); ?>"
							/>
							<p class="submit-inline">
								<button type="button" class="button" id="telos-gym-schedule-test-connection"><?php esc_html_e( 'Test connection', 'telos-gym-schedule' ); ?></button>
								<span id="telos-gym-schedule-test-result" class="telos-gym-schedule-test-result" aria-live="polite"></span>
							</p>
							<p class="description">
								<?php esc_html_e( 'Get this from your TelosGym staff dashboard: Settings → External embeds → Create key. Use this site\'s URL as the allowed origin.', 'telos-gym-schedule' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="telos_gym_schedule_accent_color"><?php esc_html_e( 'Accent colour', 'telos-gym-schedule' ); ?></label></th>
						<td>
							<input
								type="text"
								id="telos_gym_schedule_accent_color"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[accent_color]"
								class="telos-gym-schedule-color-picker"
								value="<?php echo esc_attr( $accent ); ?>"
								data-default-color="#2563eb"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="telos_gym_schedule_font_stack"><?php esc_html_e( 'Font', 'telos-gym-schedule' ); ?></label></th>
						<td>
							<select id="telos_gym_schedule_font_stack" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[font_stack]">
								<option value="theme" <?php selected( $font_stack, 'theme' ); ?>><?php esc_html_e( 'Match theme', 'telos-gym-schedule' ); ?></option>
								<option value="system" <?php selected( $font_stack, 'system' ); ?>><?php esc_html_e( 'System font', 'telos-gym-schedule' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'How to show your schedule', 'telos-gym-schedule' ); ?></h2>
			<p>
				<?php esc_html_e( 'Classic editor or page builder — paste this shortcode:', 'telos-gym-schedule' ); ?>
				<code>[telos_schedule]</code>
			</p>
			<p>
				<?php esc_html_e( 'Block editor — search the block inserter for "TelosGym Schedule".', 'telos-gym-schedule' ); ?>
			</p>
		</div>
		<?php
	}
}
```

Note (reconstruction): the `settings_errors( self::OPTION_NAME )` line above was added by a Task 3 fix round the first time through (review found `render_page()` never called it, so `sanitize()`'s `add_settings_error()` on an invalid key — and WP's own generic "Settings saved." notice — were both silently suppressed). It's included directly here since reconstruction starts from the already-fixed version.

- [x] **Step 2: Write `assets/admin.css`**

```css
.telos-gym-schedule-settings .submit-inline {
	margin: 6px 0 4px;
}

.telos-gym-schedule-test-result {
	margin-left: 8px;
	font-weight: 600;
}

.telos-gym-schedule-test-result.is-ok {
	color: #1a7f37;
}

.telos-gym-schedule-test-result.is-fail {
	color: #d63638;
}
```

- [x] **Step 3: Syntax-check and commit**

```bash
docker run --rm -v "$(pwd):/app" -w /app php:7.4-cli php -l includes/class-settings-page.php
git add includes/class-settings-page.php assets/admin.css
git commit -m "feat: add TelosGym Schedule settings page"
```

---

### Task 4: Key validator AJAX endpoint + test-connection UX

**Files:**
- Create: `includes/class-key-validator.php`
- Create: `assets/admin.js`

**Interfaces:**
- Consumes: `telosGymScheduleAdmin` localized object from Task 3 (`ajaxUrl`, `nonce`, `apiBase`, `strings`), the `#telos_gym_schedule_embed_key` / `#telos-gym-schedule-test-connection` / `#telos-gym-schedule-test-result` element IDs from Task 3's markup.
- Produces: `wp_ajax_telos_gym_schedule_get_key_for_test` AJAX action returning `{ key: string }` — used only by `admin.js`, nothing else depends on it.

- [x] **Step 1: Write `includes/class-key-validator.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin AJAX wrapper — NOT a proxy for the actual validation request (that
 * request is a direct fetch() from admin.js straight to telos-gym's public
 * API, same as the live embed). This endpoint only exists so admin.js can
 * ask for the *currently saved* key when the settings-page field is
 * showing its masked placeholder rather than a freshly typed value.
 */
class Telos_Gym_Schedule_Key_Validator {

	public static function init() {
		add_action( 'wp_ajax_telos_gym_schedule_get_key_for_test', array( __CLASS__, 'get_key_for_test' ) );
	}

	public static function get_key_for_test() {
		check_ajax_referer( 'telos_gym_schedule_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'telos-gym-schedule' ) ), 403 );
		}

		$options = get_option( 'telos_gym_schedule_options', array() );
		$key     = $options['embed_key'] ?? '';

		if ( empty( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'No key saved yet.', 'telos-gym-schedule' ) ), 404 );
		}

		wp_send_json_success( array( 'key' => $key ) );
	}
}
```

- [x] **Step 2: Write `assets/admin.js`**

```js
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var button = document.getElementById( 'telos-gym-schedule-test-connection' );
		var result = document.getElementById( 'telos-gym-schedule-test-result' );
		var field = document.getElementById( 'telos_gym_schedule_embed_key' );
		if ( ! button || ! result || ! field || typeof telosGymScheduleAdmin === 'undefined' ) {
			return;
		}

		function setResult( text, cls ) {
			result.textContent = text;
			result.className = 'telos-gym-schedule-test-result' + ( cls ? ' ' + cls : '' );
		}

		function testKey( key ) {
			var url = telosGymScheduleAdmin.apiBase.replace( /\/$/, '' ) + '/api/public/v1/schedule/?days=1';
			fetch( url, { headers: { 'X-Telos-Embed-Key': key } } )
				.then( function ( response ) {
					if ( response.ok ) {
						setResult( telosGymScheduleAdmin.strings.ok, 'is-ok' );
					} else {
						setResult( telosGymScheduleAdmin.strings.fail, 'is-fail' );
					}
				} )
				.catch( function () {
					setResult( telosGymScheduleAdmin.strings.fail, 'is-fail' );
				} );
		}

		button.addEventListener( 'click', function () {
			var typed = field.value.trim();
			setResult( telosGymScheduleAdmin.strings.testing, '' );

			if ( typed ) {
				testKey( typed );
				return;
			}

			var body = new URLSearchParams();
			body.set( 'action', 'telos_gym_schedule_get_key_for_test' );
			body.set( 'nonce', telosGymScheduleAdmin.nonce );

			fetch( telosGymScheduleAdmin.ajaxUrl, { method: 'POST', body: body } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( json ) {
					if ( json.success && json.data && json.data.key ) {
						testKey( json.data.key );
					} else {
						setResult( telosGymScheduleAdmin.strings.noKey, 'is-fail' );
					}
				} )
				.catch( function () {
					setResult( telosGymScheduleAdmin.strings.fail, 'is-fail' );
				} );
		} );

		if ( document.querySelector( '.telos-gym-schedule-color-picker' ) && window.jQuery && window.jQuery.fn.wpColorPicker ) {
			window.jQuery( '.telos-gym-schedule-color-picker' ).wpColorPicker();
		}
	} );
} )();
```

- [x] **Step 3: Syntax-check and commit**

```bash
docker run --rm -v "$(pwd):/app" -w /app php:7.4-cli php -l includes/class-key-validator.php
git add includes/class-key-validator.php assets/admin.js
git commit -m "feat: add key-validation test-connection UX"
```

---

### Task 5: Gutenberg block

**Files:**
- Create: `includes/class-block.php`
- Create: `src/block.json`
- Create: `src/index.js`
- Create: `package.json`

**Interfaces:**
- Consumes: `Telos_Gym_Schedule_Render::render( $days, $accent )` (Task 2).
- Produces: block name `telos-gym-schedule/schedule`, attributes `days` (number, default 14) and `accentColor` (string, default `''`) — fixed names, don't rename without updating both `src/index.js` and `includes/class-block.php`.

- [x] **Step 1: Write `package.json`**

```json
{
	"name": "telos-gym-schedule",
	"version": "1.0.0",
	"private": true,
	"scripts": {
		"build": "wp-scripts build",
		"start": "wp-scripts start"
	},
	"devDependencies": {
		"@wordpress/scripts": "^30.0.0"
	}
}
```

- [x] **Step 2: Write `src/block.json`**

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "telos-gym-schedule/schedule",
	"title": "TelosGym Schedule",
	"category": "widgets",
	"icon": "calendar-alt",
	"description": "Display your TelosGym class schedule.",
	"keywords": [ "schedule", "gym", "fitness", "classes" ],
	"version": "1.0.0",
	"textdomain": "telos-gym-schedule",
	"attributes": {
		"days": { "type": "number", "default": 14 },
		"accentColor": { "type": "string", "default": "" }
	},
	"supports": { "html": false },
	"editorScript": "file:./index.js",
	"editorStyle": "file:./index.css"
}
```

- [x] **Step 3: Write `src/index.js`**

```js
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import './editor.scss';

registerBlockType( metadata.name, {
	edit: function Edit( { attributes, setAttributes } ) {
		var days = attributes.days;
		var accentColor = attributes.accentColor;
		var blockProps = useBlockProps();

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Schedule settings', 'telos-gym-schedule' ) }>
						<RangeControl
							label={ __( 'Days to show', 'telos-gym-schedule' ) }
							value={ days }
							onChange={ function ( value ) { setAttributes( { days: value } ); } }
							min={ 1 }
							max={ 30 }
						/>
						<TextControl
							label={ __( 'Accent colour override', 'telos-gym-schedule' ) }
							help={ __( 'Leave blank to use the plugin-wide colour from Settings.', 'telos-gym-schedule' ) }
							value={ accentColor }
							onChange={ function ( value ) { setAttributes( { accentColor: value } ); } }
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					<div className="telos-gym-schedule-placeholder">
						<strong>{ __( 'TelosGym Schedule', 'telos-gym-schedule' ) }</strong>
						<p>{ __( 'Live schedule will appear here.', 'telos-gym-schedule' ) }</p>
					</div>
				</div>
			</>
		);
	},
	save: function () {
		return null;
	},
} );
```

- [x] **Step 4: Write `src/editor.scss`**

```scss
.telos-gym-schedule-placeholder {
	border: 1px dashed #949494;
	border-radius: 4px;
	padding: 16px;
	text-align: center;
	color: #757575;

	strong {
		display: block;
		margin-bottom: 4px;
		color: #1e1e1e;
	}
}
```

- [x] **Step 5: Write `includes/class-block.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Telos_Gym_Schedule_Block {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		$build_dir = TELOS_GYM_SCHEDULE_DIR . 'build';
		if ( ! file_exists( $build_dir . '/block.json' ) ) {
			return;
		}
		register_block_type( $build_dir, array(
			'render_callback' => array( __CLASS__, 'render' ),
		) );
	}

	public static function render( $attributes ) {
		$days   = isset( $attributes['days'] ) ? $attributes['days'] : 14;
		$accent = isset( $attributes['accentColor'] ) ? $attributes['accentColor'] : '';
		return Telos_Gym_Schedule_Render::render( $days, $accent );
	}
}
```

- [x] **Step 6: Install dependencies, build, syntax-check, commit**

```bash
npm install && npm run build
docker run --rm -v "$(pwd):/app" -w /app php:7.4-cli php -l includes/class-block.php
git add package.json package-lock.json src/ build/ includes/class-block.php
git commit -m "feat: add Gutenberg block for TelosGym Schedule"
```

Expected: `build/index.js`, `build/index.css`, `build/block.json`, `build/index.asset.php` created with no errors.

---

### Task 6: Internationalization

**Files:**
- Create: `languages/telos-gym-schedule.pot`

**Interfaces:**
- Consumes: every `__()`/`_e()` call across the repo.
- Produces: `languages/telos-gym-schedule.pot`.

- [x] **Step 1: Generate the `.pot` file** (wp-env running)

```bash
npx @wordpress/env run cli wp i18n make-pot . languages/telos-gym-schedule.pot --domain=telos-gym-schedule
```

(Confirm the plugin's mapped path inside the container first with `npx @wordpress/env run cli wp plugin list --path=/var/www/html` if the relative form above doesn't resolve.)

- [x] **Step 2: Spot-check and commit**

Confirm the `.pot` file contains entries for `"Test connection"`, `"Embed key"`, and `"Days to show"`, then:

```bash
git add languages/telos-gym-schedule.pot
git commit -m "chore: generate .pot for translations"
```

---

### Task 7: `readme.txt` (WordPress.org listing) + `README.md`

**Files:**
- Create: `readme.txt`
- Modify: `README.md`

**Interfaces:**
- Consumes: nothing — pure documentation, but its `Stable tag` must match `TELOS_GYM_SCHEDULE_VERSION`/the plugin header `Version` (currently `1.0.0`).

- [x] **Step 1: Write `readme.txt`**

```text
=== TelosGym Schedule ===
Contributors: telosgym
Tags: schedule, gym, fitness, classes, timetable
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your TelosGym class schedule on your WordPress site with a shortcode or block.

== Description ==

TelosGym Schedule shows your studio's live, up-to-date class timetable directly on your WordPress site — no iframe, no manual updates.

[TelosGym](https://telosgym.com) is class-scheduling and studio-management software for gyms, studios, and independent trainers. If you already run classes through TelosGym, this plugin displays that same live schedule on your own WordPress site.

= Features =

* `[telos_schedule]` shortcode for the classic editor or any page builder
* A native block for the block editor, with day-count and accent-colour controls
* Reads your schedule live, straight from your browser to TelosGym — nothing is cached or proxied through your WordPress server
* Simple colour and font settings to match your site
* Free — this plugin only displays your schedule; booking happens on your TelosGym-hosted booking page (link-out only, no payments here)

= How it works =

Each embed shows a "Book" link per class that sends visitors to your studio's TelosGym booking page — this plugin itself never handles bookings or payments.

== Installation ==

1. Install and activate the plugin.
2. In your TelosGym staff dashboard, go to Settings → External embeds, and create a new embed key using this WordPress site's URL as the allowed origin.
3. In WordPress, go to Settings → TelosGym Schedule and paste that key in. Click "Test connection" to confirm it works.
4. Add the `[telos_schedule]` shortcode to any page, or add the "TelosGym Schedule" block from the block inserter.

== Frequently Asked Questions ==

= What is TelosGym? =

TelosGym is class-scheduling and studio-management software. This plugin is a display-only integration for studios that already use it.

= Where do I get an embed key? =

From your TelosGym staff dashboard: Settings → External embeds → Create key, using your WordPress site's URL as the allowed origin.

= Can visitors book a class directly on my WordPress site? =

No — each class links out to your TelosGym-hosted booking page. This plugin is display/discovery only.

= Why does the schedule need my site's exact URL? =

Your embed key only works from the exact origin (domain) you registered it for. This stops anyone else from using your key on a different site.

= Does this work with multisite? =

Not in this version — each site needs its own activation and its own embed key.

== Screenshots ==

1. Settings page — enter your embed key and test the connection.
2. The shortcode rendering a live schedule on the front end.
3. The block editor with the TelosGym Schedule block's Inspector controls.

== Changelog ==

= 1.0.0 =
* Initial release: shortcode, Gutenberg block, settings page with key validation, colour/font options.
```

Note (reconstruction): `Tested up to` reads `7.1` (not the `6.7` originally written in Task 7) — this reflects Task 8 Step 1's re-verification against WordPress core's actual current version at the time of testing, carried forward directly into the reconstruction rather than being reset to the pre-Task-8 value.

- [x] **Step 2: Rewrite `README.md`**

```markdown
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
```

- [x] **Step 3: Validate and commit**

Run the file's contents through the WordPress.org readme validator: https://wordpress.org/plugins/developers/readme-validator/ (or the manual checklist: required header fields in order, short description ≤150 chars, exact section headers).

```bash
git add readme.txt README.md
git commit -m "docs: add WordPress.org readme.txt and dev README"
```

---

### Task 8: Manual test pass, screenshots, and submission

**Files:**
- Create: `.wordpress-org/screenshot-1.png`, `.wordpress-org/screenshot-2.png`, `.wordpress-org/screenshot-3.png` (WordPress.org's SVN `assets/` convention — these live outside the plugin's own trunk folder)
- Modify: `readme.txt` (bump `Requires at least`/`Tested up to` if Step 1 finds they've moved)

**Interfaces:**
- Consumes: the entire plugin — this task is the final acceptance gate before pushing/submitting.

- [ ] **Step 1: Re-verify WordPress.org's current plugin minimums**

Check the current default "Requires at least"/"Tested up to" WordPress core version and PHP floor. Update `readme.txt`/`telos-gym-schedule.php` if they've drifted further since the last check (`Tested up to: 7.1`, confirmed against `wp core version` in wp-env).

- [ ] **Step 2: Fresh install + full config flow**

Run: `npx @wordpress/env destroy` then `npx @wordpress/env start` (clean slate), install/activate the plugin, go to Settings → TelosGym Schedule, paste a real (or syntactically-valid test) embed key + click "Test connection."
Expected: real network reach to `TELOS_GYM_API_BASE` confirmed either way; green "Connected" only achievable with a real key.

- [ ] **Step 3: Invalid/revoked/origin-mismatch cases**

Test what's achievable in this environment: (a) a garbage string as the key → red "Invalid key or origin mismatch"; (b)/(c) a real key with a mismatched origin, and a revoked real key, both require a real TelosGym studio account — verify by code inspection if no such account is available (document which).

- [ ] **Step 4: Shortcode + block on the front end**

Add `[telos_schedule days="7"]` to a page and the TelosGym Schedule block (default days) to a separate page. View both logged out.
Expected: both render the same embed markup shape, matching the existing widget's contract exactly.

- [ ] **Step 5: Confirm the widget script is enqueued only when present**

View source (or Network tab) on a third page that contains **neither** the shortcode nor the block.
Expected: `public-schedule-widget.js` is **not** requested on that page.

- [ ] **Step 6: Deactivate/reactivate/uninstall cleanup — SAFE METHOD ONLY**

**Do not run a real `wp plugin uninstall` (WP-CLI) or wp-admin's plugin "Delete" action against this repo's working tree under any circumstances** — `.wp-env.json` bind-mounts the repo root live, and both of those actions delete the plugin directory from disk by default, which on this mount means deleting the actual git repository. (This destroyed the entire repo once already during this plan's first pass — see "Incident and reconstruction" at the top of this document.)

Verify deactivate/reactivate normally (these do not delete files):
- **Deactivate** (`wp plugin deactivate telos-gym-schedule`): confirm the shortcode/block stop rendering and the settings page 404s/redirects, while `wp option get telos_gym_schedule_options` still returns the saved value.
- **Reactivate** (`wp plugin activate telos-gym-schedule`): confirm the option is unchanged and rendering resumes immediately.

Verify the uninstall *logic* without deleting anything, using ONE of these safe methods instead of a real uninstall:
- **(a) Code inspection** — `uninstall.php` is one line (`delete_option( 'telos_gym_schedule_options' )`, gated by the standard `WP_UNINSTALL_PLUGIN` check); confirm by reading it that this is correct and sufficient. This alone satisfies the requirement if a live test isn't worth the risk.
- **(b) Live test against a disposable copy** — if a live test is wanted, `cp -r` this repo into a throwaway directory OUTSIDE this git working tree, point a temporary `.wp-env.json`'s `plugins` entry at that throwaway copy instead, run the real uninstall test there, then delete the throwaway copy and the temporary wp-env config when done. Never point wp-env's `plugins` array at this repo's own root for an uninstall test.
- **(c) `wp eval`** — if wp-env is already running against the real plugin (deactivated, not uninstalled), verify the option deletion behavior directly: `wp eval "delete_option('telos_gym_schedule_options'); var_dump(get_option('telos_gym_schedule_options'));"` — this exercises the exact same core WordPress call `uninstall.php` makes, with zero filesystem risk, and re-running Task 1's activation hook (deactivate/reactivate) restores the option afterward for further testing.

Expected: option cleanup confirmed correct via (a), (b), or (c) — record which method was used.

- [ ] **Step 7: Take and save screenshots**

Capture: (1) settings page with a key saved, (2) a front-end page rendering the shortcode, (3) the block editor with the block inserted and Inspector panel open. Save as `.wordpress-org/screenshot-1.png`, `-2.png`, `-3.png`.

- [ ] **Step 8: Final readme.txt validation**

Re-run the file through the WordPress.org readme validator now that screenshots exist and any Step 1 version bumps are in.

- [ ] **Step 9: Push to GitHub**

**Stop and get explicit confirmation before this step** — creating a new GitHub repo and pushing to it is a publish action outside this worktree.

```bash
gh repo create SLSoftware-UK/telos-gym-wp-plugin --private --source=. --remote=origin
git push -u origin main
```

- [ ] **Step 10: Commit screenshots and submit**

**Stop and get explicit confirmation before actually submitting to WordPress.org** — this is a public, externally-visible action outside TelosGym's control once done.

```bash
git add .wordpress-org/ readme.txt
git commit -m "docs: add store screenshots, finalize readme.txt"
git push origin main
```

Then submit the plugin at https://wordpress.org/plugins/developers/add/. Note in the handoff to Ed that WordPress.org review turnaround is typically days-to-weeks and out of TelosGym's control.
