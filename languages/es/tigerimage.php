<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerImage — Spanish strings.
 *
 * Tiger loads module translations from `languages/<lang>/<name>.php`, each file RETURNING a
 * [key => string] array. A module owns its own `tigerimage.*` keys and ships its own translations
 * for every locale — core never translates a module.
 *
 * Placeholders (%s, %s) may be REORDERED to suit the language; tf() in the studio JS and
 * sprintf() on the server both address them by number.
 */
return [
    'tigerimage.nav.label'                  => 'Imágenes',
    'tigerimage.studio.title' => 'Estudio de imágenes',
    'tigerimage.studio.subtitle' => 'Describe una imagen, compara los resultados y quédate con la que quieras.',
    'tigerimage.studio.unavailable' => 'La generación de imágenes no está configurada en este sitio',
    'tigerimage.studio.empty' => 'Todavía no has generado nada. Describe una imagen arriba para empezar.',
    'tigerimage.studio.provider_note' => 'Generando con',
    'tigerimage.field.prompt' => 'Describe la imagen',
    'tigerimage.field.prompt_hint' => 'Un gato atigrado dormido sobre una pila de libros, luz cálida de tarde',
    'tigerimage.field.negative' => 'Evitar',
    'tigerimage.field.size' => 'Formato',
    'tigerimage.field.count' => 'Cuántas',
    'tigerimage.size.square' => 'Cuadrada',
    'tigerimage.size.landscape' => 'Horizontal',
    'tigerimage.size.portrait' => 'Vertical',

    // Provider-specific generation params (rendered per honored set)
    'tigerimage.field.seed' => 'Semilla',
    'tigerimage.field.quality' => 'Calidad',
    'tigerimage.field.background' => 'Fondo',
    'tigerimage.field.output_format' => 'Formato',
    'tigerimage.field.output_compression' => 'Compresión',
    'tigerimage.opt.auto' => 'Automático',
    'tigerimage.opt.low' => 'Baja',
    'tigerimage.opt.medium' => 'Media',
    'tigerimage.opt.high' => 'Alta',
    'tigerimage.opt.opaque' => 'Opaco',
    'tigerimage.opt.transparent' => 'Transparente',
    'tigerimage.action.cancel' => 'Cancelar',
    'tigerimage.action.generate' => 'Generar',
    'tigerimage.generated' => 'Imágenes generadas.',
    'tigerimage.promoted' => 'Añadida a la biblioteca de medios.',
    'tigerimage.discarded' => 'Imagen descartada.',
    'tigerimage.error.prompt_required' => 'Describe la imagen que quieres.',
    'tigerimage.error.no_image_provider' => 'No hay ningún proveedor de imágenes configurado en este sitio.',
    'tigerimage.error.unavailable' => 'La generación de imágenes no está disponible en este momento.',
    'tigerimage.error.spend_cap_reached' => 'El presupuesto mensual de imágenes de esta organización se ha agotado.',
    'tigerimage.error.no_api_key' => 'El proveedor de imágenes no tiene una clave de API utilizable.',
    'tigerimage.error.provider_cannot_draw' => 'El proveedor configurado no puede generar imágenes.',
    'tigerimage.error.generation_failed' => 'El proveedor no pudo generar esa imagen.',
    'tigerimage.error.store_failed' => 'La imagen se generó pero no se pudo guardar.',
    'tigerimage.error.promote_failed' => 'No se pudo añadir la imagen a la biblioteca de medios.',
    'tigerimage.error.no_such_image' => 'No existe esa imagen.',
    'tigerimage.action.details' => 'Detalles',
    'tigerimage.action.refine' => 'Refinar',
    'tigerimage.action.refine_from_this' => 'Refinar a partir de esta',
    'tigerimage.action.keep' => 'Conservar',
    'tigerimage.action.bin' => 'Descartar',
    'tigerimage.action.bin_confirm' => 'Descartarla',
    'tigerimage.state.in_media' => 'En medios',
    'tigerimage.confirm.bin_title' => '¿Descartar esta imagen?',
    'tigerimage.confirm.bin_body' => 'Se elimina del almacenamiento. Los refinamientos hechos a partir de ella se conservan y se vuelven a vincular a su imagen de origen.',
    'tigerimage.detail.title' => 'Detalles de la imagen',
    'tigerimage.detail.prompt' => 'Descripción',
    'tigerimage.detail.alt' => 'Texto alternativo',
    'tigerimage.detail.how_made' => 'Cómo se creó',
    'tigerimage.detail.provider' => 'Proveedor',
    'tigerimage.detail.model' => 'Modelo',
    'tigerimage.detail.size' => 'Tamaño',
    'tigerimage.detail.lineage' => 'Historial',
    'tigerimage.detail.lineage_note' => '%1$s pasos: esta imagen es la n.º %2$s de la cadena.',
    'tigerimage.error.list_failed' => 'No se pudieron cargar tus imágenes.',
    'tigerimage.error.get_failed' => 'No se pudo cargar esa imagen.',
    'tigerimage.error.refine_failed' => 'No se pudo refinar esa imagen.',
    'tigerimage.error.discard_failed' => 'No se pudo descartar esa imagen.',
    'tigerimage.error.spend_cap_reached_token' => 'El presupuesto mensual de imágenes de esta clave de acceso se ha agotado.',
    'tigerimage.budget.label' => 'Presupuesto',
    'tigerimage.budget.org' => 'Presupuesto mensual de imágenes de esta organización.',
    'tigerimage.budget.token' => 'Presupuesto mensual de imágenes de esta clave de acceso.',
    'tigerimage.budget.estimated' => 'Estimado, no facturado.',
];
