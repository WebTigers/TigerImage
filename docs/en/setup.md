<!-- tiger:doc
header: true
order: 10
title: Setting it up
visibility: admin
-->

# Setting it up

## 1. Check the module is active

App modules are **opt-in**. Having the files in place is not enough — the module stays inert until it is
activated in **Modules**. Until then every `/tigerimage` route returns Not Found.

## 2. Pick a provider that can draw

Not every AI provider generates images. **Claude cannot**, which is the case this module exists for: on
an Anthropic install TigerImage reports itself unavailable, with the reason, *before* an agent promises
anyone a picture. Today the image-capable providers are **OpenAI** and **Google Gemini**.

If you leave the provider unset, TigerImage uses the site's agent provider when that provider can draw.
Set it explicitly when your chat agent and your image generator should differ:

| Setting | What it does |
|---|---|
| `tigerimage.provider` | `openai` or `gemini`. Unset = use the site's agent provider if it can draw. |
| `tigerimage.model` | The image model, e.g. `gpt-image-1`. |
| `tigerimage.api_key_enc` | The provider key, encrypted. Unset = reuse the agent's key. |

## 3. Confirm it is available

Open the studio at **/tigerimage/studio**. If anything is missing the page says so plainly and tells you
what to fix — a missing provider and a missing key are different messages, because the fix is different.

## Where the images go

`storage/tigerimage/`, outside the web root. Generated images are not in the Media Library and have no
public URL until you **Keep** one.

Unpromoted images are swept after `tigerimage.retention_days` (7 by default) so experiments do not become
a disk-space problem. Images you promoted belong to the Media Library and are never swept.

> **Deleting the module deletes these images.** Purging removes `storage/tigerimage/` along with the
> module's tables and settings — the confirmation's "cannot be undone" is literal. Promoted images are
> the Media Library's and are unaffected.
