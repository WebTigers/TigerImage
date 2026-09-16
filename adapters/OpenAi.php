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
    public function generateImage($prompt, array $options, $model, $apiKey)
    {
        $prompt = trim((string) $prompt);
        if ($prompt === '') { throw new RuntimeException('An image prompt cannot be empty.'); }

        $size = $this->_snapSize($options['size'] ?? '');
        $n    = max(1, min(10, (int) ($options['n'] ?? 1)));

        $payload = [
            'model'           => $model,
            'prompt'          => $prompt,
            'n'               => $n,
            'size'            => $size,
            'response_format' => 'b64_json',
        ];

        // dall-e-3 rejects both `n > 1` and `response_format` is still honoured; gpt-image-* ignores
        // response_format and always returns b64. Send what each accepts rather than one payload that
        // half-works on both.
        if (strpos(strtolower((string) $model), 'dall-e-3') !== false) {
            $payload['n'] = 1;
        }
        if (strpos(strtolower((string) $model), 'gpt-image') !== false) {
            unset($payload['response_format']);
        }

        $body = $this->_post($this->_base() . '/images/generations', $payload, $this->_headers($apiKey));

        $images = [];
        foreach (($body['data'] ?? []) as $item) {
            if (empty($item['b64_json'])) { continue; }
            $images[] = ['mime' => 'image/png', 'data' => (string) $item['b64_json']];
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
