<?php
/**
 * Copyright (c) Enalean, 2021-Present. All Rights Reserved.
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

namespace Tuleap\ProgramManagement\Adapter\Program\Backlog\TopBacklog\Workflow;

use Transition;
use Tuleap\DB\DBTransactionExecutor;
use Tuleap\Tracker\Workflow\PostAction\Update\Internal\PostActionUpdater;
use Tuleap\Tracker\Workflow\PostAction\Update\PostActionCollection;

class AddToTopBacklogPostActionValueUpdater implements PostActionUpdater
{
    /**
     * @var AddToTopBacklogPostActionDAO
     */
    private $add_to_top_backlog_post_action_dao;
    /**
     * @var DBTransactionExecutor
     */
    private $db_transaction_executor;

    public function __construct(AddToTopBacklogPostActionDAO $add_to_top_backlog_post_action_dao, DBTransactionExecutor $db_transaction_executor)
    {
        $this->add_to_top_backlog_post_action_dao = $add_to_top_backlog_post_action_dao;
        $this->db_transaction_executor            = $db_transaction_executor;
    }

    public function updateByTransition(PostActionCollection $actions, Transition $transition): void
    {
        $add_to_top_backlog_post_action_value = [];
        foreach ($actions->getExternalPostActionsValue() as $external_post_action_value) {
            if ($external_post_action_value instanceof AddToTopBacklogPostActionValue) {
                $add_to_top_backlog_post_action_value[] = $external_post_action_value;
            }
        }

        $transition_id = (int) $transition->getId();

        $this->db_transaction_executor->execute(function () use ($transition_id, $add_to_top_backlog_post_action_value) {
            $this->add_to_top_backlog_post_action_dao->deleteTransitionPostActions($transition_id);

            if (count($add_to_top_backlog_post_action_value) > 0) {
                $this->add_to_top_backlog_post_action_dao->createPostActionForTransitionID($transition_id);
            }
        });
    }
}
