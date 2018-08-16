<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 *
 * This file is a part of Codendi.
 *
 * Codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */

class FRSPackageFactoryTest extends TuleapTestCase
{
    protected $group_id   = 12;
    protected $package_id = 34;
    protected $user_id    = 56;

    private $user;
    private $frs_package_factory;
    private $user_manager;
    private $permission_manager;
    private $frs_permission_manager;

    public function setUp()
    {
        parent::setUp();
        $this->user                   = \Mockery::spy(PFUser::class);
        $this->frs_package_factory    = \Mockery::mock(FRSPackageFactory::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->user_manager           = \Mockery::spy(UserManager::class);
        $this->permission_manager     = \Mockery::spy(PermissionsManager::class);
        $this->frs_permission_manager = \Mockery::spy(Tuleap\FRS\FRSPermissionManager::class);
        $this->project_manager        = \Mockery::spy(ProjectManager::class, ['getProject' => \Mockery::spy(Project::class)]);

        stub($this->user_manager)->getUserById()->returns($this->user);
        stub($this->frs_package_factory)->getUserManager()->returns($this->user_manager);
        stub($this->frs_package_factory)->getFRSPermissionManager()->returns($this->frs_permission_manager);
        stub($this->frs_package_factory)->getProjectManager()->returns($this->project_manager);
    }

    public function testGetFRSPackageFromDb()
    {
        $packageArray1 = array('package_id'       => 1,
                               'group_id'         => 1,
                               'name'             => 'pkg1',
                               'status_id'        => 2,
                               'rank'             => null,
                               'approve_license'  => null,
                               'data_array'       => null,
                               'package_releases' => null,
                               'error_state'      => null,
                               'error_message'    => null
                               );
        $package1 = FRSPackageFactory::getFRSPackageFromArray($packageArray1);

        $packageArray2 = array('package_id'       => 2,
                               'group_id'         => 2,
                               'name'             => 'pkg2',
                               'status_id'        => 1,
                               'rank'             => null,
                               'approve_license'  => null,
                               'data_array'       => null,
                               'package_releases' => null,
                               'error_state'      => null,
                               'error_message'    => null
                               );
        $package2 = FRSPackageFactory::getFRSPackageFromArray($packageArray2);

        $dao = \Mockery::mock(FRSPackageDao::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $dao->da = TestHelper::getPartialMock('DataAccess', array('DataAccess'));
        stub($dao)->retrieve('SELECT p.*  FROM frs_package AS p  WHERE  p.package_id = 1  ORDER BY rank DESC LIMIT 1')->returnsDar($packageArray1);
        stub($dao)->retrieve('SELECT p.*  FROM frs_package AS p  WHERE  p.package_id = 2  AND p.status_id != 0  ORDER BY rank DESC LIMIT 1')->returnsDar($packageArray2);
        stub($dao)->retrieve()->returnsDar([]);

        $PackageFactory = \Mockery::mock(FRSPackageFactory::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $PackageFactory->shouldReceive('_getFRSPackageDao')->andReturns($dao);
        $this->assertEqual($PackageFactory->getFRSPackageFromDb(1, null, 0x0001), $package1);
        $this->assertEqual($PackageFactory->getFRSPackageFromDb(2), $package2);
    }

    public function testAdminHasAlwaysAccess()
    {
        stub($this->frs_permission_manager)->isAdmin()->returns(true);

        $this->assertTrue($this->frs_package_factory->userCanRead($this->group_id, $this->package_id, $this->user_id));
    }

    protected function _userCanReadWithSpecificPerms($can_read_package)
    {
        stub($this->frs_permission_manager)->userCanRead()->returns(true);
        stub($this->frs_permission_manager)->isAdmin()->returns(false);
        $this->user->shouldReceive('getUgroups')->with($this->group_id, array())->once()->andReturns(array(1,2,76));

        $this->permission_manager->shouldReceive('isPermissionExist')->andReturns(true);
        $this->permission_manager->shouldReceive('userHasPermission')->with($this->package_id, 'PACKAGE_READ', array(1,2,76))->once()->andReturns($can_read_package);
        $this->frs_package_factory->shouldReceive('getPermissionsManager')->andReturns($this->permission_manager);

        return $this->frs_package_factory;
    }

    public function testUserCanReadWithSpecificPermsHasAccess()
    {
        $this->frs_package_factory = $this->_userCanReadWithSpecificPerms(true);
        $this->assertTrue($this->frs_package_factory->userCanRead($this->group_id, $this->package_id, $this->user_id));
    }
    
    public function testUserCanReadWithSpecificPermsHasNoAccess()
    {
        $this->frs_package_factory = $this->_userCanReadWithSpecificPerms(false);
        $this->assertFalse($this->frs_package_factory->userCanRead($this->group_id, $this->package_id, $this->user_id));
    }

    /**
     * userHasPermissions return false but isPermissionExist return false because no permissions where set, so let user see the gems
     */
    public function testUserCanReadWhenNoPermissionsSet()
    {
        stub($this->frs_permission_manager)->userCanRead()->returns(true);
        $this->user->shouldReceive('getUgroups')->with($this->group_id, array())->once()->andReturns(array(1,2,76));

        $this->permission_manager = \Mockery::spy(PermissionsManager::class);
        $this->permission_manager->shouldReceive('isPermissionExist')->with($this->package_id, 'PACKAGE_READ')->once()->andReturns(false);
        $this->permission_manager->shouldReceive('userHasPermission')->with($this->package_id, 'PACKAGE_READ', array(1,2,76))->once()->andReturns(false);
        $this->frs_package_factory->shouldReceive('getPermissionsManager')->andReturns($this->permission_manager);
        
        $this->assertTrue($this->frs_package_factory->userCanRead($this->group_id, $this->package_id, $this->user_id));
    }

    public function testAdminCanAlwaysUpdate()
    {
        stub($this->frs_permission_manager)->isAdmin()->returns(true);
        $this->assertTrue($this->frs_package_factory->userCanUpdate($this->group_id, $this->package_id, $this->user_id));
    }

    public function testMereMortalCannotUpdate()
    {
        stub($this->frs_permission_manager)->isAdmin()->returns(false);
        $this->assertFalse($this->frs_package_factory->userCanUpdate($this->group_id, $this->package_id, $this->user_id));
    }

    public function testAdminCanAlwaysCreate()
    {
        stub($this->frs_permission_manager)->isAdmin()->returns(true);
        $this->assertTrue($this->frs_package_factory->userCanCreate($this->group_id, $this->user_id));
    }

    public function testMereMortalCannotCreate()
    {
        stub($this->frs_permission_manager)->isAdmin()->returns(false);
        $this->assertFalse($this->frs_package_factory->userCanCreate($this->group_id, $this->user_id));
    }

    public function testDeleteProjectPackagesFail()
    {
        $packageFactory = \Mockery::mock(FRSPackageFactory::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $package = \Mockery::spy(FRSPackage::class);
        $packageFactory->shouldReceive('getFRSPackagesFromDb')->andReturns(array($package, $package, $package));
        $packageFactory->shouldReceive('delete_package')->once()->andReturns(true);
        $packageFactory->shouldReceive('delete_package')->once()->andReturns(false);
        $packageFactory->shouldReceive('delete_package')->once()->andReturns(true);
        $this->assertFalse($packageFactory->deleteProjectPackages(1));
    }

    public function testDeleteProjectPackagesSuccess()
    {
        $packageFactory = \Mockery::mock(FRSPackageFactory::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $package = \Mockery::spy(FRSPackage::class);
        $packageFactory->shouldReceive('getFRSPackagesFromDb')->andReturns(array($package, $package, $package));
        $packageFactory->shouldReceive('delete_package')->once()->andReturns(true);
        $packageFactory->shouldReceive('delete_package')->once()->andReturns(true);
        $packageFactory->shouldReceive('delete_package')->once()->andReturns(true);
        $this->assertTrue($packageFactory->deleteProjectPackages(1));
    }
}
