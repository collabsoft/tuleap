<?php
/**
 * Copyright (c) Enalean, 2021 - Present. All Rights Reserved.
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

namespace Tuleap\ProgramManagement\Adapter\Program\Feature\Content;

use Tuleap\DB\DataAccessObject;
use Tuleap\ProgramManagement\Domain\Program\Backlog\Feature\Content\ContentStore;

class ContentDao extends DataAccessObject implements ContentStore
{
    public function searchContent(int $program_increment_id): array
    {
        $sql = '
                SELECT program_increment_tracker.item_name AS tracker_name,
                       artifact_link_value.artifact_id AS artifact_id,
                       title_value.value AS artifact_title,
                       title.field_id AS field_title_id
                FROM
                    tracker_artifact                                AS program_increment
                    INNER JOIN tracker_changeset                                                 ON (program_increment.id = tracker_changeset.artifact_id AND tracker_changeset.id = program_increment.last_changeset_id)
                    INNER JOIN tracker_changeset_value                                           ON tracker_changeset.id = tracker_changeset_value.changeset_id
                    INNER JOIN tracker                              AS program_increment_tracker ON (program_increment_tracker.id = program_increment.tracker_id)
                    INNER JOIN tracker_changeset_value_artifactlink AS artifact_link_value       ON artifact_link_value.changeset_value_id = tracker_changeset_value.id
                    INNER JOIN tracker_artifact                     AS feature_artifact          ON artifact_link_value.artifact_id = feature_artifact.id
                    INNER JOIN plugin_program_management_plan      AS plan              ON feature_artifact.tracker_id = plan.plannable_tracker_id
                    INNER JOIN tracker_changeset                    AS feature_changeset
                        ON (feature_artifact.id = feature_changeset.artifact_id AND feature_changeset.id = feature_artifact.last_changeset_id)
                    -- get title value
                    INNER JOIN (
                        tracker_semantic_title AS title
                            INNER JOIN tracker_changeset_value AS title_changeset ON (title.field_id = title_changeset.field_id)
                            INNER JOIN tracker_changeset_value_text AS title_value on title_changeset.id = title_value.changeset_value_id
                        ) ON (feature_artifact.tracker_id = title.tracker_id AND feature_changeset.id = title_changeset.changeset_id)
                    LEFT JOIN plugin_program_management_explicit_top_backlog
                              ON (plugin_program_management_explicit_top_backlog.artifact_id = program_increment.id)
                    INNER JOIN tracker_artifact_priority_rank ON feature_artifact.id = tracker_artifact_priority_rank.artifact_id
                WHERE program_increment.id =  ?
                  AND plugin_program_management_explicit_top_backlog.artifact_id IS NULL
                ORDER BY tracker_artifact_priority_rank.rank';

        return $this->getDB()->run($sql, $program_increment_id);
    }
}
