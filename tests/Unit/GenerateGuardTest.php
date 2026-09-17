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
        FakeSpend2::$tokenSpent = 0.0;   // static state leaks between tests otherwise
        Tiger_Agent_Provider_Factory::clearImageAdapters();
        if (Zend_Registry::isRegistered('Zend_Config')) { Zend_Registry::set('Zend_Config', null); }
    }

    private function boot(array $spend): GuardableImageService
    {
        $spend = $spend + ['enabled' => '1'];   // the budget feature is OFF by default; these tests exercise the cap, so turn it on
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tigerimage' => ['provider' => 'openai', 'model' => 'gpt-image-1', 'spend' => $spend],
        ]));
        Tiger_Agent_Provider_Factory::registerImageAdapter('openai', new SpyImageAdapter());
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

    /**
     * The refusal names the ceiling that bound, because telling someone the ORGANISATION is out of
     * budget when it was their key sends them to fix the wrong thing.
     */
    #[Test]
    public function the_refusal_names_the_ceiling_that_bound(): void
    {
        $svc = $this->boot(['monthly_cap' => '100', 'token_cap' => '1', 'enforce' => 'hard']);
        $svc->credentialId = 'cred-7';
        GuardableImageService::$spent = 0.0;      // the org has plenty; the KEY does not
        FakeSpend2::$tokenSpent = 0.99;

        $svc->generate(['prompt' => 'a tiger', 'n' => 1]);

        $this->assertSame(0, SpyImageAdapter::$calls, 'still refused before the provider is contacted');
        $this->assertSame('tigerimage.error.spend_cap_reached_token', $svc->lastError,
            'the message must say the KEY is spent, not the organisation');
    }

    /** An org-bound refusal keeps the organisation-worded message. */
    #[Test]
    public function an_org_refusal_still_names_the_organisation(): void
    {
        $svc = $this->boot(['monthly_cap' => '10', 'enforce' => 'hard']);
        GuardableImageService::$spent = 9.99;

        $svc->generate(['prompt' => 'a tiger', 'n' => 4]);

        $this->assertSame('tigerimage.error.spend_cap_reached', $svc->lastError);
    }

    /**
     * The credential must be CHECKED against and RECORDED, or the per-token cap is decorative.
     *
     * Mutation testing caught both: dropping the credential from the spend check, and storing null
     * instead of it, each left every other test passing. In production the first means a token cap is
     * never enforced, and the second means spend is never attributable — so the cap can never bind
     * even when it is enforced. Neither failure is visible from the outside.
     */
    #[Test]
    public function the_credential_is_checked_against_and_recorded(): void
    {
        $svc = $this->boot(['monthly_cap' => '100', 'enforce' => 'hard']);
        $svc->credentialId = 'cred-7';
        GuardableImageService::$spent = 0.0;

        $svc->generate(['prompt' => 'a tiger', 'n' => 1]);

        $this->assertSame('cred-7', $svc->checkedCredential, 'the ceiling must be checked for THIS key');
        $this->assertSame('cred-7', $svc->storedCredential,  'and the spend recorded against it');
    }

    /** A session user records no credential — null means "a human", not "unknown". */
    #[Test]
    public function a_session_user_records_no_credential(): void
    {
        $svc = $this->boot(['monthly_cap' => '100', 'enforce' => 'hard']);
        GuardableImageService::$spent = 0.0;

        $svc->generate(['prompt' => 'a tiger', 'n' => 1]);

        $this->assertNull($svc->storedCredential, 'a human clicking Generate is bound by the org cap alone');
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
    public function supportsModel($model) { return true; }
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
    public ?string $credentialId = null;
    public ?string $checkedCredential = null;
    public ?string $storedCredential = null;

    protected function _isAdmin($resource = null, $privilege = null) { return true; }
    protected function _capability() { return ['available' => true, 'provider' => 'openai', 'model' => 'gpt-image-1']; }
    protected function _apiKey(array $resolved) { return 'test-key'; }

    /** The ledger read is the DB-bound part; the POLICY under test is Spend::check itself. */
    protected function _spendCheck($orgId, $estimate, $credentialId = null)
    {
        $this->checkedCredential = $credentialId;
        return FakeSpend2::check($orgId, $estimate, $credentialId);
    }

    /** No session in a unit test, so the identity read is stubbed with whatever the test set. */
    protected function _credentialId() { return $this->credentialId; }

    /** The store is a seam so WHAT GETS RECORDED is assertable without a database. */
    protected function _library()
    {
        return new RecordingLibrary($this);
    }

    protected function _spendSummary($orgId, $credentialId = null) { return []; }
    protected function _describeMany(array $ids, $orgId) { return []; }
    protected function _orgId()   { return 'org-1'; }
    protected function _error($message = 'core.api.error.general', $data = null)
    {
        $this->lastError = (string) $message;
    }

    protected function _success($data = null, $message = 'core.api.success', $redirect = null) { }
}

/** The ledger read, stubbed. */

/** Captures the row TigerImage would have written, so attribution is assertable with no database. */
final class RecordingLibrary extends Tigerimage_Service_Library
{
    public function __construct(private GuardableImageService $svc) {}

    public function store(array $result, array $meta)
    {
        $this->svc->storedCredential = $meta['credential_id'] ?? null;
        return ['img-1'];
    }
}

/** Stubs only the ledger read, so the real cap policy runs. */
final class FakeSpend2 extends Tigerimage_Model_Spend
{
    public static float $tokenSpent = 0.0;
    public static function spentThisMonth($orgId) { return GuardableImageService::$spent; }
    public static function spentThisMonthByCredential($credentialId) { return self::$tokenSpent; }
}
