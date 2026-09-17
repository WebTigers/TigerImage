<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerimage_Model_Spend — the ceiling, checked BEFORE the call (TIGER-100).
 *
 * The risk this exists for is specific: image calls cost orders of magnitude more than text, and the
 * point of this module is to let an AGENT issue them in a loop. A text agent that retries a failed
 * generation four times has spent real money with nobody watching.
 *
 * ENFORCED BEFORE, NOT RECONCILED AFTER. A cap discovered by going over it is not a cap. The check
 * runs on the ESTIMATE, before the provider is contacted — the alternative is finding out from an
 * invoice.
 *
 * HARD BY DEFAULT. A soft cap warns; an agent does not read warnings. If the operator wants to be
 * told rather than stopped, that is a deliberate setting, not the default.
 *
 * @api
 */
class Tigerimage_Model_Spend
{
    /**
     * The budget FEATURE master switch (the gauge + the cap enforcement). OFF by default.
     *
     * An estimated-dollar gauge is noise if it can't be accurate — providers don't expose an account
     * balance to a BYO API key, so these figures are Tiger's own estimate, never the real bill. So the
     * whole budget UI + enforcement is opt-in: off, there is no gauge and no ceiling (spend freely).
     * Spending is STILL TRACKED either way (the per-image cost is recorded at generate time, not here),
     * so turning the feature on later has a real running total from day one.
     */
    const CFG_ENABLED = 'tigerimage.spend.enabled';
    /** USD per calendar month, per org. Empty/absent = uncapped. Only in force when CFG_ENABLED is on. */
    const CFG_CAP     = 'tigerimage.spend.monthly_cap';
    /** `hard` refuses; `soft` allows and reports. Hard by default. */
    const CFG_ENFORCE = 'tigerimage.spend.enforce';
    /** USD per calendar month for ANY token-authenticated caller. Empty/absent = only the org cap applies. */
    const CFG_TOKEN_CAP = 'tigerimage.spend.token_cap';
    /**
     * Per-credential override, e.g. `tigerimage.spend.token_cap_for.<credential_id>`.
     *
     * A SEPARATE key rather than children of token_cap, because a config node cannot be both a scalar
     * and a section — not in an INI file and not in Zend_Config. Nesting them would have made the
     * blanket default unreadable the moment anyone set an override.
     */
    const CFG_TOKEN_CAP_FOR = 'tigerimage.spend.token_cap_for';

    /**
     * May this org spend this much right now?
     *
     * @param  string $orgId
     * @param  float  $estimate USD the pending call is expected to cost
     * @return array {allowed:bool, reason:string, spent:float, cap:float|null, remaining:float|null, enforce:string}
     */
    public static function check($orgId, $estimate, $credentialId = null)
    {
        $enforce = self::enforcement();

        // TWO CEILINGS, and the tighter one wins (TIGER-100 / TIGER-102).
        //
        // The org cap protects the organisation's wallet. The TOKEN cap protects it from one key: a
        // scoped credential handed to an agent should not be able to spend the whole budget just
        // because the agent is entitled to spend some of it. A session user has no credential and is
        // bound by the org cap alone — a human clicking Generate is not the runaway risk.
        $limits   = self::_ceilings($orgId, $credentialId);
        $orgSpent = $limits['org']['spent'] ?? static::spentThisMonth($orgId);

        if (!$limits) {
            return ['allowed' => true, 'reason' => 'uncapped', 'spent' => $orgSpent,
                    'cap' => null, 'remaining' => null, 'fraction' => null,
                    'enforce' => $enforce, 'limit' => null];
        }

        // Report against the BINDING limit — the one with least headroom — so a caller told how much
        // it has left is told the number that will actually stop it.
        $binding  = self::_bindingOf($limits);
        $exceeded = round($binding['spent'] + (float) $estimate, 5) > $binding['cap'];

        return [
            // A soft cap still reports the breach — it just does not stop the call. The operator
            // asked to be told rather than blocked, and must still be told.
            'allowed'   => $exceeded ? ($enforce !== 'hard') : true,
            'reason'    => $exceeded ? 'spend_cap_reached' : 'within_cap',
            'spent'     => $binding['spent'],
            'cap'       => $binding['cap'],
            'remaining' => $binding['remaining'],
            // The share still available, so a refusal can repaint the gauge (TIGER-105) without a
            // second round trip — the moment a user most needs to see the bar is when it stopped them.
            'fraction'  => $binding['fraction'],
            'enforce'   => $enforce,
            // WHICH ceiling is binding — 'org' or 'token'. Without this, a user shown "budget used up"
            // cannot tell whether to raise the org cap or widen one key.
            'limit'     => $binding['limit'],
        ];
    }

    /** Spend so far this calendar month for one credential, in USD. */
    public static function spentThisMonthByCredential($credentialId)
    {
        $since = gmdate('Y-m-01 00:00:00');
        return (float) (new Tigerimage_Model_Image())->spentSinceByCredential((string) $credentialId, $since);
    }

    /**
     * The cap for a specific token, or null when tokens are not separately capped.
     *
     * A per-credential key wins over the blanket one, so a single agent can be given more or less
     * room than the default without changing everyone else's.
     *
     * @param  string $credentialId
     * @return float|null
     */
    public static function tokenCap($credentialId)
    {
        foreach ([self::CFG_TOKEN_CAP_FOR . '.' . $credentialId, self::CFG_TOKEN_CAP] as $key) {
            $raw = trim((string) self::_config($key));
            if ($raw !== '') {
                $cap = (float) $raw;
                return $cap > 0 ? $cap : null;
            }
        }
        return null;
    }

