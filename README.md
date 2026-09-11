# TigerImage

Image generation for any agent.

Claude cannot draw. ChatGPT can. Without this module, which assistant you happen to be running
silently decides whether the website it builds for you has pictures in it. TigerImage makes image
generation a **Tiger** capability instead of a model capability: a text-only assistant calls it over
`/api` or MCP and gets images back.

Free, BSD-3, first-party.

## How it works

- **Providers** come from the Tiger agent adapters, so your existing BYO keys work unchanged.
  `Tiger_Agent_Provider_ImageAdapter` is implemented by adapters that can draw (OpenAI, Gemini today).
- **Generated images are drafts.** They land in temp storage and are *not* in the Media Library. The
  library stays curated.
- **Promotion** is the moment someone says "keep this" — it creates the Media row, with the prompt as
  the description so the library is searchable by what the image *is*.
- **Everything is remembered**: prompt, negative prompt, provider, model, and the parameters the
  provider *actually used* — the size it snapped to, the seed it picked, the prompt it rewrote.
  Without that echo a refinement cannot reproduce its parent.
- **Lineage** links a refinement to what it came from, so a chain is navigable rather than a pile.

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

> **Removing the module does not yet remove this directory.** Module purge currently reaches only the
> module's own folder, tables and config rows — see TIGER-101, which makes "remove everything, cannot
> be undone" true for module-owned storage.

## Tests

```
../tiger-core/vendor/bin/phpunit -c phpunit.xml
```

Dependencies are not vendored here; the bootstrap resolves them from a sibling `tiger-core` checkout.

## License

BSD-3-Clause. Tiger™ and WebTigers™ are trademarks of WebTigers.
