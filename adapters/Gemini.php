<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerimage_Adapter_Gemini — Google image generation (TIGER-103).
 *
 * EXTENDS the core adapter so transport, the API-key header and error translation are inherited
 * rather than duplicated. Core keeps no knowledge of :predict or of which Google models draw.
 *
 * Registered by Tigerimage_Bootstrap.
 */
class Tigerimage_Adapter_Gemini extends Tiger_Agent_Provider_Gemini
    implements Tiger_Agent_Provider_ImageAdapter
{
    /**
     * Which Google models can draw: the dedicated imagen-* models, and the gemini-*-image-* chat
     * variants that emit an image part. A plain gemini-2.5-pro cannot.
     *
     * @inheritDoc
     */
    public function supportsModel($model)
    {
        $m = strtolower(trim((string) $model));
        return strpos($m, 'imagen') !== false || (bool) preg_match('~gemini-[^ ]*image~', $m);
    }

    /**
     * Generate images (TIGER-96).
     *
     * Google splits this across two shapes and the adapter hides the split, which is the whole
     * point of normalising here rather than in the caller:
     *
     *   - `imagen-*` → `:predict`, a dedicated image model, returns `predictions[].bytesBase64Encoded`.
     *   - `gemini-*-image-*` → `:generateContent`, a multimodal chat model that happens to emit an
     *     image part, returned as `candidates[].content.parts[].inlineData`.
     *
     * A reference image (img2img) only has a path on the generateContent shape, where it rides as an
     * extra inlineData part — the same wire format vision input already uses. Imagen's predict
     * endpoint takes no reference here, so asking for one with an imagen model is refused up front
     * rather than silently ignored: quietly dropping the steer produces a plausible image that is
     * not what was asked for, which is worse than an error.
     *
     * @inheritDoc
     */
    public function generateImage($prompt, array $options, $model, $apiKey)
    {
        $prompt = trim((string) $prompt);
        if ($prompt === '') { throw new RuntimeException('An image prompt cannot be empty.'); }

        $m         = strtolower((string) $model);
        $isImagen  = strpos($m, 'imagen') !== false;
        $reference = $options['reference'] ?? null;
        $n         = max(1, min(8, (int) ($options['n'] ?? 1)));

        if ($isImagen && $reference) {
            throw new RuntimeException(
                'This model cannot take a reference image. Use a gemini-*-image-* model to refine from an existing image.'
            );
        }

        if ($isImagen) {
            $params = array_filter([
                'sampleCount'     => $n,
                'negativePrompt'  => trim((string) ($options['negative_prompt'] ?? '')) ?: null,
                'aspectRatio'     => $this->_aspect($options['size'] ?? ''),
                'seed'            => isset($options['seed']) ? (int) $options['seed'] : null,
            ], static fn($v) => $v !== null);

            $body = $this->_post(
                self::BASE . '/models/' . rawurlencode($model) . ':predict',
                ['instances' => [['prompt' => $prompt]], 'parameters' => $params],
                $apiKey
            );

            $images = [];
            foreach (($body['predictions'] ?? []) as $pred) {
                if (empty($pred['bytesBase64Encoded'])) { continue; }
                $images[] = [
                    'mime' => (string) ($pred['mimeType'] ?? 'image/png'),
                    'data' => (string) $pred['bytesBase64Encoded'],
                ];
            }
            if (!$images) { throw new RuntimeException('The provider returned no image data.'); }

            return ['images' => $images, 'params' => ['provider' => 'gemini', 'model' => $model, 'n' => count($images)] + $params];
        }

        // gemini-*-image-*: a chat turn that returns an image part.
        $parts = [['text' => $prompt]];
        if ($reference && !empty($reference['data'])) {
            $parts[] = ['inlineData' => [
                'mimeType' => (string) ($reference['mime'] ?? 'image/png'),
                'data'     => (string) $reference['data'],
            ]];
        }

        $body = $this->_post(
            self::BASE . '/models/' . rawurlencode($model) . ':generateContent',
            ['contents' => [['role' => 'user', 'parts' => $parts]]],
            $apiKey
        );

        $images = [];
        foreach (($body['candidates'] ?? []) as $cand) {
            foreach (($cand['content']['parts'] ?? []) as $part) {
                if (empty($part['inlineData']['data'])) { continue; }
                $images[] = [
                    'mime' => (string) ($part['inlineData']['mimeType'] ?? 'image/png'),
                    'data' => (string) $part['inlineData']['data'],
                ];
            }
        }
        if (!$images) { throw new RuntimeException('The provider returned no image data.'); }

        return [
            'images' => $images,
            'params' => array_filter([
                'provider'  => 'gemini',
                'model'     => $model,
                'n'         => count($images),
                'reference' => $reference ? 'supplied' : null,
            ], static fn($v) => $v !== null),
        ];
    }

    /** Map a WxH request to the aspect ratio Imagen accepts; default square. */
    protected function _aspect($size)
    {
        if (preg_match('~^(\d+)x(\d+)$~', strtolower(trim((string) $size)), $m)) {
            $w = (int) $m[1]; $h = (int) $m[2];
            if ($w === 0 || $h === 0) { return '1:1'; }
            $r = $w / $h;
            if ($r >= 1.6) { return '16:9'; }
            if ($r >= 1.2) { return '4:3'; }
            if ($r <= 0.62) { return '9:16'; }
            if ($r <= 0.84) { return '3:4'; }
        }
        return '1:1';
    }
}
