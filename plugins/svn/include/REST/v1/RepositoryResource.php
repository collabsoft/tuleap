<?php
/**
 * Copyright (c) Enalean, 2017. All Rights Reserved.
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

namespace Tuleap\SVN\REST\v1;

use CodendiDataAccess;
use Luracast\Restler\RestException;
use PFUser;
use Project;
use ProjectHistoryDao;
use SystemEvent;
use SystemEventManager;
use Tuleap\REST\AuthenticatedResource;
use Tuleap\REST\Header;
use Tuleap\REST\ProjectAuthorization;
use Tuleap\Svn\AccessControl\AccessFileHistoryCreator;
use Tuleap\Svn\AccessControl\AccessFileHistoryDao;
use Tuleap\Svn\AccessControl\AccessFileHistoryFactory;
use Tuleap\Svn\AccessControl\CannotCreateAccessFileHistoryException;
use Tuleap\Svn\Admin\CannotCreateMailHeaderException;
use Tuleap\Svn\Admin\Destructor;
use Tuleap\Svn\Admin\ImmutableTagCreator;
use Tuleap\Svn\Admin\ImmutableTagDao;
use Tuleap\Svn\Admin\ImmutableTagFactory;
use Tuleap\Svn\Admin\MailNotification;
use Tuleap\Svn\Admin\MailNotificationDao;
use Tuleap\Svn\Admin\MailNotificationManager;
use Tuleap\Svn\Dao;
use Tuleap\Svn\EventRepository\SystemEvent_SVN_DELETE_REPOSITORY;
use Tuleap\Svn\Notifications\NotificationsEmailsBuilder;
use Tuleap\Svn\Notifications\UgroupsToNotifyDao;
use Tuleap\Svn\Notifications\UsersToNotifyDao;
use Tuleap\Svn\Repository\Exception\CannotCreateRepositoryException;
use Tuleap\Svn\Repository\Exception\CannotFindRepositoryException;
use Tuleap\Svn\Repository\Exception\RepositoryNameIsInvalidException;
use Tuleap\Svn\Repository\Exception\UserIsNotSVNAdministratorException;
use Tuleap\Svn\Repository\HookConfig;
use Tuleap\Svn\Repository\HookConfigChecker;
use Tuleap\Svn\Repository\HookConfigRetriever;
use Tuleap\Svn\Repository\HookConfigSanitizer;
use Tuleap\Svn\Repository\HookConfigUpdator;
use Tuleap\Svn\Repository\HookDao;
use Tuleap\Svn\Repository\ProjectHistoryFormatter;
use Tuleap\Svn\Repository\Repository;
use Tuleap\Svn\Repository\RepositoryCreator;
use Tuleap\Svn\Repository\RepositoryDeleter;
use Tuleap\Svn\Repository\RepositoryManager;
use Tuleap\Svn\Repository\RepositoryRegexpBuilder;
use Tuleap\Svn\Repository\Settings;
use Tuleap\Svn\SvnAdmin;
use Tuleap\Svn\SvnLogger;
use Tuleap\Svn\SvnPermissionManager;

class RepositoryResource extends AuthenticatedResource
{
    /**
     * @var NotificationsEmailsBuilder
     */
    private $emails_builder;
    /**
     * @var RepositoryResourceUpdater
     */
    private $repository_updater;
    /**
     * @var ImmutableTagFactory
     */
    private $immutable_tag_factory;

    /**
     * @var RepositoryDeleter
     */
    private $repository_deleter;
    /**
     * @var RepositoryManager
     */
    private $repository_manager;

    /**
     * @var SvnPermissionManager
     */
    private $permission_manager;

    /**
     * @var \UserManager
     */
    private $user_manager;

    /**
     * @var SystemEventManager
     */
    private $system_event_manager;

    /**
     * @var \ProjectManager
     */
    private $project_manager;

    /**
     * @var RepositoryCreator
     */
    private $repository_creator;

    /**
     * @var HookConfigRetriever
     */
    private $hook_config_retriever;

    /**
     * @var RepositoryRepresentationBuilder
     */
    private $representation_builder;

    public function __construct()
    {
        $dao                        = new Dao();
        $logger                     = new SvnLogger();
        $system_command             = new \System_Command();
        $backend_svn                = \Backend::instance(\Backend::SVN);
        $project_history_dao        = new ProjectHistoryDao();
        $this->system_event_manager = \SystemEventManager::instance();
        $this->project_manager      = \ProjectManager::instance();
        $hook_dao                   = new HookDao();
        $this->repository_manager   = new RepositoryManager(
            $dao,
            $this->project_manager,
            new SvnAdmin($system_command, $logger, $backend_svn),
            $logger,
            $system_command,
            new Destructor($dao, $logger),
            \EventManager::instance(),
            $backend_svn,
            new AccessFileHistoryFactory(new AccessFileHistoryDao())
        );

        $this->user_manager       = \UserManager::instance();
        $this->permission_manager = new SvnPermissionManager(
            new \User_ForgeUserGroupFactory(new \UserGroupDao()),
            \PermissionsManager::instance()
        );

        $this->hook_config_retriever = new HookConfigRetriever($hook_dao, new HookConfigSanitizer());
        $project_history_formatter   = new ProjectHistoryFormatter();
        $hook_config_updator         = new HookConfigUpdator(
            $hook_dao,
            $project_history_dao,
            new HookConfigChecker($this->hook_config_retriever),
            new HookConfigSanitizer(),
            $project_history_formatter
        );

        $immutable_tag_dao           = new ImmutableTagDao();
        $this->immutable_tag_factory = new ImmutableTagFactory($immutable_tag_dao);
        $immutable_tag_creator       = new ImmutableTagCreator(
            $immutable_tag_dao,
            $project_history_formatter,
            $project_history_dao,
            $this->immutable_tag_factory
        );
        $access_file_history_factory = new AccessFileHistoryFactory(new AccessFileHistoryDao());
        $access_file_history_creator = new AccessFileHistoryCreator(
            new AccessFileHistoryDao(),
            $access_file_history_factory,
            $project_history_dao,
            $project_history_formatter
        );
        $project_history_formatter   = new ProjectHistoryFormatter();
        $mail_notification_manager    = new MailNotificationManager(
            new MailNotificationDao(CodendiDataAccess::instance(), new RepositoryRegexpBuilder()),
            new UsersToNotifyDao(),
            new UgroupsToNotifyDao(),
            $project_history_dao
        );

        $this->repository_creator    = new RepositoryCreator(
            $dao,
            $this->system_event_manager,
            $project_history_dao,
            $this->permission_manager,
            $hook_config_updator,
            $project_history_formatter,
            $immutable_tag_creator,
            $access_file_history_creator,
            $mail_notification_manager
        );

        $this->emails_builder         = new NotificationsEmailsBuilder();
        $this->representation_builder = new RepositoryRepresentationBuilder(
            $this->permission_manager,
            $this->hook_config_retriever,
            $this->immutable_tag_factory,
            $access_file_history_factory,
            $mail_notification_manager,
            new NotificationsBuilder(
                $this->emails_builder,
                new UsersToNotifyDao(),
                \UserManager::instance(),
                new UgroupsToNotifyDao(),
                new \UGroupManager()
            )
        );

        $this->repository_deleter = new RepositoryDeleter(
            new \System_Command(),
            $project_history_dao,
            $dao,
            $this->system_event_manager,
            $this->repository_manager
        );

        $this->repository_updater = new RepositoryResourceUpdater(
            $hook_config_updator,
            $immutable_tag_creator,
            $access_file_history_factory,
            $access_file_history_creator,
            $this->immutable_tag_factory,
            $mail_notification_manager,
            new NotificationUpdateChecker($mail_notification_manager)
        );
    }

    /**
     * Return info about repository if exists
     *
     * @url OPTIONS {id}
     *
     * @param string $id Id of the repository
     */
    public function optionsId($id)
    {
        $this->sendAllowHeaders();
    }

    /**
     * Get SVN
     *
     * Get info about project SVN repositories
     *
     * <br>
     * <pre>
     * {<br>
     *   &nbsp;"id" : 90,<br>
     *   &nbsp;"project": {...},<br>
     *   &nbsp;"uri": "svn/90",<br>
     *   &nbsp;"name": "repo",<br>
     *   &nbsp;"settings": {<br>
     *   &nbsp;&nbsp;"commit_rules": {<br>
     *   &nbsp;&nbsp;"is_reference_mandatory": true|false ,<br>
     *   &nbsp;&nbsp;"is_commit_message_change_allowed": true|false<br>
     *   &nbsp;&nbsp;},<br>
     *   &nbsp;&nbsp;"immutable_tags": {<br>
     *   &nbsp;&nbsp;"paths": [<br>
     *   &nbsp;&nbsp;"/tags1",<br>
     *   &nbsp;&nbsp;"/tags2"<br>
     *   &nbsp;&nbsp; ],<br>
     *   &nbsp;&nbsp;"whitelist": [<br>
     *   &nbsp;&nbsp;"/tags/whitelist1",<br>
     *   &nbsp;&nbsp;"/tags/whitelist2"<br>
     *   &nbsp;&nbsp; ]<br>
     *   &nbsp;},<br>
     *   &nbsp;&nbsp;"access_file": "[/] * = rw @members = rw\r\n[/tags] @admins = rw",<br>
     *   &nbsp;&nbsp;"email_notifications": {<br>
     *   &nbsp;&nbsp;&nbsp;"path": "trunk",<br>
     *   &nbsp;&nbsp;&nbsp;"user_groups": {<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;"id": "101_3",<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;"uri": "user_groups/101_3",<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;"label": "Project members"<br>
     *   &nbsp;&nbsp;&nbsp;},<br>
     *   &nbsp;&nbsp;&nbsp;"users": {<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;"id": "333",<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;"uri": "/users/333",<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;"username": "..."<br>
     *   &nbsp;&nbsp;&nbsp;},<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;"emails": [<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;"foo@example.com",<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;"bar@example.com"<br>
     *   &nbsp;&nbsp;&nbsp;]<br>
     *   &nbsp;&nbsp;}<br>
     *   &nbsp;}<br>
     *  }<br>
     * </pre>
     *
     * @access hybrid
     *
     * @url GET {id}
     *
     * @param int $id Id of the repository
     *
     * @return FullRepositoryRepresentation
     *
     * @throws 404
     * @throws 403
     */
    public function get($id)
    {
        $this->checkAccess();

        $user       = $this->user_manager->getCurrentUser();
        $repository = $this->getRepository($id);

        ProjectAuthorization::userCanAccessProject(
            $user,
            $repository->getProject(),
            new \URLVerification()
        );

        $this->sendAllowHeaders();

        return $this->representation_builder->build($repository, $user);
    }

    /**
     * PUT SVN
     *
     * Update settings of an SVN repository. Only project admins can do this.
     *
     * <br>
     * <pre>
     * {<br>
     *   &nbsp;"settings": {<br>
     *   &nbsp;&nbsp;"commit_rules": {<br>
     *   &nbsp;&nbsp;"is_reference_mandatory": true|false ,<br>
     *   &nbsp;&nbsp;"is_commit_message_change_allowed": true|false<br>
     *   &nbsp;&nbsp;},<br>
     *   &nbsp;&nbsp;"immutable_tags": {<br>
     *   &nbsp;&nbsp;"paths": [<br>
     *   &nbsp;&nbsp;"/tags1",<br>
     *   &nbsp;&nbsp;"/tags2"<br>
     *   &nbsp;&nbsp; ],<br>
     *   &nbsp;&nbsp;"whitelist": [<br>
     *   &nbsp;&nbsp;"/tags/whitelist1",<br>
     *   &nbsp;&nbsp;"/tags/whitelist2"<br>
     *   &nbsp;&nbsp; ]<br>
     *   &nbsp;},<br>
     *   &nbsp;"access_file": "[/] * = rw @members = rw\r\n[/tags] @admins = rw",<br>
     *   &nbsp;&nbsp;"email_notifications": [<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"path": "/trunk",<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"emails": [<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"foo@example.com",<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"bar@example.com"<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;]<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;},<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"path": "/tags",<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"emails": [<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"foo@example.com"<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;]<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;}<br>
     *   &nbsp;&nbsp;]<br>
     *   &nbsp;}<br>
     *  }<br>
     * </pre>
     *
     * @access protected
     *
     * @url PUT {id}
     *
     * @param int $id Id of the repository
     * @param SettingsPUTRepresentation $settings The new settings of the SVN repository {@from body} {@type Tuleap\SVN\REST\v1\SettingsPUTRepresentation}
     *
     * @return FullRepositoryRepresentation
     *
     * @throws 404
     * @throws 403
     */
    protected function put($id, SettingsPUTRepresentation $settings)
    {
        $this->sendAllowHeaders();
        $this->checkAccess();

        $user       = $this->user_manager->getCurrentUser();
        $repository = $this->getRepository($id);

        ProjectAuthorization::userCanAccessProject(
            $user,
            $repository->getProject(),
            new \URLVerification()
        );

        $this->checkUserIsAdmin($repository->getProject(), $user);

        if (! $settings->isAccessFileKeySent()) {
            throw new RestException('400', '`settings[access_file]` is required');
        }

        $repository_settings = $this->getSettings($repository, $settings);

        try {
            $this->repository_updater->update($repository, $repository_settings);
        } catch (CannotCreateMailHeaderException $exception) {
            throw new RestException('500', 'An error occured while saving the notifications.');
        }

        return $this->representation_builder->build($repository, $user);
    }

    /**
     * @return Repository
     */
    private function getRepository($id)
    {
        try {
            $repository = $this->repository_manager->getRepositoryById($id);

            if ($repository->isDeleted()) {
                throw new RestException('404', 'Repository not found');
            }

            return $repository;
        } catch (CannotFindRepositoryException $e) {
            throw new RestException('404', 'Repository not found');
        }
    }

    private function checkUserIsAdmin(Project $project, PFUser $user)
    {
        if (! $this->permission_manager->isAdmin($project, $user)) {
            throw new RestException(403, 'User must be SVN admin to do this action');
        }
    }

    /**
     * Delete SVN repository
     *
     * Delete a SVN repository
     *
     * @url DELETE {id}
     * @status 202
     * @access protected
     *
     * @param int $repository_id Id of the repository
     *
     * @throws 400
     * @throws 403
     * @throws 404
     */
    protected function delete($id)
    {
        $this->checkAccess();
        $this->sendAllowHeaders();

        try {
            $current_user = $this->user_manager->getCurrentUser();
            $repository   = $this->getRepository($id);
            ProjectAuthorization::userCanAccessProject(
                $this->user_manager->getCurrentUser(),
                $repository->getProject(),
                new \URLVerification()
            );

            $this->checkUserIsAdmin($repository->getProject(), $current_user);

            if ($this->isDeletionAlreadyQueued($repository)) {
                throw new RestException('400', 'Repository already in queue for deletion');

                return;
            }

            $this->repository_deleter->queueRepositoryDeletion($repository);
        } catch (CannotFindRepositoryException $e) {
            throw new RestException('404', 'Repository not found');
        }
    }

    private function isDeletionAlreadyQueued(Repository $repository)
    {
        return SystemEventManager::instance()->areThereMultipleEventsQueuedMatchingFirstParameter(
            'Tuleap\\Svn\\EventRepository\\' . SystemEvent_SVN_DELETE_REPOSITORY::NAME,
            $repository->getProject()->getID() . SystemEvent::PARAMETER_SEPARATOR . $repository->getId()
        );
    }

    /**
     * @param Repository $repository
     * @param \PFUser    $user
     *
     * @return \Tuleap\SVN\REST\v1\RepositoryRepresentation
     */
    private function getRepositoryRepresentation(Repository $repository, \PFUser $user)
    {
        return $this->representation_builder->build($repository, $user);
    }

    /**
     * Create a SVN repository
     *
     * Create a svn repository in a given project. User must be svn administrator to be able to create the repository.
     *
     * <br>
     * <br>
     * A project admin can create an SVN repository like this:
     * <br>
     * <pre>
     * {<br>
     *   &nbsp;"project_id": 122,<br>
     *   &nbsp;"name" : "repo01"<br>
     *  }<br>
     * </pre>
     * <br>
     * <br>
     * In addition, the admin can create a repository with custom settings:
     * <br>
     * <pre>
     * {<br>
     *   &nbsp;"project_id": 122,<br>
     *   &nbsp;"name" : "repo01",<br>
     *   &nbsp;"settings": {<br>
     *   &nbsp;&nbsp;"commit_rules": {<br>
     *   &nbsp;&nbsp;"is_reference_mandatory": true|false,<br>
     *   &nbsp;&nbsp;"is_commit_message_change_allowed": true|false<br>
     *   &nbsp;&nbsp;},<br>
     *   &nbsp;&nbsp;"immutable_tags": {<br>
     *   &nbsp;&nbsp;"paths": [<br>
     *   &nbsp;&nbsp;"/tags1",<br>
     *   &nbsp;&nbsp;"/tags2"<br>
     *   &nbsp;&nbsp; ],<br>
     *   &nbsp;&nbsp;"whitelist": [<br>
     *   &nbsp;&nbsp;"/tags/whitelist1",<br>
     *   &nbsp;&nbsp;"/tags/whitelist2"<br>
     *   &nbsp;&nbsp; ]<br>
     *   &nbsp;&nbsp; },<br>
     *   &nbsp;&nbsp;"layout": [<br>
     *   &nbsp;&nbsp;"/trunk",<br>
     *   &nbsp;&nbsp;"/tags"<br>
     *   &nbsp;&nbsp; ],<br>
     *   &nbsp;&nbsp;"access_file": "[/] * = rw \r\n@members = rw\r\n[/tags] @admins = rw",<br>
     *   &nbsp;&nbsp;"email_notifications": [<br>
     *   &nbsp;&nbsp;&nbsp;{<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"path": "/trunk",<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"emails": [<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"foo@example.com",<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"bar@example.com"<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;]<br>
     *   &nbsp;&nbsp;&nbsp;},<br>
     *   &nbsp;&nbsp;&nbsp;{<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"path": "/tags",<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"emails": [<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"foo@example.com"<br>
     *   &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;]<br>
     *   &nbsp;&nbsp;&nbsp;}<br>
     *   &nbsp;&nbsp;]<br>
     *   &nbsp;}<br>
     *  }<br>
     * </pre>
     *
     * @url POST
     * @access protected
     * @status 201
     *
     * @param $project_id project id {@type int} {@from body}
     * @param $name Repository name {@type string} {@form body}
     * @param SettingsPOSTRepresentation $settings Repository settings {@type \Tuleap\SVN\REST\v1\SettingsPOSTRepresentation} {@required false}
     *
     * @return \Tuleap\SVN\REST\v1\RepositoryRepresentation
     * @throws 400 BadRequest Given project does not exist or project does not use SVN service
     * @throws 403 Forbidden User doesn't have permission to create a repository
     * @throws 500 Error Unable to create the repository
     * @throws 409 Repository name is invalid
     */
    protected function post($project_id, $name, SettingsPOSTRepresentation $settings = null)
    {
        $this->checkAccess();
        $this->options();

        $user    = $this->user_manager->getCurrentUser();
        $project = $this->project_manager->getProject($project_id);
        if ($project->isError()) {
            throw new RestException(400, "Given project does not exist");
        }

        if (! $project->usesService(\SvnPlugin::SERVICE_SHORTNAME)) {
            throw new RestException(400, "Project does not use SVN service");
        }

        ProjectAuthorization::userCanAccessProject(
            $user,
            $project,
            new \URLVerification()
        );

        $repository_to_create = new Repository("", $name, "", "", $project);
        try {
            $repository_settings       = $this->getSettings($repository_to_create, $settings);
            $has_initial_layout        = $settings !== null && $settings->layout !== null;
            $initial_repository_layout = $has_initial_layout ? $settings->layout : array();

            $this->repository_creator->createWithSettings(
                $repository_to_create,
                $user,
                $repository_settings,
                $initial_repository_layout
            );
        } catch (CannotCreateRepositoryException $e) {
            throw new RestException(500, "Unable to create the repository");
        } catch (UserIsNotSVNAdministratorException $e) {
            throw new RestException(403, "User doesn't have permission to create a repository");
        } catch (RepositoryNameIsInvalidException $e) {
            throw new RestException(409, $e->getMessage());
        } catch (CannotCreateAccessFileHistoryException $e) {
            throw new RestException(500, "Unable to store access file");
        }

        $repository = $this->repository_manager->getRepositoryByName($project, $name);

        return $this->getRepositoryRepresentation($repository, $user);
    }

    private function sendAllowHeaders()
    {
        Header::allowOptionsGetPutDelete();
    }

    /**
     * @url OPTIONS
     *
     */
    public function options()
    {
        Header::allowOptionsPost();
    }

    /**
     * @return Settings
     */
    private function getSettings(Repository $repository, SettingsRepresentation $settings = null)
    {
        $commit_rules = array();
        if ($settings && $settings->commit_rules) {
            $commit_rules = $settings->commit_rules->toArray();
        }

        $immutable_tag = $this->immutable_tag_factory->getEmpty($repository);
        if ($settings && $settings->immutable_tags) {
            $immutable_tag = $this->immutable_tag_factory->getFromRESTRepresentation(
                $repository,
                $settings->immutable_tags
            );
        }

        $access_file = "";
        if ($settings && $settings->access_file) {
            $access_file = $settings->access_file;
        }

        $mail_notification = array();
        if ($settings && $settings->email_notifications) {
            foreach ($settings->email_notifications as $notification) {
                $mail_notification[] = new MailNotification(
                    0,
                    $repository,
                    $notification->path,
                    $this->emails_builder->transformNotificationEmailsArrayAsString($notification->emails),
                    array(),
                    array()
                );
            }
        }

        return new Settings($commit_rules, $immutable_tag, $access_file, $mail_notification);
    }
}
