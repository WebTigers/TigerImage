<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `capability()` must tell an agent it is out of budget WITHOUT spending anything to find out, and
 * must say WHICH ceiling stopped it.
 *
 * An agent told only "no budget" cannot tell whether its own key has been throttled — in which case
 * another key or a human could still proceed — or whether the organisation has stopped entirely.
 * Those call for different responses, so the answer names the limit.
 *
 * @covers Tigerimage_Service_Image
 */
#[CoversClass(Tigerimage_Service_Image::class)]
final class CapabilityBudgetTest extends TestCase
{
    protected function setUp(): void
    {
        CapabilityProbeService::$summary = [];
        CapabilityProbeService::$askedFor = [];
    }

    /**
     * The credential must reach the summary, or the answer can never carry a token ceiling.
     *
     * Mutation testing caught this: passing null instead of the credential left every assertion above
     * passing, because the stubbed summary ignored its arguments — and in production it would mean an
     * agent is never told about the limit that actually applies to it.
     */
    #[Test]
    public function the_credential_is_passed_to_the_summary(): void
    {
        $this->capabilityWith(['remaining' => 90.0, 'enforce' => 'hard']);

        $this->assertSame(['org-1', 'cred-1'], CapabilityProbeService::$askedFor,
            'the spend picture must be asked for on behalf of the KEY, not just the org');
    }

    /** An exhausted TOKEN closes the door even though the org has budget left. */
    #[Test]
    public function an_exhausted_token_makes_the_module_unavailable(): void
    {
        $cap = $this->capabilityWith([
            'remaining' => 90.0, 'enforce' => 'hard',
            'token'     => ['spent_this_month' => 5.0, 'cap' => 5.0, 'remaining' => 0.0],
        ]);

        $this->assertFalse($cap['available'], 'the key is spent, so nothing can be generated with it');
        $this->assertSame('spend_cap_reached', $cap['reason']);
        $this->assertSame('token', $cap['limit'], 'it must say the KEY is what ran out, not the org');
        $this->assertStringContainsString('access key', $cap['detail']);
    }

    /** An exhausted ORG cap is reported as the org's, not blamed on the key. */
    #[Test]
    public function an_exhausted_org_cap_is_named_as_the_org(): void
    {
        $cap = $this->capabilityWith([
            'remaining' => 0.0, 'enforce' => 'hard',
            'token'     => ['spent_this_month' => 1.0, 'cap' => 50.0, 'remaining' => 49.0],
        ]);

        $this->assertFalse($cap['available']);
        $this->assertSame('org', $cap['limit'], 'the key has room; the organisation does not');
        $this->assertStringContainsString('organisation', $cap['detail']);
    }

    /** Room on both ceilings leaves the module available and names no limit. */
    #[Test]
    public function room_on_both_ceilings_stays_available(): void
    {
        $cap = $this->capabilityWith([
            'remaining' => 90.0, 'enforce' => 'hard',
            'token'     => ['spent_this_month' => 1.0, 'cap' => 5.0, 'remaining' => 4.0],
        ]);

        $this->assertTrue($cap['available']);
        $this->assertArrayNotHasKey('limit', $cap, 'nothing is binding, so nothing is named');
    }

    /** A SOFT cap reports the overage but does not close the door — that is what soft means. */
    #[Test]
    public function a_soft_cap_does_not_close_the_door(): void
    {
        $cap = $this->capabilityWith([
            'remaining' => 90.0, 'enforce' => 'soft',
            'token'     => ['spent_this_month' => 9.0, 'cap' => 5.0, 'remaining' => 0.0],
        ]);

        $this->assertTrue($cap['available'], 'soft warns; it does not stop');
    }

    /** An uncapped org with no token ceiling is never reported as out of budget. */
    #[Test]
    public function an_uncapped_org_is_never_out_of_budget(): void
    {
        $cap = $this->capabilityWith(['remaining' => null, 'enforce' => 'hard']);

        $this->assertTrue($cap['available'], 'null remaining means uncapped, not zero left');
    }

    /** Run capability() against a stubbed spend picture and return the answer. */
    private function capabilityWith(array $summary): array
    {
        CapabilityProbeService::$summary = $summary;
        $svc = new CapabilityProbeService();
        $svc->capability([]);
        return $svc->answer;
    }
}

/** Stubs the provider register and the ledger, leaving only the budget branch under test. */
final class CapabilityProbeService extends Tigerimage_Service_Image
{
    public static array $summary = [];
    public static array $askedFor = [];
    public array $answer = [];

    protected function _capability() { return ['available' => true, 'provider' => 'openai', 'model' => 'gpt-image-1']; }
    protected function _spendSummary($orgId, $credentialId = null)
    {
        self::$askedFor = [$orgId, $credentialId];
        return self::$summary;
    }
    protected function _credentialId() { return 'cred-1'; }
    protected function _orgId() { return 'org-1'; }
    protected function _success($data = null, $message = 'core.api.success', $redirect = null)
    {
        $this->answer = (array) $data;
    }
}
