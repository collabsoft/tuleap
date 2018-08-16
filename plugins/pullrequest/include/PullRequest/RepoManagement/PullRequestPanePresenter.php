<?php
/**
 * Copyright (c) Enalean, 2018. All Rights Reserved.
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

namespace Tuleap\PullRequest\RepoManagement;

use GitRepository;
use Tuleap\PullRequest\MergeSetting\MergeSetting;

class PullRequestPanePresenter
{
    /**
     * @var int
     */
    public $repository_id;
    /**
     * @var int
     */
    public $project_id;
    /**
     * @var bool
     */
    public $is_merge_commit_allowed;

    public function __construct(GitRepository $repository, MergeSetting $merge_setting)
    {
        $this->repository_id           = $repository->getId();
        $this->project_id              = $repository->getProjectId();
        $this->is_merge_commit_allowed = $merge_setting->isMergeCommitAllowed();
    }
}
