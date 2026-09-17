<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The image adapters THIS MODULE owns (TIGER-103).
 *
 * These used to live in tiger-core. They belong here now: core declares the contract and holds the
 * register, and ships no image-generation code. The transport is stubbed at the one seam each adapter
 * exposes, exactly as before — what moved is ownership, not coverage.
 */
#[CoversClass(Tigerimage_Adapter_OpenAi::class)]
#[CoversClass(Tigerimage_Adapter_Gemini::class)]
final class AdapterTest extends TestCase
{
    #[Test]
    public function each_adapter_answers_for_its_own_models(): void
    {
        $o = new Tigerimage_Adapter_OpenAi();
        $this->assertTrue($o->supportsModel('gpt-image-1'));
        $this->assertTrue($o->supportsModel('dall-e-3'));
        // the live model list exposes this one; an anchored match would have missed it
        $this->assertTrue($o->supportsModel('chatgpt-image-latest'));
        $this->assertFalse($o->supportsModel('gpt-5'));

        $g = new Tigerimage_Adapter_Gemini();
        $this->assertTrue($g->supportsModel('imagen-3.0-generate-002'));
        $this->assertTrue($g->supportsModel('gemini-2.5-flash-image-preview'));
        $this->assertFalse($g->supportsModel('gemini-2.5-pro'));
    }

    #[Test]
    public function both_implement_the_core_contract(): void
    {
        $this->assertInstanceOf(Tiger_Agent_Provider_ImageAdapter::class, new Tigerimage_Adapter_OpenAi());
        $this->assertInstanceOf(Tiger_Agent_Provider_ImageAdapter::class, new Tigerimage_Adapter_Gemini());
    }

    /* ---- OpenAI ----------------------------------------------------------------------------- */

    #[Test]
    public function openai_asks_for_base64_and_normalises_the_result(): void
    {
        $a = new FakeOpenAi();
        FakeOpenAi::$response = ['data' => [
            ['b64_json' => 'AAAA', 'revised_prompt' => 'a tidier prompt'],
            ['b64_json' => 'BBBB'],
        ]];

        $out = $a->generateImage('a tiger', ['n' => 2, 'size' => '1024x1024'], 'gpt-image-1', 'k');

        $this->assertCount(2, $out['images']);
        $this->assertSame('image/png', $out['images'][0]['mime']);
        $this->assertSame('AAAA', $out['images'][0]['data']);
        // params echo what was used, including the model's own rewrite
        $this->assertSame('gpt-image-1', $out['params']['model']);
        $this->assertSame(2, $out['params']['n']);
        $this->assertSame('a tidier prompt', $out['params']['revised_prompt']);
    }

    /** A URL would expire; base64 is requested so a stored image cannot rot. */
    #[Test]
    public function openai_requests_b64_for_dalle_and_clamps_n(): void
    {
        $a = new FakeOpenAi();
        FakeOpenAi::$response = ['data' => [['b64_json' => 'AAAA']]];
        $a->generateImage('x', ['n' => 5], 'dall-e-3', 'k');

        $this->assertSame('b64_json', FakeOpenAi::$sent['response_format']);
        $this->assertSame(1, FakeOpenAi::$sent['n'], 'dall-e-3 rejects n > 1');
    }

    /** gpt-image-* always returns base64 and rejects the field, so it must not be sent. */
    #[Test]
    public function openai_omits_response_format_for_gpt_image(): void
    {
        $a = new FakeOpenAi();
        FakeOpenAi::$response = ['data' => [['b64_json' => 'AAAA']]];
        $a->generateImage('x', [], 'gpt-image-1', 'k');

        $this->assertArrayNotHasKey('response_format', FakeOpenAi::$sent);
    }

    #[Test]
    public function openai_snaps_an_illegal_size(): void
    {
        $a = new FakeOpenAi();
        FakeOpenAi::$response = ['data' => [['b64_json' => 'AAAA']]];

        $a->generateImage('x', ['size' => '4000x1000'], 'gpt-image-1', 'k');
        $this->assertSame('1536x1024', FakeOpenAi::$sent['size'], 'landscape snaps to landscape');

        $a->generateImage('x', ['size' => 'enormous'], 'gpt-image-1', 'k');
        $this->assertSame('1024x1024', FakeOpenAi::$sent['size'], 'nonsense falls back to square');
    }

    #[Test]
    public function openai_fails_loudly_on_no_image_data(): void
    {
        $a = new FakeOpenAi();
        FakeOpenAi::$response = ['data' => []];
        $this->expectException(RuntimeException::class);
        $a->generateImage('x', [], 'gpt-image-1', 'k');
    }