    /**
     * Spend so far this calendar month, in USD.
     *
     * Called through `static::` by check() and summary() so a test can subclass and stub it — the
     * same late-binding seam Tiger_Backup uses to sandbox its root. The cap policy is worth testing
     * without standing up a database.
     */
    public static function spentThisMonth($orgId)
    {
        $since = gmdate('Y-m-01 00:00:00');
        return (float) (new Tigerimage_Model_Image())->spentSince((string) $orgId, $since);
    }

    /** Is the budget feature (gauge + cap enforcement) switched on? OFF by default — see CFG_ENABLED. */
    public static function enabled()
    {
        return trim((string) self::_config(self::CFG_ENABLED)) === '1';
    }

    /** The configured monthly cap, or null when uncapped. */
    public static function cap()
    {
        $raw = trim((string) self::_config(self::CFG_CAP));
        if ($raw === '') { return null; }
        $cap = (float) $raw;
        return $cap > 0 ? $cap : null;
    }

    /** `hard` (refuse) or `soft` (allow and report). Anything unrecognised is treated as hard. */
    public static function enforcement()
    {
        return strtolower(trim((string) self::_config(self::CFG_ENFORCE))) === 'soft' ? 'soft' : 'hard';
    }

    /**
     * The spend picture an agent or a screen can read without generating anything.
     *
     * @param  string $orgId
     * @return array
     */
    public static function summary($orgId, $credentialId = null)
    {
        $limits = self::_ceilings($orgId, $credentialId);
        // With the feature off there is no active cap (even if a monthly_cap value sits in config), so
        // cap/remaining/binding are all null — but spent_this_month is still the real tracked total.
        $cap    = self::enabled() ? self::cap() : null;
        $spent  = $limits['org']['spent'] ?? static::spentThisMonth($orgId);
        $out    = [
            'enabled'          => self::enabled(),
            'spent_this_month' => $spent,
            'cap'              => $cap,
            'remaining'        => $cap === null ? null : max(0.0, round($cap - $spent, 5)),
            'enforce'          => self::enforcement(),
            'currency'         => 'USD',
            'basis'            => 'estimated',   // never claim these are billed figures
        ];

        // A token-authenticated caller is told ITS OWN ceiling too, so an agent can see the limit that
        // actually applies to it rather than the organisation's headline figure.
        if (isset($limits['token'])) {
            $t = $limits['token'];
            $out['token'] = [
                'spent_this_month' => $t['spent'],
                'cap'              => $t['cap'],
                'remaining'        => max(0.0, round($t['cap'] - $t['spent'], 5)),
            ];
        }

        // The ceiling that will actually stop this caller, and the share of it still available.
        // The budget gauge (TIGER-105) draws THIS — from the same authority check() refuses by, so a
        // bar that still looks healthy can never sit above a call that is about to be refused.
        // Null when nothing is capped: an uncapped budget has no ceiling to draw a fraction of, and
        // inventing one would be the gauge lying.
        $out['binding'] = self::_bindingOf($limits);
        return $out;
    }

    /**
     * The ceilings in force for this caller — 'org' and/or 'token'. Empty means uncapped.
     *
     * ONE place that decides which ceilings exist and reads their ledgers, so check() and summary()
     * cannot answer differently. They used to compute this separately, which is exactly how a gauge
     * and a refusal drift apart.
     *
     * @return array<string,array{cap:float,spent:float}>
     */
    protected static function _ceilings($orgId, $credentialId = null)
    {
        // Feature off = no ceilings at all, so check() allows freely and summary()'s binding is null
        // (the gauge draws nothing). Tracking is unaffected — it lives in the image store, not here.
        if (!self::enabled()) { return []; }

        $limits = [];
        if (($orgCap = self::cap()) !== null) {
            $limits['org'] = ['cap' => $orgCap, 'spent' => static::spentThisMonth($orgId)];
        }
        if ($credentialId !== null && ($tokCap = self::tokenCap($credentialId)) !== null) {
            $limits['token'] = ['cap' => $tokCap, 'spent' => static::spentThisMonthByCredential($credentialId)];
        }
        return $limits;
    }

    /**
     * The BINDING ceiling — least headroom — or null when nothing is capped.
     *
     * Selection runs on RAW headroom, which may be negative when a soft cap has been overrun; only
     * the reported figure is floored at zero. Picking on a floored number would tie every overrun
     * ceiling at 0 and report whichever happened to be first.
     *
     * @return array{limit:string,cap:float,spent:float,remaining:float,fraction:float}|null
     */
    protected static function _bindingOf(array $limits)
    {
        $binding = null;
        foreach ($limits as $which => $l) {
            $headroom = round($l['cap'] - $l['spent'], 5);
            if ($binding === null || $headroom < $binding['remaining']) {
                $binding = ['limit' => $which, 'cap' => $l['cap'], 'spent' => $l['spent'], 'remaining' => $headroom];
            }
        }
        if ($binding === null) { return null; }

        // The share still available, 0..1 — what a gauge draws. Clamped, because a soft cap can be
        // overrun and a bar cannot be less than empty.
        $binding['fraction']  = $binding['cap'] > 0
            ? max(0.0, min(1.0, round($binding['remaining'] / $binding['cap'], 5)))
            : 0.0;
        $binding['remaining'] = max(0.0, $binding['remaining']);
        return $binding;
    }

    /** Read a dotted config key. */
    protected static function _config($key, $default = '')
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) { return $default; }
        $val = Zend_Registry::get('Zend_Config');
        foreach (explode('.', $key) as $part) {
            if (!is_object($val) || $val->get($part) === null) { return $default; }
            $val = $val->get($part);
        }
        return is_scalar($val) ? (string) $val : $default;
    }
}
