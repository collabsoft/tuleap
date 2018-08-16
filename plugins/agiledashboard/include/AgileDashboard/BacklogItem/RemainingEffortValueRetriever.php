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

namespace Tuleap\AgileDashboard\BacklogItem;

use AgileDashboard_Milestone_Backlog_IBacklogItem;
use PFUser;

class RemainingEffortValueRetriever
{
    /** @var \Tracker_FormElementFactory */
    private $form_element_factory;

    public function __construct(\Tracker_FormElementFactory $form_element_factory)
    {
        $this->form_element_factory = $form_element_factory;
    }

    public function getRemainingEffortValue(
        PFUser $current_user,
        AgileDashboard_Milestone_Backlog_IBacklogItem $backlog_item
    ) {
        $artifact               = $backlog_item->getArtifact();
        $remaining_effort_field = $this->form_element_factory->getNumericFieldByNameForUser(
            $artifact->getTracker(),
            $current_user,
            $backlog_item::REMAINING_EFFORT_FIELD_NAME
        );
        if (! $remaining_effort_field) {
            return null;
        }

        $remaining_effort_value = $artifact->getLastChangeset()->getValue($remaining_effort_field);
        if (! $remaining_effort_value) {
            return null;
        }

        return $remaining_effort_value->getValue();
    }
}
