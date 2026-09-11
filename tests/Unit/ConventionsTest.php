<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * House rules this module must not regress (TIGER-98).
 *
 * These are conventions a reviewer would otherwise have to remember every time. They are cheap to
 * check and the failures they prevent are the quiet kind: a view that works until the CSP tightens,
 * a confirm() that cannot be styled or translated, a hardcoded string nobody can localise.
 */
final class ConventionsTest extends TestCase
{
    private function root(): string { return dirname(__DIR__, 2); }

    private function views(): array
    {
        return glob($this->root() . '/views/scripts/*/*.phtml') ?: [];
    }


    /** Strip PHP comments — a key named in a docblock is documentation, not something the code emits. */
    private function stripComments(string $src): string
    {
        $out = '';
        foreach (token_get_all($src) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) { continue; }
            $out .= is_array($t) ? $t[1] : $t;
        }
        return $out;
    }

    #[Test]
    public function views_carry_no_inline_script_or_style(): void
    {
        $this->assertNotEmpty($this->views(), 'no views found — the glob is wrong, not the rule');
        foreach ($this->views() as $f) {
            $src = file_get_contents($f);
            $this->assertSame(0, preg_match('~<script[\s>]~i', $src),
                basename($f) . ': JS belongs in an asset, not the view');
            $this->assertSame(0, preg_match('~<style[\s>]~i', $src),
                basename($f) . ': CSS belongs in the skin, not the view');
        }
    }

    /** Browser dialogs cannot be styled, translated, or tested. */
    #[Test]
    public function nothing_uses_a_browser_dialog(): void
    {
        $files = array_merge($this->views(), glob($this->root() . '/assets/js/*.js') ?: []);
        foreach ($files as $f) {
            $src = file_get_contents($f);
            $this->assertSame(0, preg_match('~(^|[^.\w])(alert|confirm|prompt)\s*\(~m', $src),
                basename($f) . ': use an in-app modal, not a browser dialog');
        }
    }

    /** A string a user can see must be translatable. */
    #[Test]
    public function every_service_message_is_a_translation_key(): void
    {
        foreach (glob($this->root() . '/services/*.php') ?: [] as $f) {
            // Only the FIRST argument — anything later is a payload value, not a message key.
            preg_match_all("~->_(?:error|success)\(\s*'([^']+)'~", $this->stripComments(file_get_contents($f)), $m);
            foreach ($m[1] as $key) {
                $this->assertMatchesRegularExpression('~^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$~', $key,
                    basename($f) . ": '$key' is a literal, not a translation key");
            }
        }
    }

    /** Every key the code emits must exist in en.ini, or the UI shows a raw key to a user. */
    #[Test]
    public function every_tigerimage_key_is_defined(): void
    {
        $ini = parse_ini_file($this->root() . '/languages/en.ini', false, INI_SCANNER_RAW) ?: [];
        $this->assertNotEmpty($ini, 'en.ini did not parse');

        $used = [];
        foreach (array_merge(glob($this->root() . '/services/*.php') ?: [], $this->views()) as $f) {
            // Comments explain keys and name examples; they do not emit them.
            preg_match_all('~\btigerimage\.[a-z0-9_.]+~', $this->stripComments(file_get_contents($f)), $m);
            foreach ($m[0] as $k) { $used[$k] = $f; }
        }
        // Not translations: config keys, and asset FILENAMES (tigerimage.studio.js looks like a key).
        $config = ['tigerimage.provider', 'tigerimage.model', 'tigerimage.api_key_enc', 'tigerimage.retention_days'];
        $used = array_filter($used, static function ($f, $k) use ($config) {
            if (strpos($k, 'tigerimage.storage.') === 0) { return false; }
            if (in_array($k, $config, true)) { return false; }
            if (preg_match('~\.(js|css|ini|php|json|png|svg)$~', $k)) { return false; }
            return true;
        }, ARRAY_FILTER_USE_BOTH);

        foreach ($used as $key => $file) {
            $this->assertArrayHasKey($key, $ini, "$key is emitted by " . basename($file) . " but missing from en.ini");
        }
    }

    /** The manifest must declare every directory the module actually ships. */
    #[Test]
    public function the_manifest_declares_what_ships(): void
    {
        $m = json_decode(file_get_contents($this->root() . '/module.json'), true);
        $this->assertIsArray($m);
        foreach (['services', 'models', 'migrations', 'configs', 'controllers', 'views', 'assets', 'languages'] as $dir) {
            if (is_dir($this->root() . '/' . $dir)) {
                $this->assertArrayHasKey($dir, $m['paths'], "module.json does not declare the $dir/ directory it ships");
            }
        }
        $this->assertSame('free', $m['pricing']['model'], 'TigerImage is a free public module');
        $this->assertSame('BSD-3-Clause', $m['license']);
    }
}
