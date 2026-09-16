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
 * module still read "Active". This guards against that exact regression.
 *
 * The file is read as raw text: dotted keys (`acl.resources.k.resource`) are Zend_Config_Ini's
 * expansion, which native parse_ini_file does not do, so matching the source is the honest check.
 */
final class AclFormatTest extends TestCase
{
    private function raw(): string
    {
        $f = dirname(__DIR__, 2) . '/configs/acl.ini';
        $this->assertFileExists($f);
        return (string) file_get_contents($f);
    }

    /** The class each `acl.resources.{k}.resource = "Class"` declares. */
    private function declaredResources(string $raw): array
    {
        preg_match_all('/^\s*acl\.resources\.[A-Za-z0-9_]+\.resource\s*=\s*"([^"]+)"/m', $raw, $m);
        return $m[1];
    }

    #[Test]
    public function it_declares_resources_and_rules_not_the_dead_allow_shape(): void
    {
        $raw = $this->raw();
        $this->assertDoesNotMatchRegularExpression(
            '/acl\.resources\.[A-Za-z0-9_]+\.allow\./', $raw,
            'the `acl.resources.<Class>.allow.<role>` shape registers no resource — use acl.resources.{k}.resource + acl.rules.{k}'
        );
        $this->assertNotEmpty($this->declaredResources($raw), 'no `acl.resources.{k}.resource` declarations found');
        $this->assertMatchesRegularExpression('/^\s*acl\.rules\.[A-Za-z0-9_]+\.role\s*=/m', $raw, 'no `acl.rules.{k}.role` rules found');
    }

    #[Test]
    public function every_api_service_is_a_granted_resource(): void
    {
        $raw = $this->raw();
        $declared = $this->declaredResources($raw);
        foreach (glob(dirname(__DIR__, 2) . '/services/*.php') as $svc) {
            if (strpos((string) file_get_contents($svc), '@api') === false) { continue; }
            $class = 'Tigerimage_Service_' . basename($svc, '.php');
            $this->assertContains($class, $declared, "$class is @api but not declared in acl.ini — it would be invisible to the agent");
            $this->assertMatchesRegularExpression(
                '/acl\.rules\.[A-Za-z0-9_]+\.resource\s*=\s*"' . preg_quote($class, '/') . '"/', $raw,
                "$class has no allow rule — deny-by-default hides it"
            );
        }
    }

    #[Test]
    public function it_loads_on_every_environment(): void
    {
        $raw = $this->raw();
        foreach (['staging : production', 'testing : production', 'development : production'] as $sec) {
            $this->assertStringContainsString('[' . $sec . ']', $raw, "acl.ini needs a [$sec] section or Zend_Config_Ini throws on that env and skips the file");
        }
    }
}
