<?php
/**
 * Copyright (c) Enalean, 2016 - 2017. All Rights Reserved.
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
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

use Tuleap\Admin\AdminPageRenderer;
use Tuleap\Git\Gitolite\SSHKey\ManagementDetector;

class Git_AdminGitoliteConfig {

    const ACTION_UPDATE_CONFIG              = 'update_config';
    const ACTION_MIGRATE_SSH_KEY_MANAGEMENT = 'migrate_to_tuleap_ssh_keys_management';

    /**
     * @var Git_SystemEventManager
     */
    private $system_event_manager;

    /**
     * @var ProjectManager
     */
    private $project_manager;

    /**
     * @var CSRFSynchronizerToken
     */
    private $csrf;

    /** @var AdminPageRenderer */
    private $admin_page_renderer;
    /**
     * @var ManagementDetector
     */
    private $management_detector;

    public function __construct(
        CSRFSynchronizerToken $csrf,
        ProjectManager $project_manager,
        Git_SystemEventManager $system_event_manager,
        AdminPageRenderer $admin_page_renderer,
        ManagementDetector $management_detector
    ) {
        $this->csrf                 = $csrf;
        $this->project_manager      = $project_manager;
        $this->system_event_manager = $system_event_manager;
        $this->admin_page_renderer  = $admin_page_renderer;
        $this->management_detector  = $management_detector;
    }

    public function process(Codendi_Request $request) {
        $action = $request->get('action');

        if ($action === false) {
            return;
        }

        switch ($action) {
            case self::ACTION_UPDATE_CONFIG:
                $this->csrf->check();
                $this->regenerateGitoliteConfigForAProject($request);
                break;
            case self::ACTION_MIGRATE_SSH_KEY_MANAGEMENT:
                $this->csrf->check();
                $this->migrateToTuleapSSHKeyManagement();
                break;
            default:
                $GLOBALS['Response']->addFeedback(
                    'error',
                    $GLOBALS['Language']->getText('plugin_git', 'regenerate_config_bad_request')
                );
        }

        return true;
    }

    private function regenerateGitoliteConfigForAProject(Codendi_Request $request)
    {
        $project = $this->getProject($request->get('gitolite_config_project'));

        if (! $project) {
            $GLOBALS['Response']->addFeedback(
                'error',
                $GLOBALS['Language']->getText('plugin_git', 'regenerate_config_project_not_exist')
            );
            return;
        }

        $this->system_event_manager->queueRegenerateGitoliteConfig($project->getID());

        $GLOBALS['Response']->addFeedback(
            'info',
            $GLOBALS['Language']->getText('plugin_git', 'regenerate_config_waiting', array($project->getPublicName()))
        );
    }

    /**
     * @return Project
     */
    private function getProject($project_name_from_autocomplete) {
        return $this->project_manager->getProjectFromAutocompleter($project_name_from_autocomplete);
    }

    private function migrateToTuleapSSHKeyManagement()
    {
        if (! $this->management_detector->canRequestAuthorizedKeysFileManagementByTuleap()) {
            return;
        }
        $this->system_event_manager->queueMigrateToTuleapSSHKeyManagement();
        $GLOBALS['Response']->addFeedback(
            Feedback::INFO,
            $GLOBALS['Language']->getText('plugin_git', 'migrate_to_tuleap_ssh_keys_management_feedback')
        );
    }

    public function display(Codendi_Request $request) {
        $title    = $GLOBALS['Language']->getText('plugin_git', 'descriptor_name');
        $template_path = dirname(GIT_BASE_DIR).'/templates';

        $GLOBALS['HTML']->includeFooterJavascriptFile(GIT_BASE_URL . '/scripts/admin-gitolite.js');

        $admin_presenter = new Git_AdminGitoliteConfigPresenter(
            $title,
            $this->csrf,
            $this->management_detector->canRequestAuthorizedKeysFileManagementByTuleap()
        );

        $this->admin_page_renderer->renderANoFramedPresenter(
            $title,
            $template_path,
            'admin-plugin',
            $admin_presenter
        );
    }
}
