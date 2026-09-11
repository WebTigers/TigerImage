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
    /** USD per calendar month, per org. Empty/absent = uncapped. */
    const CFG_CAP     = 'tigerimage.spend.monthly_cap';
    /** `hard` refuses; `soft` allows and reports. Hard by default. */
    const CFG_ENFORCE = 'tigerimage.spend.enforce';

    /**
     * May this org spend this much right now?
     *
     * @param  string $orgId
     * @param  float  $estimate USD the pending call is expected to cost
     * @return array {allowed:bool, reason:string, spent:float, cap:float|null, remaining:float|null, enforce:string}
     */
    public static function check($orgId, $estimate)
    {
        $cap     = self::cap();
        $spent   = static::spentThisMonth($orgId);
        $enforce = self::enforcement();

        if ($cap === null) {
            return ['allowed' => true, 'reason' => 'uncapped', 'spent' => $spent,
                    'cap' => null, 'remaining' => null, 'enforce' => $enforce];
        }

        $remaining = round($cap - $spent, 5);
        $wouldBe   = round($spent + (float) $estimate, 5);

        if ($wouldBe > $cap) {
            return [
                // A soft cap still reports the breach — it just does not stop the call. The operator
                // asked to be told rather than blocked, and must still be told.
                'allowed'   => ($enforce !== 'hard'),
                'reason'    => 'spend_cap_reached',
                'spent'     => $spent,
                'cap'       => $cap,
                'remaining' => max(0.0, $remaining),
                'enforce'   => $enforce,
            ];
        }

        return ['allowed' => true, 'reason' => 'within_cap', 'spent' => $spent,
                'cap' => $cap, 'remaining' => $remaining, 'enforce' => $enforce];
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
    public static function summary($orgId)
    {
        $cap   = self::cap();
        $spent = static::spentThisMonth($orgId);
        return [
            'spent_this_month' => $spent,
            'cap'              => $cap,
            'remaining'        => $cap === null ? null : max(0.0, round($cap - $spent, 5)),
            'enforce'          => self::enforcement(),
            'currency'         => 'USD',
            'basis'            => 'estimated',   // never claim these are billed figures
        ];
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
