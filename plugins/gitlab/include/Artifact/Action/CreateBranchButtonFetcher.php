<?php
/**
 * Copyright (c) Enalean, 2021 - present. All Rights Reserved.
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
 *
 */

declare(strict_types=1);

namespace Tuleap\Gitlab\Artifact\Action;

use ForgeConfig;
use PFUser;
use Tuleap\Gitlab\Plugin\GitlabIntegrationAvailabilityChecker;
use Tuleap\Gitlab\REST\v1\GitlabRepositoryRepresentationFactory;
use Tuleap\Layout\JavascriptAsset;
use Tuleap\Tracker\Artifact\ActionButtons\AdditionalButtonAction;
use Tuleap\Tracker\Artifact\ActionButtons\AdditionalButtonLinkPresenter;
use Tuleap\Tracker\Artifact\Artifact;

class CreateBranchButtonFetcher
{
    /**
     * Feature flag to allow users to create GitLab branches from artifacts
     *
     * @tlp-config-feature-flag-key
     */
    public const FEATURE_FLAG_KEY = 'artifact-create-gitlab-branches';

    private GitlabIntegrationAvailabilityChecker $availability_checker;
    private JavascriptAsset $javascript_asset;
    private GitlabRepositoryRepresentationFactory $representation_factory;

    public function __construct(
        GitlabIntegrationAvailabilityChecker $availability_checker,
        GitlabRepositoryRepresentationFactory $representation_factory,
        JavascriptAsset $javascript_asset
    ) {
        $this->availability_checker   = $availability_checker;
        $this->javascript_asset       = $javascript_asset;
        $this->representation_factory = $representation_factory;
    }

    public function getActionButton(Artifact $artifact, PFUser $user): ?AdditionalButtonAction
    {
        if (! ForgeConfig::getFeatureFlag(self::FEATURE_FLAG_KEY)) {
            return null;
        }

        $project    = $artifact->getTracker()->getProject();
        $project_id = (int) $project->getID();

        if (! $this->availability_checker->isGitlabIntegrationAvailableForProject($project)) {
            return null;
        }

        if (! $user->isMember($project_id)) {
            return null;
        }

        if (! $artifact->userCanView($user)) {
            return null;
        }

        $representations = $this->representation_factory->getAllIntegrationsRepresentationsInProjectWithConfiguredToken(
            $project
        );

        if (empty($representations)) {
            return null;
        }

        $link_label = dgettext('tuleap-gitlab', 'Create GitLab branch');
        $icon       = 'fab fa-gitlab';
        $link       = new AdditionalButtonLinkPresenter(
            $link_label,
            "",
            $icon,
            self::FEATURE_FLAG_KEY,
            [
                [
                    'name'  => "integrations",
                    'value' => json_encode($representations, JSON_THROW_ON_ERROR)
                ]
            ]
        );

        return new AdditionalButtonAction(
            $link,
            $this->javascript_asset->getFileURL()
        );
    }
}