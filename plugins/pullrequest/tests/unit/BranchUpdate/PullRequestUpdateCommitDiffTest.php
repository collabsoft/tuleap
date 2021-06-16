<?php
/**
 * Copyright (c) Enalean, 2019-Present. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Tuleap\PullRequest\BranchUpdate;

use Git_Command_Exception;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

final class PullRequestUpdateCommitDiffTest extends \Tuleap\Test\PHPUnit\TestCase
{
    use MockeryPHPUnitIntegration;

    public function testAdditionalCommitsAreFound(): void
    {
        $old_src = 'a7d1692502252a5ec18bfcae4184498b1459810c';
        $new_src = 'fbe4dade4f744aa203ec35bf09f71475ecc3f9d6';
        $old_dst = '4682f1f1fb9ee3cf6ca518547ae5525c9768a319';
        $new_dst = $old_dst;

        $git_exec = \Mockery::mock(\Git_Exec::class);
        $git_exec->shouldReceive('revList')->with($old_dst, $old_src)->andReturn(
            [$old_src]
        );
        $git_exec->shouldReceive('revList')->with($new_dst, $new_src)->andReturn(
            [
                $old_src,
                $new_src,
            ]
        );

        $differ = new PullRequestUpdateCommitDiff();

        $new_commits = $differ->findNewCommitReferences(
            $git_exec,
            $old_src,
            $new_src,
            $old_dst,
            $new_dst
        );

        $this->assertEqualsCanonicalizing(
            [$new_src],
            $new_commits
        );
    }

    public function testPushForcedCommitsAreFound(): void
    {
        $old_src = 'a7d1692502252a5ec18bfcae4184498b1459810c';
        $new_src = 'fbe4dade4f744aa203ec35bf09f71475ecc3f9d6';
        $old_dst = '4682f1f1fb9ee3cf6ca518547ae5525c9768a319';
        $new_dst = $old_dst;

        $git_exec = \Mockery::mock(\Git_Exec::class);
        $git_exec->shouldReceive('revList')->with($old_dst, $old_src)->andReturn(
            [$old_src]
        );
        $git_exec->shouldReceive('revList')->with($new_dst, $new_src)->andReturn(
            [$new_src]
        );

        $differ = new PullRequestUpdateCommitDiff();

        $new_commits = $differ->findNewCommitReferences(
            $git_exec,
            $old_src,
            $new_src,
            $old_dst,
            $new_dst
        );

        $this->assertEqualsCanonicalizing(
            [$new_src],
            $new_commits
        );
    }

    public function testProvidesCommitsBetweenTheNewReferencesWhenTheOldReferencesAreNotMoreAvailableInTheRepository(): void
    {
        $old_src = 'a7d1692502252a5ec18bfcae4184498b1459810c';
        $new_src = 'fbe4dade4f744aa203ec35bf09f71475ecc3f9d6';
        $old_dst = '4682f1f1fb9ee3cf6ca518547ae5525c9768a319';
        $new_dst = $old_dst;

        $git_exec = \Mockery::mock(\Git_Exec::class);
        $git_exec->shouldReceive('revList')->with($old_dst, $old_src)->andThrow(
            new Git_Command_Exception('command execution failure', ['fatal: ambiguous argument'], 128)
        );
        $git_exec->shouldReceive('revList')->with($new_dst, $new_src)->andReturn(
            [$new_src]
        );

        $differ = new PullRequestUpdateCommitDiff();

        $new_commits = $differ->findNewCommitReferences(
            $git_exec,
            $old_src,
            $new_src,
            $old_dst,
            $new_dst
        );

        $this->assertEqualsCanonicalizing(
            [$new_src],
            $new_commits
        );
    }
}
