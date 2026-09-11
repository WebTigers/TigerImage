<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Which provider draws, and the capability answer an agent must get BEFORE it promises a user an
 * image (TIGER-99).
 *
 * The case that matters most: an install whose chat agent is Anthropic. Claude cannot draw, so
 * "available" must be false with a reason — not a confusing 401 at call time after the agent has
 * already said "sure, I'll add a picture".
 */
#[CoversClass(Tigerimage_Model_Provider::class)]
final class ProviderResolveTest extends TestCase
{
    protected function tearDown(): void
    {
        if (Zend_Registry::isRegistered('Zend_Config')) { Zend_Registry::set('Zend_Config', null); }
    }

    private function config(array $tigerimage = [], array $agent = []): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tigerimage' => $tigerimage,
            'tiger'      => ['agent' => $agent],
        ]));
    }

    #[Test]
    public function an_explicit_drawing_provider_wins(): void
    {
        $this->config(['provider' => 'openai', 'model' => 'gpt-image-1']);
        $r = Tigerimage_Model_Provider::resolve();
        $this->assertSame('openai', $r['provider']);
        $this->assertSame('gpt-image-1', $r['model']);
        $this->assertSame('tigerimage', $r['source']);
    }

    /**
     * Configured but incapable must NOT silently fall through to the agent's provider — that would
     * hide a misconfiguration behind an install that looks like it works.
     */
    #[Test]
    public function a_configured_but_incapable_provider_resolves_to_nothing(): void
    {
        $this->config(['provider' => 'anthropic', 'model' => 'claude-opus-5']);
        $this->assertNull(Tigerimage_Model_Provider::resolve());
    }

    /**
     * The case that actually pins the early return: TigerImage is misconfigured to a provider that
     * cannot draw, while the AGENT happens to have one that can. Falling through would quietly use
     * the agent's and make a broken setting look like a working install — the operator would never
     * learn their tigerimage.provider is wrong. (Found by mutation: without this, deleting the early
     * return changed nothing, because the default agent provider also cannot draw.)
     */
    #[Test]
    public function a_misconfigured_provider_does_not_fall_through_to_a_capable_agent(): void
    {
        $this->config(
            ['provider' => 'anthropic', 'model' => 'claude-opus-5'],   // TigerImage: cannot draw
            ['provider' => 'openai',    'model' => 'gpt-image-1']      // agent: CAN draw
        );
        $this->assertNull(
            Tigerimage_Model_Provider::resolve(),
            'an explicit but incapable setting must surface as unconfigured, not silently use the agent'
        );
    }

    #[Test]
    public function nothing_configured_and_no_capable_agent_is_unavailable(): void
    {
        $this->config([], []);
        $cap = Tigerimage_Model_Provider::capability();
        $this->assertFalse($cap['available']);
        $this->assertSame('no_image_provider', $cap['reason']);
        // the answer must be actionable — name what WOULD work
        $this->assertNotEmpty($cap['providers']);
        $this->assertContains('openai', $cap['providers']);
    }

    /** The whole premise: a Claude install cannot draw, and must say so before promising. */
    #[Test]
    public function a_claude_install_reports_unavailable_rather_than_failing_later(): void
    {
        $this->config([], ['provider' => 'anthropic', 'model' => 'claude-opus-5']);
        $cap = Tigerimage_Model_Provider::capability();

        $this->assertFalse($cap['available'], 'Claude cannot draw — this is the case the module exists for');
        $this->assertSame('no_image_provider', $cap['reason']);
        $this->assertStringContainsString('tigerimage.provider', $cap['detail']);
    }

    #[Test]
    public function a_configured_provider_with_no_key_is_a_different_reason(): void
    {
        $this->config(['provider' => 'openai', 'model' => 'gpt-image-1']);
        $cap = Tigerimage_Model_Provider::capability();

        $this->assertFalse($cap['available']);
        // distinguishable from no_image_provider: the fix is a key, not a provider
        $this->assertSame('no_api_key', $cap['reason']);
        $this->assertSame('openai', $cap['provider']);
    }

    #[Test]
    public function every_listed_provider_can_actually_draw(): void
    {
        foreach (Tiger_Agent_Provider_Factory::imageProviders() as $p) {
            $this->assertTrue(
                Tiger_Agent_Provider_Factory::make($p) instanceof Tiger_Agent_Provider_ImageAdapter,
                "$p is listed as an image provider but its adapter cannot draw"
            );
        }
    }
}
