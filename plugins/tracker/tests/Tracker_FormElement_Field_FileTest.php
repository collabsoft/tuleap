<?php
/**
 * Copyright (c) Enalean, 2015-2018. All Rights Reserved.
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
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

require_once('bootstrap.php');
require_once('common/include/Response.class.php');
require_once('common/language/BaseLanguage.class.php');

abstract class Tracker_FormElement_Field_File_BaseTest extends TuleapTestCase {
    protected $fixture_dir;
    protected $attachment_dir;
    protected $thumbnails_dir;
    protected $tmp_name;
    protected $another_tmp_name;
    protected $file_info_factory;

    public function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();
        ForgeConfig::store();
        $this->fixture_dir    = '/var/tmp'.'/_fixtures';
        if(!is_dir($this->fixture_dir)) {
            mkdir($this->fixture_dir);
        }

        $this->attachment_dir = $this->fixture_dir.'/attachments';
        if(!is_dir($this->attachment_dir)) {
            mkdir($this->attachment_dir);
        }

        $this->thumbnails_dir = $this->attachment_dir.'/thumbnails';
        if(!is_dir($this->thumbnails_dir)) {
            mkdir($this->thumbnails_dir);
        }
 
        $this->tmp_name         = $this->fixture_dir.'/uploaded_file.txt';
        $this->another_tmp_name = $this->fixture_dir.'/another_uploaded_file.txt';

        $this->file_info_factory = \Mockery::spy(\Tracker_FileInfoFactory::class);

        ForgeConfig::set('sys_http_user', 'user');

        $backend = \Mockery::spy(\Backend::class);
        Backend::setInstance('Backend', $backend);
    }

    public function tearDown() {
        foreach(glob($this->thumbnails_dir.'/*') as $f) {
            if ($f != '.' && $f != '..') {
                unlink($f);
            }
        }
        rmdir($this->thumbnails_dir);
        ForgeConfig::restore();
        Backend::clearInstances();

        parent::tearDown();
    }
    
}

class Tracker_FormElement_Field_FileTest extends Tracker_FormElement_Field_File_BaseTest {
    function testGetChangesetValue()
    {
        $value_dao = \Mockery::spy(\Tracker_FormElement_Field_Value_FileDao::class);

        stub($value_dao)->searchById()->returnsDarFromArray(
            [
                array('changesetvalue_id' => 123, 'fileinfo_id' => 101),
                array('changesetvalue_id' => 123, 'fileinfo_id' => 102),
                array('changesetvalue_id' => 123, 'fileinfo_id' => 103),
            ]
        );

        $tracker_file_info_factory = \Mockery::spy(\Tracker_FileInfoFactory::class);
        stub($tracker_file_info_factory)->getById()->returns(\Mockery::spy(\Tracker_FileInfo::class));
        
        $file_field = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $file_field->shouldReceive('getValueDao')->andReturns($value_dao);
        $file_field->shouldReceive('getTrackerFileInfoFactory')->andReturns($tracker_file_info_factory);
        
        $changeset_value = $file_field->getChangesetValue(\Mockery::spy(\Tracker_Artifact_Changeset::class), 123, false);
        $this->assertIsA($changeset_value, 'Tracker_Artifact_ChangesetValue_File');
        $this->assertEqual(count($changeset_value->getFiles()), 3);
    }
    
    function testGetChangesetValue_doesnt_exist() {
        $value_dao = \Mockery::spy(\Tracker_FormElement_Field_Value_FileDao::class);
        $dar = Mockery::spy(DataAccessResult::class);
        $dar->shouldReceive('getRow')->andReturn(false);
        $value_dao->shouldReceive('searchById')->andReturn($dar);
        
        $file_field = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $file_field->shouldReceive('getValueDao')->andReturn($value_dao);
        $file_field->shouldReceive('getTrackerFileInfoFactory')->andReturn(Mockery::spy(Tracker_FileInfoFactory::class));

        $changeset_value = $file_field->getChangesetValue(\Mockery::spy(\Tracker_Artifact_Changeset::class), 123, false);
        $this->assertIsA($changeset_value, 'Tracker_Artifact_ChangesetValue_File');
        $this->assertEqual(count($changeset_value->getFiles()), 0);
    }
    
    function testSoapAvailableValues() {
        $f = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->assertNull($f->getSoapAvailableValues());
    }

    function test_augmentDataFromRequest_null() {
        $f = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $f->shouldReceive('getSubmittedInfoFromFILES')->andReturns(null);
        $f->shouldReceive('getId')->andReturns(66);
        
        $fields_data = array(
            '102' => '123'
        );
        $this->assertNull($f->augmentDataFromRequest($fields_data));
        $this->assertIdentical($fields_data[66], array());
    }
    
    function test_augmentDataFromRequest_emptyarray() {
        $f = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $f->shouldReceive('getSubmittedInfoFromFILES')->andReturns(array());
        $f->shouldReceive('getId')->andReturns(66);
        
        $fields_data = array(
            '102' => '123'
        );
        $this->assertNull($f->augmentDataFromRequest($fields_data));
        $this->assertIdentical($fields_data[66], array());
    }
    
    function test_augmentDataFromRequest_one_file_belonging_to_field() {
        $f = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $f->shouldReceive('getSubmittedInfoFromFILES')->andReturns(array(
            'name' => array(
                66 => array(
                    0 => array('file' => 'toto.gif'),
                ),
            ),
            'type' => array(
                66 => array(
                    0 => array('file' => 'image/gif'),
                ),
            ),
            'error' => array(
                66 => array(
                    0 => array('file' => 0),
                ),
            ),
            'tmp_name' => array(
                66 => array(
                    0 => array('file' => 'dtgjio'),
                ),
            ),
        ));
        $f->shouldReceive('getId')->andReturns(66);
        
        $fields_data = array(
            '102' => '123'
        );
        $this->assertNull($f->augmentDataFromRequest($fields_data));
        $this->assertIdentical($fields_data[66], array(
            0 => array(
                'name' => 'toto.gif',
                'type' => 'image/gif',
                'error' => 0,
                'tmp_name' => 'dtgjio',
            ),
        ));
    }
    
    function test_augmentDataFromRequest_two_files_belonging_to_field() {
        $f = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $f->shouldReceive('getSubmittedInfoFromFILES')->andReturns(array(
            'name' => array(
                66 => array(
                    0 => array('file' => 'toto.gif'),
                    1 => array('file' => 'Spec.doc'),
                ),
            ),
            'type' => array(
                66 => array(
                    0 => array('file' => 'image/gif'),
                    1 => array('file' => 'application/word'),
                ),
            ),
            'error' => array(
                66 => array(
                    0 => array('file' => 0),
                    1 => array('file' => 1),
                ),
            ),
            'tmp_name' => array(
                66 => array(
                    0 => array('file' => 'dtgjio'),
                    1 => array('file' => ''),
                ),
            ),
        ));
        $f->shouldReceive('getId')->andReturns(66);
        
        $fields_data = array(
            '102' => '123'
        );
        $this->assertNull($f->augmentDataFromRequest($fields_data));
        $this->assertIdentical($fields_data[66], array(
            0 => array(
                'name' => 'toto.gif',
                'type' => 'image/gif',
                'error' => 0,
                'tmp_name' => 'dtgjio',
            ),
            1 => array(
                'name' => 'Spec.doc',
                'type' => 'application/word',
                'error' => 1,
                'tmp_name' => '',
            ),
        ));
    }
    
    function test_augmentDataFromRequest_two_files_belonging_to_field_and_one_file_not() {
        $f = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $f->shouldReceive('getSubmittedInfoFromFILES')->andReturns(array(
            'name' => array(
                66 => array(
                    0 => array('file' => 'toto.gif'),
                    1 => array('file' => 'Spec.doc'),
                ),
                111 => array(
                    0 => array('file' => 'Screenshot.png'),
                ),
            ),
            'type' => array(
                66 => array(
                    0 => array('file' => 'image/gif'),
                    1 => array('file' => 'application/word'),
                ),
                111 => array(
                    0 => array('file' => 'image/png'),
                ),
            ),
            'error' => array(
                66 => array(
                    0 => array('file' => 0),
                    1 => array('file' => 1),
                ),
                111 => array(
                    0 => array('file' => 0),
                ),
            ),
            'tmp_name' => array(
                66 => array(
                    0 => array('file' => 'dtgjio'),
                    1 => array('file' => ''),
                ),
                111 => array(
                    0 => array('file' => 'aoeeg'),
                ),
            ),
        ));
        $f->shouldReceive('getId')->andReturns(66);
        
        $fields_data = array(
            '102' => '123'
        );
        $this->assertNull($f->augmentDataFromRequest($fields_data));
        $this->assertIdentical($fields_data[66], array(
            0 => array(
                'name' => 'toto.gif',
                'type' => 'image/gif',
                'error' => 0,
                'tmp_name' => 'dtgjio',
            ),
            1 => array(
                'name' => 'Spec.doc',
                'type' => 'application/word',
                'error' => 1,
                'tmp_name' => '',
            ),
        ));
        $this->assertFalse(isset($fields_data[111]));
    }
    
    function test_augmentDataFromRequest_one_file_does_not_belong_to_field() {
        $f = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $f->shouldReceive('getSubmittedInfoFromFILES')->andReturns(array(
            'name' => array(
                111 => array(
                    0 => array('file' => 'toto.gif'),
                ),
            ),
            'type' => array(
                111 => array(
                    0 => array('file' => 'image/gif'),
                ),
            ),
            'error' => array(
                111 => array(
                    0 => array('file' => 0),
                ),
            ),
            'tmp_name' => array(
                111 => array(
                    0 => array('file' => 'dtgjio'),
                ),
            ),
        ));
        $f->shouldReceive('getId')->andReturns(66);
        
        $fields_data = array(
            '102' => '123'
        );
        $this->assertNull($f->augmentDataFromRequest($fields_data));
        $this->assertIdentical($fields_data[66], array());
    }
    
    function test_augmentDataFromRequest_dont_override_description() {
        $f = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $f->shouldReceive('getSubmittedInfoFromFILES')->andReturns(array(
            'name' => array(
                66 => array(
                    0 => array('file' => 'toto.gif'),
                ),
            ),
            'type' => array(
                66 => array(
                    0 => array('file' => 'image/gif'),
                ),
            ),
            'error' => array(
                66 => array(
                    0 => array('file' => 0),
                ),
            ),
            'tmp_name' => array(
                66 => array(
                    0 => array('file' => 'dtgjio'),
                ),
            ),
        ));
        $f->shouldReceive('getId')->andReturns(66);
        
        $fields_data = array(
            '102' => '123',
            '66'  => array(
                '0' => array(
                    'description' => 'The description of the file',
                ),
            ),
        );
        $this->assertNull($f->augmentDataFromRequest($fields_data));
        $this->assertEqual($fields_data[66][0]['description'], 'The description of the file');
    }

    function test_isValid_not_filled() {
        $artifact = \Mockery::spy(\Tracker_Artifact::class);
        $value = array(
            array(
                'description' =>  '',
                'name'        =>  '',
                'type'        =>  '',
                'tmp_name'    =>  '',
                'error'       =>  UPLOAD_ERR_NO_FILE,
                'size'        =>  0,
            )
        );
        
        $required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $required_file->shouldReceive('isRequired')->andReturns(true);
        $required_file->shouldReceive('isPreviousChangesetEmpty')->andReturns(true);
        $this->assertFalse($required_file->isValidRegardingRequiredProperty($artifact, $value));
        
        $not_required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $not_required_file->shouldReceive('isRequired')->andReturns(false);
        $this->assertTrue($not_required_file->isValidRegardingRequiredProperty($artifact, $value));
    }
    
    function test_isValid_two_not_filled() {
        $artifact = \Mockery::spy(\Tracker_Artifact::class);
        $value = array(
            array(
                'description' =>  '',
                'name'        =>  '',
                'type'        =>  '',
                'tmp_name'    =>  '',
                'error'       =>  UPLOAD_ERR_NO_FILE,
                'size'        =>  0,
            ),
            array(
                'description' =>  '',
                'name'        =>  '',
                'type'        =>  '',
                'tmp_name'    =>  '',
                'error'       =>  UPLOAD_ERR_NO_FILE,
                'size'        =>  0,
            )
        );
        
        $required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $required_file->shouldReceive('isRequired')->andReturns(true);
        $required_file->shouldReceive('isPreviousChangesetEmpty')->andReturns(true);
        $this->assertFalse($required_file->isValidRegardingRequiredProperty($artifact, $value));
        
        $not_required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $not_required_file->shouldReceive('isRequired')->andReturns(false);
        $this->assertTrue($not_required_file->isValidRegardingRequiredProperty($artifact, $value));
    }
    
    function test_isValid_only_description_filled() {
        $artifact = \Mockery::spy(\Tracker_Artifact::class);
        $value = array(
            array(
                'description' =>  'User sets the description but the file is not submitted (missing, ... ?)',
                'name'        =>  '',
                'type'        =>  '',
                'tmp_name'    =>  '',
                'error'       =>  UPLOAD_ERR_NO_FILE,
                'size'        =>  0,
            )
        );
        
        $required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $required_file->shouldReceive('isRequired')->andReturns(true);
        $this->assertFalse($required_file->isValid($artifact, $value));
        
        $not_required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $not_required_file->shouldReceive('isRequired')->andReturns(false);
        $this->assertFalse($not_required_file->isValid($artifact, $value));
    }
    
    function test_isValid_description_filled_but_error_with_file_upload() {
        $artifact = \Mockery::spy(\Tracker_Artifact::class);
        $value = array(
            array(
                'description' =>  'User sets the description but the file has error (network, ... ?)',
                'name'        =>  '',
                'type'        =>  '',
                'tmp_name'    =>  '',
                'error'       =>  UPLOAD_ERR_PARTIAL,
                'size'        =>  0,
            )
        );
        
        $required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $required_file->shouldReceive('isRequired')->andReturns(true);
        $this->assertFalse($required_file->isValid($artifact, $value));
        
        $not_required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $not_required_file->shouldReceive('isRequired')->andReturns(false);
        $this->assertFalse($not_required_file->isValid($artifact, $value));
    }
    
    function test_isValid_description_filled_and_file_ok() {
        $artifact = \Mockery::spy(\Tracker_Artifact::class);
        $value = array(
            array(
                'description' =>  "Capture d'ecran",
                'name'        =>  'Screenshot.png',
                'type'        =>  'image/png',
                'tmp_name'    =>  $this->tmp_name,
                'error'       =>  UPLOAD_ERR_OK,
                'size'        =>  0,
            )
        );
        
        $required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $required_file->shouldReceive('isRequired')->andReturns(true);
        $this->assertTrue($required_file->isValid($artifact, $value));
        
        $not_required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $not_required_file->shouldReceive('isRequired')->andReturns(false);
        $this->assertTrue($not_required_file->isValid($artifact, $value));
    }

    function itIsValidWhenFieldIsRequiredButHasAFileFromPreviousChangeset() {
        $artifact = \Mockery::spy(\Tracker_Artifact::class);
        $value = array(
            array(
                'description' =>  "Capture d'ecran",
                'name'        =>  'Screenshot.png',
                'type'        =>  'image/png',
                'tmp_name'    =>  $this->tmp_name,
                'error'       =>  UPLOAD_ERR_OK,
                'size'        =>  0,
            )
        );

        $required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $required_file->shouldReceive('isRequired')->andReturns(true);
        $required_file->shouldReceive('isPreviousChangesetEmpty')->andReturns(false);
        $this->assertTrue($required_file->isValid($artifact, $value));
    }

    function test_isValid_two_files_ok() {
        $artifact = \Mockery::spy(\Tracker_Artifact::class);
        $value = array(
            array(
                'description' =>  "Capture d'ecran",
                'name'        =>  'Screenshot.png',
                'type'        =>  'image/png',
                'tmp_name'    =>  $this->tmp_name,
                'error'       =>  UPLOAD_ERR_OK,
                'size'        =>  123,
            ),
            array(
                'description' =>  'Specs',
                'name'        =>  'Specifications.doc',
                'type'        =>  'application/msword',
                'tmp_name'    =>  $this->another_tmp_name,
                'error'       =>  UPLOAD_ERR_OK,
                'size'        =>  456,
            )
        );
        
        $required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $required_file->shouldReceive('isRequired')->andReturns(true);
        $this->assertTrue($required_file->isValid($artifact, $value));
        
        $not_required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $not_required_file->shouldReceive('isRequired')->andReturns(false);
        $this->assertTrue($not_required_file->isValid($artifact, $value));
    }
    
    function test_isValid_one_file_ok_among_two() {
        $artifact = \Mockery::spy(\Tracker_Artifact::class);
        $value = array(
            array(
                'description' =>  "Capture d'ecran",
                'name'        =>  'Screenshot.png',
                'type'        =>  'image/png',
                'tmp_name'    =>  $this->tmp_name,
                'error'       =>  UPLOAD_ERR_OK,
                'size'        =>  123,
            ),
            array(
                'description' =>  'Specs',
                'name'        =>  'Specifications.doc',
                'type'        =>  'application/msword',
                'tmp_name'    =>  $this->another_tmp_name,
                'error'       =>  UPLOAD_ERR_PARTIAL,
                'size'        =>  0,
            )
        );
        
        $required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $required_file->shouldReceive('isRequired')->andReturns(true);
        $this->assertFalse($required_file->isValid($artifact, $value));
        
        $not_required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $not_required_file->shouldReceive('isRequired')->andReturns(false);
        $this->assertFalse($not_required_file->isValid($artifact, $value));
    }
    
    function test_isValid_one_file_ok_and_one_empty() {
        $artifact = \Mockery::spy(\Tracker_Artifact::class);
        $value = array(
            array(
                'description' =>  "Capture d'ecran",
                'name'        =>  'Screenshot.png',
                'type'        =>  'image/png',
                'tmp_name'    =>  $this->tmp_name,
                'error'       =>  UPLOAD_ERR_OK,
                'size'        =>  123,
            ),
            array(
                'description' =>  '',
                'name'        =>  '',
                'type'        =>  '',
                'tmp_name'    =>  '',
                'error'       =>  UPLOAD_ERR_NO_FILE,
                'size'        =>  0,
            )
        );
        
        $required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $required_file->shouldReceive('isRequired')->andReturns(true);
        $this->assertTrue($required_file->isValid($artifact, $value));
        
        $not_required_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $not_required_file->shouldReceive('isRequired')->andReturns(false);
        $this->assertTrue($not_required_file->isValid($artifact, $value));
    }
    
    function testGetRootPath() {
        ForgeConfig::set('sys_data_dir', dirname(__FILE__) .'/data');
        $f = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $f->shouldReceive('getId')->andReturns(123);
        $this->assertEqual($f->getRootPath(), ForgeConfig::get('sys_data_dir') .'/tracker/123');
    }

    public function itReturnsTrueWhenTheFieldIsEmptyAtFieldUpdateAndHasAnEmptyPreviousChangeset(){
        $formelement_field_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $submitted_value = array(
            array(
                'description' =>  '',
                'name'        =>  '',
                'type'        =>  '',
                'tmp_name'    =>  '',
                'error'       =>  UPLOAD_ERR_NO_FILE,
                'size'        =>  0,
            )
        );

        $artifact = Mockery::spy(Tracker_Artifact::class);

        $formelement_field_file->shouldReceive('checkThatAtLeastOneFileIsUploaded')->andReturn(false);
        $formelement_field_file->shouldReceive('isPreviousChangesetEmpty')->andReturn(true);

        $this->assertTrue($formelement_field_file->isEmpty($submitted_value, $artifact));
    }

    public function itReturnsFalseWhenTheFieldIsEmptyAtFieldUpdateAndHasAPreviousChangeset(){
        $formelement_field_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $submitted_value = array(
            array(
                'description' =>  '',
                'name'        =>  '',
                'type'        =>  '',
                'tmp_name'    =>  '',
                'error'       =>  UPLOAD_ERR_NO_FILE,
                'size'        =>  0,
            )
        );

        $file = new Tracker_FileInfo(123, '*', '*', 'Description 123', 'file123.txt', 123, 'text/xml');
        $changesets = \Mockery::spy(\Tracker_Artifact_ChangesetValue_File::class);
        stub($changesets)->getFiles()->returns(array($file));
        $artifact = \Mockery::spy(\Tracker_Artifact::class);
        stub($artifact)->getLastChangeset()->returns($changesets);

        $formelement_field_file->shouldReceive('checkThatAtLeastOneFileIsUploaded')->andReturns(false);
        $formelement_field_file->shouldReceive('isPreviousChangesetEmpty')->andReturns(false);

        $this->assertFalse($formelement_field_file->isEmpty($submitted_value, $artifact));
    }

    public function itReturnsTrueWhenTheFieldIsEmptyAtFieldUpdateAndHasAPreviousChangesetWhichIsDeleted(){
        $formelement_field_file = \Mockery::mock(\Tracker_FormElement_Field_File::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $submitted_value = array(
            'delete' => array(123),
            array(
                'description' =>  '',
                'name'        =>  '',
                'type'        =>  '',
                'tmp_name'    =>  '',
                'error'       =>  UPLOAD_ERR_NO_FILE,
                'size'        =>  0,
            )
        );

        $file = new Tracker_FileInfo(123, '*', '*', 'Description 123', 'file123.txt', 123, 'text/xml');
        $changesets = \Mockery::spy(\Tracker_Artifact_ChangesetValue_File::class);
        stub($changesets)->getFiles()->returns(array($file));
        $artifact = \Mockery::spy(\Tracker_Artifact::class);
        stub($artifact)->getLastChangeset()->returns($changesets);

        $formelement_field_file->shouldReceive('checkThatAtLeastOneFileIsUploaded')->andReturns(false);
        $formelement_field_file->shouldReceive('isPreviousChangesetEmpty')->andReturns(true);

        $this->assertTrue($formelement_field_file->isEmpty($submitted_value, $artifact));
    }
}


abstract class Tracker_FormElement_Field_File_TemporaryFileTest extends Tracker_FormElement_Field_File_BaseTest {
    /** @var PFUser */
    protected $current_user;
    protected $tmp_dir;

    public function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();
        $this->tmp_dir = '/var/tmp'.'/_fixtures/tmp';
        ForgeConfig::set('codendi_cache_dir', $this->tmp_dir);
        if (! is_dir($this->tmp_dir)) {
            mkdir($this->tmp_dir);
        }
        
        $this->current_user = aUser()->withId(123)->build();
        $user_manager = mockery_stub(\UserManager::class)->getCurrentUser()->returns($this->current_user);
        UserManager::setInstance($user_manager);
    }

    public function tearDown() {
        $this->recurseDeleteInDir($this->tmp_dir);
        rmdir($this->tmp_dir);
        clearstatcache();
        UserManager::clearInstance();
        parent::tearDown();
    }
}