    #[Test]
    public function openai_sends_the_gpt_image_knobs_and_matches_the_mime(): void
    {
        $a = new FakeOpenAi();
        FakeOpenAi::$response = ['data' => [['b64_json' => 'AAAA']]];
        $out = $a->generateImage('x', [
            'quality' => 'high', 'background' => 'transparent',
            'output_format' => 'webp', 'output_compression' => '80',
        ], 'gpt-image-1', 'k');

        $this->assertSame('high', FakeOpenAi::$sent['quality']);
        $this->assertSame('transparent', FakeOpenAi::$sent['background']);
        $this->assertSame('webp', FakeOpenAi::$sent['output_format']);
        $this->assertSame(80, FakeOpenAi::$sent['output_compression']);
        $this->assertSame('image/webp', $out['images'][0]['mime'], 'stored mime matches the requested format, not a hardcoded png');
    }

    #[Test]
    public function openai_omits_auto_and_unset_knobs_and_defaults_to_png(): void
    {
        $a = new FakeOpenAi();
        FakeOpenAi::$response = ['data' => [['b64_json' => 'AAAA']]];
        $out = $a->generateImage('x', ['quality' => 'auto'], 'gpt-image-1', 'k');

        $this->assertArrayNotHasKey('quality', FakeOpenAi::$sent, 'auto = the model default, so nothing is sent');
        $this->assertArrayNotHasKey('output_format', FakeOpenAi::$sent);
        $this->assertArrayNotHasKey('output_compression', FakeOpenAi::$sent, 'no compression without a jpeg/webp format');
        $this->assertSame('image/png', $out['images'][0]['mime']);
    }

    #[Test]
    public function imageParams_are_per_provider_openai_has_no_negative_or_seed(): void
    {
        $names = array_column((new Tigerimage_Adapter_OpenAi())->imageParams(), 'name');
        $this->assertContains('quality', $names, 'OpenAI honors quality (the cost lever)');
        $this->assertContains('output_format', $names);
        $this->assertNotContains('negative', $names, 'the OpenAI image API has no negative prompt — do not advertise it');
        $this->assertNotContains('seed', $names, 'nor a seed');

        $gemini = array_column((new Tigerimage_Adapter_Gemini())->imageParams(), 'name');
        $this->assertContains('negative', $gemini, 'Imagen DOES honor a negative prompt');
        $this->assertContains('seed', $gemini);
        $this->assertNotContains('quality', $gemini, 'Imagen has no quality knob');
    }

    /* ---- Gemini ----------------------------------------------------------------------------- */

    #[Test]
    public function gemini_uses_predict_for_imagen(): void
    {
        $a = new FakeGemini();
        FakeGemini::$response = ['predictions' => [
            ['bytesBase64Encoded' => 'CCCC', 'mimeType' => 'image/jpeg'],
        ]];

        $out = $a->generateImage('a tiger', ['size' => '1920x1080', 'n' => 1], 'imagen-3.0-generate-002', 'k');

        $this->assertStringContainsString(':predict', FakeGemini::$url);
        $this->assertSame('16:9', FakeGemini::$sent['parameters']['aspectRatio'], 'WxH maps to an aspect ratio');
        $this->assertSame('CCCC', $out['images'][0]['data']);
        $this->assertSame('image/jpeg', $out['images'][0]['mime'], 'the provider mime is preserved');
    }

    #[Test]
    public function gemini_uses_generate_content_for_the_chat_image_models(): void
    {
        $a = new FakeGemini();
        FakeGemini::$response = ['candidates' => [
            ['content' => ['parts' => [['inlineData' => ['data' => 'DDDD', 'mimeType' => 'image/png']]]]],
        ]];

        $out = $a->generateImage('a tiger', [], 'gemini-2.5-flash-image-preview', 'k');

        $this->assertStringContainsString(':generateContent', FakeGemini::$url);
        $this->assertSame('DDDD', $out['images'][0]['data']);
    }

    /** The reference rides as inlineData — the same wire shape vision input already uses. */
    #[Test]
    public function gemini_sends_a_reference_image_as_inline_data(): void
    {
        $a = new FakeGemini();
        FakeGemini::$response = ['candidates' => [
            ['content' => ['parts' => [['inlineData' => ['data' => 'DDDD']]]]],
        ]];

        $out = $a->generateImage('warmer', ['reference' => ['mime' => 'image/png', 'data' => 'SEED']],
            'gemini-2.5-flash-image-preview', 'k');

        $parts = FakeGemini::$sent['contents'][0]['parts'];
        $this->assertSame('SEED', $parts[1]['inlineData']['data']);
        $this->assertSame('supplied', $out['params']['reference']);
    }

    /**
     * Imagen's predict endpoint has no reference slot. Dropping the steer silently would produce a
     * plausible image that is not what was asked for — worse than refusing.
     */
    #[Test]
    public function gemini_refuses_a_reference_imagen_cannot_honour(): void
    {
        $a = new FakeGemini();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('~reference image~i');
        $a->generateImage('x', ['reference' => ['mime' => 'image/png', 'data' => 'SEED']],
            'imagen-3.0-generate-002', 'k');
    }

    /* ---- shared ----------------------------------------------------------------------------- */

