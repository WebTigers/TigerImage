<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
namespace Tiger\TigerImage\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * configs/acl.ini must speak the shape Tiger_Acl_Acl reads — `acl.resources.{k}.resource = "Class"`
 * plus `acl.rules.{k}.role/.resource/.permission`. Through 1.0.4 it used an invented
 * `acl.resources.<Class>.allow.<role>` that registers NOTHING: the resource never entered Zend_Acl,
 * so deny-by-default hid every @api service from the agent tool catalog (no MCP tools) — while the
 * module still read "Active". This guards against that exact regression, and that every @api service
 * is actually granted.
 */
final class AclFormatTest extends TestCase
{
    private function ini(): array
    {
        $f = dirname(__DIR__, 2) . '/configs/acl.ini';
        $this->assertFileExists($f);
        return parse_ini_file($f, true, INI_SCANNER_RAW);
    }

    #[Test]
    public function it_declares_resources_and_rules_not_the_dead_allow_shape(): void
    {
        $flat = file_get_contents(dirname(__DIR__, 2) . '/configs/acl.ini');
        $this->assertDoesNotMatchRegularExpression(
            '/acl\.resources\.[A-Za-z0-9_]+\.allow\./', $flat,
            'the `acl.resources.<Class>.allow.<role>` shape registers no resource — use acl.resources.{k}.resource + acl.rules.{k}'
        );
        $prod = $this->ini()['production'] ?? [];
        $this->assertNotEmpty($prod['acl']['resources'] ?? [], 'no resources declared');
        $this->assertNotEmpty($prod['acl']['rules'] ?? [], 'no rules declared');
        foreach ($prod['acl']['resources'] as $k => $r) {
            $this->assertArrayHasKey('resource', $r, "resource `$k` must name a class via `.resource`");
            $this->assertMatchesRegularExpression('/^[A-Za-z][A-Za-z0-9_]+$/', $r['resource']);
        }
    }

    #[Test]
    public function every_api_service_is_a_granted_resource(): void
    {
        $prod = $this->ini()['production'] ?? [];
        $declared = array_column($prod['acl']['resources'] ?? [], 'resource');
        foreach (glob(dirname(__DIR__, 2) . '/services/*.php') as $svc) {
            $src = (string) file_get_contents($svc);
            if (strpos($src, '@api') === false) { continue; }   // only agent-callable services must be gated
            $class = 'Tigerimage_Service_' . basename($svc, '.php');
            $this->assertContains($class, $declared, "$class is @api but not declared in acl.ini — it would be invisible to the agent");
            $rulesFor = array_filter($prod['acl']['rules'], fn ($r) => ($r['resource'] ?? '') === $class);
            $this->assertNotEmpty($rulesFor, "$class has no allow rule — deny-by-default hides it");
        }
    }

    #[Test]
    public function it_loads_on_every_environment(): void
    {
        // A section-less env (only [production]) throws in Zend_Config_Ini on dev/staging → the whole
        // acl.ini is skipped there. Require the inheritance sections other modules carry.
        $ini = $this->ini();
        foreach (['staging : production', 'testing : production', 'development : production'] as $sec) {
            $this->assertArrayHasKey($sec, $ini, "acl.ini needs a [$sec] section or it throws on that env");
        }
    }
}
