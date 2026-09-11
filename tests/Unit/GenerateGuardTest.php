<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The enforcement POINT (TIGER-100) — that generate() actually refuses before contacting a provider.
 *
 * The cap policy is tested in SpendTest. This covers the line that matters most in the whole ticket:
 * the guard sitting between the capability check and the adapter call. Mutation testing showed the
 * policy tests alone leave that wiring unguarded — deleting the check changed nothing.
 *
 * The provider is a spy that records whether it was called. "Did we contact a paid API" is the
 * property under test, and it is the only one that costs money to get wrong.
 */
final class GenerateGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        SpyImageAdapter::$calls = 0;
        Tiger_Agent_Provider_Factory::setAdapter(null);
        if (Zend_Registry::isRegistered('Zend_Config')) { Zend_Registry::set('Zend_Config', null); }
    }

    private function boot(array $spend): GuardableImageService
    {
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tigerimage' => ['provider' => 'openai', 'model' => 'gpt-image-1', 'spend' => $spend],
        ]));
        Tiger_Agent_Provider_Factory::setAdapter(new SpyImageAdapter());
        return new GuardableImageService();
    }

    #[Test]
    public function a_hard_cap_refuses_before_the_provider_is_contacted(): void
    {
        $svc = $this->boot(['monthly_cap' => '10', 'enforce' => 'hard']);
        GuardableImageService::$spent = 9.99;

        $svc->generate(['prompt' => 'a tiger', 'n' => 4]);

        $this->assertSame(0, SpyImageAdapter::$calls,
            'THE property: no paid API call may happen once the cap is reached');
        $this->assertSame('tigerimage.error.spend_cap_reached', $svc->lastError);
    }

    #[Test]
    public function under_the_cap_the_provider_is_reached(): void
    {
        $svc = $this->boot(['monthly_cap' => '100', 'enforce' => 'hard']);
        GuardableImageService::$spent = 0.0;

        $svc->generate(['prompt' => 'a tiger', 'n' => 1]);

        $this->assertSame(1, SpyImageAdapter::$calls, 'a call within budget must go through');
    }

    /** Soft means warn, not block — the provider IS reached. */
    #[Test]
    public function a_soft_cap_does_not_block_the_call(): void
    {
        $svc = $this->boot(['monthly_cap' => '1', 'enforce' => 'soft']);
        GuardableImageService::$spent = 99.0;

        $svc->generate(['prompt' => 'a tiger', 'n' => 1]);

        $this->assertSame(1, SpyImageAdapter::$calls, 'soft warns rather than blocking');
    }

    #[Test]
    public function uncapped_installs_are_not_blocked(): void
    {
        $svc = $this->boot([]);
        GuardableImageService::$spent = 9999.0;

        $svc->generate(['prompt' => 'a tiger', 'n' => 1]);

        $this->assertSame(1, SpyImageAdapter::$calls, 'no cap configured means no ceiling');
    }
}

/** Records whether a paid call was made. */
final class SpyImageAdapter implements Tiger_Agent_Provider_Adapter, Tiger_Agent_Provider_ImageAdapter
{
    public static int $calls = 0;
    public function complete($system, array $messages, $model, $apiKey) { return ['text' => '', 'usage' => []]; }
    public function models($apiKey = '') { return []; }
    public function generateImage($prompt, array $options, $model, $apiKey)
    {
        self::$calls++;
        return ['images' => [['mime' => 'image/png', 'data' => base64_encode('x')]], 'params' => []];
    }
}

/** Stubs everything around the guard — identity, ledger read, key, and the store write. */
final class GuardableImageService extends Tigerimage_Service_Image
{
    public static float $spent = 0.0;
    public string $lastError = '';

    protected function _isAdmin($resource = null, $privilege = null) { return true; }
    protected function _capability() { return ['available' => true, 'provider' => 'openai', 'model' => 'gpt-image-1']; }
    protected function _apiKey(array $resolved) { return 'test-key'; }

    /** The ledger read is the DB-bound part; the POLICY under test is Spend::check itself. */
    protected function _spendCheck($orgId, $estimate)
    {
        return FakeSpend2::check($orgId, $estimate);
    }
    protected function _orgId()   { return 'org-1'; }
    protected function _error($message = 'core.api.error.general', $data = null)
    {
        $this->lastError = (string) $message;
    }

    protected function _success($data = null, $message = 'core.api.success', $redirect = null) { }
}

/** The ledger read, stubbed. */

/** Stubs only the ledger read, so the real cap policy runs. */
final class FakeSpend2 extends Tigerimage_Model_Spend
{
    public static function spentThisMonth($orgId) { return GuardableImageService::$spent; }
}
