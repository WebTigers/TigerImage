<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerimage_Form_Generate — the argument SCHEMA for image generation.
 *
 * Not used to validate the call (Tigerimage_Service_Image::generate reads $params directly). It exists
 * so the /api + MCP tool surface hands an agent a TYPED schema for `tigerimage__image__generate` — the
 * real field NAMES (`prompt`, `negative`, `size`, `n`) and the closed sets (`size`, `n` as enums) —
 * instead of `additionalProperties: true`. The studio's UI labels ("Avoid", "Shape") don't match the
 * API field names, and this is a METERED tool: an agent must not have to guess an argument, or read the
 * page's JS, when each wrong call bills the provider.
 *
 * @api
 */
class Tigerimage_Form_Generate extends Tiger_Form
{
    /** Schema-only form — no session, no CSRF token in the schema. */
    protected function csrf(): bool { return false; }

    protected function elements(): array
    {
        return [
            ['text', 'prompt', [
                'required' => true,
                'label'    => $this->_t('tigerimage.field.prompt'),   // "Describe the image"
                'filters'  => ['StringTrim'],
            ]],
            ['text', 'negative', [
                'required' => false,
                'label'    => $this->_t('tigerimage.field.negative'), // "Avoid" — the negative prompt
                'filters'  => ['StringTrim'],
            ]],
            ['text', 'size', [
                'required'   => false,
                'label'      => $this->_t('tigerimage.field.size'),   // "Shape"
                'validators' => [['InArray', false, [['1024x1024', '1536x1024', '1024x1536']]]],
            ]],
            ['text', 'n', [
                'required'   => false,
                'label'      => $this->_t('tigerimage.field.count'),  // "How many"
                'validators' => [['InArray', false, [['1', '2', '4']]]],
            ]],
            ['text', 'seed', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Int']],
            ]],
            ['text', 'reference_id', [
                'required' => false,
                'filters'  => ['StringTrim'],
            ]],
        ];
    }
}
