# AGENTS.md — TigerImage

> **Step 0 — check core before you build.** Before writing any mechanism, grep
> [`../tiger-core/CAPABILITIES.md`](../tiger-core/CAPABILITIES.md) — a generated, CI-checked index of every `Tiger_*` class and module.
> Core probably already has it. TigerImage once reimplemented `Tiger_View_Helper_I18n` by hand because this
> hop was skipped, and lost a property core had designed in. **Assume a capability exists until you have
> grepped the index and confirmed it doesn't.**

Orientation for AI agents (and humans) working in this repo. Read this first, then
[`README.md`](README.md) — the operator detail. If you change a decision, update the README in the same
change; the "why" is the most perishable part.

## What this is

A **Tiger module** — image generation for any agent. It installs as `application/modules/tigerimage/`
inside a Tiger app and self-registers via the module scan. It adds **no new AI account**: it drives the
provider adapters Tiger already has. See [`TIGER.md`](TIGER.md) for the pitch and
[`module.json`](module.json) for the manifest.

## Invariants — do not break these

- **The spend cap is checked BEFORE the provider is contacted**, on the estimate, and is **hard by
  default**. A cap you discover by going over it is not a cap, and an agent does not read warnings.
  Never relax this to a post-hoc reconciliation.
- **Two ceilings, and the tighter one wins** — the organisation's and the access key's. Both come from
  `Tigerimage_Model_Spend::_bindingOf()`. **One authority.** If a screen ever computes "which ceiling
  binds" for itself, a bar will eventually show headroom above a call that is about to be refused.
- **An unrecognised model costs `UNKNOWN_COST`, never zero.** Otherwise a newly released model is the
  one thing a cap cannot stop.
- **Costs are ESTIMATES and must say so** wherever they are shown. Providers do not return a price with
  an image. Never present these figures as billed.
- **Generated images live in `storage/tigerimage/`** — outside the docroot *and* outside the module
  directory. `Tiger_Module_Installer` updates a module by renaming its directory to a backup and then
  deleting that backup, so anything kept inside the module is destroyed by a routine update.
- **Capability is answerable without spending anything.** An agent must be able to learn it cannot draw
  — or has no budget left — *before* it promises a user a picture.
- **Every mutation goes through `/api`.** The studio drives the same surface an agent does; there is no
  second code path that can drift.

## Platform conventions (Tiger-native)

- **Migrations use timestamp versions** (`YYYYMMDDHHMMSS_*.php`), never `0001` — the `tiger_migration`
  ledger is one shared bare-version namespace across core + app + all modules, so `0001` collides with
  core's and silently no-ops.
- **`Tiger_Model_Table` subclasses must declare `protected $_primary = '<pk>'`** or the UUID mint
  targets the wrong column and the insert throws.
- **A service never builds a `$db` select/where/predicate.** It calls a named model finder that returns
  a Row or Rowset. `$row->save()` and `$rowset->find()` in a service are fine.
- **Translations live in `languages/<lang>/tigerimage.php`**, each RETURNING a `[key => string]` array.
  A flat `languages/*.ini` matches the loader's glob nowhere, errors nowhere, and translates nothing —
  this module shipped that way once. All six locales (en/es/pt/hi/de/fr) must stay complete; a test
  enforces it.
- **No inline `<script>` or `<style>` in a view.** JS goes in `assets/js/` and is loaded with
  `<script src="<?= $this->escape($this->asset('/_modules/tigerimage/js/…')) ?>" defer>`. Note that
  `asset()` returns a **URL, not a tag** — echoing it bare prints the path into the page as text, which
  is how the studio once shipped with JavaScript that never loaded.
- **The JS renders no English.** The view emits a `[key => string]` JSON map in `data-strings`; the
  script reads it through `t()` / `tf()` using FULL keys, so the key sweep reaches the JavaScript too.
- **No browser dialogs.** Confirmation is the in-app modal in the view, never `confirm()`.
- **`small` is for footnotes, not content.**

## The custom resource type

`adapters/` is not one of ZF1's eight module resource types, so `Bootstrap.php` registers it with
`addResourceType('adapter', 'adapters', 'Adapter')` and lazily registers each adapter **by class name**
with `Tiger_Agent_Provider_Factory::registerImageAdapter()`. Lazy and by name on purpose: a module
Bootstrap that throws is fatal during `Resource_Modules` and takes down every page on the site.

Core holds only the *register*. It knows nothing about this module — that is the loose coupling, and a
test asserts core alone reports no image capability.

## Tests

```
../tiger-core/vendor/bin/phpunit -c phpunit.xml    # PHP
node tests/js/gauge.test.js                        # the gauge's colour rule
```

Dependencies are not vendored; the bootstrap resolves them from a sibling `tiger-core` checkout. CI runs
both on PHP 8.1/8.3/8.4/8.5.

**Mutation-test anything load-bearing, and verify the mutation actually applied before believing a
survivor.** Three separate credential-threading bugs in this module's spend cap passed a full green
suite, and the test that would have caught the dead JavaScript was itself the reason the bug was
written. A green suite here has been wrong before.