class Tracker_FormElement_Field_File_FileSystemPersistanceTest  extends Tracker_FormElement_Field_File {
    public function __construct($id) {
        $tracker_id = $parent_id = $name = $label = $description = $use_it = $scope = $required = $notifications = $rank = null;
        parent::__construct($id, $tracker_id, $parent_id, $name, $label, $description, $use_it, $scope, $required, $notifications, $rank);
    }
    public function createAttachment(Tracker_FileInfo $attachment, $file_info) {
        return parent::createAttachment($attachment, $file_info);
    }
}

class Tracker_FormElement_Field_File_PersistDataTest extends Tracker_FormElement_Field_File_TemporaryFileTest {
    /** @var Tracker_FormElement_Field_File_FileSystemPersistanceTest */
    private $field;

    private $storage_dir;

    public function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();
        $this->storage_dir = $this->fixture_dir.'/storage';
        if(!is_dir($this->storage_dir)) {
            mkdir($this->storage_dir);
        }

        ForgeConfig::set('sys_data_dir', $this->storage_dir);
        $this->field_id = 987;
        $this->field    = \Mockery::mock(
            \Tracker_FormElement_Field_File_FileSystemPersistanceTest::class,
            [$this->field_id]
        )
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        stub($this->field)->getTemporaryFileManagerDao()->returns(\Mockery::spy(\Tracker_Artifact_Attachment_TemporaryFileManagerDao::class));
        stub($this->field)->getFileInfoFactory()->returns(\Mockery::spy(\Tracker_FileInfoFactory::class));

