<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The Bootstrap contract (TIGER-103).
 *
 * A module Bootstrap that throws is fatal during Resource_Modules — it takes down EVERY page, not
 * just this module's. That is exactly what happened on first deploy: Tigerimage_Provider_* was not
 * loadable because ZF1's resource loader knows nothing about a `providers/` directory, and the
 * class-not-found killed the whole application boot.
 *
 * So two properties are guarded here: the provider namespace is declared, and registration cannot
 * escalate a missing class into a dead site.
 */
final class BootstrapTest extends TestCase
{
    private function src(): string
    {
        return file_get_contents(dirname(__DIR__, 2) . '/Bootstrap.php');
    }

    #[Test]
    public function it_declares_the_provider_resource_type(): void
    {
        $this->assertStringContainsString("addResourceType('provider', 'providers', 'Provider')", $this->src(),
            'without this, Tigerimage_Provider_* is not loadable and the app boot fatals');
    }

    #[Test]
    public function registration_cannot_take_down_the_site(): void
    {
        $src = $this->src();
        $this->assertStringContainsString('catch (Throwable', $src,
            'a throwing module Bootstrap is fatal for every page, not just this module');
        $this->assertStringContainsString('class_exists($class)', $src,
            'a missing provider class must degrade to "cannot draw", not to a white screen');
    }

    #[Test]
    public function the_providers_directory_exists_and_is_declared_in_the_manifest(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertDirectoryExists($root . '/providers');
        $m = json_decode(file_get_contents($root . '/module.json'), true);
        $this->assertArrayHasKey('providers', $m['paths']);
    }
}
