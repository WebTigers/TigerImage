<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerimage_Service_Image — the agent-facing surface (TIGER-99).
 *
 * This is the reason the module exists. A text-only assistant — Claude, or anything that can call a
 * tool — reaches these methods over `/api` or MCP and comes away with images in the Media Library, so
 * it can finish a website rather than hand back a page with no pictures.
 *
 * No new transport: services are reflected into `/api` and therefore into MCP by the core, through the
 * same ACL that governs the call. Discovery IS authorization — a caller who cannot see a method here
 * cannot invoke it either.
 *
 * Every error is a NAMED, distinguishable reason (TIGER-99's failure vocabulary). "Something went
 * wrong" tells an agent nothing it can act on; `no_image_provider` tells it to stop promising images
 * and say why.
 *
 * @api
 */
class Tigerimage_Service_Image extends Tiger_Service_Service
{
    /** Hard ceiling per call, whatever the caller asks for. An agent loop must not order 50 images. */
    const MAX_N = 4;

    /**
     * Can this install generate images right now, and with what?
     *
     * ALWAYS CALL THIS FIRST. An agent that promises a user an image and then discovers no provider is
     * configured has already made a promise it cannot keep — the failure belongs before the sentence,
     * not after it.
     *
     * @param  array $params unused
     * @return void
     */
    public function capability(array $params): void
    {
        $cap = $this->_capability();
        $cap['max_per_call'] = self::MAX_N;
        $cap['spend']        = $this->_spendSummary($this->_orgId(), $this->_credentialId());

        // An agent must be able to learn it is out of budget WITHOUT spending anything to find out.
        // Either ceiling can be the exhausted one, and the answer says WHICH — an agent told only
        // "no budget" cannot tell whether it has been throttled or the organisation has stopped.
        $orgDone   = $cap['spend']['remaining'] !== null && $cap['spend']['remaining'] <= 0;
        $tokenDone = isset($cap['spend']['token']) && $cap['spend']['token']['remaining'] <= 0;
        if (!empty($cap['available']) && ($orgDone || $tokenDone) && $cap['spend']['enforce'] === 'hard') {
            $cap['available'] = false;
            $cap['reason']    = 'spend_cap_reached';
            $cap['limit']     = $tokenDone ? 'token' : 'org';
            $cap['detail']    = $tokenDone
                ? 'The monthly image budget for this access key is used up.'
                : 'The monthly image budget for this organisation is used up.';
        }
        $this->_success($cap);
    }


    /**
     * Map a capability reason to a DEFINED translation key.
     *
     * Explicit, not concatenated. An unknown reason falls back to a key that exists, so the worst
     * outcome is a vaguer message rather than a raw `tigerimage.error.whatever` shown to a user.
     * Anything added here must be added to languages/en.ini — the conventions test enforces it.
     *
     * @param  string $reason from Tigerimage_Model_Provider::capability()
     * @return string
     */
    public static function reasonKey($reason)
    {
        switch ((string) $reason) {
            case 'no_image_provider': return 'tigerimage.error.no_image_provider';
            case 'no_api_key':        return 'tigerimage.error.no_api_key';
            case 'spend_cap_reached': return 'tigerimage.error.spend_cap_reached';
            default:                  return 'tigerimage.error.unavailable';
        }
    }

    /**
     * Generate images from a prompt.
     *
     * @param  array $params prompt (required), negative?, size?, n?, seed?, parent_id?, reference_id?
     * @return void
     * @apiRequest Tigerimage_Form_Generate
     */
    public function generate(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $prompt = trim((string) ($params['prompt'] ?? ''));
        if ($prompt === '') { $this->_error('tigerimage.error.prompt_required'); return; }

        $cap = $this->_capability();
        if (empty($cap['available'])) {
            // Hand back the REASON, not a generic failure — the agent's next sentence depends on it.
            // Mapped explicitly rather than concatenated: a built key means a NEW reason (a spend cap,
            // say) emits a translation key nobody defined, and the user sees the raw string.
            $this->_error(self::reasonKey($cap['reason'] ?? ''), ['detail' => $cap['detail'] ?? '']);
            return;
        }

        $resolved = Tigerimage_Model_Provider::resolve();
        // The REGISTERED image adapter, not Factory::make() — make() returns the core text adapter,
        // which by design cannot draw (TIGER-103).
        $adapter  = Tiger_Agent_Provider_Factory::imageAdapter($resolved['provider']);
        if ($adapter === null) {
            $this->_error('tigerimage.error.provider_cannot_draw');
            return;
        }

        $options = [
            'negative_prompt' => trim((string) ($params['negative'] ?? '')),
            'size'            => trim((string) ($params['size'] ?? '')),
            'n'               => max(1, min(self::MAX_N, (int) ($params['n'] ?? 1))),
        ];
        if (isset($params['seed']) && $params['seed'] !== '') { $options['seed'] = (int) $params['seed']; }

        // A reference image is named by ID, never uploaded through this call: the image is already in
        // our store, and making an agent round-trip base64 through the API would be slower, larger and
        // a way to smuggle arbitrary bytes past the size caps.
        $orgId  = $this->_orgId();
        $parent = null;
        $refId  = trim((string) ($params['reference_id'] ?? $params['parent_id'] ?? ''));
        if ($refId !== '') {
            $parent = (new Tigerimage_Model_Image())->byId($refId, $orgId);
            if (!$parent) { $this->_error('tigerimage.error.no_such_image'); return; }
            $bytes = Tigerimage_Model_Store::disk()->get((string) $parent->path, 'private');
            if ($bytes) {
                $options['reference'] = ['mime' => (string) $parent->mime, 'data' => base64_encode($bytes)];
            }
        }

        // BEFORE the provider is contacted. A cap discovered by going over it is not a cap, and the
        // alternative to checking here is finding out from an invoice.
        $estimate = Tigerimage_Model_Pricing::estimate(
            $resolved['provider'], $resolved['model'], $options['n'], $options['size']
        );
        $credentialId = $this->_credentialId();
        $spend = $this->_spendCheck($orgId, $estimate, $credentialId);
        if (!$spend['allowed']) {
            // Name the ceiling that actually bound. "The organisation's budget is used up" is simply
            // false when it was the key that ran out, and it sends the reader to fix the wrong thing.
            $capKey = (($spend['limit'] ?? '') === 'token')
                ? 'tigerimage.error.spend_cap_reached_token'
                : 'tigerimage.error.spend_cap_reached';
            $this->_error($capKey, [
                'spent'     => $spend['spent'],
                'cap'       => $spend['cap'],
                'estimate'  => $estimate,
                'remaining' => $spend['remaining'],
                // 'org' or 'token' — otherwise a user told "budget used up" cannot tell whether to
                // raise the org cap or widen one key.
                'limit'     => $spend['limit'] ?? null,
                // Enough for the studio to repaint its gauge from the refusal itself (TIGER-105).
                'fraction'  => $spend['fraction'] ?? null,
            ]);
            return;
        }

        try {
            $result = $adapter->generateImage($prompt, $options, $resolved['model'], $this->_apiKey($resolved));
        } catch (Throwable $e) {
            // A provider refusal (safety filter, bad prompt) is NOT a Tiger bug — pass the reason
            // through so the agent can tell the user what to change.
            $this->_error('tigerimage.error.generation_failed', ['detail' => $e->getMessage()]);
            return;
        }

        try {
            $ids = $this->_library()->store($result, [
                'org_id'    => $orgId,
                'prompt'    => $prompt,
                'negative'  => $options['negative_prompt'] ?: null,
                'parent_id' => $parent ? (string) $parent->image_id : null,
                // Attribute the spend to the key that incurred it, so a per-token ceiling is enforceable.
                'credential_id' => $credentialId,
                // Charge per image actually returned, not per image requested — a provider that gave
                // us three when we asked for four should not bill the org for four.
                'cost'      => Tigerimage_Model_Pricing::estimate(
                    $resolved['provider'], $resolved['model'], count($result['images'] ?? []), $options['size']
                ) / max(1, count($result['images'] ?? [1])),
            ]);
        } catch (Throwable $e) {
            $this->_error('tigerimage.error.store_failed', ['detail' => $e->getMessage()]);
            return;
        }

        $this->_success([
            'images'   => $this->_describeMany($ids, $orgId),
            'provider' => $resolved['provider'],
            'model'    => $resolved['model'],
            // Told after every generation, so a loop can see its own budget shrinking rather than
            // discovering the ceiling by hitting it.
            'spend'    => $this->_spendSummary($orgId, $credentialId),
        ], 'tigerimage.generated');
    }

    /**
     * Refine an existing image — a new generation parented to it, inheriting its parameters unless
     * overridden. `refine` exists separately from `generate` so an agent does not have to know that
     * "the same but warmer" means re-sending every parameter it never saw.
     *
     * @param  array $params image_id (required), prompt?, negative?, size?, n?
     * @return void
     */
    public function refine(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $id  = trim((string) ($params['image_id'] ?? ''));
        $m   = new Tigerimage_Model_Image();
        $row = $id !== '' ? $m->byId($id, $this->_orgId()) : null;
        if (!$row) { $this->_error('tigerimage.error.no_such_image'); return; }

        $prior = $m->paramsOf($row);
        $this->generate([
            'prompt'       => trim((string) ($params['prompt'] ?? '')) ?: (string) $row->prompt,
            'negative'     => $params['negative'] ?? (string) $row->negative,
            'size'         => $params['size'] ?? ($prior['size'] ?? ''),
            'n'            => $params['n'] ?? 1,
            'reference_id' => (string) $row->image_id,
        ]);
    }

    /** Recent images for this org, newest first, with their parameters. */
    public function listImages(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $limit = max(1, min(200, (int) ($params['limit'] ?? 60)));
        $rows  = (new Tigerimage_Model_Image())->recentForOrg($this->_orgId(), $limit);

        $out = [];
        foreach ($rows as $r) { $out[] = $this->_describe($r); }
        $this->_success(['images' => $out]);
    }

    /** One image, with its full refinement chain so an agent can see how it got here. */
    public function get(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $m   = new Tigerimage_Model_Image();
        $row = $m->byId(trim((string) ($params['image_id'] ?? '')), $this->_orgId());
        if (!$row) { $this->_error('tigerimage.error.no_such_image'); return; }

        $chain = [];
        foreach ($m->chainFor((string) $row->image_id) as $link) { $chain[] = $this->_describe($link); }
        $this->_success(['image' => $this->_describe($row), 'chain' => $chain]);
    }

    /**
     * Promote an image into the Media Library and return the media id — the value an agent drops
     * straight into the page it is building.
     *
     * @param  array $params image_id (required), title?, alt?
     * @return void
     */
    public function promote(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $id = trim((string) ($params['image_id'] ?? ''));
        if ($id === '') { $this->_error('tigerimage.error.no_such_image'); return; }

        try {
            $mediaId = (new Tigerimage_Service_Library())->promote($id, $this->_orgId(), [
                'title' => trim((string) ($params['title'] ?? '')) ?: null,
                'alt'   => trim((string) ($params['alt'] ?? '')) ?: null,
            ]);
        } catch (Throwable $e) {
            $this->_error('tigerimage.error.promote_failed', ['detail' => $e->getMessage()]);
            return;
        }
        $this->_success(['media_id' => $mediaId, 'image_id' => $id], 'tigerimage.promoted');
    }

    /** Discard an image. Its refinements are re-parented, not deleted. */
    public function discard(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        try {
            $r = (new Tigerimage_Service_Library())->discard(trim((string) ($params['image_id'] ?? '')), $this->_orgId());
        } catch (Throwable $e) {
            $this->_error('tigerimage.error.no_such_image');
            return;
        }
        $this->_success($r, 'tigerimage.discarded');
    }


    /* ---- seams --------------------------------------------------------------------------------
     * Two thin indirections so the cap-enforcement point can be tested without a live provider key.
     * The guard between the capability check and the paid API call is the most consequential line in
     * this class — "did we contact a billable endpoint" is the one property that costs money to get
     * wrong — and it is worth being able to assert it in a unit test.
     * ------------------------------------------------------------------------------------------ */

    /** @return array the capability answer */
    protected function _capability()
    {
        return Tigerimage_Model_Provider::capability();
    }

    /** @return string the provider key */
    protected function _apiKey(array $resolved)
    {
        return Tigerimage_Model_Provider::apiKey($resolved);
    }

    /** @return array the spend decision — see Tigerimage_Model_Spend::check() */
    protected function _spendCheck($orgId, $estimate, $credentialId = null)
    {
        return Tigerimage_Model_Spend::check($orgId, $estimate, $credentialId);
    }

    /** @return array the spend picture — see Tigerimage_Model_Spend::summary(). A seam, like _spendCheck. */
    protected function _spendSummary($orgId, $credentialId = null)
    {
        return Tigerimage_Model_Spend::summary($orgId, $credentialId);
    }

    /** @return Tigerimage_Service_Library the store, behind a seam so what gets RECORDED is assertable */
    protected function _library()
    {
        return new Tigerimage_Service_Library();
    }

    /* ---- helpers ---------------------------------------------------------------------------- */


    /**
     * Which credential authenticated this call, or null for a session user.
     *
     * Core carries this on the identity from 1.5.21 (TIGER-102). Before that it was simply not
     * knowable, which is why a scoped token could spend an organisation's whole budget. Guarded, so
     * the module degrades to org-cap-only on an older core rather than fataling.
     */
    protected function _credentialId()
    {
        $identity = Zend_Auth::getInstance()->getIdentity();
        $id = $identity->credential_id ?? null;
        return ($id === null || $id === '') ? null : (string) $id;
    }

    /** The caller's org. */
    protected function _orgId()
    {
        $identity = Zend_Auth::getInstance()->getIdentity();
        return (string) ($identity->org_id ?? '');
    }

    /**
     * The shape an agent sees. Deliberately NOT the image bytes: a tool result is text in a model's
     * context window, and base64 of a 1024px PNG is ~1.4MB of it. Ids and metadata are what an agent
     * needs to reason and promote; a human looks at the studio.
     */
    protected function _describe($row)
    {
        $m = new Tigerimage_Model_Image();
        return [
            'image_id'  => (string) $row->image_id,
            'state'     => (string) $row->state,
            'parent_id' => $row->parent_id ? (string) $row->parent_id : null,
            'media_id'  => $row->media_id ? (string) $row->media_id : null,
            'prompt'    => (string) $row->prompt,
            'negative'  => $row->negative ? (string) $row->negative : null,
            'provider'  => (string) $row->provider,
            'model'     => (string) $row->model,
            'params'    => $m->paramsOf($row),
            'mime'      => (string) $row->mime,
            'width'     => (int) $row->width,
            'height'    => (int) $row->height,
            'bytes'     => (int) $row->bytes,
            'created_at'=> (string) $row->created_at,
        ];
    }

    /** @param array<int,string> $ids */
    protected function _describeMany(array $ids, $orgId)
    {
        $m   = new Tigerimage_Model_Image();
        $out = [];
        foreach ($ids as $id) {
            $row = $m->byId($id, $orgId);
            if ($row) { $out[] = $this->_describe($row); }
        }
        return $out;
    }
}
