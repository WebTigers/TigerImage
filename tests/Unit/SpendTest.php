<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Spend control (TIGER-100).
 *
 * The risk is specific: image calls cost orders of magnitude more than text, and the point of this
 * module is to let an AGENT issue them in a loop. A text agent that retries four times has spent real
 * money with nobody watching.
 */
#[CoversClass(Tigerimage_Model_Pricing::class)]
#[CoversClass(Tigerimage_Model_Spend::class)]
final class SpendTest extends TestCase
{
    protected function tearDown(): void
    {
        if (Zend_Registry::isRegistered('Zend_Config')) { Zend_Registry::set('Zend_Config', null); }
    }

    private function config(array $spend): void
    {
        $spend = $spend + ["enabled" => "1"];   // default the budget feature ON so cap tests still enforce
        Zend_Registry::set("Zend_Config", new Zend_Config(["tigerimage" => ["spend" => $spend]]));
    }

    /* ---- pricing ---------------------------------------------------------------------------- */

    #[Test]
    public function it_prices_the_models_it_knows(): void
    {
        $this->assertSame(0.040, Tigerimage_Model_Pricing::perImage('openai', 'gpt-image-1'));
        $this->assertSame(0.020, Tigerimage_Model_Pricing::perImage('openai', 'dall-e-2'));
        $this->assertSame(0.040, Tigerimage_Model_Pricing::perImage('gemini', 'imagen-3.0-generate-002'));
    }

    /**
     * An unknown model must NOT be free. Charging nothing for something unrecognised would make a
     * newly-released model the one thing a cap cannot stop — which is precisely when you want one.
     */
    #[Test]
    public function an_unknown_model_is_expensive_not_free(): void
    {
        $cost = Tigerimage_Model_Pricing::perImage('openai', 'some-model-shipped-yesterday');
        $this->assertGreaterThan(0.0, $cost, 'an unknown model must never be free');
        $this->assertSame(Tigerimage_Model_Pricing::UNKNOWN_COST, $cost);
        $this->assertFalse(Tigerimage_Model_Pricing::isKnown('openai', 'some-model-shipped-yesterday'));

        // and a provider we have never heard of, likewise
        $this->assertSame(Tigerimage_Model_Pricing::UNKNOWN_COST,
            Tigerimage_Model_Pricing::perImage('brand-new-co', 'anything'));
    }

    #[Test]
    public function cost_scales_with_count_and_size(): void
    {
        $one  = Tigerimage_Model_Pricing::estimate('openai', 'gpt-image-1', 1, '1024x1024');
        $four = Tigerimage_Model_Pricing::estimate('openai', 'gpt-image-1', 4, '1024x1024');
        $this->assertEqualsWithDelta($one * 4, $four, 0.00001, 'four images cost four times one');

        $big = Tigerimage_Model_Pricing::estimate('openai', 'gpt-image-1', 1, '1536x1536');
        $this->assertGreaterThan($one, $big, 'a bigger canvas costs more');
    }

    /* ---- the property that matters: a loop cannot spend past the ceiling -------------------- */

    #[Test]
    public function a_hard_cap_refuses_the_call_that_would_cross_it(): void
    {
        $this->config(['monthly_cap' => '10', 'enforce' => 'hard']);
        FakeSpend::$spent = 9.98;

        $r = FakeSpend::check('org', 0.16);            // 9.98 + 0.16 = 10.14 > 10
        $this->assertFalse($r['allowed'], 'the call that would cross the cap must be refused BEFORE it runs');
        $this->assertSame('spend_cap_reached', $r['reason']);
        $this->assertEqualsWithDelta(0.02, $r['remaining'], 0.00001);
    }

    #[Test]
    public function a_call_that_fits_is_allowed(): void
    {
        $this->config(['monthly_cap' => '10', 'enforce' => 'hard']);
        FakeSpend::$spent = 9.0;
        $r = FakeSpend::check('org', 0.16);
        $this->assertTrue($r['allowed']);
        $this->assertSame('within_cap', $r['reason']);
    }

    /** A soft cap still REPORTS the breach — the operator asked to be told, not kept in the dark. */
    #[Test]
    public function a_soft_cap_allows_but_still_reports(): void
    {
        $this->config(['monthly_cap' => '10', 'enforce' => 'soft']);
        FakeSpend::$spent = 20.0;

        $r = FakeSpend::check('org', 5.0);
        $this->assertTrue($r['allowed'], 'soft means warn, not block');
        $this->assertSame('spend_cap_reached', $r['reason'], 'but the breach is still reported');
        $this->assertSame(0.0, $r['remaining']);
    }