        $this->attachment_id = 654;
        $this->attachment = \Mockery::mock(
            \Tracker_FileInfo::class,
            [$this->attachment_id, $this->field, null, null, null, null, null]
        )
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        stub($this->attachment)->save()->returns(true);
    }

    public function tearDown() {
        $this->recurseDeleteInDir($this->storage_dir);
        rmdir($this->storage_dir);
        parent::tearDown();
    }

    public function itCreatesAFileWhenItComesFromAsSoapRequest() {
        $file_id        = 'coucou123';
        $temp_file = new Tracker_SOAP_TemporaryFile($this->current_user, $file_id);
        $temp_file_path = $temp_file->getPath();

        $file_info = array(
            'tmp_name' => $temp_file_path,
            'id'       => $file_id,
        );

        touch($temp_file_path);

        expect($this->attachment)->delete()->never();
        expect($this->attachment)->postUploadActions()->once();

        $this->assertTrue($this->field->createAttachment($this->attachment, $file_info));
        $this->assertTrue(file_exists($this->field->getRootPath().'/'.$this->attachment_id));
        $this->assertFalse(file_exists($temp_file_path));
    }

    public function itDoesntAcceptToMoveAFileThatIsNotAValidSoapTemporaryFile() {
        $file_id       = 'coucou123';
        $file_tmp_path = '/etc/passwd';
        $file_info     = array(
            'tmp_name' => $file_tmp_path,
            'id'  => $file_id,
        );

        expect($this->attachment)->delete()->once();

        $this->assertFalse($this->field->createAttachment($this->attachment, $file_info));
        $this->assertFalse(file_exists($this->field->getRootPath().'/'.$this->attachment_id));
    }
}



