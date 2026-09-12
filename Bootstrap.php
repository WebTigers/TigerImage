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
     * Teach the module autoloader about `providers/`.
     *
     * ZF1's resource loader knows the standard namespaces — Model, Service, Form, Plugin, DbTable —
     * and nothing else. `Tigerimage_Provider_*` is ours, so without this it is simply not loadable.
     * Discovered the hard way: the class-not-found took down the WHOLE application boot, not just
     * image generation, because a module Bootstrap failing is a fatal during Resource_Modules.
     */
    protected function _initProviderAutoload()
    {
        $loader = $this->getResourceLoader();
        if ($loader) {
            $loader->addResourceType('provider', 'providers', 'Provider');
        }
    }

    /**
     * Register this module's image adapters with the core provider registry (TIGER-103).
     *
     * THIS is the loose coupling: core declares Tiger_Agent_Provider_ImageAdapter and holds a
     * register; it ships no image-generation code and knows nothing about which models draw. An
     * install without this module has no image capability and reports so honestly, rather than
     * carrying calls to an endpoint it never makes. The same path is open to a future audio, video or
     * embedding module — none of them need to touch core.
     *
     * FAIL-SAFE BY DESIGN. Registering a capability must never be able to take down the site. A
     * module Bootstrap that throws is fatal during Resource_Modules — every page, not just this
     * module's — so anything missing here degrades to "cannot draw" rather than to a white screen.
     */
    protected function _initImageProviders()
    {
        $this->bootstrap('providerAutoload');

        try {
            if (!class_exists('Tiger_Agent_Provider_Factory')
                || !method_exists('Tiger_Agent_Provider_Factory', 'registerImageAdapter')) {
                return;   // older core: stay inert
            }
            foreach (['openai' => 'Tigerimage_Provider_OpenAi', 'gemini' => 'Tigerimage_Provider_Gemini'] as $key => $class) {
                if (class_exists($class)) {
                    Tiger_Agent_Provider_Factory::registerImageAdapter($key, new $class());
                }
            }
        } catch (Throwable $e) {
            // Losing image generation is a degraded feature; a fatal here is a dead site.
            if (class_exists('Tiger_Log')) {
                Tiger_Log::error('tigerimage.register_failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
