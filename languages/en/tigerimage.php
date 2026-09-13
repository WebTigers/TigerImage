<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerImage — English strings.
 *
 * The shape matters: Tiger loads module translations from `languages/<lang>/<name>.php`, each file
 * RETURNING a [key => string] array (Tiger_Application_Bootstrap::_languageFiles). This module
 * originally shipped a flat `languages/en.ini`, which matched nothing that glob looks for — so not
 * one string was ever translated and the studio rendered its own key names on screen.
 */
return [
    'tigerimage.studio.title'               => 'Image studio',
    'tigerimage.studio.subtitle'            => 'Describe a picture, compare what comes back, keep the one you want.',
    'tigerimage.studio.unavailable'         => 'Image generation is not set up on this site',
    'tigerimage.studio.empty'               => 'Nothing generated yet. Describe an image above to start.',
    'tigerimage.studio.provider_note'       => 'Generating with',
    'tigerimage.field.prompt'               => 'Describe the image',
    'tigerimage.field.prompt_hint'          => 'A tabby cat asleep on a stack of books, warm afternoon light',
    'tigerimage.field.negative'             => 'Avoid',
    'tigerimage.field.size'                 => 'Shape',
    'tigerimage.field.count'                => 'How many',
    'tigerimage.size.square'                => 'Square',
    'tigerimage.size.landscape'             => 'Landscape',
    'tigerimage.size.portrait'              => 'Portrait',
    'tigerimage.action.cancel'           => 'Cancel',
    'tigerimage.action.generate'            => 'Generate',
    'tigerimage.generated'                  => 'Images generated.',
    'tigerimage.promoted'                   => 'Added to the Media Library.',
    'tigerimage.discarded'                  => 'Image binned.',
    'tigerimage.error.prompt_required'      => 'Describe the image you want.',
    'tigerimage.error.no_image_provider'    => 'No image provider is configured on this site.',
    'tigerimage.error.unavailable'          => 'Image generation is not available right now.',
    'tigerimage.error.spend_cap_reached'    => 'The monthly image budget for this organisation is used up.',
    'tigerimage.error.no_api_key'           => 'The image provider has no usable API key.',
    'tigerimage.error.provider_cannot_draw' => 'The configured provider cannot generate images.',
    'tigerimage.error.generation_failed'    => 'The provider could not generate that image.',
    'tigerimage.error.store_failed'         => 'The image was generated but could not be stored.',
    'tigerimage.error.promote_failed'       => 'Could not add the image to the Media Library.',
    'tigerimage.error.no_such_image'        => 'No such image.',
    // --- strings the studio's JavaScript renders (TIGER-107) -------------------------------
    'tigerimage.action.details'          => 'Details',
    'tigerimage.action.refine'           => 'Refine',
    'tigerimage.action.refine_from_this' => 'Refine from this',
    'tigerimage.action.keep'             => 'Keep',
    'tigerimage.action.bin'              => 'Bin',
    'tigerimage.action.bin_confirm'      => 'Bin it',
    'tigerimage.state.in_media'          => 'In Media',
    'tigerimage.confirm.bin_title'       => 'Bin this image?',
    'tigerimage.confirm.bin_body'        => 'It is removed from storage. Any refinements made from it are kept and re-attached to its parent.',
    'tigerimage.detail.title'            => 'Image details',
    'tigerimage.detail.prompt'           => 'Prompt',
    'tigerimage.detail.alt'              => 'Alt text',
    'tigerimage.detail.how_made'         => 'How it was made',
    'tigerimage.detail.provider'         => 'Provider',
    'tigerimage.detail.model'            => 'Model',
    'tigerimage.detail.size'             => 'Size',
    'tigerimage.detail.lineage'          => 'Lineage',
    // %s = how many steps in the chain, %s = which one this image is.
    'tigerimage.detail.lineage_note'     => '%s steps — this image is #%s in the chain.',
    'tigerimage.error.list_failed'       => 'Could not load your images.',
    'tigerimage.error.get_failed'        => 'Could not load that image.',
    'tigerimage.error.refine_failed'     => 'Could not refine that image.',
    'tigerimage.error.discard_failed'    => 'Could not bin that image.',
    // The cap message names the ceiling that actually bound — otherwise someone told "budget used
    // up" cannot tell whether to raise the org cap or widen one key.
    'tigerimage.error.spend_cap_reached_token' => 'The monthly image budget for this access key is used up.',

    'tigerimage.budget.label'               => 'Budget',
    'tigerimage.budget.remaining'           => '%s of %s left this month',
    'tigerimage.budget.org'                 => 'This organisation\'s monthly image budget.',
    'tigerimage.budget.token'               => 'This access key\'s monthly image budget.',
    'tigerimage.budget.estimated'           => 'Estimated, not billed.',
];
