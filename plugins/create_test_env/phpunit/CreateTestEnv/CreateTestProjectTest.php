<?php
/**
 * Copyright (c) Enalean, 2018. All Rights Reserved.
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

namespace Tuleap\CreateTestEnv;

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../include/create_test_envPlugin.class.php';

class CreateTestProjectTest extends TestCase
{

    public function setUp()
    {
        \ForgeConfig::store();
        \ForgeConfig::set('sys_custompluginsroot', __DIR__.'/_fixtures');
    }

    public function tearDown()
    {
        \ForgeConfig::restore();
    }

    /**
     * @dataProvider userNameProvider
     */
    public function testUnixNameIsValid($realname, $username, $expected_result)
    {
        $create = new CreateTestProject($username, $realname, CreateTestProject::DEFAULT_ARCHIVE);
        $this->assertEquals($expected_result, $create->generateProjectUnixName());
    }

    public function userNameProvider()
    {
        return [
            [ '', 'joperesr', 'test-for-joperesr' ],
            [ '', 'jope_resr', 'test-for-jope-resr' ],
            [ '', 'jope.resr', 'test-for-jope-resr' ],
        ];
    }

    /**
     * @dataProvider fullNameProvider
     */
    public function testFullNameIsValid($realname, $username, $expected_result)
    {
        $create = new CreateTestProject($username, $realname, CreateTestProject::DEFAULT_ARCHIVE);
        $this->assertEquals($expected_result, $create->generateProjectFullName());
    }

    public function fullNameProvider()
    {
        return [
            [ '', 'joperesr', 'Test project for joperesr' ],
            [ '', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Test project for aaaaaaaaaaaaaaaaaaaaaaa' ],
        ];
    }

    /**
     * @test
     */
    public function it_uses_the_default_project_template_shipped_by_plugin() // @codingStandardsIgnoreLine
    {
        $create = new CreateTestProject('foo', 'bar', CreateTestProject::DEFAULT_ARCHIVE);
        $this->assertEquals(realpath(__DIR__.'/../../resources/sample-project/project.xml'), realpath($create->getProjectXMLFilePath()));
    }

    /**
     * @test
     */
    public function it_uses_a_config_deployed_in_etc() // @codingStandardsIgnoreLine
    {
        $create = new CreateTestProject('foo', 'bar', 'sp-prj-2');
        $this->assertEquals(realpath(__DIR__.'/_fixtures/create_test_env/resources/sp-prj-2/project.xml'), realpath($create->getProjectXMLFilePath()));
    }
}
