<?php
/**
 * Copyright (c) Enalean, 2017 - 2018. All Rights Reserved.
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

namespace Tuleap\Svn\Repository;

use Tuleap\Svn\AccessControl\AccessFileHistory;
use Tuleap\Svn\AccessControl\AccessFileHistoryCreator;
use Tuleap\Svn\Admin\ImmutableTag;
use Tuleap\Svn\Dao;
use Tuleap\Svn\SvnPermissionManager;

require_once __DIR__ . '/../../bootstrap.php';

class RepositoryCreatorTest extends \TuleapTestCase
{
    /**
     * @var AccessFileHistoryCreator
     */
    private $history_creator;
    /**
     * @var \ProjectHistoryDao
     */
    private $history_dao;
    /**
     * @var HookConfigUpdator
     */
    private $hook_config_updator;
    /**
     * @var Dao
     */
    private $dao;
    /**
     * @var \Project
     */
    private $project;
    /**
     * @var SvnPermissionManager
     */
    private $permissions_manager;
    /**
     * @var \PFUser
     */
    private $user;

    /**
     * @var \SystemEventManager
     */
    private $system_event_manager;

    /**
     * @var Repository
     */
    private $repository;

    /**
     * @var RepositoryCreator
     */
    private $repository_creator;

    public function setUp()
    {
        parent::setUp();

        $this->system_event_manager = mock('SystemEventManager');
        $this->history_dao          = mock('ProjectHistoryDao');
        $this->dao                  = mock('Tuleap\Svn\Dao');
        $this->permissions_manager  = mock('Tuleap\Svn\SvnPermissionManager');
        $this->hook_config_updator  = mock('Tuleap\Svn\Repository\HookConfigUpdator');
        $this->history_creator      = mock('Tuleap\Svn\AccessControl\AccessFileHistoryCreator');
        $this->repository_creator   = new RepositoryCreator(
            $this->dao,
            $this->system_event_manager,
            $this->history_dao,
            $this->permissions_manager,
            $this->hook_config_updator,
            new ProjectHistoryFormatter(),
            mock('Tuleap\Svn\Admin\ImmutableTagCreator'),
            $this->history_creator,
            mock('Tuleap\Svn\Admin\MailNotificationManager')
        );

        $this->project    = aMockProject()->withId(101)->build();
        $this->user       = aUser()->build();
        $this->repository = new Repository(
            01,
            'repo01',
            '',
            '',
            $this->project
        );

        stub($this->dao)->create()->returns(array(1));
    }

    public function itCreatesTheRepository()
    {
        stub($this->permissions_manager)->isAdmin($this->project, $this->user)->returns(true);

        expect($this->system_event_manager)->createEvent()->once();
        expect($this->history_dao)->groupAddHistory('svn_multi_repository_creation', '*', '*')->once();

        $this->repository_creator->create($this->repository, $this->user);
    }

    public function itThrowsAnExceptionWhenUserIsNotASVNAdministrator()
    {
        stub($this->permissions_manager)->isAdmin($this->project, $this->user)->returns(false);

        expect($this->system_event_manager)->createEvent()->never();
        $this->expectException('Tuleap\Svn\Repository\Exception\UserIsNotSVNAdministratorException');
        expect($this->history_dao)->groupAddHistory()->never();

        $this->repository_creator->create($this->repository, $this->user);
    }

    public function itThrowsAnExceptionWhenRepositoryNameIsAlreadyUsed()
    {
        stub($this->permissions_manager)->isAdmin($this->project, $this->user)->returns(true);
        stub($this->dao)->doesRepositoryAlreadyExist()->returns(true);

        expect($this->system_event_manager)->createEvent()->never();
        $this->expectException('Tuleap\Svn\Repository\Exception\RepositoryNameIsInvalidException');
        expect($this->history_dao)->groupAddHistory()->never();

        $this->repository_creator->create($this->repository, $this->user);
    }

    public function itCreatesRepositoryWithCustomSettingsAndImportAllAccessFileHistory()
    {
        stub($this->permissions_manager)->isAdmin($this->project, $this->user)->returns(true);

        expect($this->system_event_manager)->createEvent()->once();
        expect($this->history_creator)->useAVersionWithHistoryWithoutUpdateSVNAccessFile()->once();
        expect($this->history_dao)->groupAddHistory('svn_multi_repository_creation_with_full_settings', '*', '*')->once();
        expect($this->history_creator)->storeInDBWithoutCleaningContent()->never();

        $commit_rules        = array(
            HookConfig::COMMIT_MESSAGE_CAN_CHANGE => true,
            HookConfig::MANDATORY_REFERENCE       => true
        );
        $immutable_tag       = new ImmutableTag($this->repository, array(), array());
        $access_file         = "[/]\r\n* = rw \r\n@members = rw\r\n[/tags]\r\n@admins = rw";
        $access_file_history = array(new AccessFileHistory($this->repository, 1, 1, $access_file, time()));
        $mail_notifications  = array();
        $settings            = new Settings(
            $commit_rules,
            $immutable_tag,
            $access_file,
            $mail_notifications,
            $access_file_history,
            1,
            false
        );
        $initial_layout      = array();

        $this->repository_creator->createWithSettings(
            $this->repository,
            $this->user,
            $settings,
            $initial_layout,
            false
        );
    }

    public function itCreatesRepositoryWithCustomSettingsAndImportAllAccessFileHistoryWithoutPurgeThemContent()
    {
        stub($this->permissions_manager)->isAdmin($this->project, $this->user)->returns(true);

        expect($this->system_event_manager)->createEvent()->once();
        expect($this->history_creator)->useAVersionWithHistoryWithoutUpdateSVNAccessFile()->once();
        expect($this->history_dao)->groupAddHistory('svn_multi_repository_creation_with_full_settings', '*', '*')->once();
        expect($this->history_creator)->storeInDBWithoutCleaningContent()->once();

        $commit_rules        = array(
            HookConfig::COMMIT_MESSAGE_CAN_CHANGE => true,
            HookConfig::MANDATORY_REFERENCE       => true
        );
        $immutable_tag       = new ImmutableTag($this->repository, array(), array());
        $access_file         = "[/]\r\n* = rw \r\n@members = rw\r\n[/tags]\r\n@admins = rw";
        $access_file_history = array(new AccessFileHistory($this->repository, 1, 1, $access_file, time()));
        $mail_notifications  = array();
        $settings            = new Settings(
            $commit_rules,
            $immutable_tag,
            $access_file,
            $mail_notifications,
            $access_file_history,
            1,
            true
        );
        $initial_layout      = array();

        $this->repository_creator->createWithSettings(
            $this->repository,
            $this->user,
            $settings,
            $initial_layout,
            false
        );
    }

    public function itCreatesRepositoryWithCustomSettings()
    {
        stub($this->permissions_manager)->isAdmin($this->project, $this->user)->returns(true);

        expect($this->system_event_manager)->createEvent()->once();
        expect($this->hook_config_updator)->initHookConfiguration()->once();
        expect($this->history_creator)->useAVersionWithHistoryWithoutUpdateSVNAccessFile()->never();
        expect($this->history_dao)->groupAddHistory('svn_multi_repository_creation_with_full_settings', '*', '*')->once();

        $commit_rules       = array(
            HookConfig::COMMIT_MESSAGE_CAN_CHANGE => true,
            HookConfig::MANDATORY_REFERENCE       => true
        );
        $immutable_tag      = new ImmutableTag($this->repository, array(), array());
        $access_file        = "[/]\r\n* = rw \r\n@members = rw\r\n[/tags]\r\n@admins = rw";
        $mail_notifications = array();
        $settings           = new Settings(
            $commit_rules,
            $immutable_tag,
            $access_file,
            $mail_notifications,
            array(),
            1,
            false
        );
        $initial_layout     = array();

        $this->repository_creator->createWithSettings($this->repository, $this->user, $settings, $initial_layout, false);
    }

    public function itCreatesRepositoryWithNoCustomSettings()
    {
        stub($this->permissions_manager)->isAdmin($this->project, $this->user)->returns(true);

        expect($this->system_event_manager)->createEvent()->once();
        expect($this->hook_config_updator)->initHookConfiguration()->never();
        expect($this->history_dao)->groupAddHistory('svn_multi_repository_creation', '*', '*')->once();

        $commit_rules       = array();
        $immutable_tag      = new ImmutableTag($this->repository, array(), array());
        $access_file        = "";
        $mail_notifications = array();
        $settings           = new Settings(
            $commit_rules,
            $immutable_tag,
            $access_file,
            $mail_notifications,
            array(),
            1,
            false
        );
        $initial_layout     = array();

        $this->repository_creator->createWithSettings($this->repository, $this->user, $settings, $initial_layout, false);
    }
}
