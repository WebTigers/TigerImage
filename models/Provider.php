<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerimage_Model_Provider — which provider/model/key draws, and whether anything can (TIGER-99).
 *
 * THE COMMON CASE IS TWO DIFFERENT PROVIDERS. The whole reason this module exists is that Claude
 * cannot draw — so an install whose agent is Anthropic needs a SECOND provider for images. TigerImage
 * therefore carries its own provider/model/key rather than assuming the agent's will do.
 *
 * Resolution, in order:
 *   1. TigerImage's own config, when set. The explicit answer always wins.
 *   2. The agent's provider — but ONLY if it can actually draw. Reusing an Anthropic key for image
 *      generation would fail at call time with a confusing provider error; better to report honestly
 *      that nothing is configured.
 *
 * AI KEYS ARE ORG-LEVEL ONLY (TIGER-511). Everything here is resolved for ONE named org — the caller's
 * org on a request, the image row's org for anything that runs without a signed-in org. Both tiers are
 * that org's own: the org's agent comes from Tiger_Agent::forOrg() (never the signed-in-org facade, never
 * a platform/install agent), and TigerImage's own provider/model/key are read from that org's own config
 * rows. Only the INSTALL org also reads the install-wide tier (global row, else ini) — those settings are
 * its own. No org means no provider: an org with no key of its own cannot draw, it never borrows one.
 *
 * @api
 */
class Tigerimage_Model_Provider
{
    const CFG_PROVIDER = 'tigerimage.provider';
    const CFG_MODEL    = 'tigerimage.model';
    const CFG_KEY_ENC  = 'tigerimage.api_key_enc';

    /**
     * The resolved image provider for an org, or null when nothing that org owns can draw.
     *
     * @param  string|null $orgId the org to resolve for; null = the signed-in org (Tiger_Model_Table::org()).
     *                            Code with no signed-in org (a job, a sweep) passes the row's org.
     * @return array{provider:string,model:string,source:string,org_id:string}|null
     */
    public static function resolve($orgId = null)
    {
        $orgId = self::_org($orgId);
        if ($orgId === '') {
            return null;   // no org, no provider — never an install/platform fallback
        }

        $own      = trim((string) self::_setting($orgId, self::CFG_PROVIDER));
        $ownModel = trim((string) self::_setting($orgId, self::CFG_MODEL));

        if ($own !== '') {
            $model = $ownModel !== '' ? $ownModel : self::_firstDrawingModel($own);
            if ($model !== '' && Tiger_Agent_Provider_Factory::canGenerateImages($own, $model)) {
                return ['provider' => $own, 'model' => $model, 'source' => 'tigerimage', 'org_id' => $orgId];
            }
            // Configured but not capable — say so rather than silently falling through to the agent's,
            // which would hide a misconfiguration behind a working-looking install.
            return null;
        }

        $agent = self::_agent($orgId);
        if ($agent !== null) {
            $p = (string) $agent['provider'];
            $m = (string) $agent['model'];
            // The agent picks a provider + key for TEXT. Images reuse the SAME provider + key but need
            // a drawing model: use the agent's model if it can draw, else this provider's default image
            // model. So setting the agent to OpenAI (or Gemini) with a key is enough to draw — no
            // second TigerImage provider config required (TIGER-147).
            $model = ($m !== '' && Tiger_Agent_Provider_Factory::canGenerateImages($p, $m)) ? $m : self::_firstDrawingModel($p);
            if ($model !== '' && Tiger_Agent_Provider_Factory::canGenerateImages($p, $model)) {
                return ['provider' => $p, 'model' => $model, 'source' => 'agent', 'org_id' => $orgId];
            }
        }
        return null;
    }

    /**
     * The key for the resolved provider — the RESOLVED org's own key, never anyone else's.
     *
     * Falls back to the agent's key ONLY when the provider resolved to the agent's — a key is
     * provider-specific, and handing OpenAI an Anthropic key is a confusing 401 rather than a
     * useful error.
     *
     * @param  array $resolved from resolve() (carries the org it was resolved for)
     * @return string '' when unset or unreadable
     */
    public static function apiKey(array $resolved)
    {
        $orgId = (string) ($resolved['org_id'] ?? '');
        if ($orgId === '') {
            return '';
        }
        if (($resolved['source'] ?? '') === 'agent') {
            $agent = self::_agent($orgId);
            return $agent !== null ? (string) $agent['api_key'] : '';
        }
        $blob = (string) self::_setting($orgId, self::CFG_KEY_ENC);
        if ($blob === '') { return ''; }
        try { return (string) Tiger_Crypto::decrypt($blob); } catch (Throwable $e) { return ''; }
    }

