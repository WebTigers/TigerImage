<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerImage — Portuguese strings.
 *
 * Tiger loads module translations from `languages/<lang>/<name>.php`, each file RETURNING a
 * [key => string] array. A module owns its own `tigerimage.*` keys and ships its own translations
 * for every locale — core never translates a module.
 *
 * Placeholders (%s, %s) may be REORDERED to suit the language; tf() in the studio JS and
 * sprintf() on the server both address them by number.
 */
return [
    'tigerimage.studio.title' => 'Estúdio de imagens',
    'tigerimage.studio.subtitle' => 'Descreva uma imagem, compare o que voltar e fique com a que quiser.',
    'tigerimage.studio.unavailable' => 'A geração de imagens não está configurada neste site',
    'tigerimage.studio.empty' => 'Nada gerado ainda. Descreva uma imagem acima para começar.',
    'tigerimage.studio.provider_note' => 'Gerando com',
    'tigerimage.field.prompt' => 'Descreva a imagem',
    'tigerimage.field.prompt_hint' => 'Um gato rajado dormindo sobre uma pilha de livros, luz quente de fim de tarde',
    'tigerimage.field.negative' => 'Evitar',
    'tigerimage.field.size' => 'Formato',
    'tigerimage.field.count' => 'Quantas',
    'tigerimage.size.square' => 'Quadrada',
    'tigerimage.size.landscape' => 'Horizontal',
    'tigerimage.size.portrait' => 'Vertical',
    'tigerimage.action.cancel' => 'Cancelar',
    'tigerimage.action.generate' => 'Gerar',
    'tigerimage.generated' => 'Imagens geradas.',
    'tigerimage.promoted' => 'Adicionada à biblioteca de mídia.',
    'tigerimage.discarded' => 'Imagem descartada.',
    'tigerimage.error.prompt_required' => 'Descreva a imagem que você quer.',
    'tigerimage.error.no_image_provider' => 'Nenhum provedor de imagens está configurado neste site.',
    'tigerimage.error.unavailable' => 'A geração de imagens não está disponível no momento.',
    'tigerimage.error.spend_cap_reached' => 'O orçamento mensal de imagens desta organização acabou.',
    'tigerimage.error.no_api_key' => 'O provedor de imagens não tem uma chave de API utilizável.',
    'tigerimage.error.provider_cannot_draw' => 'O provedor configurado não consegue gerar imagens.',
    'tigerimage.error.generation_failed' => 'O provedor não conseguiu gerar essa imagem.',
    'tigerimage.error.store_failed' => 'A imagem foi gerada, mas não pôde ser armazenada.',
    'tigerimage.error.promote_failed' => 'Não foi possível adicionar a imagem à biblioteca de mídia.',
    'tigerimage.error.no_such_image' => 'Essa imagem não existe.',
    'tigerimage.action.details' => 'Detalhes',
    'tigerimage.action.refine' => 'Refinar',
    'tigerimage.action.refine_from_this' => 'Refinar a partir desta',
    'tigerimage.action.keep' => 'Manter',
    'tigerimage.action.bin' => 'Descartar',
    'tigerimage.action.bin_confirm' => 'Descartar',
    'tigerimage.state.in_media' => 'Na mídia',
    'tigerimage.confirm.bin_title' => 'Descartar esta imagem?',
    'tigerimage.confirm.bin_body' => 'Ela é removida do armazenamento. Os refinamentos feitos a partir dela são mantidos e revinculados à imagem de origem.',
    'tigerimage.detail.title' => 'Detalhes da imagem',
    'tigerimage.detail.prompt' => 'Descrição',
    'tigerimage.detail.alt' => 'Texto alternativo',
    'tigerimage.detail.how_made' => 'Como foi criada',
    'tigerimage.detail.provider' => 'Provedor',
    'tigerimage.detail.model' => 'Modelo',
    'tigerimage.detail.size' => 'Tamanho',
    'tigerimage.detail.lineage' => 'Histórico',
    'tigerimage.detail.lineage_note' => '%1$s etapas: esta imagem é a nº %2$s da sequência.',
    'tigerimage.error.list_failed' => 'Não foi possível carregar suas imagens.',
    'tigerimage.error.get_failed' => 'Não foi possível carregar essa imagem.',
    'tigerimage.error.refine_failed' => 'Não foi possível refinar essa imagem.',
    'tigerimage.error.discard_failed' => 'Não foi possível descartar essa imagem.',
    'tigerimage.error.spend_cap_reached_token' => 'O orçamento mensal de imagens desta chave de acesso acabou.',
    'tigerimage.budget.label' => 'Orçamento',
    'tigerimage.budget.org' => 'Orçamento mensal de imagens desta organização.',
    'tigerimage.budget.token' => 'Orçamento mensal de imagens desta chave de acesso.',
    'tigerimage.budget.estimated' => 'Estimado, não faturado.',
];
