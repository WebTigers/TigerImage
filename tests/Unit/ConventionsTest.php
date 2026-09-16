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
            // Comments EXPLAIN the rule — one that names the tag it forbids is documentation, not
            // markup. (This test failed on a comment reading "not an inline <script>".)
            $src  = $this->stripComments(file_get_contents($f));
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

    /* ---- the studio's JavaScript (TIGER-107) ------------------------------------------------ */

    /** The studio JS, as source. */
    private function studioJs(): string
    {
        return (string) file_get_contents($this->root() . '/assets/js/tigerimage.studio.js');
    }

    /** The aliases the VIEW registers with core's i18n helper, alias => translation key. */
    private function registeredAliases(): array
    {
        $src = file_get_contents($this->root() . '/views/scripts/studio/index.phtml');
        if (!preg_match('~\$this->i18n\(\s*\[(.*?)\]\s*\);~s', $src, $m)) { return []; }
        preg_match_all("~'([^']+)'\s*=>\s*'([^']+)'~", $m[1], $k, PREG_SET_ORDER);
        $out = [];
        foreach ($k as $pair) { $out[$pair[1]] = $pair[2]; }
        return $out;
    }

    /**
     * The JS must ask for no alias the view has not registered.
     *
     * This is the contract between the two files and the failure mode the mechanism invites: add a
     * `t('…')` call, forget the alias in the view's map, and Tiger.t returns the alias itself — a
     * button quietly reading "keep". Nothing else catches it. The translation key exists, so the key
     * sweep is happy, and an alias on screen looks far more like a real word than a full key did.
     */
    #[Test]
    public function the_view_registers_every_alias_the_js_asks_for(): void
    {
        $registered = $this->registeredAliases();
        $this->assertNotEmpty($registered, 'the view registers no strings — the i18n map is gone');

        preg_match_all("~\bt\(\s*'([A-Za-z][A-Za-z0-9_]*)'~", $this->studioJs(), $m);
        $asked = array_unique($m[1]);
        $this->assertNotEmpty($asked, 'the JS asks for no strings — the pattern changed');

        foreach ($asked as $alias) {
            $this->assertArrayHasKey($alias, $registered,
                "the JS renders '$alias' but the view does not register it — Tiger.t would print the alias");
        }
    }

    /** Every alias the view registers must map to a key that exists, and be used by the JS. */
    #[Test]
    public function the_registered_aliases_are_real_and_used(): void
    {
        $registered = $this->registeredAliases();
        $strings    = [];
        foreach (glob($this->root() . '/languages/en/*.php') ?: [] as $f) { $strings += (array) include $f; }

        preg_match_all("~\bt\(\s*'([A-Za-z][A-Za-z0-9_]*)'~", $this->studioJs(), $m);
        $asked = array_unique($m[1]);

        foreach ($registered as $alias => $key) {
            $this->assertArrayHasKey($key, $strings, "alias '$alias' maps to '$key', which has no English string");
            $this->assertContains($alias, $asked, "alias '$alias' is registered but no longer used — dead weight in every page");
        }
    }

    /**
     * No user-visible English baked into the JS.
     *
     * Two narrow checks rather than one broad one, because "looks like a sentence" is not decidable:
     * a literal assigned to textContent, and words sitting between tags in a generated template. Both
     * are how every string in this file was previously hardcoded, and neither has a legitimate use.
     */
    #[Test]
    public function the_js_renders_no_hardcoded_english(): void
    {
        $src = $this->studioJs();

        preg_match_all('~textContent\s*=\s*([\'"])(.*?)\1~', $src, $m);
        foreach ($m[2] as $literal) {
            // Only WORDS are the concern. Clearing a node, or a language-neutral busy marker like an
            // ellipsis, has nothing to translate — demanding a key for '…' would be ceremony.
            if (!preg_match('~\pL~u', $literal)) { continue; }
            $this->fail("studio.js assigns the literal \"$literal\" to textContent — use t()");
        }

        // Words adjacent to tag syntax: text a user reads, sitting in generated markup. Three shapes,
        // because the markup is built by concatenation and the text can sit at a string BOUNDARY —
        // 'Details</button>' has no opening > in the same literal, and a single >Word< pattern walks
        // straight past it. Mutation testing found exactly that.
        $shapes = [
            '~>\s*([A-Za-z][A-Za-z ]{2,})\s*<~',            // >Details<   (whole text in one literal)
            '~[\'"]\s*([A-Za-z][A-Za-z ]{2,})\s*</~',        // 'Details</  (literal opens with the text)
            '~>\s*([A-Za-z][A-Za-z ]{2,})\s*[\'"]~',         // >Details'   (literal ends with the text)
        ];
        foreach ($shapes as $shape) {
            preg_match_all($shape, $src, $m);
            foreach ($m[1] as $text) {
                $this->fail("studio.js renders the literal \"" . trim($text) . "\" into markup — use t()");
            }
        }

        $this->assertMatchesRegularExpression('~\bvar t =~', $src,
            'the t() binding to core\'s Tiger.t is gone');
        $this->assertStringContainsString('window.Tiger', $src,
            'strings must come from core\'s helper, not a private copy (TIGER-120)');
    }

    /**
     * Every supported locale carries every key, with its placeholders intact.
     *
     * A module owns its `tigerimage.*` keys and ships its OWN translations for every locale — core
     * never translates a module. Without this check a locale falls behind silently: the string simply
     * renders in English for that user and nobody notices until they complain.
     *
     * Placeholders are checked by SET, not by order, because a translator may legitimately reorder
     * %1$s and %2$s to suit the language — that is why they are numbered. Dropping one, or inventing
     * one, is the actual bug.
     */
    #[Test]
    public function every_locale_is_complete(): void
    {
        $expected = ['de', 'en', 'es', 'fr', 'hi', 'pt'];
        $present  = array_values(array_filter(scandir($this->root() . '/languages'),
            fn ($d) => $d[0] !== '.' && is_dir($this->root() . '/languages/' . $d)));
        sort($present);
        $this->assertSame($expected, $present, 'the shipped locales changed');

        $en = (array) include $this->root() . '/languages/en/tigerimage.php';
        $this->assertNotEmpty($en);

        foreach ($expected as $lang) {
            $strings = (array) include $this->root() . "/languages/$lang/tigerimage.php";

            $this->assertSame([], array_diff(array_keys($en), array_keys($strings)),
                "$lang is missing keys that en has — it would fall back to English for those");
            $this->assertSame([], array_diff(array_keys($strings), array_keys($en)),
                "$lang defines keys en does not — one of the two is wrong");

            foreach ($en as $key => $source) {
                // Compared as a SET of slot types, not by number or position: a translation may say
                // %2$s before %1$s — that is what numbering is for. Losing or inventing one is the bug.
                $slot = static fn ($p) => substr($p, -1);
                preg_match_all('~%(?:\d+\$)?[sd]~', (string) $source, $want);
                preg_match_all('~%(?:\d+\$)?[sd]~', (string) $strings[$key], $got);
                $want = array_map($slot, $want[0]); $got = array_map($slot, $got[0]);
                sort($want); sort($got);
                $this->assertSame($want, $got,
                    "$lang: '$key' does not carry the same placeholders as en");

                // Two or more arguments MUST be numbered (TIGER-121), or a translator cannot reorder.
                preg_match_all('~%(?:\d+\$)?[sd]~', (string) $strings[$key], $all);
                if (count($all[0]) >= 2) {
                    foreach ($all[0] as $ph) {
                        $this->assertMatchesRegularExpression('~^%\d+\$~', $ph,
                            "$lang: '$key' takes " . count($all[0]) . " arguments but uses sequential '$ph' — number them");
                    }
                }
                if ($lang !== 'en') {
                    $this->assertNotSame('', trim((string) $strings[$key]), "$lang: '$key' is empty");
                }
            }

            // A locale that is WHOLESALE English — someone copied en/ and never translated it — is
            // the failure this can actually detect. Per-string equality cannot be the rule: 'Budget'
            // is identical in en/de/fr and 'Portrait' in en/fr, and flagging those would make the
            // check cry wolf until it was ignored. Today the worst locale coincides on 4%.
            if ($lang !== 'en') {
                $identical = count(array_filter($en, fn ($v, $k) => $strings[$k] === $v, ARRAY_FILTER_USE_BOTH));
                $this->assertLessThan(0.5 * count($en), $identical,
                    "$lang matches en on $identical of " . count($en) . " strings — it looks like an untranslated copy");
            }
        }
    }

    /**
     * routes.ini must be in the shape Tiger's ingester reads (TIGER-122).
     *
     * Tiger_Routing_ModuleRoutes navigates to `resources.router.routes.*`. This file was written as
     * bare `routes.*`, which parsed fine, matched nothing, and errored nowhere — the studio's URLs
     * worked only because they happened to coincide with default module/controller/action routing.
     * The file was decorative for its whole life until this test.
     */
    #[Test]
    public function routes_ini_declares_routes_where_the_ingester_looks(): void
    {
        $f = $this->root() . '/configs/routes.ini';
        $this->assertFileExists($f);
        $raw = file_get_contents($f);
        $this->assertDoesNotMatchRegularExpression('~^\s*routes\.~m', $raw,
            'bare `routes.*` is never read — the ingester wants `resources.router.routes.*`');

        $cfg    = new \Zend_Config_Ini($f, 'production');
        $res    = $cfg->get('resources');
        $router = $res ? $res->get('router') : null;
        $routes = $router ? $router->get('routes') : null;
        $this->assertNotNull($routes, 'no resources.router.routes node');
        $this->assertGreaterThan(0, count($routes->toArray()));
        foreach ($routes->toArray() as $name => $r) {
            foreach (['module', 'controller', 'action'] as $k) {
                $this->assertArrayHasKey($k, $r['defaults'] ?? [], "$name defaults lack $k");
            }
        }
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
        $scan = array_merge(
            glob($this->root() . '/services/*.php') ?: [],
            glob($this->root() . '/assets/js/*.js') ?: [],   // the JS renders keys too (TIGER-107)
            $this->views()
        );
        foreach ($scan as $f) {
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

    /**
     * The studio must talk to /api the way /api actually READS a request — form-encoded fields, flat.
     *
     * Tiger's /api gateway resolves module/service/method and the payload from POST form fields
     * (WEBSERVICES.md §2/§6); PHP never populates $_POST from a raw JSON body, so a
     * `Content-Type: application/json` request arrives with no routing fields and every call fails
     * generically ("Something went wrong"). The studio shipped exactly that bug — a JSON body with a
     * nested `params` object — so the whole screen was dead in a browser while the unit tests (which
     * call the service directly) stayed green. This is the guard that would have caught it.
     */
    #[Test]
    public function studio_js_posts_to_api_form_encoded_not_json(): void
    {
        $js = file_get_contents($this->root() . '/assets/js/tigerimage.studio.js');
        $this->assertNotFalse($js);
        $this->assertStringContainsString('/api', $js, 'the studio calls /api');
        $this->assertStringContainsString('URLSearchParams', $js, 'the studio must post form-encoded fields to /api');
        $this->assertStringNotContainsString("'application/json'", $js,
            "the studio must NOT send a JSON body to /api — /api reads POST form fields, not php://input");
        $this->assertStringNotContainsString('JSON.stringify', $js,
            'a JSON.stringify body to /api arrives with empty params — post URLSearchParams instead');
    }
}