    /** Exactly at the cap is allowed; a penny over is not. */
    #[Test]
    public function the_boundary_is_inclusive(): void
    {
        $this->config(['monthly_cap' => '10', 'enforce' => 'hard']);
        FakeSpend::$spent = 9.5;

        $this->assertTrue(FakeSpend::check('org', 0.5)['allowed'],  'landing exactly on the cap is fine');
        $this->assertFalse(FakeSpend::check('org', 0.51)['allowed'], 'a penny over is not');
    }

    #[Test]
    public function feature_off_ignores_a_configured_cap_but_still_tracks(): void
    {
        // A cap is set, but the budget feature is OFF: no enforcement, no gauge — yet the tracked
        // total is still reported, so turning the feature on later has real history from day one.
        $this->config(['monthly_cap' => '10', 'enforce' => 'hard', 'enabled' => '0']);
        FakeSpend::$spent = 50.0;                          // far over the $10 cap

        $r = FakeSpend::check('org', 100.0);               // a huge call
        $this->assertTrue($r['allowed'], 'feature off = spend freely, no ceiling');
        $this->assertSame('uncapped', $r['reason']);

        $s = FakeSpend::summary('org');
        $this->assertFalse($s['enabled']);
        $this->assertNull($s['cap'], 'no active cap when the feature is off');
        $this->assertNull($s['enforce'], 'enforce reads null when off — not a dangling "hard" beside cap:null');
        $this->assertNull($s['binding'], 'null binding = the gauge draws nothing');
        $this->assertSame(50.0, $s['spent_this_month'], 'tracking continues regardless');
    }

    #[Test]
    public function feature_on_makes_the_cap_and_gauge_live(): void
    {
        $this->config(['monthly_cap' => '10', 'enforce' => 'hard', 'enabled' => '1']);
        FakeSpend::$spent = 9.98;
        $this->assertFalse(FakeSpend::check('org', 0.16)['allowed'], 'over the cap is refused when on');

        $s = FakeSpend::summary('org');
        $this->assertTrue($s['enabled']);
        $this->assertSame(10.0, $s['cap']);
        $this->assertNotNull($s['binding'], 'a binding drives the gauge');
    }

    /* ---- the per-token ceiling (TIGER-100 + TIGER-102) -------------------------------------- */

    private function spendConfig(array $spend): void
    {
        $spend = $spend + ["enabled" => "1"];   // default the budget feature ON so cap tests still enforce
        Zend_Registry::set("Zend_Config", new Zend_Config(["tigerimage" => ["spend" => $spend]]));
    }

    /**
     * The scenario the whole thing exists for: a scoped token handed to an agent must not be able to
     * spend the organisation's entire budget just because the org has room.
     */
    #[Test]
    public function a_token_cap_binds_even_when_the_org_has_plenty_left(): void
    {
        $this->spendConfig(['monthly_cap' => '100', 'token_cap' => '1', 'enforce' => 'hard']);
        FakeSpend::$spent = 5.0;          // org: 95 left
        FakeSpend::$tokenSpent = 0.95;    // token: 0.05 left

        $r = FakeSpend::check('org', 0.10, 'cred-1');

        $this->assertFalse($r['allowed'], 'the token is out of room even though the org is not');
        $this->assertSame('token', $r['limit'], 'and the caller is told WHICH ceiling stopped it');
        $this->assertSame(1.0, $r['cap']);
    }

    /** The org cap still binds when it is the tighter of the two. */
    #[Test]
    public function the_org_cap_binds_when_it_is_tighter(): void
    {
        $this->spendConfig(['monthly_cap' => '1', 'token_cap' => '100', 'enforce' => 'hard']);
        FakeSpend::$spent = 0.99;
        FakeSpend::$tokenSpent = 0.0;

        $r = FakeSpend::check('org', 0.10, 'cred-1');
        $this->assertFalse($r['allowed']);
        $this->assertSame('org', $r['limit']);
    }

