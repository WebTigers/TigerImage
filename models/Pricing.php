<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerimage_Model_Pricing — what a generation is about to cost (TIGER-100).
 *
 * ESTIMATES, AND HONEST ABOUT IT. Providers do not return a price with an image, and their published
 * rates change without notice. These figures exist to enforce a ceiling and to show an operator where
 * the money went — not to reconcile a bill. A cap built on a stale estimate still stops a runaway
 * loop, which is the job.
 *
 * Unknown model → UNKNOWN_COST rather than zero. Charging nothing for a model we do not recognise
 * would make a new model the one thing a cap cannot stop.
 *
 * @api
 */
class Tigerimage_Model_Pricing
{
    /**
     * USD per image, by provider and model cue. Deliberately coarse — a wrong estimate that is the
     * right order of magnitude enforces a cap correctly; precision here is false comfort.
     */
    const RATES = [
        'openai' => [
            'gpt-image-1' => 0.040,     // ~1024px, standard quality
            'dall-e-3'    => 0.040,
            'dall-e-2'    => 0.020,
        ],
        'gemini' => [
            'imagen'      => 0.040,
            'gemini'      => 0.039,     // the *-image-* chat variants
        ],
        'grok'   => ['grok' => 0.070],
        'openrouter' => ['' => 0.050],  // routes upstream; assume the pricier end
    ];

    /** What we charge against the cap when we do not recognise the model. Not zero, on purpose. */
    const UNKNOWN_COST = 0.100;

    /**
     * Estimated cost of a generation, in USD.
     *
     * @param  string $provider
     * @param  string $model
     * @param  int    $n      how many images
     * @param  string $size   larger sizes cost more on most providers
     * @return float
     */
    public static function estimate($provider, $model, $n = 1, $size = '')
    {
        $per  = self::perImage($provider, $model);
        $n    = max(1, (int) $n);
        return round($per * $n * self::_sizeFactor($size), 5);
    }

    /** The per-image rate, or UNKNOWN_COST. */
    public static function perImage($provider, $model)
    {
        $p = strtolower(trim((string) $provider));
        $m = strtolower(trim((string) $model));
        if (!isset(self::RATES[$p])) { return self::UNKNOWN_COST; }

        foreach (self::RATES[$p] as $cue => $rate) {
            if ($cue === '' || strpos($m, $cue) !== false) { return (float) $rate; }
        }
        return self::UNKNOWN_COST;
    }

    /** Whether this estimate is a real rate or the unknown-model fallback — surfaced, not hidden. */
    public static function isKnown($provider, $model)
    {
        return self::perImage($provider, $model) !== self::UNKNOWN_COST;
    }

    /** Bigger canvases cost more; a coarse multiplier is enough to keep a cap honest. */
    protected static function _sizeFactor($size)
    {
        if (preg_match('~^(\d+)x(\d+)$~', strtolower(trim((string) $size)), $m)) {
            $px = (int) $m[1] * (int) $m[2];
            if ($px > 1400 * 1400) { return 2.0; }
            if ($px > 1100 * 1100) { return 1.5; }
        }
        return 1.0;
    }
}
