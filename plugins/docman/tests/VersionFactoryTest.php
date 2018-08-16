<?php

/**
 * Copyright (c) STMicroelectronics, 2010. All Rights Reserved.
 * Copyright (c) Enalean, 2012 - 2017. All Rights Reserved.
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

require_once 'bootstrap.php';


Mock::generate('EventManager');

Mock::generate('UserManager');
Mock::generate('PFUser');

Mock::generate('Docman_VersionDao');
Mock::generate('Docman_Version');
Mock::generate('Docman_ItemFactory');
Mock::generate('Docman_File');
Mock::generatePartial('Docman_VersionFactory', 'Docman_VersionFactoryTestVersionDeleteFile', array('_getVersionDao', 'archiveBeforePurge'));

Mock::generatePartial('Docman_VersionFactory', 'Docman_VersionFactoryTestVersion', array('purgeDeletedVersion', '_getVersionDao', '_purge', '_getEventManager', '_getItemFactory', '_getUserManager'));


class Docman_VersionFactoryTest extends TuleapTestCase {

    function testPurgeDeletedVersionsWithNoVersions() {
        $versionFactory = new Docman_VersionFactoryTestVersion($this);

        $dao = mock('Docman_VersionDao');
        expect($dao)->listVersionsToPurge()->once();
        stub($dao)->listVersionsToPurge()->returnsEmptyDar();
        $versionFactory->setReturnValue('_getVersionDao', $dao);

        $versionFactory->expectNever('purgeDeletedVersion');

        $this->assertTrue($versionFactory->PurgeDeletedVersions(1234567890));
    }

    function testPurgeDeletedVersions() {
        $versionFactory = new Docman_VersionFactoryTestVersion($this);

        $dao = mock('Docman_VersionDao');
        expect($dao)->listVersionsToPurge()->once();
        stub($dao)->listVersionsToPurge()->returnsDar(
            array(
                'id'        => null,
                'user_id'   => null,
                'item_id'   => null,
                'number'    => null,
                'label'     => null,
                'changelog' => null,
                'date'      => null,
                'filename'  => null,
                'filesize'  => null,
                'filetype'  => null,
                'path'      => null
            )
        );
        $versionFactory->setReturnValue('_getVersionDao', $dao);

        $versionFactory->expectOnce('purgeDeletedVersion');

        $this->assertTrue($versionFactory->PurgeDeletedVersions(1234567890));
    }

    function testPurgeDeletedVersionFileNotFound() {
        $versionFactory = new Docman_VersionFactoryTestVersionDeleteFile($this);

        $version = new Docman_Version(array('id'        => null,
                                            'user_id'   => null,
                                            'item_id'   => null,
                                            'number'    => null,
                                            'label'     => null,
                                            'changelog' => null,
                                            'date'      => null,
                                            'filename'  => 'noFile',
                                            'filesize'  => null,
                                            'filetype'  => null,
                                            'path'      => $this->getTmpDir().'/noFile'));

        $versionFactory->expectOnce('archiveBeforePurge', array($version));
        $this->assertFalse($versionFactory->PurgeDeletedVersion($version));
    }

    function testPurgeDeletedVersion() {
        $versionFactory = new Docman_VersionFactoryTestVersionDeleteFile($this);

        $dao = new MockDocman_VersionDao($this);
        $dao->setReturnValue('setPurgeDate', true);
        $versionFactory->setReturnValue('_getVersionDao', $dao);

        $version = new Docman_Version(array('id'        => null,
                                            'user_id'   => null,
                                            'item_id'   => null,
                                            'number'    => null,
                                            'label'     => null,
                                            'changelog' => null,
                                            'date'      => null,
                                            'filename'  => 'fileToPurge.txt',
                                            'filesize'  => null,
                                            'filetype'  => null,
                                            'path'      => $this->getTmpDir().'/fileToPurge_txt'));

        $fp = fopen($version->getPath(), 'w');

        stub($versionFactory)->archiveBeforePurge()->returns(true);
        $versionFactory->expectOnce('archiveBeforePurge', array($version));

        $this->assertTrue($versionFactory->PurgeDeletedVersion($version));
        $this->assertFalse(file_exists($version->getPath()));
    }

    public function itDoesNotRemoveLocalFileIfPurgeFails() {
        $versionFactory = new Docman_VersionFactoryTestVersionDeleteFile($this);

        $dao = new MockDocman_VersionDao($this);
        $dao->setReturnValue('setPurgeDate', true);
        $versionFactory->setReturnValue('_getVersionDao', $dao);

        $version = new Docman_Version(array(
            'id'        => null,
            'user_id'   => null,
            'item_id'   => null,
            'number'    => null,
            'label'     => null,
            'changelog' => null,
            'date'      => null,
            'filename'  => 'fileToDontPurge.txt',
            'filesize'  => null,
            'filetype'  => null,
            'path'      => $this->getTmpDir().'/fileToPurge_txt'));
        fopen($version->getPath(), 'w');

        stub($versionFactory)->archiveBeforePurge()->returns(false);
        $versionFactory->expectNever('archiveBeforePurge', array($version));

        $this->assertTrue(file_exists($version->getPath()));

        unlink($version->getPath());
    }


    function testRestoreOneVersion() {
        $filePath       = $this->getTmpDir().'/version.test';
        touch($filePath);
        $versionFactory = new Docman_VersionFactoryTestVersion($this);
        $dao            = mock('Docman_VersionDao');
        $versionFactory->setReturnValue('_getVersionDao', $dao);

        stub($dao)->searchDeletedVersion(1664, 2)->returnsDar(array('purge_date' => null, 'label' => 'Ho hisse la saucisse', 'path' => $filePath));

        $file = new MockDocman_File($this);
        $file->setReturnValue('getGroupId', 114);
        $if = new MockDocman_ItemFactory($this);
        $if->expectOnce('getItemFromDb', array(1664, array('ignore_deleted' => true)));
        $if->setReturnValue('getItemFromDb', $file);
        $versionFactory->setReturnValue('_getItemFactory', $if);

        $user = mock('PFUser');
        $um   = new MockUserManager($this);
        $um->setReturnValue('getCurrentUser', $user);
        $versionFactory->setReturnValue('_getUserManager', $um);

        $em = new MockEventManager($this);
        $em->expectOnce('processEvent', array('plugin_docman_event_restore_version', array('group_id' => 114, 'item' => $file, 'old_value' => '2 (Ho hisse la saucisse)', 'user' => $user)));
        $versionFactory->setReturnValue('_getEventManager', $em);

        $dao->expectOnce('restore', array(1664, 2));
        $dao->setReturnValue('restore', true);

        $version = new MockDocman_Version($this);
        $version->setReturnValue('getNumber', 2);
        $version->setReturnValue('getItemId', 1664);

        $this->assertTrue($versionFactory->restore($version));
        unlink($filePath);
    }

    function testRestoreOneVersionButFileIsDeleted() {
        $filePath       = $this->getTmpDir().'/version.test';
        $versionFactory = new Docman_VersionFactoryTestVersion($this);
        $dao            = mock('Docman_VersionDao');
        $versionFactory->setReturnValue('_getVersionDao', $dao);

        stub($dao)->searchDeletedVersion(1664, 2)->returnsDar(array('purge_date' => null, 'path' => $filePath));

        $em = new MockEventManager($this);
        $em->expectNever('processEvent', array('plugin_docman_event_restore_version'));
        $versionFactory->setReturnValue('_getEventManager', $em);

        $dao->expectNever('restore', array(1664, 2));

        $version = new MockDocman_Version($this);
        $version->setReturnValue('getNumber', 2);
        $version->setReturnValue('getItemId', 1664);
        $version->setReturnValue('getPath', $filePath);

        $this->assertFalse($versionFactory->restore($version));
    }

    function testRestoreOneVersionAlreadyPurged() {
        $filePath       = $this->getTmpDir().'/version.test';
        $versionFactory = new Docman_VersionFactoryTestVersion($this);
        $dao            = mock('Docman_VersionDao');
        $versionFactory->setReturnValue('_getVersionDao', $dao);

        stub($dao)->searchDeletedVersion(1664, 2)->returnsDar(array('purge_date' => 1234567890, 'path' => $filePath));

        $em = new MockEventManager($this);
        $em->expectNever('processEvent', array('plugin_docman_event_restore_version'));
        $versionFactory->setReturnValue('_getEventManager', $em);

        $dao->expectNever('restore');

        $version = new MockDocman_Version($this);
        $version->setReturnValue('getNumber', 2);
        $version->setReturnValue('getItemId', 1664);

        $this->assertFalse($versionFactory->restore($version));
    }

}
