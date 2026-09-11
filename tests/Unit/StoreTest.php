<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Where generated images live, and how they are named (TIGER-97).
 *
 * The load-bearing decision under test: storage is `storage/tigerimage`, OUTSIDE the module directory,
 * because a module update renames the module dir away and deletes the backup — anything stored inside
 * it is destroyed on a routine update, silently.
 */
#[CoversClass(Tigerimage_Model_Store::class)]
final class StoreTest extends TestCase
{
    protected function setUp(): void { Tigerimage_Model_Store::reset(); }
    protected function tearDown(): void { Tigerimage_Model_Store::reset(); }

    #[Test]
    public function the_default_root_is_outside_the_module_directory(): void
    {
        $root = Tigerimage_Model_Store::DEFAULT_ROOT;
        $this->assertSame('storage/tigerimage', $root);
        // The regression this guards: a module update destroys application/modules/<slug>/.
        $this->assertStringNotContainsString('modules', $root,
            'storage inside the module dir is destroyed by a routine module update');
        $this->assertStringNotContainsString('public', $root,
            'an unpromoted image must not sit under the docroot');
    }

    #[Test]
    public function it_maps_every_mime_to_a_sane_extension(): void
    {
        $this->assertSame('png',  Tigerimage_Model_Store::extensionFor('image/png'));
        $this->assertSame('jpg',  Tigerimage_Model_Store::extensionFor('image/jpeg'));
        $this->assertSame('jpg',  Tigerimage_Model_Store::extensionFor('image/jpg'));
        $this->assertSame('webp', Tigerimage_Model_Store::extensionFor('image/webp'));
        $this->assertSame('gif',  Tigerimage_Model_Store::extensionFor('image/gif'));
        // anything unrecognised stores as png rather than inventing an extension from user input
        $this->assertSame('png',  Tigerimage_Model_Store::extensionFor('application/octet-stream'));
        $this->assertSame('png',  Tigerimage_Model_Store::extensionFor(''));
        $this->assertSame('png',  Tigerimage_Model_Store::extensionFor('  IMAGE/PNG  '));
    }

    /** Sharded by date so one directory never accumulates every image an install ever made. */
    #[Test]
    public function keys_are_date_sharded_and_carry_the_id(): void
    {
        $id  = '01a08f7e-ccdd-74e5-af55-b4690573fbb3';
        $key = Tigerimage_Model_Store::key($id, 'image/webp');

        $this->assertSame(gmdate('Y/m') . '/' . $id . '.webp', $key);
        $this->assertMatchesRegularExpression('~^\d{4}/\d{2}/~', $key);
    }

    /**
     * A key is a filesystem path. The id is ours today, but interpolating an unvalidated one builds
     * `2026/09/../../etc/passwd` the moment anything upstream lets a caller choose it — so the
     * function refuses rather than trusting its caller. (This test found that hole.)
     */
    #[Test]
    public function keys_cannot_be_built_from_a_traversing_id(): void
    {
        foreach (['../../etc/passwd', '..', 'a/../../b', '', 'not-a-uuid'] as $evil) {
            // NOT fail() inside the try: PHPUnit's AssertionFailedError extends RuntimeException, so a
            // `catch (RuntimeException)` swallows the failure and the test can never fail. Mutation
            // testing found exactly that — removing the validation left this test green.
            $threw = false;
            try {
                Tigerimage_Model_Store::key($evil, 'image/png');
            } catch (RuntimeException $e) {
                $threw = true;
                $this->assertStringContainsString('non-UUID', $e->getMessage());
            }
            $this->assertTrue($threw, "a non-UUID id must be refused: '$evil'");
        }
    }

    #[Test]
    public function a_real_uuid_is_accepted(): void
    {
        $id = '01a08f7e-ccdd-74e5-af55-b4690573fbb3';
        $this->assertStringEndsWith($id . '.png', Tigerimage_Model_Store::key($id, 'image/png'));
    }
}
