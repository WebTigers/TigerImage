<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tigerimage_Model_Store — where generated image FILES live (TIGER-97).
 *
 * WHY NOT INSIDE THE MODULE. The obvious place — `application/modules/tigerimage/...` — silently
 * destroys data. Tiger_Module_Installer updates a module by renaming its directory to a backup,
 * renaming the new one into place, then `_rrmdir()`-ing that backup. Anything a module stored inside
 * itself is gone on the first routine update, with no error and no warning. So images live under
 * `storage/tigerimage/`, which is the established convention (`storage/media` is the Media module's
 * private root, `storage/backups` is TigerBackup's): outside the docroot, outside the swap path.
 *
 * The cost of that choice is that `purge()` does not currently reach it — see TIGER-101, which makes
 * module removal delete `storage/<slug>/` so "remove everything, cannot be undone" is true.
 *
 * LOCAL BY DEFAULT, OFFSITE IF CONFIGURED. Disks come from Tiger_Media_Storage, so `filesystem`,
 * `s3`, `gcs` and `azure` all work with no code here — the same abstraction the Media Library already
 * uses, rather than a second storage stack that would drift from it.
 *
 * Everything written here is PRIVATE. A generated image is a draft until a human or an agent promotes
 * it, and drafts do not belong under the docroot.
 *
 * @api
 */
class Tigerimage_Model_Store
{
    /** Where a `filesystem` disk keeps images, relative to the application root. */
    const DEFAULT_ROOT = 'storage/tigerimage';

    /** Config keys (the `config` tier, so a per-org override reskins storage the usual way). */
    const CONFIG_DISK = 'tigerimage.storage.disk';
    const CONFIG_ROOT = 'tigerimage.storage.root';

    /** @var Tiger_Media_Storage_Interface|null */
    protected static $_disk = null;

    /** @var string the resolved disk name, for recording on the row */
    protected static $_diskName = 'local';

    /**
     * The disk images are written to.
     *
     * Resolution order: an explicitly configured disk name (handed to Tiger_Media_Storage, so an
     * offsite bucket is a config change and nothing more), else a local filesystem disk rooted at
     * storage/tigerimage. Local is the default deliberately — it works on shared hosting with no
     * credentials, which is the floor Tiger targets.
     *
     * @return Tiger_Media_Storage_Interface
     */
    public static function disk()
    {
        if (self::$_disk !== null) { return self::$_disk; }

        $configured = trim((string) self::_config(self::CONFIG_DISK, ''));
        if ($configured !== '') {
            self::$_diskName = $configured;
            self::$_disk     = Tiger_Media_Storage::disk($configured);
            return self::$_disk;
        }

        $root = trim((string) self::_config(self::CONFIG_ROOT, '')) ?: self::DEFAULT_ROOT;
        self::$_diskName = 'local';
        self::$_disk = Tiger_Media_Storage::make([
            'adapter' => 'filesystem',
            // Private only. There is no public root: an unpromoted image must not be reachable by URL,
            // and promotion is what moves it into the Media Library's public space.
            'private_root' => $root,
            'public_root'  => $root,
        ]);
        return self::$_disk;
    }

    /** The resolved disk name, recorded on the row so a later read knows where to look. */
    public static function diskName()
    {
        self::disk();
        return self::$_diskName;
    }

    /** Drop the memoised disk — tests, and a settings change within one request. */
    public static function reset()
    {
        self::$_disk     = null;
        self::$_diskName = 'local';
    }

    /**
     * The storage key for an image.
     *
     * Sharded by date so one directory never accumulates every image an install ever made — the same
     * reason the Media library shards. The id is already unique, so the date is purely for the
     * filesystem's benefit and for a human reading a backup.
     *
     * @param  string $imageId a UUID
     * @param  string $mime    used only to choose the extension
     * @return string
     */
    public static function key($imageId, $mime = 'image/png')
    {
        // The id is ours (a UUID), so this should never fire — which is exactly why it is here.
        // A key is a filesystem path; interpolating an unvalidated id builds `2026/09/../../etc/passwd`
        // the moment anything upstream lets a caller choose one. Refuse rather than sanitise: a
        // silently-rewritten id would store the image somewhere its row does not point.
        $id = (string) $imageId;
        if (!preg_match('~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~i', $id)) {
            throw new RuntimeException('Refusing to build a storage key from a non-UUID image id.');
        }
        return gmdate('Y/m') . '/' . $id . '.' . self::extensionFor($mime);
    }

    /** Map a mime to a file extension; anything unrecognised is stored as .png. */
    public static function extensionFor($mime)
    {
        switch (strtolower(trim((string) $mime))) {
            case 'image/jpeg':
            case 'image/jpg':  return 'jpg';
            case 'image/webp': return 'webp';
            case 'image/gif':  return 'gif';
            case 'image/png':
            default:           return 'png';
        }
    }

    /** Read a config value through the resolved config, falling back when the registry is absent. */
    protected static function _config($key, $default = '')
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) { return $default; }
        $cfg = Zend_Registry::get('Zend_Config');
        $val = $cfg;
        foreach (explode('.', $key) as $part) {
            if (!is_object($val) || $val->get($part) === null) { return $default; }
            $val = $val->get($part);
        }
        return is_scalar($val) ? (string) $val : $default;
    }
}
