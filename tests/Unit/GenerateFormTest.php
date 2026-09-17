<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tigerimage_Form_Generate — the argument schema for the METERED generate tool. Not used to validate
 * the call, but it IS what the /api + MCP surface reflects into a typed inputSchema (field names +
 * enums), so an agent doesn't guess an argument on an endpoint that bills per call. This guards the
 * field names + the closed sets; the core OpenAPI generator turns them into the JSON-Schema enum
 * (GeneratorTest covers that mapping).
 */
final class GenerateFormTest extends TestCase
{
    protected function setUp(): void
    {
        if (!Zend_Registry::isRegistered('Zend_Translate')) {
            Zend_Registry::set('Zend_Translate', new Zend_Translate([
                'adapter' => 'array', 'locale' => 'en',
                'content' => ['tigerimage.field.prompt' => 'Describe the image', 'tigerimage.field.negative' => 'Avoid',
                              'tigerimage.field.size' => 'Shape', 'tigerimage.field.count' => 'How many'],
            ]));
        }
    }

    #[Test]
    public function it_declares_the_real_field_names_the_service_reads(): void
    {
        $f = new Tigerimage_Form_Generate();
        foreach (['prompt', 'negative', 'size', 'n'] as $name) {
            $this->assertNotNull($f->getElement($name), "the schema declares `$name` (the API field, not the UI label)");
        }
        $this->assertTrue($f->getElement('prompt')->isRequired(), 'prompt is the one required field');
        $this->assertFalse($f->getElement('size')->isRequired());
    }

    #[Test]
    public function size_and_n_are_closed_sets_the_generator_turns_into_enums(): void
    {
        $f  = new Tigerimage_Form_Generate();
        $sz = $f->getElement('size')->getValidator('InArray');
        $this->assertNotFalse($sz, 'size carries an InArray → enum');
        $this->assertSame(['1024x1024', '1536x1024', '1024x1536'], array_values($sz->getHaystack()));

        $n = $f->getElement('n')->getValidator('InArray');
        $this->assertSame(['1', '2', '4'], array_values($n->getHaystack()));
    }
}
