<?php
/**
 * Copyright (c) Enalean, 2017-2018. All Rights Reserved.
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

namespace Tuleap\Tracker\Artifact\Changeset\PostCreation;

require_once __DIR__.'/../../../../bootstrap.php';

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ActionsRunnerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var MockInterface
     */
    private $logger;
    /**
     * @var MockInterface
     */
    private $dao;

    protected function setUp()
    {
        $this->logger = \Mockery::mock(\Logger::class);
        $this->dao    = \Mockery::mock(ActionsRunnerDao::class);
        \ForgeConfig::store();
    }

    protected function tearDown()
    {
        \ForgeConfig::restore();
    }

    public function testAllPostCreationTasksAreExecuted()
    {
        $task_1 = \Mockery::mock(PostCreationTask::class);
        $task_2 = \Mockery::mock(PostCreationTask::class);

        $actions_runner = new ActionsRunner($this->logger, $this->dao, $task_1, $task_2);

        $changeset = \Mockery::mock(\Tracker_Artifact_Changeset::class);

        $task_1->shouldReceive('execute')->with($changeset)->once();
        $task_2->shouldReceive('execute')->with($changeset)->once();

        $actions_runner->executePostCreationActions($changeset);
    }

    public function testAsyncPostCreationTasksFallbackInSyncProcessingInCaseOfError()
    {
        $task   = \Mockery::mock(PostCreationTask::class);

        $actions_runner = new ActionsRunner($this->logger, $this->dao, $task);

        $changeset = \Mockery::mock(\Tracker_Artifact_Changeset::class);
        $changeset->shouldReceive('getId');

        \ForgeConfig::set('sys_async_emails', 'all');

        $this->dao->shouldReceive('addNewPostCreationEvent')->once();
        $this->dao->shouldReceive('addEndDate')->once();
        $this->logger->shouldReceive('error')->once();

        $task->shouldReceive('execute')->once();

        $actions_runner->executePostCreationActions($changeset);
    }
}
