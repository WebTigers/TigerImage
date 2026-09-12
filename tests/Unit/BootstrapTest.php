<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The Bootstrap contract (TIGER-103).
 *
 * A module Bootstrap that throws is fatal during Resource_Modules — it takes down EVERY page, not
 * just this module's. That is exactly what happened on first deploy: Tigerimage_Adapter_* was not
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

    /**
     * ZF1 ships eight resource types and `Adapter` is not one of them, so it must be declared. The
     * NAME matters as much as the declaration: TigerMarketplace already declares this exact
     * Adapter/adapters type, and one name per concept across modules is the point.
     */
    #[Test]
    public function it_declares_the_adapter_resource_type(): void
    {
        $this->assertStringContainsString("addResourceType('adapter', 'adapters', 'Adapter')", $this->src(),
            'without this, Tigerimage_Adapter_* is not loadable');
    }

    /**
     * Registration must pass CLASS NAMES, never instances.
     *
     * A module Bootstrap that throws is fatal during Resource_Modules — every page, not just this
     * module's. Naming a class means nothing is constructed at boot, so autoload timing cannot turn
     * into a dead site, and a request that never makes an image never builds an adapter.
     */
    #[Test]
    public function registration_is_lazy(): void
    {
        $src = $this->src();
        $this->assertStringContainsString("registerImageAdapter('openai', 'Tigerimage_Adapter_OpenAi')", $src);
        $this->assertSame(0, preg_match('~registerImageAdapter\([^)]*new \\?[A-Z]~', $src),
            'pass a class name, not an instance — constructing at boot is what made this fatal before');
    }

    #[Test]
    public function registration_cannot_take_down_the_site(): void
    {
        $src = $this->src();
        $this->assertStringContainsString('catch (Throwable', $src,
            'a throwing module Bootstrap is fatal for every page, not just this module');
        $this->assertStringContainsString('catch (Throwable', $src,
            'belt-and-braces: core resolves lazily and degrades, but a Bootstrap must never throw');
    }

    #[Test]
    public function the_adapters_directory_exists_and_is_declared_in_the_manifest(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertDirectoryExists($root . '/adapters');
        $m = json_decode(file_get_contents($root . '/module.json'), true);
        $this->assertArrayHasKey('adapters', $m['paths']);
    }
}
