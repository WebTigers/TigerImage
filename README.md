# TigerImage

Image generation for any agent.

Claude cannot draw. ChatGPT can. Without this module, which assistant you happen to be running
silently decides whether the website it builds for you has pictures in it. TigerImage makes image
generation a **Tiger** capability instead of a model capability: a text-only assistant calls it over
`/api` or MCP and gets images back.

Free, BSD-3, first-party.

## How it works

- **Providers** are this module's own adapters, subclassing the core ones so transport, auth headers
  and your existing BYO keys are reused rather than duplicated. They are **registered with core at
  bootstrap** via `Tiger_Agent_Provider_Factory::registerImageAdapter()` — core declares the
  `ImageAdapter` contract and holds the register, and ships no image-generation code itself. Uninstall
  this module and Tiger honestly reports that it cannot draw.
- **Generated images are drafts.** They land in temp storage and are *not* in the Media Library. The
  library stays curated.
- **Promotion** is the moment someone says "keep this" — it creates the Media row, with the prompt as
  the description so the library is searchable by what the image *is*.
- **Everything is remembered**: prompt, negative prompt, provider, model, and the parameters the
  provider *actually used* — the size it snapped to, the seed it picked, the prompt it rewrote.
  Without that echo a refinement cannot reproduce its parent.
- **Lineage** links a refinement to what it came from, so a chain is navigable rather than a pile.

## Installing

Needs **tiger-core 1.5.20+**. 1.5.19 made image adapters a lazy registry; 1.5.20 made module
purge remove `storage/tigerimage/`, so deleting the module actually deletes the images.

App modules are **opt-in**: dropping the files in and running the migration is not enough — the module
stays inert until it has an `active=1` row, because `Resource_Modules` strips inactive modules from the
controller map. Activate it in the Module Manager. (`module:list` showing `[x]` reflects *discovery*,
not activation.)

`storage/tigerimage` must be writable by the **web** user, not just the account that installed it:
`ec2-user:apache`, `2775` with the setgid bit so new files inherit the group.

## Storage

Images live in **`storage/tigerimage/`** — local by default, offsite (S3, GCS, Azure) by config,
through the same `Tiger_Media_Storage` disks the Media Library already uses.

**Not inside the module directory, deliberately.** `Tiger_Module_Installer` updates a module by
renaming its directory to a backup, renaming the new one into place, then deleting that backup.
Anything stored inside the module is destroyed on a routine update, silently. `storage/` is outside
the docroot and outside the swap path — the same place `storage/media` and `storage/backups` live.

| Setting | Default |
|---|---|
| `tigerimage.storage.disk` | *(unset — a local filesystem disk)* |
| `tigerimage.storage.root` | `storage/tigerimage` |
| `tigerimage.retention_days` | `7` (unpromoted images only) |
| `tigerimage.spend.monthly_cap` | *(unset — uncapped)* USD per org per calendar month |
| `tigerimage.spend.enforce` | `hard` (refuse) · `soft` (allow and report) |

> **Deleting the module deletes these images.** Purge removes `storage/tigerimage/` along with the
> module's tables, config rows and files, so the confirmation's "cannot be undone" is literal — every
> generated image goes with it. Images you **promoted** into the Media Library are Media's, not ours,
> and are unaffected. Needs tiger-core **1.5.20+**; on an older core the directory is left behind.

## Spend

Image calls cost orders of magnitude more than text, and the point of this module is to let an **agent**
issue them in a loop. So the cap is checked **before** the provider is contacted — a cap discovered by
going over it is not a cap — and it is **hard by default**, because an agent does not read warnings.

Costs are **estimates**, and say so. Providers do not return a price with an image and their published
rates move. The figures exist to stop a runaway loop and show an operator where the money went, not to
reconcile a bill. An unrecognised model is charged `UNKNOWN_COST`, never zero — otherwise a newly
released model would be the one thing a cap cannot stop.

> **Per-token ceilings are not possible yet.** A scoped MCP token handed to an agent can spend the whole
> org budget, because `identityFromToken()` builds the identity from the *user* and never records which
> credential authenticated. Enforcing a per-token limit needs that in core first.

## Tests

```
../tiger-core/vendor/bin/phpunit -c phpunit.xml
```

Dependencies are not vendored here; the bootstrap resolves them from a sibling `tiger-core` checkout.

## License

BSD-3-Clause. Tiger™ and WebTigers™ are trademarks of WebTigers.
