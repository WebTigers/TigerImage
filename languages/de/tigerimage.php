<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerImage — German strings.
 *
 * Tiger loads module translations from `languages/<lang>/<name>.php`, each file RETURNING a
 * [key => string] array. A module owns its own `tigerimage.*` keys and ships its own translations
 * for every locale — core never translates a module.
 *
 * Placeholders (%s, %s) may be REORDERED to suit the language; tf() in the studio JS and
 * sprintf() on the server both address them by number.
 */
return [
    'tigerimage.studio.title' => 'Bildstudio',
    'tigerimage.studio.subtitle' => 'Beschreiben Sie ein Bild, vergleichen Sie die Ergebnisse und behalten Sie das, das Sie wollen.',
    'tigerimage.studio.unavailable' => 'Die Bilderzeugung ist auf dieser Website nicht eingerichtet',
    'tigerimage.studio.empty' => 'Noch nichts erzeugt. Beschreiben Sie oben ein Bild, um zu beginnen.',
    'tigerimage.studio.provider_note' => 'Erzeugt mit',
    'tigerimage.field.prompt' => 'Beschreiben Sie das Bild',
    'tigerimage.field.prompt_hint' => 'Eine getigerte Katze schläft auf einem Bücherstapel, warmes Nachmittagslicht',
    'tigerimage.field.negative' => 'Vermeiden',
    'tigerimage.field.size' => 'Format',
    'tigerimage.field.count' => 'Wie viele',
    'tigerimage.size.square' => 'Quadratisch',
    'tigerimage.size.landscape' => 'Querformat',
    'tigerimage.size.portrait' => 'Hochformat',
    'tigerimage.action.cancel' => 'Abbrechen',
    'tigerimage.action.generate' => 'Erzeugen',
    'tigerimage.generated' => 'Bilder erzeugt.',
    'tigerimage.promoted' => 'Zur Medienbibliothek hinzugefügt.',
    'tigerimage.discarded' => 'Bild verworfen.',
    'tigerimage.error.prompt_required' => 'Beschreiben Sie das gewünschte Bild.',
    'tigerimage.error.no_image_provider' => 'Auf dieser Website ist kein Bildanbieter konfiguriert.',
    'tigerimage.error.unavailable' => 'Die Bilderzeugung ist derzeit nicht verfügbar.',
    'tigerimage.error.spend_cap_reached' => 'Das monatliche Bildbudget dieser Organisation ist aufgebraucht.',
    'tigerimage.error.no_api_key' => 'Der Bildanbieter hat keinen verwendbaren API-Schlüssel.',
    'tigerimage.error.provider_cannot_draw' => 'Der konfigurierte Anbieter kann keine Bilder erzeugen.',
    'tigerimage.error.generation_failed' => 'Der Anbieter konnte dieses Bild nicht erzeugen.',
    'tigerimage.error.store_failed' => 'Das Bild wurde erzeugt, konnte aber nicht gespeichert werden.',
    'tigerimage.error.promote_failed' => 'Das Bild konnte nicht zur Medienbibliothek hinzugefügt werden.',
    'tigerimage.error.no_such_image' => 'Dieses Bild existiert nicht.',
    'tigerimage.action.details' => 'Details',
    'tigerimage.action.refine' => 'Verfeinern',
    'tigerimage.action.refine_from_this' => 'Hiervon ausgehend verfeinern',
    'tigerimage.action.keep' => 'Behalten',
    'tigerimage.action.bin' => 'Verwerfen',
    'tigerimage.action.bin_confirm' => 'Verwerfen',
    'tigerimage.state.in_media' => 'In Medien',
    'tigerimage.confirm.bin_title' => 'Dieses Bild verwerfen?',
    'tigerimage.confirm.bin_body' => 'Es wird aus dem Speicher entfernt. Daraus erzeugte Verfeinerungen bleiben erhalten und werden wieder dem Ursprungsbild zugeordnet.',
    'tigerimage.detail.title' => 'Bilddetails',
    'tigerimage.detail.prompt' => 'Beschreibung',
    'tigerimage.detail.alt' => 'Alternativtext',
    'tigerimage.detail.how_made' => 'Wie es entstanden ist',
    'tigerimage.detail.provider' => 'Anbieter',
    'tigerimage.detail.model' => 'Modell',
    'tigerimage.detail.size' => 'Größe',
    'tigerimage.detail.lineage' => 'Verlauf',
    'tigerimage.detail.lineage_note' => '%1$s Schritte – dieses Bild ist Nr. %2$s in der Kette.',
    'tigerimage.error.list_failed' => 'Ihre Bilder konnten nicht geladen werden.',
    'tigerimage.error.get_failed' => 'Dieses Bild konnte nicht geladen werden.',
    'tigerimage.error.refine_failed' => 'Dieses Bild konnte nicht verfeinert werden.',
    'tigerimage.error.discard_failed' => 'Dieses Bild konnte nicht verworfen werden.',
    'tigerimage.error.spend_cap_reached_token' => 'Das monatliche Bildbudget dieses Zugriffsschlüssels ist aufgebraucht.',
    'tigerimage.budget.label' => 'Budget',
    'tigerimage.budget.org' => 'Monatliches Bildbudget dieser Organisation.',
    'tigerimage.budget.token' => 'Monatliches Bildbudget dieses Zugriffsschlüssels.',
    'tigerimage.budget.estimated' => 'Geschätzt, nicht abgerechnet.',
];
