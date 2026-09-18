<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tigerimage_Service_Library::_altFrom — deriving accessible alt text from a generation prompt.
 *
 * Round-4 B3: promote copied the WHOLE prompt into alt_text, trailing generator directives and all
 * ("editorial photography, shallow depth of field, no people"). Those are instructions to the model,
 * not a description of the picture — and a derived-but-wrong alt is worse than an empty one, because
 * it looks filled in. alt is now the SUBJECT: the descriptive clauses, with the trailing directive run
 * stripped. (The full prompt still lives in `caption`, for search.)
 */
#[CoversClass(Tigerimage_Service_Library::class)]
final class AltTextTest extends TestCase
{
    private function altFrom(string $prompt): string
    {
        $svc    = (new ReflectionClass(Tigerimage_Service_Library::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(Tigerimage_Service_Library::class, '_altFrom');
        return (string) $method->invoke($svc, $prompt);
    }

    #[Test]
    public function it_strips_the_trailing_directive_run_but_keeps_the_description(): void
    {
        $prompt = 'A quiet reading room at golden hour: a long oak table, tall windows casting warm light, '
                . 'editorial photography, shallow depth of field, no people';
        $alt = $this->altFrom($prompt);

        $this->assertStringContainsString('reading room at golden hour', $alt, 'the subject survives');
        $this->assertStringContainsString('tall windows casting warm light', $alt, 'descriptive clauses survive');
        $this->assertStringNotContainsString('photography', $alt, 'the camera directive is gone');
        $this->assertStringNotContainsString('depth of field', $alt);
        $this->assertStringNotContainsString('no people', $alt, 'a generation instruction is not a description');
    }

    #[Test]
    public function it_drops_trailing_technical_tokens(): void
    {
        $this->assertSame('a red bicycle against a brick wall',
            $this->altFrom('a red bicycle against a brick wall, 8k, cinematic, hyperrealistic'));
    }

    #[Test]
    public function a_plain_subject_prompt_is_left_alone(): void
    {
        $this->assertSame('a lone lighthouse on a cliff', $this->altFrom('a lone lighthouse on a cliff'));
    }

    #[Test]
    public function a_directive_word_mid_description_is_not_stripped(): void
    {
        // "photography" here is the SUBJECT, not a trailing directive — only the tail is peeled.
        $this->assertSame('a photography studio with softboxes and a backdrop',
            $this->altFrom('a photography studio with softboxes and a backdrop'));
    }

    #[Test]
    public function an_empty_prompt_yields_a_safe_placeholder(): void
    {
        $this->assertSame('Generated image', $this->altFrom('   '));
    }

    #[Test]
    public function it_caps_length_on_a_word_boundary(): void
    {
        $long = str_repeat('a beautiful sprawling meadow of wildflowers ', 20);   // > 200 chars, no directives
        $alt  = $this->altFrom($long);
        $this->assertLessThanOrEqual(200, mb_strlen($alt));
        $this->assertDoesNotMatchRegularExpression('/\s\S{0,3}$/', ' ' . $alt, 'no dangling partial word at the cut');
    }
}
