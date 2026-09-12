/* SPDX-License-Identifier: BSD-3-Clause
 * Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
 *
 * The budget gauge's colour rule (TIGER-105).
 *
 * Plain node + assert, no framework: one pure function does not justify a toolchain, and a test that
 * needs no install is a test CI will actually run.
 *
 *   node tests/js/gauge.test.js
 */
'use strict';

const assert = require('assert');
const G = require('../../assets/js/tigerimage.gauge.js');

let passed = 0;
function test(name, fn) {
    try { fn(); passed++; console.log('  ok   ' + name); }
    catch (e) { console.error('  FAIL ' + name + '\n       ' + e.message); process.exitCode = 1; }
}

/* ---- the two stops ------------------------------------------------------------------------ */

test('full budget is green', () => {
    assert.strictEqual(G.hue(1.0), 120);
});

test('green holds all the way down to 40%', () => {
    assert.strictEqual(G.hue(0.90), 120);
    assert.strictEqual(G.hue(0.41), 120);
    assert.strictEqual(G.hue(0.40), 120, '40% is still fully green — the stop is inclusive');
});

test('red from 10% down, and it stays red', () => {
    assert.strictEqual(G.hue(0.10), 0, '10% is already fully red');
    assert.strictEqual(G.hue(0.05), 0);
    assert.strictEqual(G.hue(0.0), 0, 'an empty budget is red, not wrapped back to green');
});

test('between the stops the hue moves continuously, never in bands', () => {
    // The midpoint of 40%..10% is 25%, which lands on yellow. Compared with a tolerance because the
    // ramp is floating-point division; the painted value is rounded to a whole degree anyway.
    assert.ok(Math.abs(G.hue(0.25) - 60) < 1e-6, 'the midpoint is yellow, got ' + G.hue(0.25));

    // Strictly decreasing across the ramp: no plateau would mean a band.
    let prev = Infinity;
    for (let f = 0.40; f >= 0.10; f -= 0.01) {
        const h = G.hue(f);
        assert.ok(h < prev + 1e-9, 'hue must not increase as the budget falls (at ' + f.toFixed(2) + ')');
        assert.ok(h >= 0 && h <= 120, 'hue stays within green..red');
        prev = h;
    }
});

test('a hair either side of a stop barely differs — the stops are not cliffs', () => {
    assert.ok(Math.abs(G.hue(0.401) - G.hue(0.399)) < 1, 'no jump at the green stop');
    assert.ok(Math.abs(G.hue(0.101) - G.hue(0.099)) < 1, 'no jump at the red stop');
});

/* ---- refusing to lie ----------------------------------------------------------------------- */

test('an unusable value reads as empty, never as a healthy bar', () => {
    // A gauge that cannot work out the budget must not show reassuring green.
    assert.strictEqual(G.hue(NaN), 0);
    assert.strictEqual(G.hue(undefined), 0);
    assert.strictEqual(G.hue(null), 0, 'null coerces to 0, which is empty — and empty is red');
    assert.strictEqual(G.percent(NaN), 0);
});

test('percent is clamped to the bar', () => {
    assert.strictEqual(G.percent(1.5), 100, 'a bar cannot be more than full');
    assert.strictEqual(G.percent(-0.5), 0, 'or less than empty');
    assert.strictEqual(G.percent(0.257), 26);
});

console.log('\n' + passed + ' passed' + (process.exitCode ? ', with failures' : ''));