    /**
     * What an agent needs to know BEFORE promising a user an image (TIGER-99).
     *
     * Failing at call time after saying "sure, I'll add a picture" is the bad outcome — so this is
     * answerable without generating anything.
     *
     * @param  string|null $orgId the org asking; null = the signed-in org
     * @return array {available: bool, reason?: string, provider?: string, model?: string, providers: string[]}
     */
    public static function capability($orgId = null)
    {
        $providers = Tiger_Agent_Provider_Factory::imageProviders();
        $resolved  = self::resolve($orgId);

        if (!$resolved) {
            return [
                'available' => false,
                'reason'    => 'no_image_provider',
                'detail'    => 'No image-capable provider is configured. Set tigerimage.provider and '
                             . 'tigerimage.model to one of: ' . implode(', ', $providers) . '.',
                'providers' => $providers,
            ];
        }

        if (self::apiKey($resolved) === '') {
            return [
                'available' => false,
                'reason'    => 'no_api_key',
                'detail'    => ($resolved['source'] ?? '') === 'agent'
                    ? ucfirst($resolved['provider']) . ' can generate images — add its API key in AI agent settings and it works, no separate image provider needed.'
                    : 'An image provider is configured but has no usable API key.',
                'provider'  => $resolved['provider'],
                'model'     => $resolved['model'],
                'providers' => $providers,
            ];
        }

        return [
            'available' => true,
            'provider'  => $resolved['provider'],
            'model'     => $resolved['model'],
            'source'    => $resolved['source'],
            'providers' => $providers,
            // The parameters THIS provider actually honors (OpenAI ≠ Gemini) — so a screen or an agent
            // asks "what can I pass right now?" and gets the truthful, dynamic answer a static schema
            // can't give a multi-provider tool. The studio renders exactly these fields.
            'params'    => self::_imageParams($resolved['provider']),
        ];
    }

    /**
     * The optional generation params a provider HONORS — asked of its registered adapter, so the truth
     * lives in one place (the adapter that actually builds the request), not a static list that drifts.
     *
     * @param  string $provider
     * @return array<int,array>
     */
    protected static function _imageParams($provider)
    {
        $adapter = Tiger_Agent_Provider_Factory::imageAdapter($provider);
        return ($adapter !== null && method_exists($adapter, 'imageParams')) ? $adapter->imageParams() : [];
    }

    /**
     * A drawing model for a provider, when one was not named.
     *
     * Asks the REGISTERED ADAPTER which of the provider's models it can draw with — core no longer
     * holds that knowledge (TIGER-103).
     */
    protected static function _firstDrawingModel($provider)
    {
        $adapter = Tiger_Agent_Provider_Factory::imageAdapter($provider);
        if ($adapter === null) { return ''; }
        foreach (Tiger_Agent_Provider_Factory::staticModels($provider) as $m) {
            $id = (string) ($m['id'] ?? '');
            if ($id !== '' && $adapter->supportsModel($id)) { return $id; }
        }
        // The static roster is chat/text models; a provider that draws (openai, gemini) has no image
        // model there. Ask the adapter for its own default so the agent's provider + key can draw.
        if (method_exists($adapter, 'defaultModel')) {
            $d = (string) $adapter->defaultModel();
            if ($d !== '' && $adapter->supportsModel($d)) { return $d; }
        }
        return '';
    }

    /** The org to resolve for: the one named, else the signed-in org ('' = none). */
    protected static function _org($orgId)
    {
        if ($orgId !== null) {
            return (string) $orgId;
        }
        $org = Tiger_Model_Table::org();
        return $org === null ? '' : (string) $org;
    }

    /**
     * The org's OWN agent (Tiger_Agent::forOrg — TIGER-511), or null when it has none. Never the
     * signed-in-org facade: the org being resolved for is named, so a job running with no session (or
     * an admin of one org) can never pick up another org's — or the install's — key.
     *
     * @param  string $orgId
     * @return array|null
     */
    protected static function _agent($orgId)
    {
        if (!class_exists('Tiger_Agent') || !method_exists('Tiger_Agent', 'forOrg')) {
            return null;
        }
        try {
            return Tiger_Agent::forOrg((string) $orgId);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * One of TigerImage's own settings for ONE org — that org's own row only. The install org also reads
     * the install-wide tier (global row, else ini): the install's settings are its own. The merged
     * Zend_Config is never consulted, because it folds the install-wide tier under every org (TIGER-511).
     *
     * @param  string $orgId
     * @param  string $key
     * @return string '' when the org set none
     */
    protected static function _setting($orgId, $key)
    {
        $v = Tiger_Model_Org::isInstallOrg($orgId)
            ? Tiger_Model_Config::orgValue($orgId, $key, '')
            : Tiger_Model_Config::orgOwnValue($orgId, $key, '');
        return is_scalar($v) ? (string) $v : '';
    }
}
