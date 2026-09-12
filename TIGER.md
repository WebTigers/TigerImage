# TigerImage

*Image generation for any agent. Describe a picture, compare what comes back, keep the one you want —
and because every image remembers how it was made, "the same but warmer" is a tweak instead of a
retype. Free, first-party, BSD-licensed.*

> **`TIGER.md` is the vendor description** — the pitch the Module Installer shows before you install.
> The machine-readable manifest is [`module.json`](module.json); the operator detail is in the
> [README](README.md).

## What it does

- **Gives a text-only assistant the ability to illustrate.** Your chat agent writes a page and can now
  make the picture for it, in the same conversation, through the same `/api` the studio uses.
- **Iterates like a studio, not a file picker.** A grid to compare variants, the prompt and parameters
  editable on any of them, and refine-from-here on every tile. Lineage links a refinement to what it
  came from, so a chain is navigable rather than a pile.
- **Keeps the good ones.** Promote an image into the Media Library and it becomes an ordinary media
  row — searchable, reusable, with alt text you wrote while you were looking at it.
- **Bins the rest, on its own.** Unpromoted images are swept after a retention window, so an
  experiment does not become a disk-space problem.

## It works with the provider you already have

TigerImage does not add a second AI account. It drives the **same provider adapters Tiger already
uses**, so an install configured for OpenAI generates with OpenAI.

And it tells you the truth up front: **Claude cannot draw.** On an Anthropic install the module reports
itself unavailable, with the reason, *before* an agent promises anyone a picture — rather than failing
at call time after the promise is made.

## Spend is a ceiling, not a report

Image calls cost orders of magnitude more than text, and the point of this module is to let an **agent**
issue them in a loop. So the budget is checked **before** the provider is contacted — a cap you discover
by going over it is not a cap — and it is **hard by default**, because an agent does not read warnings.

Two ceilings, and the tighter one wins: one for the organisation, one for an individual **access key**,
so a scoped token handed to an agent cannot spend the whole budget just because the agent is entitled to
spend some of it. A small gauge in the studio shows the one that will actually stop you, draining from
green to red as it goes.

Costs are **estimates**, and say so everywhere they appear. Providers do not return a price with an
image. The figures exist to stop a runaway loop and show you where the money went — not to reconcile a
bill. An unrecognised model is charged as unknown, never as zero, because otherwise a newly released
model would be the one thing a cap cannot stop.

## Where your images live

Outside the web root, and outside the module directory — in `storage/tigerimage/`, beside `storage/media`.
Not an implementation detail: updating a module renames its directory away and deletes the backup, so
anything kept *inside* a module is destroyed by a routine update. Deleting the module deletes these
images, which is what the confirmation dialog says; ones you promoted belong to the Media Library and
are untouched.

## Requirements

- Tiger ≥ 1.5.21, PHP ≥ 8.1 (see [`module.json`](module.json)).
- An image-capable provider configured — OpenAI or Google Gemini today.

## License

**Free** and **BSD 3-Clause** — a first-party module, yours to use, modify, and redistribute. The
Tiger / TigerImage / WebTigers trademarks are reserved. See [LICENSE](LICENSE).
