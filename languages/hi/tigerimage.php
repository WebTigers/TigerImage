<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerImage — Hindi strings.
 *
 * Tiger loads module translations from `languages/<lang>/<name>.php`, each file RETURNING a
 * [key => string] array. A module owns its own `tigerimage.*` keys and ships its own translations
 * for every locale — core never translates a module.
 *
 * Placeholders (%1$s, %2$s) may be REORDERED to suit the language; tf() in the studio JS and
 * sprintf() on the server both address them by number.
 */
return [
    'tigerimage.studio.title' => 'छवि स्टूडियो',
    'tigerimage.studio.subtitle' => 'कोई छवि बताइए, जो आए उनकी तुलना कीजिए, और जो पसंद हो उसे रख लीजिए।',
    'tigerimage.studio.unavailable' => 'इस साइट पर छवि निर्माण सेट अप नहीं है',
    'tigerimage.studio.empty' => 'अभी तक कुछ नहीं बनाया गया। शुरू करने के लिए ऊपर कोई छवि बताइए।',
    'tigerimage.studio.provider_note' => 'इससे बनाया जा रहा है',
    'tigerimage.field.prompt' => 'छवि का विवरण दीजिए',
    'tigerimage.field.prompt_hint' => 'किताबों के ढेर पर सोती हुई एक धारीदार बिल्ली, दोपहर की गर्म रोशनी',
    'tigerimage.field.negative' => 'इनसे बचें',
    'tigerimage.field.size' => 'आकार',
    'tigerimage.field.count' => 'कितनी',
    'tigerimage.size.square' => 'वर्गाकार',
    'tigerimage.size.landscape' => 'क्षैतिज',
    'tigerimage.size.portrait' => 'ऊर्ध्वाधर',
    'tigerimage.action.cancel' => 'रद्द करें',
    'tigerimage.action.generate' => 'बनाएँ',
    'tigerimage.generated' => 'छवियाँ बन गईं।',
    'tigerimage.promoted' => 'मीडिया लाइब्रेरी में जोड़ी गई।',
    'tigerimage.discarded' => 'छवि हटा दी गई।',
    'tigerimage.error.prompt_required' => 'आप जो छवि चाहते हैं उसका विवरण दीजिए।',
    'tigerimage.error.no_image_provider' => 'इस साइट पर कोई छवि प्रदाता कॉन्फ़िगर नहीं है।',
    'tigerimage.error.unavailable' => 'छवि निर्माण अभी उपलब्ध नहीं है।',
    'tigerimage.error.spend_cap_reached' => 'इस संगठन का मासिक छवि बजट समाप्त हो चुका है।',
    'tigerimage.error.no_api_key' => 'छवि प्रदाता के पास कोई उपयोग योग्य API कुंजी नहीं है।',
    'tigerimage.error.provider_cannot_draw' => 'कॉन्फ़िगर किया गया प्रदाता छवियाँ नहीं बना सकता।',
    'tigerimage.error.generation_failed' => 'प्रदाता वह छवि नहीं बना सका।',
    'tigerimage.error.store_failed' => 'छवि बन गई, लेकिन सहेजी नहीं जा सकी।',
    'tigerimage.error.promote_failed' => 'छवि को मीडिया लाइब्रेरी में नहीं जोड़ा जा सका।',
    'tigerimage.error.no_such_image' => 'ऐसी कोई छवि नहीं है।',
    'tigerimage.action.details' => 'विवरण',
    'tigerimage.action.refine' => 'निखारें',
    'tigerimage.action.refine_from_this' => 'इससे आगे निखारें',
    'tigerimage.action.keep' => 'रखें',
    'tigerimage.action.bin' => 'हटाएँ',
    'tigerimage.action.bin_confirm' => 'हटा दें',
    'tigerimage.state.in_media' => 'मीडिया में',
    'tigerimage.confirm.bin_title' => 'क्या यह छवि हटानी है?',
    'tigerimage.confirm.bin_body' => 'यह भंडारण से हटा दी जाती है। इससे बनाए गए निखार सुरक्षित रहते हैं और उनकी मूल छवि से फिर जोड़ दिए जाते हैं।',
    'tigerimage.detail.title' => 'छवि विवरण',
    'tigerimage.detail.prompt' => 'विवरण',
    'tigerimage.detail.alt' => 'वैकल्पिक पाठ',
    'tigerimage.detail.how_made' => 'यह कैसे बनी',
    'tigerimage.detail.provider' => 'प्रदाता',
    'tigerimage.detail.model' => 'मॉडल',
    'tigerimage.detail.size' => 'आकार',
    'tigerimage.detail.lineage' => 'शृंखला',
    'tigerimage.detail.lineage_note' => '%1$s चरण — यह छवि शृंखला में %2$s वें स्थान पर है।',
    'tigerimage.error.list_failed' => 'आपकी छवियाँ लोड नहीं हो सकीं।',
    'tigerimage.error.get_failed' => 'वह छवि लोड नहीं हो सकी।',
    'tigerimage.error.refine_failed' => 'वह छवि निखारी नहीं जा सकी।',
    'tigerimage.error.discard_failed' => 'वह छवि हटाई नहीं जा सकी।',
    'tigerimage.error.spend_cap_reached_token' => 'इस एक्सेस कुंजी का मासिक छवि बजट समाप्त हो चुका है।',
    'tigerimage.budget.label' => 'बजट',
    'tigerimage.budget.remaining' => 'इस माह %s में से %s शेष',
    'tigerimage.budget.org' => 'इस संगठन का मासिक छवि बजट।',
    'tigerimage.budget.token' => 'इस एक्सेस कुंजी का मासिक छवि बजट।',
    'tigerimage.budget.estimated' => 'अनुमानित, बिल नहीं किया गया।',
];
