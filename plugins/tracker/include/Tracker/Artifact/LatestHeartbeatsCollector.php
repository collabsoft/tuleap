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

declare(strict_types=1);

namespace Tuleap\Tracker\Artifact;

use Codendi_HTMLPurifier;
use PFUser;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tracker_ArtifactDao;
use Tracker_ArtifactFactory;
use Tuleap\Glyph\GlyphFinder;
use Tuleap\Project\HeartbeatsEntry;
use Tuleap\Project\HeartbeatsEntryCollection;
use Tuleap\Tracker\Artifact\Heartbeat\ExcludeTrackersFromArtifactHeartbeats;
use UserHelper;
use UserManager;

class LatestHeartbeatsCollector
{
    /**
     * @var Tracker_ArtifactDao
     */
    private $dao;
    /**
     * @var Tracker_ArtifactFactory
     */
    private $factory;
    /**
     * @var GlyphFinder
     */
    private $glyph_finder;
    /**
     * @var UserManager
     */
    private $user_manager;
    /**
     * @var UserHelper
     */
    private $user_helper;
    /**
     * @var EventDispatcherInterface
     */
    private $event_manager;

    public function __construct(
        Tracker_ArtifactDao $dao,
        Tracker_ArtifactFactory $factory,
        GlyphFinder $glyph_finder,
        UserManager $user_manager,
        UserHelper $user_helper,
        EventDispatcherInterface $event_manager
    ) {
        $this->dao           = $dao;
        $this->factory       = $factory;
        $this->glyph_finder  = $glyph_finder;
        $this->user_manager  = $user_manager;
        $this->user_helper   = $user_helper;
        $this->event_manager = $event_manager;
    }

    public function collect(HeartbeatsEntryCollection $collection): void
    {
        $event = new ExcludeTrackersFromArtifactHeartbeats($collection->getProject());
        $this->event_manager->dispatch($event);

        $artifacts = $this->dao->searchLatestUpdatedArtifactsInProject(
            (int) $collection->getProject()->getID(),
            $collection::NB_MAX_ENTRIES,
            ...$event->getExcludedTrackerIDs()
        );

        if (! $artifacts) {
            return;
        }

        $artifact_list = [];
        foreach ($artifacts as $row) {
            $artifact_list[] = $this->factory->getInstanceFromRow($row);
        }

        foreach ($artifact_list as $artifact) {
            if (! $artifact->userCanView($collection->getUser())) {
                $collection->thereAreActivitiesUserCannotSee();
                continue;
            }

            $collection->add(
                new HeartbeatsEntry(
                    $artifact->getLastUpdateDate(),
                    $this->glyph_finder->get('tuleap-tracker-small'),
                    $this->glyph_finder->get('tuleap-tracker'),
                    $this->getHTMLMessage($artifact)
                )
            );
        }
    }

    private function getLastModifiedBy(Artifact $artifact): ?PFUser
    {
        $user = null;

        $last_modified_by_id = $artifact->getLastModifiedBy();
        if (is_numeric($last_modified_by_id)) {
            $user = $this->user_manager->getUserById($last_modified_by_id);
        }

        return $user;
    }

    private function getHTMLMessage(Artifact $artifact): string
    {
        $last_modified_by = $this->getLastModifiedBy($artifact);
        $is_an_update     = $artifact->hasMoreThanOneChangeset();

        $title = $this->getTitle($artifact);
        if ($last_modified_by) {
            $user_link = $this->user_helper->getLinkOnUser($last_modified_by);
            if ($is_an_update) {
                $message = sprintf(
                    dgettext('tuleap-tracker', '%s has been updated by %s'),
                    $title,
                    $user_link
                );
            } else {
                $message = sprintf(
                    dgettext('tuleap-tracker', '%s has been created by %s'),
                    $title,
                    $user_link
                );
            }
        } else {
            if ($is_an_update) {
                $message = sprintf(
                    dgettext('tuleap-tracker', '%s has been updated'),
                    $title
                );
            } else {
                $message = sprintf(
                    dgettext('tuleap-tracker', '%s has been created'),
                    $title
                );
            }
        }

        return $message;
    }

    private function getTitle(Artifact $artifact): string
    {
        $purifier = Codendi_HTMLPurifier::instance();

        $tlp_badget_color = $purifier->purify('tlp-badge-' . $artifact->getTracker()->getColor()->getName());
        $title            = '
            <a class="direct-link-to-artifact" href="' . $artifact->getUri() . '">
                <span class="tlp-badge-outline ' . $tlp_badget_color . '">
                ' . $artifact->getXRef() . '
                </span>
                ' . $purifier->purify($artifact->getTitle()) . '</a>';

        return $title;
    }
}
