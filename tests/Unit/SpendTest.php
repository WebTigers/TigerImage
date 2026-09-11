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
        Zend_Registry::set('Zend_Config', new Zend_Config(['tigerimage' => ['spend' => $spend]]));
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
    public static float $spent = 0.0;
    public static function spentThisMonth($orgId) { return self::$spent; }
}
