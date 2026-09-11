<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerImage module bootstrap.
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader, so
 * Tigerimage_Service_* (services/) and Tigerimage_Model_* (models/) load by convention;
 * configs/acl.ini + languages/ are picked up by the core globs.
 *
 * Deliberately thin: this module registers no front-controller plugin and no view helper. Its whole
 * surface is services, which the core reflects into /api and therefore into MCP.
 */
class Tigerimage_Bootstrap extends Zend_Application_Module_Bootstrap
{
}