    /**
     * A session user has no credential and is bound by the org cap alone. A human clicking Generate
     * is not the runaway risk the token cap exists for.
     */
    #[Test]
    public function a_session_user_is_not_subject_to_the_token_cap(): void
    {
        $this->spendConfig(['monthly_cap' => '100', 'token_cap' => '1', 'enforce' => 'hard']);
        FakeSpend::$spent = 5.0;
        FakeSpend::$tokenSpent = 999.0;   // irrelevant: no credential was presented

        $r = FakeSpend::check('org', 10.0, null);
        $this->assertTrue($r['allowed'], 'no credential means only the org cap applies');
        $this->assertSame('org', $r['limit']);
    }

    /**
     * A per-credential cap overrides the blanket one, so one agent can be given its own room.
     *
     * They are SEPARATE config keys on purpose: a node cannot be both a scalar and a section, so
     * nesting the overrides under token_cap would make the blanket default unreadable the moment
     * anyone set one. This test found that.
     */
    #[Test]
    public function a_per_credential_cap_overrides_the_blanket_one(): void
    {
        $this->spendConfig([
            'monthly_cap'   => '100',
            'token_cap'     => '5',
            'token_cap_for' => ['cred-vip' => '50'],
        ]);
        $this->assertSame(50.0, Tigerimage_Model_Spend::tokenCap('cred-vip'), 'the specific key wins');
        $this->assertSame(5.0,  Tigerimage_Model_Spend::tokenCap('cred-other'), 'everyone else gets the default');
    }

    #[Test]
    public function with_no_token_cap_configured_only_the_org_cap_applies(): void
    {
        $this->spendConfig(['monthly_cap' => '10', 'enforce' => 'hard']);
        FakeSpend::$spent = 1.0;
        FakeSpend::$tokenSpent = 9999.0;

        $r = FakeSpend::check('org', 1.0, 'cred-1');
        $this->assertTrue($r['allowed']);
        $this->assertSame('org', $r['limit']);
    }

    #[Test]
    public function the_summary_reports_the_token_ceiling_to_a_token_caller(): void
    {
        $this->spendConfig(['monthly_cap' => '100', 'token_cap' => '2']);
        FakeSpend::$spent = 10.0;
        FakeSpend::$tokenSpent = 1.5;

        $s = FakeSpend::summary('org', 'cred-1');
        $this->assertSame(2.0, $s['token']['cap']);
        $this->assertSame(0.5, $s['token']['remaining'], 'an agent sees the limit that actually applies to it');

        $none = FakeSpend::summary('org', null);
        $this->assertArrayNotHasKey('token', $none, 'a session caller is not shown a token ceiling');
    }

    /* ---- the cap ---------------------------------------------------------------------------- */

    #[Test]
    public function no_configured_cap_means_uncapped(): void
    {
        $this->config([]);
        $r = FakeSpend::check('org', 999.0);
        $this->assertTrue($r['allowed']);
        $this->assertSame('uncapped', $r['reason']);
        $this->assertNull($r['cap']);
    }

    /** Hard by default: an agent does not read warnings. */
    #[Test]
    public function enforcement_is_hard_unless_explicitly_soft(): void
    {
        $this->config([]);                          $this->assertSame('hard', Tigerimage_Model_Spend::enforcement());
        $this->config(['enforce' => 'nonsense']);   $this->assertSame('hard', Tigerimage_Model_Spend::enforcement());
        $this->config(['enforce' => 'HARD']);       $this->assertSame('hard', Tigerimage_Model_Spend::enforcement());
        $this->config(['enforce' => 'soft']);       $this->assertSame('soft', Tigerimage_Model_Spend::enforcement());
    }

    #[Test]
    public function a_zero_or_negative_cap_is_treated_as_uncapped_not_as_blocked(): void
    {
        // Otherwise a typo'd 0 silently blocks every generation on the install with no explanation.
        $this->config(['monthly_cap' => '0']);  $this->assertNull(Tigerimage_Model_Spend::cap());
        $this->config(['monthly_cap' => '-5']); $this->assertNull(Tigerimage_Model_Spend::cap());
        $this->config(['monthly_cap' => '']);   $this->assertNull(Tigerimage_Model_Spend::cap());
    }

    /* ---- the binding ceiling, which the gauge draws (TIGER-105) ----------------------------- */

