<?php
/**
 * Copyright (c) Enalean, 2019-Present. All Rights Reserved.
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

namespace Tuleap\admin\ProjectCreation\ProjectVisibility;

use ConfigDao;
use ForgeAccess;
use ForgeConfig;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Project;
use Tuleap\ForgeConfigSandbox;

final class ProjectVisibilityConfigManagerTest extends TestCase
{
    use MockeryPHPUnitIntegration, ForgeConfigSandbox;

    public function testDefaultProjectVisibilityUpdate() : void
    {
        $config_dao     = Mockery::mock(ConfigDao::class);
        $config_manager = new ProjectVisibilityConfigManager($config_dao);

        ForgeConfig::set(ForgeAccess::CONFIG, ForgeAccess::RESTRICTED);

        $config_dao->shouldReceive('save')->once();

        $this->assertTrue($config_manager->updateDefaultProjectVisibility(Project::ACCESS_PRIVATE_WO_RESTRICTED));
    }

    public function testDefaultProjectVisibilityUpdateWithIncorrectProjectVisibility() : void
    {
        $config_dao     = Mockery::mock(ConfigDao::class);
        $config_manager = new ProjectVisibilityConfigManager($config_dao);

        ForgeConfig::set(ForgeAccess::CONFIG, ForgeAccess::ANONYMOUS);

        $config_dao->shouldNotReceive('save');

        $this->assertFalse($config_manager->updateDefaultProjectVisibility(Project::ACCESS_PRIVATE_WO_RESTRICTED));
    }
}
