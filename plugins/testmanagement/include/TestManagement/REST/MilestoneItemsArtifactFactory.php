<?php
/**
 * Copyright (c) Enalean, 2017-Present. All Rights Reserved.
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

namespace Tuleap\TestManagement;

use EventManager;
use PFUser;
use Project;
use Tracker_ArtifactFactory;
use Tracker_FormElement_Field_ArtifactLink;
use Tuleap\TestManagement\Event\GetItemsFromMilestone;
use Tuleap\TestManagement\Nature\NatureCoveredByPresenter;

class MilestoneItemsArtifactFactory
{
    /**
     * @var Config
     */
    private $config;

    /** @var Tracker_ArtifactFactory */
    private $tracker_artifact_factory;

    /** @var ArtifactDao */
    private $dao;

    /** @var EventManager */
    private $event_manager;

    public function __construct(
        Config $config,
        ArtifactDao $dao,
        Tracker_ArtifactFactory $tracker_artifact_factory,
        EventManager $event_manager
    ) {
        $this->config                   = $config;
        $this->dao                      = $dao;
        $this->tracker_artifact_factory = $tracker_artifact_factory;
        $this->event_manager            = $event_manager;
    }

    public function getCoverTestDefinitionsUserCanViewForMilestone(PFUser $user, Project $project, int $milestone_id): array
    {
        $test_definitions = [];

        $event = new GetItemsFromMilestone($user, $milestone_id);
        $this->event_manager->processEvent($event);

        $this->appendArtifactsByNatures(
            $user,
            $test_definitions,
            $event,
            $project,
            [NatureCoveredByPresenter::NATURE_COVERED_BY, Tracker_FormElement_Field_ArtifactLink::NATURE_IS_CHILD],
        );

        return $test_definitions;
    }

    /**
     * @param string[] $natures
     * @psalm-param non-empty-array<string> $natures
     */
    private function appendArtifactsByNatures(PFUser $user, array &$test_definitions, GetItemsFromMilestone $event, Project $project, array $natures): void
    {
        $artifacts_ids = $event->getItemsIds();
        if (empty($artifacts_ids)) {
            return;
        }

        $results = $this->dao->searchPaginatedLinkedArtifactsByLinkNatureAndTrackerId(
            $artifacts_ids,
            $natures,
            $this->config->getTestDefinitionTrackerId($project),
            PHP_INT_MAX,
            0
        );

        $this->appendArtifactsUserCanView($user, $test_definitions, $results);
    }

    private function appendArtifactsUserCanView(PFUser $user, array &$test_definitions, array $results): void
    {
        foreach ($results as $row) {
            $test_def_artifact = $this->tracker_artifact_factory->getInstanceFromRow($row);
            if ($test_def_artifact->userCanView($user)) {
                $test_definitions[] = $test_def_artifact;
            }
        }
    }
}
