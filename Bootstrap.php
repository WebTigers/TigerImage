<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerImage module bootstrap.
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader, so
 * Tigerimage_Service_* (services/) and Tigerimage_Model_* (models/) load by convention;
 * configs/acl.ini + languages/ are picked up by the core globs.
 */
class Tigerimage_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /**
     * Teach the module autoloader about `adapters/`.
     *
     * ZF1 ships exactly eight resource types — Model_DbTable, Model_Mapper, Form, Model, Plugin,
     * Service, View_Helper, View_Filter — so anything else must be declared. That is ordinary practice
     * here rather than a workaround: analytics, register, TigerLicense and TigerStripe each declare
     * `Widget`, TigerRegistry declares `Domain`, and TigerMarketplace declares this exact
     * `Adapter`/`adapters/` type. Matching it keeps one name for one concept across modules.
     */
    protected function _initAdapterAutoload()
    {
        $loader = $this->getResourceLoader();
        if ($loader) {
            $loader->addResourceType('adapter', 'adapters', 'Adapter');
        }
    }

    /**
     * Register this module's image adapters with the core provider registry (TIGER-103).
     *
     * THIS is the loose coupling: core declares Tiger_Agent_Provider_ImageAdapter and holds a
     * register; it ships no image-generation code and knows nothing about which models draw. An
     * install without this module has no image capability and reports so honestly. The same path is
     * open to a future audio, video or embedding module — none of them need to touch core.
     *
     * CLASS NAMES, NOT INSTANCES. Nothing is constructed here, so autoload timing cannot matter and a
     * request that never generates an image never builds an adapter. Core resolves on first use and
     * degrades to "cannot draw" if a name turns out to be wrong.
     *
     * The try/catch is belt-and-braces on top of that: a module Bootstrap that throws is fatal during
     * Resource_Modules — it takes down every page, not just this module's — and losing image
     * generation must never be able to cost the site.
     */
    protected function _initImageAdapters()
    {
        $this->bootstrap('adapterAutoload');

        try {
            if (!class_exists('Tiger_Agent_Provider_Factory')
                || !method_exists('Tiger_Agent_Provider_Factory', 'registerImageAdapter')) {
                return;   // older core: stay inert rather than fatal
            }
            Tiger_Agent_Provider_Factory::registerImageAdapter('openai', 'Tigerimage_Adapter_OpenAi');
            Tiger_Agent_Provider_Factory::registerImageAdapter('gemini', 'Tigerimage_Adapter_Gemini');
        } catch (Throwable $e) {
            if (class_exists('Tiger_Log')) {
                Tiger_Log::error('tigerimage.register_failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Put TigerImage in the admin nav — with a HEALTH BADGE.
     *
     * Two things the module lacked (TIGER-147): it registered no nav item at all (the Studio was
     * reachable only by URL, so an operator could not find it), and nothing surfaced that it could not
     * actually draw — it just read "Active". The badge is that health signal: it lights up (an
     * attention pill) exactly when Tigerimage_Model_Provider::capability() reports unavailable — no
     * image provider, no key, or the spend cap reached — and clears when it can draw again. Clicking
     * through lands on the Studio, which states the reason and the fix. Cheap + fail-soft: the badge
     * runs on every admin render, so it only reads config (never the provider), and a throw shows no
     * badge rather than breaking the menu.
     */
    protected function _initAdminNav()
    {
        if (!class_exists('Tiger_Admin_Nav')) { return; }

        Tiger_Admin_Nav::register([
            'key'      => 'tigerimage',
            'label'    => 'tigerimage.nav.label',
            'icon'     => 'fa-image',
            'href'     => '/tigerimage/studio',
            'resource' => 'Tigerimage_StudioController',
            'order'    => 42,
            'badge'    => static function () {
                if (!class_exists('Tigerimage_Model_Provider')) { return 0; }
                try {
                    $cap = Tigerimage_Model_Provider::capability();
                    return empty($cap['available']) ? 1 : 0;   // 1 = needs attention (unavailable)
                } catch (Throwable $e) {
                    return 0;
                }
            },
        ]);
    }
}