    #[Test]
    public function an_empty_prompt_is_refused_by_every_adapter(): void
    {
        // NOT fail() inside the try: PHPUnit's AssertionFailedError extends RuntimeException, so a
        // `catch (RuntimeException)` swallows the failure and the test can never fail. Found by
        // mutation testing the sibling module.
        foreach ([new FakeOpenAi(), new FakeGemini()] as $a) {
            $threw = false;
            try {
                $a->generateImage('   ', [], 'gpt-image-1', 'k');
            } catch (RuntimeException $e) {
                $threw = true;
                $this->assertStringContainsString('empty', strtolower($e->getMessage()));
            }
            $this->assertTrue($threw, 'an empty prompt should throw: ' . get_class($a));
        }
    }


    /**
     * Aspect mapping is not cosmetic: a user who asks for a portrait image and silently gets a square
     * one has to notice it themselves. Every branch, including the degenerate inputs.
     */
    #[Test]
    public function gemini_maps_every_aspect_branch(): void
    {
        $cases = [
            '1920x1080' => '16:9',   // wide
            '1200x900'  => '4:3',    // mildly wide
            '1080x1920' => '9:16',   // tall
            '900x1200'  => '3:4',    // mildly tall
            '1024x1024' => '1:1',    // square
            'nonsense'  => '1:1',    // unparseable → square
            '0x0'       => '1:1',    // degenerate → square, not a division by zero
            ''          => '1:1',    // absent → square
        ];
        $a = new FakeGemini();
        FakeGemini::$response = ['predictions' => [['bytesBase64Encoded' => 'X']]];

        foreach ($cases as $size => $expected) {
            $a->generateImage('x', ['size' => $size], 'imagen-3.0-generate-002', 'k');
            $this->assertSame($expected, FakeGemini::$sent['parameters']['aspectRatio'],
                "size '$size' should map to $expected");
        }
    }

    #[Test]
    public function openai_snaps_portrait_to_portrait(): void
    {
        $a = new FakeOpenAi();
        FakeOpenAi::$response = ['data' => [['b64_json' => 'A']]];

        $a->generateImage('x', ['size' => '600x1400'], 'gpt-image-1', 'k');
        $this->assertSame('1024x1536', FakeOpenAi::$sent['size']);

        $a->generateImage('x', ['size' => '1024x1536'], 'gpt-image-1', 'k');
        $this->assertSame('1024x1536', FakeOpenAi::$sent['size'], 'an already-legal size passes through');
    }

    #[Test]
    public function gemini_fails_loudly_on_no_image_data_on_both_paths(): void
    {
        $a = new FakeGemini();

        FakeGemini::$response = ['predictions' => []];
        $threw = false;
        try { $a->generateImage('x', [], 'imagen-3.0-generate-002', 'k'); }
        catch (RuntimeException $e) { $threw = true; $this->assertStringContainsString('no image data', $e->getMessage()); }
        $this->assertTrue($threw, 'imagen path should throw on empty predictions');

        FakeGemini::$response = ['candidates' => [['content' => ['parts' => [['text' => 'sorry']]]]]];
        $threw = false;
        try { $a->generateImage('x', [], 'gemini-2.5-flash-image-preview', 'k'); }
        catch (RuntimeException $e) { $threw = true; $this->assertStringContainsString('no image data', $e->getMessage()); }
        $this->assertTrue($threw, 'generateContent path should throw when the model answered with text');
    }

    #[Test]
    public function gemini_passes_negative_prompt_and_seed_to_imagen(): void
    {
        $a = new FakeGemini();
        FakeGemini::$response = ['predictions' => [['bytesBase64Encoded' => 'X']]];

        $a->generateImage('a tiger', ['negative_prompt' => 'blurry', 'seed' => 42], 'imagen-3.0-generate-002', 'k');
        $params = FakeGemini::$sent['parameters'];
        $this->assertSame('blurry', $params['negativePrompt']);
        $this->assertSame(42, $params['seed']);

        // absent options must not be sent as empty values the provider would reject
        $a->generateImage('a tiger', [], 'imagen-3.0-generate-002', 'k');
        $this->assertArrayNotHasKey('negativePrompt', FakeGemini::$sent['parameters']);
        $this->assertArrayNotHasKey('seed', FakeGemini::$sent['parameters']);
    }

}

/** Stubs the one cURL seam so the payload can be inspected and a canned body returned. */
final class FakeOpenAi extends Tigerimage_Adapter_OpenAi
{
    public static array $response = [];
    public static array $sent     = [];
    public static string $url     = '';
    protected function _post($url, array $payload, array $headers)
    {
        self::$url = $url; self::$sent = $payload; return self::$response;
    }
}

final class FakeGemini extends Tigerimage_Adapter_Gemini
{
    public static array $response = [];
    public static array $sent     = [];
    public static string $url     = '';
    protected function _post($url, array $payload, $apiKey)
    {
        self::$url = $url; self::$sent = $payload; return self::$response;
    }
}
