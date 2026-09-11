<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Refinement chains (TIGER-97) — the walk, exercised against a stubbed row store so it needs no DB.
 *
 * Lineage is what separates this from a folder of files: an image is never right first time, and
 * "how did I get here" has to be answerable.
 */
final class LineageTest extends TestCase
{
    #[Test]
    public function it_returns_the_chain_root_first(): void
    {
        $m = new FakeLineageModel([
            'a' => null,   // root
            'b' => 'a',
            'c' => 'b',
        ]);
        $chain = array_map(static fn($r) => $r->image_id, $m->chainFor('c'));
        $this->assertSame(['a', 'b', 'c'], $chain, 'oldest ancestor first');
    }

    #[Test]
    public function a_root_is_a_chain_of_one(): void
    {
        $m = new FakeLineageModel(['a' => null]);
        $this->assertSame(['a'], array_map(static fn($r) => $r->image_id, $m->chainFor('a')));
    }

    #[Test]
    public function an_unknown_id_is_an_empty_chain(): void
    {
        $m = new FakeLineageModel(['a' => null]);
        $this->assertSame([], $m->chainFor('nope'));
    }

    /**
     * A cycle should be impossible, which is why it must not hang the request if a re-parent bug ever
     * creates one. Termination here is a liveness property, not a correctness nicety.
     */
    #[Test]
    public function a_cycle_terminates_instead_of_hanging(): void
    {
        $m = new FakeLineageModel(['a' => 'b', 'b' => 'a']);
        $chain = $m->chainFor('a');
        $this->assertLessThanOrEqual(3, count($chain), 'a cycle must stop, not spin');
    }

    #[Test]
    public function depth_is_bounded(): void
    {
        $rows = [];
        for ($i = 0; $i < 500; $i++) { $rows["n$i"] = $i === 0 ? null : 'n' . ($i - 1); }
        $m = new FakeLineageModel($rows);

        $chain = $m->chainFor('n499', 10);
        $this->assertCount(10, $chain, 'the walk stops at maxDepth rather than loading 500 rows');
    }
}

/** Stubs byId() so the chain walk can be exercised without a database. */
final class FakeLineageModel extends Tigerimage_Model_Image
{
    private array $rows;

    /** @param array<string,string|null> $rows id => parent_id */
    public function __construct(array $rows)
    {
        $this->rows = $rows;   // deliberately no parent::__construct — no DB adapter needed
    }

    public function byId($imageId, $orgId = null)
    {
        $id = (string) $imageId;
        if (!array_key_exists($id, $this->rows)) { return null; }
        return (object) ['image_id' => $id, 'parent_id' => $this->rows[$id]];
    }
}
