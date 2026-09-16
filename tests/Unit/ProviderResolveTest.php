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
    /**
     * Register the module's real adapters, exactly as Tigerimage_Bootstrap does.
     *
     * Core holds only the register now (TIGER-103), so capability is false until a module registers.
     * Using the REAL adapters rather than fakes means these tests also prove the registration path
     * the Bootstrap relies on.
     */
    protected function setUp(): void
    {
        Tiger_Agent_Provider_Factory::clearImageAdapters();
        Tiger_Agent_Provider_Factory::registerImageAdapter('openai', new Tigerimage_Adapter_OpenAi());
        Tiger_Agent_Provider_Factory::registerImageAdapter('gemini', new Tigerimage_Adapter_Gemini());
    }

    protected function tearDown(): void
    {
        Tiger_Agent_Provider_Factory::clearImageAdapters();
        if (Zend_Registry::isRegistered('Zend_Config')) { Zend_Registry::set('Zend_Config', null); }
    }

    /** Without the module's registration, core reports no capability at all — the loose-coupling test. */
    #[Test]
    public function core_alone_cannot_draw(): void
    {
        Tiger_Agent_Provider_Factory::clearImageAdapters();
        $this->config(['provider' => 'openai', 'model' => 'gpt-image-1']);

        $this->assertNull(Tigerimage_Model_Provider::resolve(),
            'with no adapter registered, even a correctly configured provider cannot draw');
        $cap = Tigerimage_Model_Provider::capability();
        $this->assertFalse($cap['available']);
        $this->assertSame([], $cap['providers']);
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

    /**
     * The wire Beau asked for (TIGER-147): the agent is set to a DRAWING provider (OpenAI) but with a
     * TEXT model (gpt-4.1, the agent's chat choice). TigerImage should still draw — reusing the same
     * provider + key with that provider's DEFAULT image model — so a second config is never needed.
     */
    #[Test]
    public function the_agent_provider_and_key_satisfy_images_even_on_a_text_model(): void
    {
        $this->config([], ['provider' => 'openai', 'model' => 'gpt-4.1']);   // agent: OpenAI, a TEXT model
        $r = Tigerimage_Model_Provider::resolve();
        $this->assertNotNull($r, 'OpenAI can draw, so the agent provider must satisfy images');
        $this->assertSame('openai', $r['provider']);
        $this->assertSame('agent', $r['source'], 'reuses the agent provider + key');
        $this->assertTrue(Tiger_Agent_Provider_Factory::canGenerateImages($r['provider'], $r['model']),
            'resolved to an actual drawing model (the provider default), not the agent text model');
        $this->assertNotSame('gpt-4.1', $r['model']);
        // No key set here, so capability is not yet available — but the reason has shifted from
        // "nothing can draw" to "a provider is recognized, add its key" (before the wire it was
        // no_image_provider). That shift IS the fix.
        $cap = Tigerimage_Model_Provider::capability();
        $this->assertFalse($cap['available']);
        $this->assertSame('no_api_key', $cap['reason'], 'the agent provider is recognized as image-capable; only the key is missing');
        $this->assertStringContainsString('agent settings', $cap['detail']);
    }

    /** A non-drawing agent provider (Anthropic) still cannot draw, whatever its model. */
    #[Test]
    public function a_non_drawing_agent_provider_still_cannot_draw(): void
    {
        $this->config([], ['provider' => 'anthropic', 'model' => 'claude-opus-5']);
        $this->assertNull(Tigerimage_Model_Provider::resolve());
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

    /**
     * Every listed provider must have a REGISTERED adapter — not merely a core adapter of the same
     * name. Factory::make() returns the core TEXT adapter, which by design cannot draw, so asking it
     * would now be the wrong question (TIGER-103).
     */
    #[Test]
    public function every_listed_provider_has_a_registered_drawing_adapter(): void
    {
        $listed = Tiger_Agent_Provider_Factory::imageProviders();
        $this->assertNotEmpty($listed, 'the module registered adapters in setUp');

        foreach ($listed as $p) {
            $adapter = Tiger_Agent_Provider_Factory::imageAdapter($p);
            $this->assertInstanceOf(Tiger_Agent_Provider_ImageAdapter::class, $adapter,
                "$p is listed but has no registered image adapter");
            $this->assertNotInstanceOf(Tiger_Agent_Provider_ImageAdapter::class,
                Tiger_Agent_Provider_Factory::make($p),
                "the CORE adapter for $p must not draw — that is the coupling this removed");
        }
    }
}