    /**
     * The gauge draws the ceiling that will actually stop you, not the headline org figure.
     *
     * A bar showing 90%% left while the very next call is refused is worse than no bar at all — it
     * tells the user the opposite of the truth.
     */
    #[Test]
    public function the_binding_ceiling_is_the_one_with_least_headroom(): void
    {
        $this->spendConfig(['monthly_cap' => '100', 'token_cap' => '2']);
        FakeSpend::$spent      = 10.0;   // org: 90 of 100 left
        FakeSpend::$tokenSpent = 1.5;    // key: 0.5 of 2 left  <- this is what stops you

        $b = FakeSpend::summary('org', 'cred-1')['binding'];
        $this->assertSame('token', $b['limit']);
        $this->assertSame(0.5, $b['remaining']);
        $this->assertSame(0.25, $b['fraction'], 'a quarter of the KEY is left, not 90%% of the org');
    }

    /** With no token cap, the org ceiling is the binding one. */
    #[Test]
    public function the_org_ceiling_binds_when_it_is_the_tighter_one(): void
    {
        $this->spendConfig(['monthly_cap' => '10', 'token_cap' => '100']);
        FakeSpend::$spent      = 6.0;    // org: 4 of 10 left
        FakeSpend::$tokenSpent = 1.0;    // key: 99 of 100 left

        $b = FakeSpend::summary('org', 'cred-1')['binding'];
        $this->assertSame('org', $b['limit']);
        $this->assertSame(0.4, $b['fraction']);
    }

    /** Uncapped draws no gauge — inventing a ceiling would be the bar lying. */
    #[Test]
    public function an_uncapped_install_has_no_binding_ceiling(): void
    {
        $this->config([]);
        $this->assertNull(FakeSpend::summary('org')['binding'],
            'with no cap there is no fraction to draw, and a full bar would invent one');
    }

    /** A soft cap can be overrun; the bar is empty, never negative. */
    #[Test]
    public function an_overrun_soft_cap_reads_as_empty_not_negative(): void
    {
        $this->spendConfig(['monthly_cap' => '10', 'enforce' => 'soft']);
        FakeSpend::$spent = 25.0;

        $b = FakeSpend::summary('org')['binding'];
        $this->assertSame(0.0, $b['fraction'], 'a bar cannot be less than empty');
        $this->assertSame(0.0, $b['remaining']);
    }

    /**
     * Selection runs on RAW headroom, so two overrun ceilings do not tie at zero.
     *
     * If the pick were made on the floored figure, both would read 0 and whichever came first in the
     * array would be reported — naming the wrong limit to someone trying to fix it.
     */
    #[Test]
    public function the_worse_of_two_overrun_ceilings_is_the_one_named(): void
    {
        $this->spendConfig(['monthly_cap' => '10', 'token_cap' => '5', 'enforce' => 'soft']);
        FakeSpend::$spent      = 11.0;   // org over by 1
        FakeSpend::$tokenSpent = 25.0;   // key over by 20 — much worse

        $b = FakeSpend::summary('org', 'cred-1')['binding'];
        $this->assertSame('token', $b['limit'], 'the key is the deeper hole and the thing to fix');
    }

    /** The gauge and the refusal must never disagree about which ceiling bound. */
    #[Test]
    public function the_gauge_and_the_refusal_name_the_same_ceiling(): void
    {
        $this->spendConfig(['monthly_cap' => '100', 'token_cap' => '2', 'enforce' => 'hard']);
        FakeSpend::$spent      = 10.0;
        FakeSpend::$tokenSpent = 1.99;

        $refusal = FakeSpend::check('org', 0.04, 'cred-1');
        $gauge   = FakeSpend::summary('org', 'cred-1')['binding'];

        $this->assertFalse($refusal['allowed']);
        $this->assertSame($refusal['limit'], $gauge['limit'], 'one authority, or the bar lies');
        $this->assertSame($refusal['remaining'], $gauge['remaining']);
    }

    #[Test]
    public function the_summary_never_claims_to_be_a_bill(): void
    {
        $this->config(['monthly_cap' => '10']);
        $s = FakeSpend::summary('org');
        $this->assertSame('estimated', $s['basis'], 'these are estimates and must say so');
        $this->assertSame('USD', $s['currency']);
        $this->assertSame(10.0, $s['cap']);
    }
}

/** Stubs the ledger read so the cap POLICY can be tested without a database. */
final class FakeSpend extends Tigerimage_Model_Spend
{
    public static float $spent      = 0.0;
    public static float $tokenSpent = 0.0;
    public static function spentThisMonth($orgId) { return self::$spent; }
    public static function spentThisMonthByCredential($credentialId) { return self::$tokenSpent; }
}
