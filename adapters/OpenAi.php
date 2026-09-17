<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerimage_Adapter_OpenAi — OpenAI image generation (TIGER-103).
 *
 * EXTENDS the core adapter rather than reimplementing it: transport, auth headers, error translation
 * and the base URL are all inherited, so there is one place those live. Only drawing is added here,
 * and core keeps no knowledge of an endpoint it never calls.
 *
 * Registered by Tigerimage_Bootstrap, so an install without this module has no OpenAI image capability
 * and says so honestly.
 */
class Tigerimage_Adapter_OpenAi extends Tiger_Agent_Provider_OpenAi
    implements Tiger_Agent_Provider_ImageAdapter
{
    /** Sizes the images endpoint accepts. A caller's request is snapped to the nearest of these. */
    const IMAGE_SIZES = ['1024x1024', '1024x1536', '1536x1024'];

    /**
     * Which OpenAI models can draw.
     *
     * The ADAPTER answers, not core. This used to be a static switch in Tiger_Agent_Provider_Factory,
     * which meant every vendor release was a core edit. Verified against the live model list: the
     * account exposes gpt-image-1, -mini, -1.5, -2 and chatgpt-image-latest, and the `gpt-image`
     * substring matches all of them — including the chatgpt- prefixed one, which an anchored match
     * would have missed.
     *
     * @inheritDoc
     */
    public function supportsModel($model)
    {
        $m = strtolower(trim((string) $model));
        return strpos($m, 'gpt-image') !== false || strpos($m, 'dall-e') !== false;
    }

    /**
     * The provider's default image model — used when the agent supplies a TEXT model (its own choice
     * for chat) but this provider can still draw. Reusing the agent's provider + key for images then
     * "just works" without a separate TigerImage provider config (TIGER-147). Must be a model
     * supportsModel() accepts.
     *
     * @return string
     */
    public function defaultModel()
    {
        return 'gpt-image-1';   // OpenAI's current general-purpose image model; returns base64, no allowlist to age out
    }

    /**
     * Generate images via `POST /images/generations` (TIGER-96).
     *
     * A different endpoint from chat — which is exactly why this is a separate interface rather than
     * a branch inside complete().
     *
     * `b64_json` is requested explicitly. The endpoint can return a URL instead, but those URLs
     * expire, and handing the caller something that rots is how you get an image that works in
     * testing and 404s a week later. gpt-image-* returns base64 regardless; asking for it keeps
     * dall-e-* consistent with it.
     *
     * @inheritDoc
     */
    /**
     * The optional generation params gpt-image-* HONORS (beyond the prompt). The studio + the
     * capability payload render only these, so a field that does NOTHING on this provider — a negative
     * prompt or a seed, neither of which the OpenAI image API accepts — is never shown to a user or
     * handed to an agent. That is the whole point: don't advertise a knob the adapter drops.
     *
     * @return array<int,array> ordered param descriptors {name, type, label, options?, default?, min?, max?}
     */
    public function imageParams()
    {
        return [
            ['name' => 'size', 'type' => 'select', 'label' => 'tigerimage.field.size', 'default' => '1024x1024',
             'options' => ['1024x1024' => 'tigerimage.size.square', '1536x1024' => 'tigerimage.size.landscape', '1024x1536' => 'tigerimage.size.portrait']],
            ['name' => 'n', 'type' => 'select', 'label' => 'tigerimage.field.count', 'default' => '4',
             'options' => ['1' => '1', '2' => '2', '4' => '4']],
            ['name' => 'quality', 'type' => 'select', 'label' => 'tigerimage.field.quality', 'default' => 'auto',
             'options' => ['auto' => 'tigerimage.opt.auto', 'low' => 'tigerimage.opt.low', 'medium' => 'tigerimage.opt.medium', 'high' => 'tigerimage.opt.high']],
            ['name' => 'background', 'type' => 'select', 'label' => 'tigerimage.field.background', 'default' => 'auto',
             'options' => ['auto' => 'tigerimage.opt.auto', 'opaque' => 'tigerimage.opt.opaque', 'transparent' => 'tigerimage.opt.transparent']],
            ['name' => 'output_format', 'type' => 'select', 'label' => 'tigerimage.field.output_format', 'default' => 'png',
             'options' => ['png' => 'PNG', 'jpeg' => 'JPEG', 'webp' => 'WebP']],
            ['name' => 'output_compression', 'type' => 'number', 'label' => 'tigerimage.field.output_compression', 'min' => 0, 'max' => 100],
        ];
    }

    public function generateImage($prompt, array $options, $model, $apiKey)
    {
        $prompt = trim((string) $prompt);
        if ($prompt === '') { throw new RuntimeException('An image prompt cannot be empty.'); }

        $size = $this->_snapSize($options['size'] ?? '');
        $n    = max(1, min(10, (int) ($options['n'] ?? 1)));

        $isGptImage = strpos(strtolower((string) $model), 'gpt-image') !== false;
        $payload = [
            'model'           => $model,
            'prompt'          => $prompt,
            'n'               => $n,
            'size'            => $size,
            'response_format' => 'b64_json',
        ];

        // The image FORMAT the bytes come back as — png unless gpt-image is told otherwise. Tracked so
        // the stored mime matches (a hardcoded image/png on a jpeg/webp response is a lie).
        $format = 'png';

        // dall-e-3 rejects `n > 1`; gpt-image-* ignores `response_format` (always b64) but accepts the
        // quality/background/output params dall-e does not. Send each only what it honours.
        if (strpos(strtolower((string) $model), 'dall-e-3') !== false) {
            $payload['n'] = 1;
        }
        if ($isGptImage) {
            unset($payload['response_format']);
            // quality (the cost lever) + background (transparent = logos/icons). 'auto' = the model's
            // default, so we send nothing and let it decide.
            foreach (['quality', 'background'] as $k) {
                $v = strtolower(trim((string) ($options[$k] ?? '')));
                if ($v !== '' && $v !== 'auto') { $payload[$k] = $v; }
            }
            // output_format + compression — the fix for a 1.7 MB PNG landing in the Media Library.
            $of = strtolower(trim((string) ($options['output_format'] ?? '')));
            if (in_array($of, ['png', 'jpeg', 'webp'], true)) { $payload['output_format'] = $of; $format = $of; }
            if (in_array($format, ['jpeg', 'webp'], true) && ($options['output_compression'] ?? '') !== '') {
                $payload['output_compression'] = max(0, min(100, (int) $options['output_compression']));
            }
        }

        $body = $this->_post($this->_base() . '/images/generations', $payload, $this->_headers($apiKey));

        $mime = 'image/' . $format;
        $images = [];
        foreach (($body['data'] ?? []) as $item) {
            if (empty($item['b64_json'])) { continue; }
            $images[] = ['mime' => $mime, 'data' => (string) $item['b64_json']];
        }
        if (!$images) {
            throw new RuntimeException('The provider returned no image data.');
        }

        return [
            'images' => $images,
            // Echo what was ACTUALLY used, including our snap and the model's own revision of the
            // prompt where it makes one — without that, a refinement cannot reproduce this image.
            'params' => array_filter([
                'provider'        => 'openai',
                'model'           => $model,
                'size'            => $size,
                'n'               => count($images),
                'quality'         => $payload['quality'] ?? null,
                'background'      => $payload['background'] ?? null,
                'output_format'   => $payload['output_format'] ?? null,
                'revised_prompt'  => $body['data'][0]['revised_prompt'] ?? null,
            ], static fn($v) => $v !== null),
        ];
    }

    /** Snap a requested size to the nearest legal one; default square. */
    protected function _snapSize($requested)
    {
        $requested = strtolower(trim((string) $requested));
        if (in_array($requested, self::IMAGE_SIZES, true)) { return $requested; }
        if (preg_match('~^(\d+)x(\d+)$~', $requested, $m)) {
            $w = (int) $m[1]; $h = (int) $m[2];
            if ($w > $h) { return '1536x1024'; }
            if ($h > $w) { return '1024x1536'; }
        }
        return '1024x1024';
    }
}
