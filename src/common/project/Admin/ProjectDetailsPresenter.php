<?php
/**
 * Copyright (c) Enalean, 2016 - 2018. All rights reserved
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/
 */

namespace Tuleap\Project\Admin;

use EventManager;
use Project;
use ProjectManager;
use TemplateSingleton;
use Tuleap\Project\ProjectAccessPresenter;

class ProjectDetailsPresenter
{
    /**
     * Display additional links in admin » projects » project
     *
     * Parameters:
     *  - $project  => (in) Project
     *  - $links    => (out) Array of {href, label}
     */
    const GET_MORE_INFO_LINKS = 'get_more_info_links';

    public $public_name;
    public $short_name;
    public $id;
    public $description;
    public $information_label;
    public $history_label;
    public $project_details_label;
    public $access_label;
    public $description_label;
    public $more_label;
    public $homepage_label;
    public $admin_label;
    public $members_label;
    public $pending_label;
    public $links;
    public $manage_access_label;
    public $unix_name;
    public $save_label;
    public $unix_name_label;
    public $status_label;
    public $status;
    public $can_change_status;
    public $type_label;
    public $type;
    public $instructions_label;
    public $send_email_label;
    public $custom_fields;
    public $has_custom_fields;
    public $instructions_desc;
    public $is_system;
    public $is_active;
    /**
     * @var ProjectAccessPresenter
     */
    public $access_presenter;
    /**
     * @var \CSRFSynchronizerToken
     */
    public $csrf_token;

    public function __construct(
        Project $project,
        $all_custom_fields,
        ProjectAccessPresenter $access_presenter,
        \CSRFSynchronizerToken $csrf_token
    ) {
        $this->id          = $project->getID();
        $this->public_name = $project->getUnconvertedPublicName();
        $this->unix_name   = $project->getUnixNameMixedCase();
        $this->short_name  = $project->getUnixName();
        $this->description = $project->getDescription();
        $this->is_system   = $project->getStatus() === Project::STATUS_SYSTEM;
        $this->is_active   = $project->isActive();

        $this->links = array();
        EventManager::instance()->processEvent(
            self::GET_MORE_INFO_LINKS,
            array('project' => $project, 'links' => &$this->links)
        );

        $this->status            = $this->getStatus($project);
        $this->can_change_status = $project->getStatus() === Project::STATUS_DELETED;
        $this->types             = $this->getTypes($project);

        $template = ProjectManager::instance()->getProject($project->getTemplate());
        $this->built_from = array(
            'href' => '/admin/groupedit.php?group_id='.$template->getID(),
            'name' => $template->getPublicname()
        );

        $this->custom_fields     = $all_custom_fields;
        $this->has_custom_fields = count($this->custom_fields) > 0;

        $this->information_label     = $GLOBALS['Language']->getText('admin_project', 'information_label');
        $this->history_label         = $GLOBALS['Language']->getText('admin_project', 'history_label');
        $this->project_details_label = $GLOBALS['Language']->getText('admin_project', 'project_details_label');
        $this->access_label          = $GLOBALS['Language']->getText('admin_project', 'access_label');
        $this->description_label     = $GLOBALS['Language']->getText('admin_project', 'description_label');
        $this->more_label            = $GLOBALS['Language']->getText('admin_project', 'more_label');
        $this->homepage_label        = $GLOBALS['Language']->getText('admin_project', 'homepage_label');
        $this->admin_label           = $GLOBALS['Language']->getText('admin_project', 'admin_label');
        $this->members_label         = $GLOBALS['Language']->getText('admin_project', 'members_label');
        $this->pending_label         = $GLOBALS['Language']->getText('admin_project', 'pending_label');
        $this->manage_access_label   = $GLOBALS['Language']->getText('admin_project', 'manage_access_label');
        $this->unix_name_label       = $GLOBALS['Language']->getText('admin_project', 'unix_name_label');
        $this->save_label            = $GLOBALS['Language']->getText('admin_project', 'save_label');
        $this->status_label          = $GLOBALS['Language']->getText('admin_project', 'status_label');
        $this->type_label            = $GLOBALS['Language']->getText('admin_groupedit', 'group_type');
        $this->built_from_label      = $GLOBALS['Language']->getText('admin_groupedit', 'built_from_template');
        $this->instructions_label    = $GLOBALS['Language']->getText('admin_project', 'instructions_label');
        $this->send_email_label      = $GLOBALS['Language']->getText('admin_project', 'send_email_label');
        $this->instructions_desc     = $GLOBALS['Language']->getText('admin_project', 'instructions_desc');
        $this->access_presenter      = $access_presenter;
        $this->csrf_token            = $csrf_token;
    }

    private function getTypes(Project $project)
    {
        $localized_types = TemplateSingleton::instance()->getLocalizedTypes();

        $types = array();
        foreach ($localized_types as $id => $type) {
            $types[] = array(
                'key'        => $id,
                'type'       => $type,
                'is_current' => $id == $project->getType()
            );
        }

        return $types;
    }

    private function getStatus(Project $project)
    {
        $labels = array(
            Project::STATUS_INCOMPLETE => $GLOBALS['Language']->getText('admin_groupedit', 'status_I'),
            Project::STATUS_ACTIVE     => $GLOBALS['Language']->getText('admin_groupedit', 'status_A'),
            Project::STATUS_PENDING    => $GLOBALS['Language']->getText('admin_groupedit', 'status_P'),
            Project::STATUS_HOLDING    => $GLOBALS['Language']->getText('admin_groupedit', 'status_H'),
            Project::STATUS_DELETED    => $GLOBALS['Language']->getText('admin_groupedit', 'status_D')
        );

        $all_status = array();
        foreach ($labels as $key => $status) {
            $all_status[] = array(
                'key'        => $key,
                'status'     => $status,
                'is_current' => $project->getStatus() === $key
            );
        }

        return $all_status;
    }
}
