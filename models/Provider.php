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
 * @api
 */
class Tigerimage_Model_Provider
{
    const CFG_PROVIDER = 'tigerimage.provider';
    const CFG_MODEL    = 'tigerimage.model';
    const CFG_KEY_ENC  = 'tigerimage.api_key_enc';

    /**
     * The resolved image provider, or null when nothing on this install can draw.
     *
     * @return array{provider:string,model:string,source:string}|null
     */
    public static function resolve()
    {
        $own      = trim((string) self::_config(self::CFG_PROVIDER));
        $ownModel = trim((string) self::_config(self::CFG_MODEL));

        if ($own !== '') {
            $model = $ownModel !== '' ? $ownModel : self::_firstDrawingModel($own);
            if ($model !== '' && Tiger_Agent_Provider_Factory::canGenerateImages($own, $model)) {
                return ['provider' => $own, 'model' => $model, 'source' => 'tigerimage'];
            }
            // Configured but not capable — say so rather than silently falling through to the agent's,
            // which would hide a misconfiguration behind a working-looking install.
            return null;
        }

        if (class_exists('Tiger_Agent')) {
            $p = Tiger_Agent::provider();
            $m = Tiger_Agent::model();
            // The agent picks a provider + key for TEXT. Images reuse the SAME provider + key but need
            // a drawing model: use the agent's model if it can draw, else this provider's default image
            // model. So setting the agent to OpenAI (or Gemini) with a key is enough to draw — no
            // second TigerImage provider config required (TIGER-147).
            $model = ($m !== '' && Tiger_Agent_Provider_Factory::canGenerateImages($p, $m)) ? $m : self::_firstDrawingModel($p);
            if ($model !== '' && Tiger_Agent_Provider_Factory::canGenerateImages($p, $model)) {
                return ['provider' => $p, 'model' => $model, 'source' => 'agent'];
            }
        }
        return null;
    }

    /**
     * The key for the resolved provider.
     *
     * Falls back to the agent's key ONLY when the provider resolved to the agent's — a key is
     * provider-specific, and handing OpenAI an Anthropic key is a confusing 401 rather than a
     * useful error.
     *
     * @param  array $resolved from resolve()
     * @return string '' when unset or unreadable
     */
    public static function apiKey(array $resolved)
    {
        if (($resolved['source'] ?? '') === 'agent' && class_exists('Tiger_Agent')) {
            return (string) Tiger_Agent::apiKey();
        }
        $blob = (string) self::_config(self::CFG_KEY_ENC);
        if ($blob === '') { return ''; }
        try { return (string) Tiger_Crypto::decrypt($blob); } catch (Throwable $e) { return ''; }
    }

    /**
     * What an agent needs to know BEFORE promising a user an image (TIGER-99).
     *
     * Failing at call time after saying "sure, I'll add a picture" is the bad outcome — so this is
     * answerable without generating anything.
     *
     * @return array {available: bool, reason?: string, provider?: string, model?: string, providers: string[]}
     */
    public static function capability()
    {
        $providers = Tiger_Agent_Provider_Factory::imageProviders();
        $resolved  = self::resolve();

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
        ];
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

    /** Read a dotted config key from the resolved config. */
    protected static function _config($key, $default = '')
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) { return $default; }
        $val = Zend_Registry::get('Zend_Config');
        foreach (explode('.', $key) as $part) {
            if (!is_object($val) || $val->get($part) === null) { return $default; }
            $val = $val->get($part);
        }
        return is_scalar($val) ? (string) $val : $default;
    }
}
