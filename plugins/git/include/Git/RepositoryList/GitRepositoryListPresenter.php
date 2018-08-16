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

namespace Tuleap\Git\RepositoryList;

class GitRepositoryListPresenter
{
    public $repositories_administration_url;
    public $repositories_fork_url;
    public $repositories_list_url;
    public $project_id;
    /**
     * @var bool
     */
    public $is_admin;
    /** @var string */
    public $json_encoded_repositories_owners;

    public function __construct(\Project $project, $is_git_administrator, array $repositories_owners)
    {
        $this->repositories_administration_url = GIT_BASE_URL . "/?" . http_build_query(
            [
                "group_id" => $project->getID(),
                "action"   => "admin"
            ]
        );

        $this->repositories_fork_url = GIT_BASE_URL . "/?" . http_build_query(
            [
                "group_id" => $project->getID(),
                "action"   => "fork_repositories"
            ]
        );

        $this->repositories_list_url = GIT_BASE_URL . "/" . urlencode($project->getUnixNameLowerCase()) . "/";
        $this->project_id            = $project->getID();
        $this->is_admin              = $is_git_administrator;

        $this->json_encoded_repositories_owners = json_encode($repositories_owners);
    }
}
