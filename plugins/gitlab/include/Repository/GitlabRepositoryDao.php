<?php
/**
 * Copyright (c) Enalean, 2020-Present. All Rights Reserved.
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
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

declare(strict_types=1);

namespace Tuleap\Gitlab\Repository;

use Tuleap\DB\DataAccessObject;

class GitlabRepositoryDao extends DataAccessObject
{
    /**
     * @psalm-return list<array{id:int, gitlab_id:int, name:string, path:string, description:string, full_url:string, last_push_date:int}>
     */
    public function getGitlabRepositoriesForProject(int $project_id): array
    {
        $sql = 'SELECT plugin_gitlab_repository.*
                FROM plugin_gitlab_repository
                    INNER JOIN plugin_gitlab_repository_project
                        ON (plugin_gitlab_repository.id = plugin_gitlab_repository_project.id)
                WHERE plugin_gitlab_repository_project.project_id = ?';

        return $this->getDB()->run($sql, $project_id);
    }
}
