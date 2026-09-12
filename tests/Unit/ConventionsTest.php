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

    /**
     * No INLINE script or style in a view — but a `<script src>` pointing at an asset is the point.
     *
     * This rule used to forbid the `<script` tag outright, and that is how the studio shipped with a
     * bare `<?= $this->asset('…studio.js') ?>` that printed the URL into the page as text: the helper
     * returns a URL, not a tag, so the script never loaded and the whole studio was dead in a browser.
     * A rule strict enough to forbid the correct construct pushes people into an incorrect one.
     *
     * Core's own modules (comment, analytics) load module JS exactly this way.
     */
    #[Test]
    public function views_carry_no_inline_script_or_style(): void
    {
        $this->assertNotEmpty($this->views(), 'no views found — the glob is wrong, not the rule');
        foreach ($this->views() as $f) {
            $src  = file_get_contents($f);
            $name = basename($f);

            // Every <script> must be a src= reference; one with a body is inline JS.
            preg_match_all('~<script\b[^>]*>~i', $src, $tags);
            foreach ($tags[0] as $tag) {
                $this->assertMatchesRegularExpression('~\ssrc\s*=~i', $tag,
                    $name . ': inline JS belongs in an asset — ' . $tag);
            }

            $this->assertSame(0, preg_match('~<style[\s>]~i', $src),
                $name . ': CSS belongs in a stylesheet, not the view');
        }
    }

    /**
     * A module asset referenced from a view must actually exist, and be referenced as a TAG.
     *
     * Both halves of the studio's dead-JS bug are pinned here: `asset()` echoed on its own emits a
     * URL as page text, and a path that does not resolve to a shipped file 404s at the browser.
     */
    #[Test]
    public function referenced_module_assets_exist_and_are_real_tags(): void
    {
        $seen = 0;
        foreach ($this->views() as $f) {
            $src = file_get_contents($f);

            // An asset() call must sit inside an attribute (src=" or href="), never be echoed bare.
            preg_match_all('~<\?=\s*(?:\$this->escape\(\s*)?\$this->asset\(~', $src, $calls, PREG_OFFSET_CAPTURE);
            foreach ($calls[0] as $call) {
                $before = substr($src, max(0, $call[1] - 80), min(80, $call[1]));
                $this->assertMatchesRegularExpression('~(?:src|href)\s*=\s*"$~i', $before,
                    basename($f) . ': asset() returns a URL — it must fill an attribute, not be echoed alone');
            }

            // And the file it names must be shipped.
            preg_match_all('~/_modules/tigerimage/([A-Za-z0-9_./-]+)~', $src, $paths);
            foreach ($paths[1] as $rel) {
                $seen++;
                $this->assertFileExists($this->root() . '/assets/' . $rel,
                    basename($f) . ': references an asset that is not in the module');
            }
        }
        $this->assertGreaterThan(0, $seen, 'no module assets referenced — the pattern is wrong, not the rule');
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

    /**
     * Translations must sit where Tiger actually LOADS them: languages/<lang>/<name>.php, returning
     * a [key => string] array.
     *
     * This module shipped a flat `languages/en.ini` instead. Nothing errored — the glob in
     * Tiger_Application_Bootstrap::_languageFiles simply matched no file, so not one string was ever
     * translated and the studio rendered its own key names at the user. The old test below checked
     * that every key EXISTED, which it did; nobody checked the file could be read by the framework.
     * Content without the contract proves nothing.
     */
    #[Test]
    public function translations_are_where_the_framework_looks_for_them(): void
    {
        $files = glob($this->root() . '/languages/*/*.php') ?: [];
        $this->assertNotEmpty($files,
            'no languages/<lang>/*.php — Tiger loads module translations from that glob and nothing else');

        $this->assertSame([], glob($this->root() . '/languages/*.ini') ?: [],
            'a languages/*.ini is never read by Tiger and will silently translate nothing');

        foreach ($files as $f) {
            $data = include $f;
            $this->assertIsArray($data, basename($f) . ' must RETURN a [key => string] array');
            $this->assertNotEmpty($data, basename($f) . ' returned an empty array');
            foreach ($data as $k => $v) {
                $this->assertIsString($k, basename($f) . ': keys must be strings');
                $this->assertIsString($v, basename($f) . ": '$k' must map to a string");
            }
        }
    }

    /**
     * A `core.*` key a view borrows must actually exist in tiger-core.
     *
     * The studio referenced `core.action.close` and `core.action.cancel`; core has neither (it has
     * `core.common.close`, and every module owns its own cancel). Both would have rendered their own
     * key name on the modal buttons. A module cannot invent keys in someone else's namespace — if it
     * is not there, the string belongs to the module.
     */
    #[Test]
    public function borrowed_core_keys_exist_in_core(): void
    {
        $file = TIGER_CORE_PATH . '/core/languages/en/core.php';
        if (!is_file($file)) { $this->markTestSkipped('tiger-core language file not resolvable'); }
        $core = (array) include $file;
        $this->assertNotEmpty($core, 'core English strings did not load');

        $checked = 0;
        foreach ($this->views() as $f) {
            preg_match_all('~\bcore\.[a-z0-9_.]+~', $this->stripComments(file_get_contents($f)), $m);
            foreach (array_unique($m[0]) as $key) {
                $checked++;
                $this->assertArrayHasKey($key, $core,
                    basename($f) . ": borrows '$key', which tiger-core does not define");
            }
        }
        $this->assertGreaterThan(0, $checked, 'no core keys found — the pattern is wrong, not the rule');
    }

    /** Every key the code emits must exist, or the UI shows a raw key to a user. */
    #[Test]
    public function every_tigerimage_key_is_defined(): void
    {
        $ini = [];
        foreach (glob($this->root() . '/languages/en/*.php') ?: [] as $f) {
            $ini += (array) include $f;
        }
        $this->assertNotEmpty($ini, 'no English strings loaded');

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
            $this->assertArrayHasKey($key, $ini, "$key is emitted by " . basename($file) . " but has no English string");
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
