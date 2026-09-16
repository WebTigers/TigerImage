<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerimage_StudioController — the image studio (TIGER-98).
 *
 * An image is never right first time. The screen's whole job is to make the second, third and tenth
 * attempt cheap: a grid to compare variants, the prompt and parameters editable on any of them, and
 * refine-from-here on every tile. That is what MidJourney and Tensor.Art get right and what an upload
 * form gets wrong.
 *
 * Two actions only. Everything that MUTATES goes through /api (Tigerimage_Service_Image), so the
 * screen and an agent drive exactly the same surface — no second code path that can drift.
 *
 * Route: /tigerimage/studio  ·  /tigerimage/studio/raw/id/<id>
 */
class Tigerimage_StudioController extends Tiger_Controller_Admin_Action
{
    /** Admin shell (layout) comes from the base; keep the explicit init cascade (ADMIN.md). The studio
     *  is an admin tool reached from the sidebar (Images), so it must render in the admin layout — not
     *  the public site theme. rawAction() disables the layout itself, so image streaming is unaffected. */
    public function init()
    {
        parent::init();
    }

    /** The grid. Data is fetched by the page's JS from /api, so this action only sets up the shell. */
    public function indexAction()
    {
        $cap = Tigerimage_Model_Provider::capability();
        $this->view->capability = $cap;

        // A diagnostic when the module is installed but cannot draw — so an operator reading the system
        // log learns the reason, not just a silent "Active" (TIGER-147). Operator-triggered (a Studio
        // visit), so it never spams; fail-soft on an older core with no logger.
        if (empty($cap['available']) && class_exists('Tiger_Log')) {
            Tiger_Log::warn('tigerimage.unavailable', ['reason' => $cap['reason'] ?? 'unknown', 'detail' => $cap['detail'] ?? '']);
        }

        // The budget gauge (TIGER-105), rendered server-side so it is right on first paint rather
        // than appearing a moment later. A page view is always a session login — it has no
        // credential — so the org ceiling is the only one that can bind here.
        $identity = Zend_Auth::getInstance()->getIdentity();
        $this->view->spend = Tigerimage_Model_Spend::summary((string) ($identity->org_id ?? ''));

        $this->view->headTitle($this->view->t('tigerimage.studio.title'));
    }

    /**
     * Stream a generated image through an ACL + org-scope check.
     *
     * Generated images live OUTSIDE the docroot and are not in the Media Library until promoted, so
     * there is no URL the web server can serve — the studio cannot show a grid without this. Modelled
     * on Media_FileController::serveAction().
     *
     * `no-store`: an unpromoted image is a draft, and a draft that a shared proxy has cached is a
     * draft that outlives its own deletion.
     */
    public function rawAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $response = $this->getResponse();

        $identity = Zend_Auth::getInstance()->getIdentity();
        $orgId    = (string) ($identity->org_id ?? '');
        if ($orgId === '') { $response->setHttpResponseCode(403); return; }

        $row = (new Tigerimage_Model_Image())->byId((string) $this->getParam('id', ''), $orgId);
        if (!$row) { $response->setHttpResponseCode(404); return; }

        try {
            $disk = Tigerimage_Model_Store::disk();
            if (!$disk->exists((string) $row->path, 'private')) { $response->setHttpResponseCode(404); return; }
            $bytes = $disk->get((string) $row->path, 'private');
        } catch (Throwable $e) {
            $response->setHttpResponseCode(404); return;
        }

        $response->setHeader('Content-Type', (string) $row->mime ?: 'image/png', true)
                 ->setHeader('Content-Length', (string) strlen((string) $bytes), true)
                 ->setHeader('Cache-Control', 'private, no-store', true)
                 ->setHeader('X-Content-Type-Options', 'nosniff', true)
                 ->setBody($bytes);
    }
}
