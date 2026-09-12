<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerImage module bootstrap.
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader, so
 * Tigerimage_Service_* (services/), Tigerimage_Model_* (models/) and Tigerimage_Provider_*
 * (providers/) load by convention; configs/acl.ini + languages/ are picked up by the core globs.
 */
class Tigerimage_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /**
     * Register this module's image adapters with the core provider registry (TIGER-103).
     *
     * THIS is the loose coupling: core declares Tiger_Agent_Provider_ImageAdapter and holds a
     * register; it ships no image-generation code and knows nothing about which models draw. An
     * install without this module has no image capability and reports so honestly, rather than
     * carrying calls to an endpoint it never makes.
     *
     * The same path is open to any future audio, video or embedding module — none of them need to
     * touch core.
     */
    protected function _initImageProviders()
    {
        if (!class_exists('Tiger_Agent_Provider_Factory')
            || !method_exists('Tiger_Agent_Provider_Factory', 'registerImageAdapter')) {
            return;   // older core: stay inert rather than fatal
        }
        Tiger_Agent_Provider_Factory::registerImageAdapter('openai', new Tigerimage_Provider_OpenAi());
        Tiger_Agent_Provider_Factory::registerImageAdapter('gemini', new Tigerimage_Provider_Gemini());
    }
}