class Tracker_FormElement_Field_File_GenerateFakeSoapDataTest extends Tracker_FormElement_Field_File_TemporaryFileTest {
    /** @var Tracker_FormElement_Field_File */
    private $field;

    public function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();
        $this->field = \Mockery::mock(\Tracker_FormElement_Field_File_FileSystemPersistanceTest::class)->makePartial()->shouldAllowMockingProtectedMethods();

        stub($this->field)->getTemporaryFileManagerDao()->returns(\Mockery::spy(\Tracker_Artifact_Attachment_TemporaryFileManagerDao::class));
        stub($this->field)->getFileInfoFactory()->returns(\Mockery::spy(\Tracker_FileInfoFactory::class));
    }

    private function createFakeSoapFileRequest($id, $description, $filename, $filesize, $filetype, $action = null) {
        $soap_file = new stdClass();
        $soap_file->id           = $id;
        $soap_file->submitted_by = 0;
        $soap_file->description  = $description;
        $soap_file->filename     = $filename;
        $soap_file->filesize     = $filesize;
        $soap_file->filetype     = $filetype;
        if ($action) {
            $soap_file->action = $action;
        }
        return $soap_file;
    }

    private function createFakeSoapFieldValue() {
        $field_value = new stdClass();
        $field_value->file_info = func_get_args();
        return $field_value;
    }

    public function itRaisesAnErrorWhenThereIsNoData() {
        $this->expectException();
        $this->field->getFieldData(null);
    }

    public function itRaisesAnErrorWhenTryToSubmitAnArray() {
        $this->expectException();
        $this->field->getFieldData(array());
    }

    public function itRaisesAnErrorWhenTryToSubmitAnStringValue() {
        $this->expectException();
        $this->field->getFieldData('bla');
    }

    public function itRaisesAnErrorWhenTryToSubmitStdClassWithValue() {
        $this->expectException();
        $field_value = new stdClass();
        $field_value->value = 'bla';
        $this->field->getFieldData($field_value);
    }

    public function itRaisesAnErrorWhenTryToSubmitFileInfoThatIsNotAnArray() {
        $this->expectException();
        $field_value = new stdClass();
        $field_value->file_info = 'bla';
        $this->field->getFieldData($field_value);
    }

    public function itRaisesAnErrorWhenTryToSubmitFileInfoThatIsNotAnArrayOfFileValue() {
        $this->expectException();
        $field_value = $this->createFakeSoapFieldValue('bla');
        $this->field->getFieldData($field_value);
    }

    public function itRaisesAnErrorWhenTryToSubmitFileInfoHasNotTheRequiredFields() {
        $this->expectException();
        $field_value = $this->createFakeSoapFieldValue(new stdClass());
        $this->field->getFieldData($field_value);
    }

    public function itRaisesAnErrorWhenNoFileGiven() {
        $this->expectException();

        $description = "Purchase Order";
        $filename    = 'my_file.ods';
        $filesize    = 1234;
        $filetype    = 'application/vnd.oasis.opendocument.spreadsheet';

        $field_value = $this->createFakeSoapFieldValue(
            $this->createFakeSoapFileRequest(null, $description, $filename, $filesize, $filetype)
        );

        $this->field->getFieldData($field_value);
    }

    public function itRaisesAnErrorWhenFileDoesNotExist() {
        $this->expectException();

        $description = "Purchase Order";
        $filename    = 'my_file.ods';
        $filesize    = 1234;
        $filetype    = 'application/vnd.oasis.opendocument.spreadsheet';

        $field_value = $this->createFakeSoapFieldValue(
            $this->createFakeSoapFileRequest(123, $description, $filename, $filesize, $filetype)
        );

        $this->field->getFieldData($field_value);
    }

    public function itDoesNothingWhenArrayIsEmpty() {
        $field_value = $this->createFakeSoapFieldValue();
        $this->assertEqual(array(), $this->field->getFieldData($field_value));
    }

    public function itConvertsOneFile() {
        $description = "Purchase Order";
        $filename    = 'my_file.ods';
        $filesize    = 1234;
        $filetype    = 'application/vnd.oasis.opendocument.spreadsheet';
        $file_id     = 'coucou123';

        $field_value = $this->createFakeSoapFieldValue(
            $this->createFakeSoapFileRequest($file_id, $description, $filename, $filesize, $filetype)
        );

        $temp_file = new Tracker_SOAP_TemporaryFile($this->current_user, $file_id);
        $temp_file_path = $temp_file->getPath();
        touch($temp_file_path);

        $field = aFileField()->build();

        $this->assertEqual(
            $field->getFieldData($field_value),
            array(
                array(
                    'id'          =>  $file_id,
                    'description' =>  $description,
                    'name'        =>  $filename,
                    'type'        =>  $filetype,
                    'tmp_name'    =>  $temp_file_path,
                    'error'       =>  UPLOAD_ERR_OK,
                    'size'        =>  $filesize,
                )
           )
        );
    }

    public function itConvertsTwoFiles() {
        $description1 = "Purchase Order";
        $filename1    = 'my_file.ods';
        $filesize1    = 1234;
        $filetype1    = 'application/vnd.oasis.opendocument.spreadsheet';
        $file_id1     = 'sdfsdfaz';
        $temp_file1      = new Tracker_SOAP_TemporaryFile($this->current_user, $file_id1);
        $temp_file_path1 = $temp_file1->getPath();
        touch($temp_file_path1);

        $description2 = "Capture d'écran";
        $filename2    = 'stuff.png';
        $filesize2    = 5698;
        $filetype2    = 'image/png';
        $file_id2     = 'sdfsdfaz';
        $temp_file2      = new Tracker_SOAP_TemporaryFile($this->current_user, $file_id2);
        $temp_file_path2 = $temp_file2->getPath();
        touch($temp_file_path2);

        $field_value = $this->createFakeSoapFieldValue(
            $this->createFakeSoapFileRequest($file_id1, $description1, $filename1, $filesize1, $filetype1),
            $this->createFakeSoapFileRequest($file_id2, $description2, $filename2, $filesize2, $filetype2)
        );

        $this->assertEqual(
            $this->field->getFieldData($field_value),
            array(
                array(
                    'id'          =>  $file_id1,
                    'description' =>  $description1,
                    'name'        =>  $filename1,
                    'type'        =>  $filetype1,
                    'tmp_name'    =>  $temp_file_path1,
                    'error'       =>  UPLOAD_ERR_OK,
                    'size'        =>  $filesize1,
                ),
                array(
                    'id'          =>  $file_id2,
                    'description' =>  $description2,
                    'name'        =>  $filename2,
                    'type'        =>  $filetype2,
                    'tmp_name'    =>  $temp_file_path2,
                    'error'       =>  UPLOAD_ERR_OK,
                    'size'        =>  $filesize2,
                )
           )
        );
    }

    public function itConvertsForDeletionOfOneFile() {
        $file_id = 678;

        $soap_value = $this->createFakeSoapFieldValue(
            $this->createFakeSoapFileRequest($file_id, '', '', '', '', 'delete')
        );
        $this->assertEqual(
            $this->field->getFieldData($soap_value),
            array(
                'delete' => array($file_id)
            )
        );
    }

    public function itConvertsForDeletionOfTwoFiles() {
        $file_id1 = 678;
        $file_id2 = 12;

        $soap_value = $this->createFakeSoapFieldValue(
            $this->createFakeSoapFileRequest($file_id1, '', '', '', '', 'delete'),
            $this->createFakeSoapFileRequest($file_id2, '', '', '', '', 'delete')
        );
        $this->assertEqual(
            $this->field->getFieldData($soap_value),
            array(
                'delete' => array($file_id1, $file_id2)
            )
        );
    }

    public function itCreatesAndDeleteInTheSameTime() {
        $description1 = "Purchase Order";
        $filename1    = 'my_file.ods';
        $filesize1    = 1234;
        $filetype1    = 'application/vnd.oasis.opendocument.spreadsheet';
        $file_id1     = 'sdfsdfaz';
        $temp_file1      =  new Tracker_SOAP_TemporaryFile($this->current_user, $file_id1);
        $temp_file_path1 = $temp_file1->getPath();
        touch($temp_file_path1);

        $file_id2 = 12;

        $field_value = $this->createFakeSoapFieldValue(
            $this->createFakeSoapFileRequest($file_id1, $description1, $filename1, $filesize1, $filetype1),
            $this->createFakeSoapFileRequest($file_id2, '', '', '', '', 'delete')
        );

        $this->assertEqual(
            $this->field->getFieldData($field_value),
            array(
                'delete' => array($file_id2),
                array(
                    'id'          =>  $file_id1,
                    'description' =>  $description1,
                    'name'        =>  $filename1,
                    'type'        =>  $filetype1,
                    'tmp_name'    =>  $temp_file_path1,
                    'error'       =>  UPLOAD_ERR_OK,
                    'size'        =>  $filesize1,
                ),
           )
        );
    }
}

class Tracker_FormElement_Field_File_RESTTests extends TuleapTestCase {

    public function itThrowsAnExceptionWhenReturningValueIndexedByFieldName() {
        $field = new Tracker_FormElement_Field_File(
            1,
            101,
            null,
            'field_file',
            'Field File',
            '',
            1,
            'P',
            true,
            '',
            1
        );

        $this->expectException('Tracker_FormElement_RESTValueByField_NotImplementedException');

        $value = 'some_value';

        $field->getFieldDataFromRESTValueByField($value);
    }
}