<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerImage — French strings.
 *
 * Tiger loads module translations from `languages/<lang>/<name>.php`, each file RETURNING a
 * [key => string] array. A module owns its own `tigerimage.*` keys and ships its own translations
 * for every locale — core never translates a module.
 *
 * Placeholders (%s, %s) may be REORDERED to suit the language; tf() in the studio JS and
 * sprintf() on the server both address them by number.
 */
return [
    'tigerimage.nav.label'                  => 'Images',
    'tigerimage.studio.title' => 'Studio d\'images',
    'tigerimage.studio.subtitle' => 'Décrivez une image, comparez les résultats et gardez celle que vous voulez.',
    'tigerimage.studio.unavailable' => 'La génération d\'images n\'est pas configurée sur ce site',
    'tigerimage.studio.empty' => 'Rien de généré pour le moment. Décrivez une image ci-dessus pour commencer.',
    'tigerimage.studio.provider_note' => 'Génération avec',
    'tigerimage.field.prompt' => 'Décrivez l\'image',
    'tigerimage.field.prompt_hint' => 'Un chat tigré endormi sur une pile de livres, lumière chaude de fin de journée',
    'tigerimage.field.negative' => 'À éviter',
    'tigerimage.field.size' => 'Format',
    'tigerimage.field.count' => 'Combien',
    'tigerimage.size.square' => 'Carré',
    'tigerimage.size.landscape' => 'Paysage',
    'tigerimage.size.portrait' => 'Portrait',

    // Provider-specific generation params (rendered per honored set)
    'tigerimage.field.seed' => 'Graine',
    'tigerimage.field.quality' => 'Qualité',
    'tigerimage.field.background' => 'Arrière-plan',
    'tigerimage.field.output_format' => 'Format',
    'tigerimage.field.output_compression' => 'Compression',
    'tigerimage.opt.auto' => 'Auto',
    'tigerimage.opt.low' => 'Basse',
    'tigerimage.opt.medium' => 'Moyenne',
    'tigerimage.opt.high' => 'Haute',
    'tigerimage.opt.opaque' => 'Opaque',
    'tigerimage.opt.transparent' => 'Transparent',
    'tigerimage.action.cancel' => 'Annuler',
    'tigerimage.action.generate' => 'Générer',
    'tigerimage.generated' => 'Images générées.',
    'tigerimage.promoted' => 'Ajoutée à la médiathèque.',
    'tigerimage.discarded' => 'Image supprimée.',
    'tigerimage.error.prompt_required' => 'Décrivez l\'image que vous voulez.',
    'tigerimage.error.no_image_provider' => 'Aucun fournisseur d\'images n\'est configuré sur ce site.',
    'tigerimage.error.unavailable' => 'La génération d\'images n\'est pas disponible pour le moment.',
    'tigerimage.error.spend_cap_reached' => 'Le budget mensuel d\'images de cette organisation est épuisé.',
    'tigerimage.error.no_api_key' => 'Le fournisseur d\'images n\'a pas de clé d\'API utilisable.',
    'tigerimage.error.provider_cannot_draw' => 'Le fournisseur configuré ne peut pas générer des images.',
    'tigerimage.error.generation_failed' => 'Le fournisseur n\'a pas pu générer cette image.',
    'tigerimage.error.store_failed' => 'L\'image a été générée mais n\'a pas pu être enregistrée.',
    'tigerimage.error.promote_failed' => 'Impossible d\'ajouter l\'image à la médiathèque.',
    'tigerimage.error.no_such_image' => 'Cette image n\'existe pas.',
    'tigerimage.action.details' => 'Détails',
    'tigerimage.action.refine' => 'Affiner',
    'tigerimage.action.refine_from_this' => 'Affiner à partir de celle-ci',
    'tigerimage.action.keep' => 'Garder',
    'tigerimage.action.bin' => 'Supprimer',
    'tigerimage.action.bin_confirm' => 'Supprimer',
    'tigerimage.state.in_media' => 'Dans la médiathèque',
    'tigerimage.confirm.bin_title' => 'Supprimer cette image ?',
    'tigerimage.confirm.bin_body' => 'Elle est retirée du stockage. Les affinages réalisés à partir d\'elle sont conservés et rattachés à leur image d\'origine.',
    'tigerimage.detail.title' => 'Détails de l\'image',
    'tigerimage.detail.prompt' => 'Description',
    'tigerimage.detail.alt' => 'Texte alternatif',
    'tigerimage.detail.how_made' => 'Comment elle a été créée',
    'tigerimage.detail.provider' => 'Fournisseur',
    'tigerimage.detail.model' => 'Modèle',
    'tigerimage.detail.size' => 'Taille',
    'tigerimage.detail.lineage' => 'Historique',
    'tigerimage.detail.lineage_note' => '%1$s étapes — cette image est la n° %2$s de la chaîne.',
    'tigerimage.error.list_failed' => 'Impossible de charger vos images.',
    'tigerimage.error.get_failed' => 'Impossible de charger cette image.',
    'tigerimage.error.refine_failed' => 'Impossible d\'affiner cette image.',
    'tigerimage.error.discard_failed' => 'Impossible de supprimer cette image.',
    'tigerimage.error.spend_cap_reached_token' => 'Le budget mensuel d\'images de cette clé d\'accès est épuisé.',
    'tigerimage.budget.label' => 'Budget',
    'tigerimage.budget.org' => 'Budget mensuel d\'images de cette organisation.',
    'tigerimage.budget.token' => 'Budget mensuel d\'images de cette clé d\'accès.',
    'tigerimage.budget.estimated' => 'Estimé, non facturé.',
];
