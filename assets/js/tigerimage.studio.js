/* SPDX-License-Identifier: BSD-3-Clause
 * Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
 *
 * The image studio (TIGER-98).
 *
 * Every mutation goes through /api — the same surface an agent uses — so the screen and the agent can
 * never drift into describing the site differently. Nothing here talks to the database.
 *
 * No browser dialogs (house rule): confirmation is the in-app modal in the view.
 */
(function () {
    'use strict';

    var API  = '/api';
    var grid = document.getElementById('ti-grid');
    if (!grid) { return; }                       // unavailable state renders no grid

    var form     = document.getElementById('ti-generate');
    var empty    = document.getElementById('ti-empty');
    var feedback = document.getElementById('ti-feedback');

    /* ---- strings (TIGER-107) ----------------------------------------------------------------
     *
     * Every user-visible string comes from the server, translated. The view emits them as a JSON map
     * in a data attribute — not an inline <script>, which the house rule forbids in a view.
     *
     * A missing key renders as the KEY, not as blank: a screen reading 'tigerimage.action.keep' is
     * obviously broken and gets fixed, where an empty button just looks like a bad design.
     */
    var STRINGS = {};
    try {
        var strEl = document.getElementById('ti-strings');
        if (strEl) { STRINGS = JSON.parse(strEl.getAttribute('data-strings') || '{}') || {}; }
    } catch (e) {
        STRINGS = {};                            // malformed map must not take the whole screen down
    }

    function t(key) {
        return Object.prototype.hasOwnProperty.call(STRINGS, key) ? STRINGS[key] : key;
    }

    /** Fill %1$s / %2$s placeholders, so a translator can reorder them. */
    function tf(key) {
        var args = Array.prototype.slice.call(arguments, 1);
        return t(key).replace(/%(\d+)\$s/g, function (m, n) {
            var v = args[parseInt(n, 10) - 1];
            return (v === undefined || v === null) ? m : String(v);
        });
    }

    /* ---- the budget gauge (TIGER-105) ------------------------------------------------------
     *
     * TWO STOPS. Green at and above 40% remaining; the hue then runs green -> red, reaching red at
     * 10% and staying there below it. No bands: a bar that jumps from yellow to orange reads as a
     * state change, when what is actually happening is a budget draining smoothly.
     *
     * It draws the BINDING ceiling — the one that will actually stop the next call — which the
     * server picks, from the same code path a refusal uses. A bar showing plenty left above a call
     * that is about to be refused would be worse than no bar at all.
     */

    // The colour rule lives in tigerimage.gauge.js — pure maths, no DOM, so it can be tested.
    var GAUGE = (typeof TigerImageGauge !== 'undefined') ? TigerImageGauge : null;
    var gauge = document.getElementById('ti-budget');

    function money(n) {
        return '$' + Number(n).toFixed(2);
    }

    /**
     * Paint the gauge from a binding-ceiling object {fraction, remaining, cap, limit}.
     *
     * A null binding means nothing is capped, so there is no gauge to draw — the element is removed
     * rather than emptied, because an uncapped budget showing a full bar is the bar inventing a
     * ceiling that does not exist.
     */
    function paintGauge(binding) {
        if (!gauge) { return; }
        if (!binding) { gauge.remove(); gauge = null; return; }

        if (!GAUGE) { return; }                  // the rule failed to load; leave the bar as rendered
        var fraction = Number(binding.fraction);
        var pct      = GAUGE.percent(fraction);
        var fill     = document.getElementById('ti-budget-fill');
        var text     = document.getElementById('ti-budget-text');

        gauge.style.setProperty('--ti-gauge-hue', String(Math.round(GAUGE.hue(fraction))));
        if (fill) { fill.style.width = pct + '%'; }

        var bar = gauge.querySelector('.progress');
        if (bar) { bar.setAttribute('aria-valuenow', String(pct)); }

        if (text) {
            text.textContent = money(binding.remaining) + ' / ' + money(binding.cap);
        }
    }

    /**
     * Repaint from any /api response that says something about spend.
     *
     * Two shapes, because the moment the gauge matters MOST is the one that is not a success: a
     * generation returns a full summary, while a refusal returns the binding ceiling directly. Being
     * refused and watching the bar still show headroom is exactly the confusion this is meant to end.
     */
    function paintFromResponse(res) {
        var d = res && res.data;
        if (!d) { return; }

        if (d.spend && Object.prototype.hasOwnProperty.call(d.spend, 'binding')) {
            paintGauge(d.spend.binding);
        } else if (Object.prototype.hasOwnProperty.call(d, 'fraction') && d.cap) {
            paintGauge({ fraction: d.fraction, remaining: d.remaining, cap: d.cap, limit: d.limit });
        }
    }

    // First paint from what the server already rendered, so the bar is correct immediately rather
    // than after a round trip.
    if (gauge) {
        paintGauge({
            fraction:  parseFloat(gauge.dataset.fraction),
            remaining: parseFloat(gauge.dataset.remaining),
            cap:       parseFloat(gauge.dataset.cap),
            limit:     gauge.dataset.limit
        });
    }

    /* ---- /api ---------------------------------------------------------------------------- */

    function call(service, method, params) {
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ module: 'tigerimage', service: service, method: method, params: params || {} })
        }).then(function (r) { return r.json(); });
    }

    /** Show the message the service gave us. A named reason is more useful than "something failed". */
    function say(res, fallback) {
        var msg = (res && res.messages && res.messages.length) ? res.messages[0].message : fallback;
        var ok  = res && res.result === 1;
        feedback.innerHTML =
            '<div class="alert alert-' + (ok ? 'success' : 'danger') + ' alert-dismissible fade show" role="alert">' +
            escapeHtml(msg || '') +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }

    function escapeHtml(s) {
        return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---- the grid ------------------------------------------------------------------------ */

    function tile(img) {
        var promoted = img.state === 'promoted';
        return '' +
          '<div class="col" data-image-id="' + escapeHtml(img.image_id) + '">' +
            '<div class="card h-100">' +
              '<img class="card-img-top ti-thumb" loading="lazy" alt="' + escapeHtml(img.prompt) + '"' +
                   ' src="/tigerimage/studio/raw/id/' + encodeURIComponent(img.image_id) + '">' +
              '<div class="card-body p-2">' +
                '<p class="text-body-secondary text-truncate mb-2" title="' + escapeHtml(img.prompt) + '">' +
                   escapeHtml(img.prompt) + '</p>' +
                '<div class="d-flex gap-1 flex-wrap">' +
                  '<button class="btn btn-sm btn-outline-secondary" data-act="detail">' +
                     escapeHtml(t('tigerimage.action.details')) + '</button>' +
                  '<button class="btn btn-sm btn-outline-primary" data-act="refine">' +
                     escapeHtml(t('tigerimage.action.refine')) + '</button>' +
                  (promoted
                    ? '<span class="badge text-bg-success align-self-center">' +
                         escapeHtml(t('tigerimage.state.in_media')) + '</span>'
                    : '<button class="btn btn-sm btn-primary" data-act="promote">' +
                         escapeHtml(t('tigerimage.action.keep')) + '</button>' +
                      '<button class="btn btn-sm btn-outline-danger" data-act="discard">' +
                         escapeHtml(t('tigerimage.action.bin')) + '</button>') +
                '</div>' +
              '</div>' +
            '</div>' +
          '</div>';
    }

    function render(images) {
        grid.innerHTML = images.map(tile).join('');
        empty.classList.toggle('d-none', images.length > 0);
    }

    function load() {
        return call('image', 'listImages', { limit: 60 }).then(function (res) {
            if (res.result !== 1) { say(res, t('tigerimage.error.list_failed')); return; }
            render((res.data && res.data.images) || []);
        });
    }

    /* ---- actions ------------------------------------------------------------------------- */

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('ti-go');
        btn.disabled = true;                     // generation is slow AND costs money — no double-fire
        btn.textContent = '…';

        call('image', 'generate', {
            prompt:   document.getElementById('ti-prompt').value,
            negative: document.getElementById('ti-negative').value,
            size:     document.getElementById('ti-size').value,
            n:        document.getElementById('ti-n').value
        }).then(function (res) {
            say(res, t('tigerimage.error.generation_failed'));
            paintFromResponse(res);      // the whole point: watch the budget drain as you spend it
            return res.result === 1 ? load() : null;
        }).finally(function () {
            btn.disabled = false;
            btn.textContent = form.dataset.label || t('tigerimage.action.generate');
        });
    });
    document.getElementById('ti-go').dataset.label = document.getElementById('ti-go').textContent.trim();
    form.dataset.label = document.getElementById('ti-go').textContent.trim();

    grid.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-act]');
        if (!btn) { return; }
        var id = btn.closest('[data-image-id]').getAttribute('data-image-id');

        switch (btn.getAttribute('data-act')) {
            case 'promote': return promote(id, btn);
            case 'discard': return confirmDiscard(id);
            case 'refine':  return detail(id, true);
            case 'detail':  return detail(id, false);
        }
    });

    function promote(id, btn) {
        btn.disabled = true;
        // Alt text matters and the person who just looked at the image is the one who can write it,
        // so ask rather than silently reusing the prompt.
        var alt = (document.getElementById('ti-detail-alt') || {}).value || '';
        call('image', 'promote', { image_id: id, alt: alt }).then(function (res) {
            say(res, t('tigerimage.error.promote_failed'));
            if (res.result === 1) { load(); }
        }).finally(function () { btn.disabled = false; });
    }

    function confirmDiscard(id) {
        var modalEl = document.getElementById('ti-confirm');
        document.getElementById('ti-confirm-title').textContent = t('tigerimage.confirm.bin_title');
        document.getElementById('ti-confirm-body').textContent  = t('tigerimage.confirm.bin_body');
        var ok = document.getElementById('ti-confirm-ok');
        ok.textContent = t('tigerimage.action.bin_confirm');

        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        function go() {
            ok.removeEventListener('click', go);
            modal.hide();
            call('image', 'discard', { image_id: id }).then(function (res) {
                say(res, t('tigerimage.error.discard_failed'));
                if (res.result === 1) { load(); }
            });
        }
        ok.addEventListener('click', go);
        modal.show();
    }

    /** The drawer: parameters visible and editable, and refine-from-here. */
    function detail(id, focusRefine) {
        call('image', 'get', { image_id: id }).then(function (res) {
            if (res.result !== 1) { say(res, t('tigerimage.error.get_failed')); return; }
            var img = res.data.image, chain = res.data.chain || [];

            document.getElementById('ti-detail-title').textContent = t('tigerimage.detail.title');
            document.getElementById('ti-detail-body').innerHTML = '' +
              '<img class="img-fluid rounded mb-3" alt="' + escapeHtml(img.prompt) + '"' +
                   ' src="/tigerimage/studio/raw/id/' + encodeURIComponent(img.image_id) + '">' +
              '<label class="form-label" for="ti-detail-prompt">' +
                 escapeHtml(t('tigerimage.detail.prompt')) + '</label>' +
              '<textarea class="form-control mb-2" id="ti-detail-prompt" rows="3">' + escapeHtml(img.prompt) + '</textarea>' +
              '<label class="form-label" for="ti-detail-alt">' +
                 escapeHtml(t('tigerimage.detail.alt')) + '</label>' +
              '<input class="form-control mb-3" id="ti-detail-alt" value="' + escapeHtml(img.prompt) + '">' +
              '<button class="btn btn-primary mb-3" id="ti-detail-refine">' +
                 escapeHtml(t('tigerimage.action.refine_from_this')) + '</button>' +
              '<h3 class="h6">' + escapeHtml(t('tigerimage.detail.how_made')) + '</h3>' +
              '<dl class="row">' +
                row(t('tigerimage.detail.provider'), img.provider) +
                row(t('tigerimage.detail.model'), img.model) +
                row(t('tigerimage.detail.size'), img.width + '×' + img.height) +
                Object.keys(img.params || {}).map(function (k) { return row(k, img.params[k]); }).join('') +
              '</dl>' +
              (chain.length > 1
                ? '<h3 class="h6">' + escapeHtml(t('tigerimage.detail.lineage')) + '</h3>' +
                  '<p class="text-body-secondary">' + escapeHtml(tf('tigerimage.detail.lineage_note',
                      chain.length,
                      chain.map(function (c) { return c.image_id; }).indexOf(img.image_id) + 1)) + '</p>'
                : '');

            document.getElementById('ti-detail-refine').addEventListener('click', function () {
                var b = this; b.disabled = true;
                call('image', 'refine', {
                    image_id: img.image_id,
                    prompt:   document.getElementById('ti-detail-prompt').value
                }).then(function (r) {
                    say(r, t('tigerimage.error.refine_failed'));
                    paintFromResponse(r);
                    if (r.result === 1) { load(); }
                }).finally(function () { b.disabled = false; });
            });

            var oc = bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('ti-detail'));
            oc.show();
            if (focusRefine) { document.getElementById('ti-detail-prompt').focus(); }
        });
    }

    function row(k, v) {
        return '<dt class="col-5 text-body-secondary">' + escapeHtml(k) + '</dt>' +
               '<dd class="col-7">' + escapeHtml(v) + '</dd>';
    }

    load();
})();
