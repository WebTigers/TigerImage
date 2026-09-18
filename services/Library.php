<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerimage_Service_Library — storing generated images, refining them, and promoting the keepers
 * into the Media Library (TIGER-97).
 *
 * The two-stage flow is the design: a generated image lands in TEMP storage and is not in the Media
 * Library. The library stays curated — a grid of forty rejected variants is not an asset library.
 * Promotion is the human (or the agent) saying "keep this", and only a promoted image is safe from
 * the retention sweep.
 *
 * No `$db` predicates here. Every query goes through a named finder on Tigerimage_Model_Image.
 *
 * @api
 */
class Tigerimage_Service_Library extends Tiger_Service_Service
{
    /** How long an unpromoted image survives, in days, unless configured otherwise. */
    const DEFAULT_RETENTION_DAYS = 7;

    /**
     * Store what a provider returned.
     *
     * Takes the NORMALISED result from Tiger_Agent_Provider_ImageAdapter::generateImage() — one shape
     * regardless of provider — and writes one row per image. The whole `params` echo is stored, not a
     * chosen subset: the point is to be able to make this image again, and a field dropped here is a
     * refinement that cannot reproduce its parent.
     *
     * @param  array  $result   {images: [{mime, data}], params: {...}} from the adapter
     * @param  array  $context  prompt, negative?, parent_id?, org_id, cost?
     * @return array<int,string> the new image ids, in the order returned
     */
    public function store(array $result, array $context)
    {
        $images = $result['images'] ?? [];
        if (!$images) { throw new RuntimeException('Nothing to store: the provider returned no images.'); }

        $params = $result['params'] ?? [];
        $model  = new Tigerimage_Model_Image();
        $disk   = Tigerimage_Model_Store::disk();
        $ids    = [];

        foreach ($images as $img) {
            $bytes = base64_decode((string) ($img['data'] ?? ''), true);
            if ($bytes === false || $bytes === '') { continue; }

            $mime = (string) ($img['mime'] ?? 'image/png');
            $id   = Tiger_Uuid::v7();
            $key  = Tigerimage_Model_Store::key($id, $mime);

            // File first, row second. A row pointing at a file that was never written is a broken
            // thumbnail forever; a file with no row is swept as an orphan. The harmless failure is
            // the one to prefer.
            $disk->write($key, $bytes, 'private', $mime);

            $dims = $this->_dimensions($bytes);
            $model->insert([
                'image_id'  => $id,
                'org_id'    => (string) ($context['org_id'] ?? ''),
                'parent_id' => !empty($context['parent_id']) ? (string) $context['parent_id'] : null,
                'state'     => Tigerimage_Model_Image::STATE_TEMP,
                'disk'      => Tigerimage_Model_Store::diskName(),
                'path'      => $key,
                'mime'      => $mime,
                'bytes'     => strlen($bytes),
                'width'     => $dims[0],
                'height'    => $dims[1],
                'prompt'    => (string) ($context['prompt'] ?? ''),
                'negative'  => isset($context['negative']) ? (string) $context['negative'] : null,
                'provider'  => (string) ($params['provider'] ?? ''),
                'model'     => (string) ($params['model'] ?? ''),
                'params'    => json_encode($params, JSON_UNESCAPED_SLASHES),
                'cost'      => $context['cost'] ?? null,
            ]);
            $ids[] = $id;
        }

        if (!$ids) { throw new RuntimeException('Nothing to store: every image was empty or undecodable.'); }
        return $ids;
    }

    /**
     * Promote a temp image into the Media Library.
     *
     * The prompt becomes the media description so the library is searchable by what the image IS
     * rather than by a UUID filename — which is the difference between an asset library and a folder.
     *
     * Idempotent: promoting an already-promoted image returns its existing media id rather than
     * creating a second row. An agent retrying a call must not litter.
     *
     * @param  string $imageId
     * @param  string $orgId
     * @param  array  $opts    title?, alt?
     * @return string the media id
     */
    public function promote($imageId, $orgId, array $opts = [])
    {
        $model = new Tigerimage_Model_Image();
        $row   = $model->byId($imageId, $orgId);
        if (!$row) { throw new RuntimeException('No such image.'); }

        if ($row->state === Tigerimage_Model_Image::STATE_PROMOTED && !empty($row->media_id)) {
            return (string) $row->media_id;
        }

        $bytes = Tigerimage_Model_Store::disk()->get((string) $row->path, 'private');
        if ($bytes === null || $bytes === false || $bytes === '') {
            throw new RuntimeException('The image file is missing from storage.');
        }

        $mediaId = $this->_toMediaLibrary($row, $bytes, $opts);

        $row->state    = Tigerimage_Model_Image::STATE_PROMOTED;
        $row->media_id = $mediaId;
        $row->save();                        // save() keeps Tiger_Model_Table's stamping

        return $mediaId;
    }

