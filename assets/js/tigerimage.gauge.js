/* SPDX-License-Identifier: BSD-3-Clause
 * Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
 *
 * The budget gauge's colour rule (TIGER-105) — pure maths, no DOM.
 *
 * Kept separate from the studio so it can be tested directly: the colour IS the feature here, and a
 * rule nobody can assert is a rule that drifts. tigerimage.studio.js does the painting.
 *
 * TWO STOPS, not four bands:
 *
 *   above 40% remaining ......... green
 *   40% -> 10% .................. hue runs green -> red, continuously
 *   10% and below ............... red
 *
 * Bands were the first design and were wrong: a bar that jumps yellow -> orange reads as a state
 * change, when what is happening is a budget draining smoothly. A continuous hue says "getting
 * worse" without ever claiming a threshold was crossed.
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module !== 'undefined' && module.exports) { module.exports = api; }   // node, for tests
    else { root.TigerImageGauge = api; }                                             // browser
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var FULL_GREEN = 0.40;   // at or above this share remaining, fully green
    var FULL_RED   = 0.10;   // at or below this share remaining, fully red
    var HUE_GREEN  = 120;    // HSL degrees; red is 0

    /**
     * Hue in HSL degrees for a remaining share (0..1).
     *
     * Anything unusable — NaN, a missing value — is treated as EMPTY, not full. A gauge that cannot
     * work out the budget must not show a reassuring green bar.
     *
     * @param  {number} fraction share of the binding ceiling still available
     * @return {number} 0 (red) .. 120 (green)
     */
    function hue(fraction) {
        var f = Number(fraction);
        if (!isFinite(f)) { return 0; }
        if (f >= FULL_GREEN) { return HUE_GREEN; }
        if (f <= FULL_RED)   { return 0; }
        return HUE_GREEN * (f - FULL_RED) / (FULL_GREEN - FULL_RED);
    }

    /** The share as a whole percentage, clamped — what the bar's width and aria-valuenow use. */
    function percent(fraction) {
        var f = Number(fraction);
        if (!isFinite(f)) { return 0; }
        return Math.round(Math.max(0, Math.min(1, f)) * 100);
    }

    return { hue: hue, percent: percent, FULL_GREEN: FULL_GREEN, FULL_RED: FULL_RED, HUE_GREEN: HUE_GREEN };
}));
