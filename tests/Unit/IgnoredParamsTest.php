<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * generate() must TELL the caller when a param it passed is not honored by the live provider
 * (TIGER-173). The per-provider honored set is truthfully reported by the capability answer's
 * `params`, but generate() used to DROP an unhonored knob silently — so a caller who passed a
 * `negative` prompt to OpenAI's gpt-image (which ignores it) believed a constraint was in force.
 * Round-4 shipped a garbled sign for exactly this reason.
 *
 * The report is informational, never a refusal: the images are still generated. These tests seam
 * the provider exactly as GenerateGuardTest does (config + a spy adapter), and stub `_capability`
 * to carry an OpenAI-shaped honored set (no `negative`, no `seed`).
 */
final class IgnoredParamsTest extends TestCase
{
    protected function tearDown(): void
    {
        IgnoredParamsSpyAdapter::$calls = 0;
        Tiger_Agent_Provider_Factory::clearImageAdapters();
        if (Zend_Registry::isRegistered('Zend_Config')) { Zend_Registry::set('Zend_Config', null); }
    }

    private function boot(): IgnoredParamsImageService
    {
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tigerimage' => ['provider' => 'openai', 'model' => 'gpt-image-1'],
        ]));
        Tiger_Agent_Provider_Factory::registerImageAdapter('openai', new IgnoredParamsSpyAdapter());
        return new IgnoredParamsImageService();
    }

    /** @return string[] the info-message texts on the response */
    private function infoMessages(Tiger_Model_ResponseObject $res): array
    {
        $out = [];
        foreach ($res->messages as $m) {
            if ($m->class === 'info') { $out[] = $m->message; }
        }
        return $out;
    }

    #[Test]
    public function an_unhonored_param_is_reported_but_the_call_still_succeeds(): void
    {
        $svc = $this->boot();
        $svc->generate(['prompt' => 'a shop sign that reads OPEN', 'negative' => 'blurry', 'n' => 1]);

        $res = $svc->getResponse();
        $this->assertSame(1, $res->result, 'the call still succeeds — this is informational, not a refusal');
        $this->assertSame(1, IgnoredParamsSpyAdapter::$calls, 'the provider was still contacted');

        $this->assertContains('negative', $res->data['ignored_params'],
            'the dropped knob is reported in structured data an agent can act on');

        $messages = $this->infoMessages($res);
        $this->assertNotEmpty($messages, 'an informational message must be emitted');
        $this->assertContains('negative is not honored by openai and was ignored.', $messages);
    }

    #[Test]
    public function a_honored_param_is_not_reported(): void
    {
        $svc = $this->boot();
        // size + n are in the OpenAI honored set — passing them warns about nothing.
        $svc->generate(['prompt' => 'a tiger', 'size' => '1024x1024', 'n' => 2]);

        $res = $svc->getResponse();
        $this->assertSame([], $res->data['ignored_params'], 'nothing dropped, nothing warned');
        $this->assertSame([], $this->infoMessages($res));
    }

    #[Test]
    public function only_supplied_params_are_reported(): void
    {
        $svc = $this->boot();
        // `seed` is unhonored by OpenAI but NOT supplied here; `negative` is unhonored AND supplied.
        $svc->generate(['prompt' => 'a tiger', 'negative' => 'blurry', 'seed' => '', 'n' => 1]);

        $ignored = $svc->getResponse()->data['ignored_params'];
        $this->assertContains('negative', $ignored);
        $this->assertNotContains('seed', $ignored, 'a param the caller did not actually pass is not warned about');
    }

    #[Test]
    public function an_unknown_honored_set_warns_about_nothing(): void
    {
        // An older core / an adapter without imageParams() reports no `params`. We cannot know what is
        // honored, so we must not guess — warn about nothing rather than about everything.
        $svc = $this->boot();
        $svc->honoredParams = [];   // capability answer carries no honored set
        $svc->generate(['prompt' => 'a tiger', 'negative' => 'blurry', 'seed' => '42', 'n' => 1]);

        $res = $svc->getResponse();
        $this->assertSame([], $res->data['ignored_params']);
        $this->assertSame([], $this->infoMessages($res));
    }
}

/** Records that the paid call happened; draws nothing real. */
final class IgnoredParamsSpyAdapter implements Tiger_Agent_Provider_Adapter, Tiger_Agent_Provider_ImageAdapter
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

/**
 * Stubs the seams around the honored-param comparison, but keeps the REAL _success/_error so the
 * emitted messages land on the response envelope. `_capability` carries an OpenAI-shaped honored set
 * (no `negative`, no `seed`) — the truth the comparison is checked against.
 */
final class IgnoredParamsImageService extends Tigerimage_Service_Image
{
    /** The capability answer's honored `params` — overridable per test. */
    public array $honoredParams = [
        ['name' => 'size'], ['name' => 'n'], ['name' => 'quality'],
        ['name' => 'background'], ['name' => 'output_format'], ['name' => 'output_compression'],
    ];

    protected function _isAdmin($resource = null, $privilege = null) { return true; }
    protected function _capability()
    {
        return ['available' => true, 'provider' => 'openai', 'model' => 'gpt-image-1', 'params' => $this->honoredParams];
    }
    protected function _apiKey(array $resolved) { return 'test-key'; }
    protected function _spendCheck($orgId, $estimate, $credentialId = null) { return ['allowed' => true]; }
    protected function _spendSummary($orgId, $credentialId = null) { return []; }
    protected function _credentialId() { return null; }
    protected function _orgId() { return 'org-1'; }
    protected function _describeMany(array $ids, $orgId) { return []; }
    protected function _library() { return new IgnoredParamsRecordingLibrary(); }
}

/** The store, stubbed so no database is needed. */
final class IgnoredParamsRecordingLibrary extends Tigerimage_Service_Library
{
    public function __construct() {}
    public function store(array $result, array $meta) { return ['img-1']; }
}
