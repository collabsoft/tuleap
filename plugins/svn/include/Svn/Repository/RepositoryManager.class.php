<?php
/*8
 * Copyright (c) Enalean, 2015 - 2018. All Rights Reserved.
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

use Backend;
use EventManager;
use Exception;
use ForgeConfig;
use HTTPRequest;
use Logger;
use Project;
use ProjectManager;
use Rule_ProjectName;
use System_Command;
use SystemEvent;
use SystemEventManager;
use Tuleap\Svn\AccessControl\AccessFileHistoryFactory;
use Tuleap\Svn\Admin\Destructor;
use Tuleap\Svn\Dao;
use Tuleap\Svn\EventRepository\SystemEvent_SVN_RESTORE_REPOSITORY;
use Tuleap\Svn\Repository\Exception\CannotFindRepositoryException;
use Tuleap\Svn\SvnAdmin;

class RepositoryManager
{
    const PREFIX = 'svn';

    /** @var Dao */
    private $dao;
     /** @var ProjectManager */
    private $project_manager;
     /** @var SvnAdmin */
    private $svnadmin;
     /** @var Logger */
    private $logger;
    /** @var System_Command */
    private $system_command;
    /** @var Destructor */
    private $destructor;
    /** @var EventManager */
    private $event_manager;
    /** @var Backend */
    private $backend;
    /** @var AccessFileHistoryFactory */
    private $access_file_history_factory;

    public function __construct(
        Dao $dao,
        ProjectManager $project_manager,
        SvnAdmin $svnadmin,
        Logger $logger,
        System_Command $system_command,
        Destructor $destructor,
        EventManager $event_manager,
        Backend $backend,
        AccessFileHistoryFactory $access_file_history_factory
    ) {
        $this->dao                         = $dao;
        $this->project_manager             = $project_manager;
        $this->svnadmin                    = $svnadmin;
        $this->logger                      = $logger;
        $this->system_command              = $system_command;
        $this->destructor                  = $destructor;
        $this->event_manager               = $event_manager;
        $this->backend                     = $backend;
        $this->access_file_history_factory = $access_file_history_factory;
    }

    /**
     * @return Repository[]
     */
    public function getRepositoriesInProject(Project $project) {
        $repositories = array();
        foreach ($this->dao->searchByProject($project) as $row) {
            $repositories[] = $this->instantiateFromRow($row, $project);
        }

        return $repositories;
    }

    /**
     * @return RepositoryPaginatedCollection
     */
    public function getRepositoryPaginatedCollection(Project $project, $limit, $offset)
    {
        $repositories = array();
        foreach ($this->dao->searchPaginatedByProject($project, $limit, $offset) as $row) {
            $repositories[] = $this->instantiateFromRow($row, $project);
        }

        return new RepositoryPaginatedCollection(
            $repositories,
            $this->dao->foundRows()
        );
    }

    /**
     * @return RepositoryPaginatedCollection
     */
    public function getRepositoryPaginatedCollectionByName(Project $project, $repository_name, $limit, $offset)
    {
        $repositories = array();
        foreach ($this->dao->searchPaginatedByProjectAndByName($project, $repository_name, $limit, $offset) as $row) {
            $repositories[] = $this->instantiateFromRow($row, $project);
        }

        return new RepositoryPaginatedCollection(
            $repositories,
            $this->dao->foundRows()
        );
    }

    public function getRepositoriesInProjectWithLastCommitInfo(Project $project)
    {
        $repositories = array();
        foreach ($this->dao->searchByProject($project) as $row) {
            $repositories[] = array(
                'repository'  => $this->instantiateFromRow($row, $project),
                'commit_date' => $row['commit_date']
            );
        }

        return $repositories;
    }

    public function getRepositoryByName(Project $project, $name) {
        $row = $this->dao->searchRepositoryByName($project, $name);
        if ($row) {
            return $this->instantiateFromRow($row, $project);
        } else {
            throw new CannotFindRepositoryException();
        }
    }

    public function getByIdAndProject($id_repository, Project $project) {
        $row = $this->dao->searchByRepositoryIdAndProjectId($id_repository, $project);
        if (! $row) {
            throw new CannotFindRepositoryException();
        }

        return $this->instantiateFromRow($row, $project);
    }

    public function getRepositoryById($id)
    {
        $row = $this->dao->searchByRepositoryId($id);
        if (!$row) {
            throw new CannotFindRepositoryException();
        }

        return $this->instantiateFromRowWithoutProject($row);
    }

    /**
     * @return SystemEvent or null
     */
    public function queueRepositoryRestore(Repository $repository, SystemEventManager $system_event_manager)
    {
        return $system_event_manager->createEvent(
            'Tuleap\\Svn\\EventRepository\\'.SystemEvent_SVN_RESTORE_REPOSITORY::NAME,
            $repository->getProject()->getID() . SystemEvent::PARAMETER_SEPARATOR . $repository->getId(),
            SystemEvent::PRIORITY_MEDIUM,
            SystemEvent::OWNER_ROOT
        );
    }

    public function getRepositoryFromSystemPath($path) {
         if (! preg_match('/\/(\d+)\/('.RuleName::PATTERN_REPOSITORY_NAME.')$/', $path, $matches)) {
            throw new CannotFindRepositoryException($GLOBALS['Language']->getText('plugin_svn','find_error'));
        }

        $project = $this->project_manager->getProject($matches[1]);
        return $this->getRepositoryIfProjectIsValid($project, $matches[2]);
    }

    public function getRepositoryFromPublicPath(HTTPRequest $request)
    {
        $path    = $request->get('root');
        $project = $request->getProject();

        if (! preg_match('/^'.preg_quote($project->getUnixNameMixedCase(), '/').'\/('.RuleName::PATTERN_REPOSITORY_NAME.')$/', $path, $matches)) {
            throw new CannotFindRepositoryException();
        }

        return $this->getRepositoryIfProjectIsValid($project, $matches[1]);
    }

    private function getRepositoryIfProjectIsValid($project, $repository_name) {
        if (!$project instanceof Project || $project->getID() == null || $project->isError()) {
            throw new CannotFindRepositoryException($GLOBALS['Language']->getText('plugin_svn','find_error'));
        }

        return $this->getRepositoryByName($project, $repository_name);
    }

    private function getArchivedRepositoriesToPurge($retention_date)
    {
        $deleted_repositories  = $this->dao->getDeletedRepositoriesToPurge($retention_date);
        return $this->instantiateRepositoriesFromRow($deleted_repositories);
    }

    public function getRestorableRepositoriesByProject(Project $project)
    {
        $deleted_repositories              = $this->dao->getRestorableRepositoriesByProject($project->getID());
        $deleted_repositories_instantiated = $this->instantiateRepositoriesFromRow($deleted_repositories);
        $deleted_existed_repositories      = array();
        foreach ($deleted_repositories_instantiated as $delete_repository) {
            $archive = $delete_repository->getBackupPath();
            if (file_exists($archive)) {
                array_push($deleted_existed_repositories, $delete_repository);
            }
        }
        return $deleted_existed_repositories;
    }

    private function instantiateRepositoriesFromRow($deleted_repositories)
    {
        $archived_repositories = array();
        foreach ($deleted_repositories as $deleted_repository) {
            $repository = $this->instantiateFromRowWithoutProject($deleted_repository);
            array_push($archived_repositories, $repository);
        }
        return $archived_repositories;
    }

    /**
     * @return Repository
     */
    private function instantiateFromRow(array $row, Project $project)
    {
        return new Repository(
            $row['id'],
            $row['name'],
            $row['backup_path'],
            $row['repository_deletion_date'],
            $project
        );
    }

    /**
     * @return Repository
     */
    private function instantiateFromRowWithoutProject(array $row)
    {
        $project = $this->project_manager->getProject($row['project_id']);

        return $this->instantiateFromRow($row, $project);
    }

    public function purgeArchivedRepositories()
    {
        if (! ForgeConfig::get('sys_file_deletion_delay')) {
            $this->logger->warn("Purge of archived SVN repositories is disabled: sys_file_deletion_delay is missing in local.inc file");
            return;
        }
        $retention_period      = intval(ForgeConfig::get('sys_file_deletion_delay'));
        $retention_date        = strtotime("-" . $retention_period . " days");
        $archived_repositories = $this->getArchivedRepositoriesToPurge($retention_date);

        foreach ($archived_repositories as $repository) {
            try {
                if ($this->archiveBeforePurge($repository)) {
                    $this->deleteArchivedRepository($repository);
                    $this->destructor->delete($repository);
                }
            } catch (Exception $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    public function restoreRepository(Repository $repository)
    {
        $this->logger->info('Restoring repository : '.$repository->getName());
        $backup_path = $repository->getBackupPath();

        if (! file_exists($backup_path)) {
            $this->logger->error('[Restore] Unable to find repository archive: '.$backup_path);
            return false;
        }

        $this->svnadmin->importRepository($repository);

        $new_ugroup_name = null;
        $old_ugroup_name = null;
        $this->backend->updateCustomSVNAccessForRepository(
            $repository->getProject(),
            $repository->getSystemPath(),
            $new_ugroup_name,
            $old_ugroup_name,
            $repository->getFullName(),
            $this->access_file_history_factory->getCurrentVersion($repository)->getContent()
        );
        $this->deleteArchivedRepository($repository);
        $this->dao->markAsDeleted($repository->getId(), null, null);
    }

    private function deleteArchivedRepository(Repository $repository)
    {
        $this->logger->info('Purge of archived SVN repository: '.$repository->getName());
        $path = $repository->getBackupPath();
        $this->logger->debug('Delete backup '. $path);
        if (empty($path) || ! is_writable($path)) {
            $this->logger->debug('Empty path or permission denied '.$path);
        }
        $this->logger->debug('Removing physically the repository');

        $command_output = $this->system_command->exec('rm -rf '.escapeshellarg($path));
        foreach ($command_output as $line) {
            $this->logger->debug('[svn '.$repository->getName().'] cannot remove repository: '. $line);
        }
    }

    private function archiveBeforePurge(Repository $repository)
    {
        $source_path = $repository->getBackupPath();

        if (dirname($source_path)) {
            $status      = true;
            $error       = null;
            $params      = array(
                'source_path'     => $source_path,
                'archive_prefix'  => self::PREFIX,
                'status'          => &$status,
                'error'           => &$error,
                'skip_duplicated' => false
            );

            $this->event_manager->processEvent('archive_deleted_item', $params);

            if ($params['status']) {
                $this->logger->info('The repository' . $repository->getName() . ' has been moved to the archiving area before purge ');
                $this->logger->info('Archive of the SVN repository: ' . $repository->getName() . ' done');
            } else {
                $this->logger->warn('Can not move the repository ' . $repository->getName() . ' to the archiving area before purge :[' . $params['error'] . ']');
                $this->logger->warn('An error occured while archiving SVN repository: ' . $repository->getName());
            }

            return $params['status'];
        }
    }
}
