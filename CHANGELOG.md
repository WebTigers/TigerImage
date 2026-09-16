# Changelog

All notable changes to TigerImage are recorded here. Format loosely follows
[Keep a Changelog](https://keepachangelog.com/); versioning is SemVer with a `-beta` stability suffix
while pre-1.0.

## [1.0.6] — 2026-09-16

### Added

- **A health state instead of a silent "Active" (TIGER-147).** The module now registers an admin nav
  item (it had none — the Studio was reachable only by URL), and that item carries a HEALTH BADGE: an
  attention pill lights up exactly when `Tigerimage_Model_Provider::capability()` reports the module
  cannot draw — no image provider, no key, or the spend cap reached — and clears when it can. The
  Studio it links to already states the reason and the fix. The badge is config-only and fail-soft
  (it renders on every admin page). Opening the Studio while unavailable also writes a
  `tigerimage.unavailable` diagnostic (reason + detail) to the system log, so an operator learns why
  rather than probing. Delivers the module's own documented promise: report unavailable, with the
  reason, up front.

## [1.0.5] — 2026-09-16

### Fixed

- **The agent could never see TigerImage.** `configs/acl.ini` used an invented
  `acl.resources.<Class>.allow.<role>` shape that `Tiger_Acl_Acl` does not read — so NO resource and
  NO rule were registered, `Tigerimage_Service_Image`/`_Library` stayed unknown to `Zend_Acl`, and
  deny-by-default excluded them from the agent's `/api`/MCP tool catalog. The module installed, read
  **Active**, its route resolved — but it exposed zero tools, silently. Rewritten to the correct
  format (`acl.resources.{k}.resource` + `acl.rules.{k}.role/.resource/.permission`) for the Image
  and Library services and the Studio controller, granted to admin + developer. Verified live: the
  module's 11 tools (generate, refine, capability, promote, …) now appear in `tools/list`.
- Added the missing `[staging : production]` / `[testing : production]` / `[development : production]`
  inheritance sections — without them `acl.ini` threw on any non-production env and was skipped whole
  (the "Section 'development' cannot be found" error on dev installs).
- Guard test (`AclFormatTest`): rejects the dead `.allow.` shape, requires every `@api` service to be
  a declared+granted resource, and requires the env sections. This is the same config-format class as
  the routes.ini bug (TIGER-122); now both are held by tests. (TIGER-147)

## [1.0.4] — 2026-09-13

### Fixed
- **`configs/routes.ini` was never read.** It declared bare `routes.*`; Tiger's ingester reads
  `resources.router.routes.*`. The file parsed, matched nothing, and errored nowhere — the studio's URLs
  worked only because they happened to coincide with default `module/controller/action` routing. Fixed,
  and a convention test now fails on the bare shape (TIGER-122).

## [1.0.3] — 2026-09-13

### Changed
- AGENTS.md: Step 0 — grep tiger-core/CAPABILITIES.md before building anything; core probably already has it.

## [1.0.2] — 2026-09-13

### Changed
- **`tigerimage.detail.lineage_note` uses numbered placeholders** (`%1$s`, `%2$s`) so a translator can
  reorder them. 1.0.1 had converted it to sequential `%s` to match `Tiger.t()`; core 1.5.22 fixed
  `Tiger.t()` instead, which was the right end to fix. Requires **tiger-core 1.5.22+**.
- The locale-completeness test now compares placeholders as a set of slot *types* rather than exact
  tokens — so a reordered translation is allowed — and fails any multi-argument string that is not
  numbered.

### Removed
- `tigerimage.budget.remaining` — added in 0.5.0-beta and never rendered; the gauge formats its own
  figures. Six locales were carrying a string nobody used.

## [1.0.1] — 2026-09-13

### Changed
- **The studio's JavaScript now gets its strings from core's `Tiger_View_Helper_I18n`** instead of a
  private copy. 1.0.0 shipped a hand-rolled mechanism — the view JSON-encoding a map into a data
  attribute, the script reading it through its own `t()`. Core already did exactly that, with a shared
  `tiger.i18n.js`, a view-registers / layout-emits seam, and attribute-escaping that survives an
  admin-authored translation override. The duplicate is gone.

- **Page source no longer carries this module's translation keys.** The old version shipped full keys
  (`tigerimage.action.keep`) to every rendered page; core deliberately delivers translated *values*
  under generic aliases, so the `tigerimage.<area>.<type>` taxonomy — including the shape of keys that
  were never shipped — is no longer readable in the HTML. That is the better design and the reason to
  use core's helper rather than a local one.

- Interpolated strings use `%s` rather than numbered `%1$s`, matching `Tiger.t()`. All six locales
  already filled their two placeholders in the same order, so nothing reads differently. A locale that
  genuinely needs to reorder them would need numbered-placeholder support in core first.

## [1.0.0] — 2026-09-12

**1.0** — the module line follows Tiger 1.0.

### Changed
- Version is now `1.0.0` (was `0.5.0-beta`).
- Ships the full **six-locale UI** (en/es/pt/hi/de/fr). A test keeps every locale complete: same keys,
  same placeholders, and no locale that is a wholesale copy of English.

### Added
- `AGENTS.md`, `TRADEMARKS.md`, this changelog, and `docs/en/` admin help — the module boilerplate a
  release version is expected to carry.

## [0.5.0-beta] — 2026-09-12

### Added
- **The budget gauge** — a small bar of the budget still available, beside the studio title. It draws
  the *binding* ceiling, from the same authority a refusal uses, and repaints after every generation and
  after a refusal. Green at and above 40% remaining, shifting to red at 10% and below. An uncapped
  install shows no gauge: there is no ceiling to draw a fraction of, and a full bar would invent one.
- **CI** — PHPUnit on PHP 8.1/8.3/8.4/8.5, the JS suite on node, a manifest check, and a tag-time check
  that the required tiger-core version is actually released.
- `TIGER.md`, the vendor description shown by the Module Installer.

### Fixed
- **The studio's JavaScript never loaded.** The view echoed `asset()` bare; the helper returns a URL,
  not a tag, so the page printed the path as text. The page was inert — no grid, no actions.
- **Not one string was translated.** The module shipped `languages/en.ini`; Tiger reads
  `languages/<lang>/<name>.php`. The glob matched nothing, errored nowhere, and the studio rendered its
  own key names at the user.
- **Two borrowed core keys did not exist** (`core.action.close`, `core.action.cancel`), so both modal
  buttons showed their key names.
- The studio's JavaScript no longer hardcodes English; the spend refusal now names the ceiling that
  actually bound rather than always blaming the organisation.

## [0.4.0-beta] — 2026-09-12

### Added
- **A per-token spend ceiling.** A scoped access key can be capped separately from the organisation, so
  a token handed to an agent cannot spend the whole budget. Both ceilings are checked and the tighter
  one wins; the refusal names which. Requires tiger-core 1.5.21, which put `credential_id` on the
  identity.

## [0.3.0-beta] — 2026-09-11

### Added
- First public release: the studio, provider adapters for OpenAI and Gemini, lineage-aware refinement,
  Media Library promotion, the retention sweep, and an organisation-wide monthly spend cap enforced
  before the provider is contacted.