    /**
     * Discard an image: remove the file, soft-delete the row, and re-parent its refinements.
     *
     * Children are RE-PARENTED rather than cascaded. Deleting a middle link in a chain should not
     * silently take the work below it — a refinement is still a useful image once its parent is gone.
     *
     * @param  string $imageId
     * @param  string $orgId
     * @return array {deleted: bool, reparented: int}
     */
    public function discard($imageId, $orgId)
    {
        $model = new Tigerimage_Model_Image();
        $row   = $model->byId($imageId, $orgId);
        if (!$row) { throw new RuntimeException('No such image.'); }

        $moved = $model->reparentChildren($row);

        // Best-effort: a file already gone must not block the row from going.
        try { Tigerimage_Model_Store::disk()->delete((string) $row->path, 'private'); } catch (Throwable $e) { }

        $model->softDelete(['image_id = ?' => (string) $row->image_id]);
        return ['deleted' => true, 'reparented' => $moved];
    }

    /**
     * Retention sweep — delete temp images past their window.
     *
     * Bounded per run so one sweep cannot run unboundedly long on an install that generated
     * thousands. Promoted images are never touched: the model's finder does not return them.
     *
     * @param  int|null $days  override the configured window
     * @param  int      $limit max images per run
     * @return array {swept: int, freed: int}
     */
    public function sweep($days = null, $limit = 200)
    {
        $days   = $days !== null ? (int) $days : (int) $this->_retentionDays();
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * 86400));

        $model = new Tigerimage_Model_Image();
        $disk  = Tigerimage_Model_Store::disk();
        $swept = 0; $freed = 0;

        foreach ($model->tempOlderThan($cutoff, $limit) as $row) {
            try { $disk->delete((string) $row->path, 'private'); } catch (Throwable $e) { }
            $model->reparentChildren($row);
            $model->softDelete(['image_id = ?' => (string) $row->image_id]);
            $swept++;
            $freed += (int) $row->bytes;
        }
        return ['swept' => $swept, 'freed' => $freed];
    }

    /** The configured retention window in days. */
    protected function _retentionDays()
    {
        if (Zend_Registry::isRegistered('Zend_Config')) {
            $cfg = Zend_Registry::get('Zend_Config');
            $ti  = $cfg->get('tigerimage');
            $r   = $ti ? $ti->get('retention_days') : null;
            if ($r !== null && (int) $r > 0) { return (int) $r; }
        }
        return self::DEFAULT_RETENTION_DAYS;
    }

    /**
     * Hand the bytes to the Media Library.
     *
     * Isolated so the coupling to Media is one method: TigerImage owns generation, Media owns the
     * library, and this is the seam between them.
     *
     * The FILE IS COPIED, not referenced. A media row whose storage_key pointed back into
     * storage/tigerimage would break the moment the retention sweep ran or the image was binned —
     * the library would hold a row for a file it does not own. Promotion means the Media Library now
     * has its own copy, on its own disk, under its own visibility rules.
     *
     * Written PUBLIC: the point of promoting is to use the image on a page.
     */
    protected function _toMediaLibrary($row, $bytes, array $opts)
    {
        $ext      = Tigerimage_Model_Store::extensionFor((string) $row->mime);
        $title    = trim((string) ($opts['title'] ?? '')) ?: $this->_titleFrom($row->prompt);
        $slug     = trim(preg_replace('~[^a-z0-9]+~', '-', strtolower($title)), '-') ?: 'image';
        $filename = substr($slug, 0, 60) . '.' . $ext;
        $key      = gmdate('Y/m') . '/' . substr($slug, 0, 60) . '-' . bin2hex(random_bytes(4)) . '.' . $ext;

        $disk = Tiger_Media_Storage::defaultDisk();
        Tiger_Media_Storage::disk($disk)->write($key, $bytes, Tiger_Model_Media::VISIBILITY_PUBLIC, (string) $row->mime);

        return (string) (new Tiger_Model_Media())->insert([
            'org_id'      => (string) $row->org_id,
            'locale'      => '',
            'disk'        => $disk,
            'storage_key' => $key,
            'visibility'  => Tiger_Model_Media::VISIBILITY_PUBLIC,
            'kind'        => 'image',
            'mime_type'   => (string) $row->mime,
            'extension'   => $ext,
            'file_size'   => strlen((string) $bytes),
            'checksum'    => hash('sha256', (string) $bytes),
            'width'       => (int) $row->width,
            'height'      => (int) $row->height,
            'filename'    => $filename,
            'title'       => $title,
            // Searchable by what it IS: the full prompt is the caption (best description anyone will
            // write). But alt_text describes the image to a screen-reader user, so it is the SUBJECT,
            // not the generation recipe — a caller-supplied alt wins, else the prompt with its trailing
            // style/camera directives stripped (round-4 B3: "editorial photography, shallow depth of
            // field, no people" are instructions to the generator, not a description of the picture).
            'caption'     => (string) $row->prompt,
            'alt_text'    => trim((string) ($opts['alt'] ?? '')) ?: $this->_altFrom($row->prompt),
        ]);
    }

    /**
     * Derive alt text from a generation prompt: keep the descriptive clauses, drop the trailing run of
     * style/technical directives a generator prompt tacks on ("editorial photography", "no people",
     * "8k", "cinematic"…). A derived-but-wrong alt is worse than none, because it looks filled in.
     *
     * Prompts read subject-first with directives last, so we split on commas and peel matching clauses
     * off the END only — a directive word appearing mid-description (a scene that is genuinely "about"
     * light) is untouched. Capped to a sane alt length.
     */
    protected function _altFrom($prompt)
    {
        $p = trim(preg_replace('/\s+/', ' ', (string) $prompt));
        if ($p === '') { return 'Generated image'; }

        // Whole-clause directive markers (matched case-insensitively as a substring of a trailing clause).
        static $directives = [
            'photography', 'photorealistic', 'photo realistic', 'realistic', 'depth of field', 'bokeh',
            'no people', 'highly detailed', 'high detail', 'ultra detailed', 'intricate detail', 'sharp focus',
            'soft focus', 'cinematic', 'studio lighting', 'soft lighting', 'dramatic lighting', 'volumetric',
            'wide angle', 'close up', 'close-up', 'macro', 'telephoto', 'aerial', 'overhead', 'top down',
            'render', 'octane', 'unreal engine', 'concept art', 'digital art', 'illustration', 'painterly',
            'watercolor', 'oil painting', 'trending on artstation', 'award winning', 'masterpiece', 'hdr',
            'film grain', 'vignette', 'lens flare', 'golden ratio', 'rule of thirds', 'vibrant colors',
            'muted colors', 'monochrome', 'hyperrealistic', 'ultra realistic', '4k', '8k', '16k', 'uhd',
        ];

        $clauses = array_map('trim', explode(',', $p));
        while (count($clauses) > 1) {
            $last = strtolower(end($clauses));
            $isDirective = false;
            foreach ($directives as $d) {
                if (strpos($last, $d) !== false) { $isDirective = true; break; }
            }
            if (!$isDirective) { break; }
            array_pop($clauses);
        }
        $alt = rtrim(implode(', ', $clauses), " ,;:");
        $alt = $alt !== '' ? $alt : $p;
        if (mb_strlen($alt) > 200) {
            $alt = mb_substr($alt, 0, 200);
            $alt = preg_replace('/\s+\S*$/u', '', $alt) ?: $alt;   // trim a trailing partial word
        }
        return rtrim($alt, " ,;:");
    }

    /** A short title from a prompt — first clause, trimmed. */
    protected function _titleFrom($prompt)
    {
        $p = trim(preg_replace('/\s+/', ' ', (string) $prompt));
        if ($p === '') { return 'Generated image'; }
        $cut = preg_split('/[.,;:]/', $p)[0];
        return mb_substr(trim($cut) ?: $p, 0, 120);
    }

    /** Pixel dimensions from the bytes, or [0,0] if unreadable. */
    protected function _dimensions($bytes)
    {
        $info = @getimagesizefromstring($bytes);
        return $info ? [(int) $info[0], (int) $info[1]] : [0, 0];
    }
}
