<?php
/**
 * Copyright (c) Enalean, 2015 - 2018. All Rights Reserved.
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
require_once('common/dao/include/DataAccessResult.class.php');
require_once('common/user/User.class.php');
require_once('common/include/Response.class.php');
require_once('common/language/BaseLanguage.class.php');
require_once('common/reference/ReferenceManager.class.php');
require_once('common/user/UserManager.class.php');

class MockWorkflow_Tracker_ArtifactTest_WorkflowNoPermsOnPostActionFields extends Workflow {
    function before(&$fields_data, $submitter, $artifact) {
        $fields_data[102] = '456';
        return parent::before($fields_data, $submitter, $artifact);
    }
}

class Tracker_ArtifactTest extends TuleapTestCase {

    function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();
        $this->response = $GLOBALS['Response'];
        $this->language = $GLOBALS['Language'];

        $this->setText('fields not valid', array('plugin_tracker_artifact', 'fields_not_valid'));

        $tracker     = \Mockery::spy(\Tracker::class);
        $factory     = \Mockery::spy(\Tracker_FormElementFactory::class);
        $this->field = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->field->shouldReceive('getId')->andReturns(101);
        $this->field->shouldReceive('getLabel')->andReturns('Summary');
        $this->field->shouldReceive('getName')->andReturns('summary');
        $factory->shouldReceive('getUsedFields')->andReturns(array($this->field));
        $factory->shouldReceive('getAllFormElementsForTracker')->andReturns(array());

        $this->artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->artifact->shouldReceive('getFormElementFactory')->andReturns($factory);
        $this->artifact->shouldReceive('getTracker')->andReturns($tracker);
        $this->artifact->shouldReceive('getLastChangeset')->andReturns(false); // no changeset => artifact submission

        $workflow = \Mockery::spy(\Workflow::class);
        $workflow->shouldReceive('validate')->andReturns(true);

        $this->artifact->shouldReceive('getWorkflow')->andReturns($workflow);
        $this->artifact_update = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->artifact_update->shouldReceive('getFormElementFactory')->andReturns($factory);
        $this->artifact_update->shouldReceive('getTracker')->andReturns($tracker);
        $this->artifact_update->shouldReceive('getWorkflow')->andReturns($workflow);
        $this->changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $this->changeset_value = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $this->changeset->shouldReceive('getValue')->with($this->field)->andReturns($this->changeset_value);
        $this->artifact_update->shouldReceive('getLastChangeset')->andReturns($this->changeset); // changeset => artifact modification
    }

    function tearDown() {
        unset($this->field);
        unset($this->artifact);
        parent::tearDown();
    }

    function testGetValue() {
        $changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $field     = \Mockery::spy(\Tracker_FormElement_Field_Date::class);
        $value     = \Mockery::spy(\Tracker_Artifact_ChangesetValue_Date::class);

        $changeset->shouldReceive('getValue')->andReturns($value);

        $id = $tracker_id = $use_artifact_permissions = $submitted_by = $submitted_on = '';
        $artifact = new Tracker_Artifact($id, $tracker_id, $submitted_by, $submitted_on, $use_artifact_permissions);

        $this->assertEqual($artifact->getValue($field, $changeset), $value);
    }

    function testGetValue_without_changeset() {
        $changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $field     = \Mockery::spy(\Tracker_FormElement_Field_Date::class);
        $value     = \Mockery::spy(\Tracker_Artifact_ChangesetValue_Date::class);

        $changeset->shouldReceive('getValue')->andReturns($value);

        $artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $artifact->shouldReceive('getLastChangeset')->andReturns($changeset);

        $this->assertEqual($artifact->getValue($field), $value);
    }
}

class Tracker_Artifact_delegatedCreateNewChangesetTest extends Tracker_ArtifactTest {

    function testCreateNewChangesetWithWorkflowAndNoPermsOnPostActionField() {
        $email   = null; //not anonymous user
        $comment = '';

        $comment_dao = \Mockery::spy(\Tracker_Artifact_Changeset_CommentDao::class);
        $comment_dao->shouldReceive('createNewVersion')->once()->andReturn(true);

        $dao = \Mockery::spy(\Tracker_Artifact_ChangesetDao::class);
        $dao->shouldReceive('create')->with(66, 1234, null, $_SERVER['REQUEST_TIME'])->andReturn(1001);
        $dao->shouldReceive('create')->with(66, 1234, null, $_SERVER['REQUEST_TIME'])->andReturn(1002);

        $user = \Mockery::spy(\PFUser::class);
        $user->shouldReceive('getId')->andReturns(1234);
        $user->shouldReceive('isAnonymous')->andReturns(false);

        $tracker = \Mockery::spy(\Tracker::class);
        $tracker->shouldReceive('getGroupId')->andReturns(666);
        $tracker->shouldReceive('getItemName')->andReturns('foobar');
        $tracker->shouldReceive('getFormElements')->andReturns(array());

        $factory = \Mockery::spy(\Tracker_FormElementFactory::class);

        $artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $workflow = \Mockery::spy(\Workflow::class);
        $workflow->shouldReceive('before')->times(2);
        $workflow->shouldReceive('validate')->andReturns(true);
        $artifact->shouldReceive('getWorkflow')->andReturns($workflow);

        $field1  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field1->shouldReceive('getId')->andReturns(101);
        $field1->shouldReceive('isValid')->andReturns(true);
        $field1->shouldReceive('userCanUpdate')->andReturns(true);
        $workflow->shouldReceive('bypassPermissions')->with($field1)->andReturns(false);
        $field1->shouldReceive('saveNewChangeset')->once()->andReturns(true);

        $field2  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field2->shouldReceive('getId')->andReturns(102);
        $field2->shouldReceive('isValid')->andReturns(true);
        $field2->shouldReceive('userCanUpdate')->andReturns(false);
        $workflow->shouldReceive('bypassPermissions')->with($field2)->andReturns(true);
        $field2->shouldReceive('saveNewChangeset')->with(Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), $user, false, true)->once()->andReturns(true);
        $factory->shouldReceive('getUsedFields')->andReturns(array($field1, $field2));
        $factory->shouldReceive('getAllFormElementsForTracker')->andReturns(array());

        $new_changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $new_changeset->shouldReceive('executePostCreationActions')->with([]);

        $changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $changeset->shouldReceive('getValues')->andReturns(array());
        $changeset->shouldReceive('hasChanges')->andReturns(true);
        $changeset_value1 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset->shouldReceive('getValue')->with($field1)->andReturns($changeset_value1);

        $reference_manager = \Mockery::spy(\ReferenceManager::class);
        $reference_manager->shouldReceive('extractCrossRef')->with([
            $comment,
            66,
            'plugin_tracker_artifact',
            666,
            $user->getId(),
            'foobar',
        ]);

        $art_factory = \Mockery::spy(\Tracker_ArtifactFactory::class);
        stub($art_factory)->save()->once()->returns(true);

        $artifact->shouldReceive('getTracker')->andReturns($tracker);
        $artifact->shouldReceive('getId')->andReturns(66);
        $artifact->shouldReceive('getLastChangeset')->andReturns($changeset);

        // Valid
        $fields_data = array(
            101 => '123',
            102 => '456'
        );

        $submitted_on      = $_SERVER['REQUEST_TIME'];
        $send_notification = false;
        $comment_format    = Tracker_Artifact_Changeset_Comment::TEXT_COMMENT;

        $fields_validator = \Mockery::spy(\Tracker_Artifact_Changeset_NewChangesetFieldsValidator::class);
        stub($fields_validator)->validate()->returns(true);

        $creator = new Tracker_Artifact_Changeset_NewChangesetCreator(
            $fields_validator,
            $factory,
            $dao,
            $comment_dao,
            $art_factory,
            \Mockery::spy(\EventManager::class),
            $reference_manager,
            \Mockery::spy(\Tuleap\Tracker\FormElement\Field\ArtifactLink\SourceOfAssociationCollectionBuilder::class)
        );

        $creator->create($artifact, $fields_data, $comment, $user, $submitted_on, $send_notification, $comment_format);
    }

    function testDontCreateNewChangesetIfNoCommentOrNoChanges() {
        $this->language->shouldReceive('getText')->with('plugin_tracker_artifact', 'no_changes', Mockery::any())->andReturns('no changes');
        $this->response->shouldReceive('addFeedback')->never();

        $comment_dao = \Mockery::spy(\Tracker_Artifact_Changeset_CommentDao::class);
        $comment_dao->shouldReceive('createNewVersion')->never();

        $dao = \Mockery::spy(\Tracker_Artifact_ChangesetDao::class);
        $dao->shouldReceive('create')->never();

        $user = \Mockery::spy(\PFUser::class);
        $user->shouldReceive('getId')->andReturns(1234);
        $user->shouldReceive('isAnonymous')->andReturns(false);

        $tracker = \Mockery::spy(\Tracker::class);
        $tracker->shouldReceive('getFormElements')->andReturns(array());
        $factory = \Mockery::spy(\Tracker_FormElementFactory::class);

        $field1  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field1->shouldReceive('getId')->andReturns(101);
        $field1->shouldReceive('isValid')->andReturns(true);
        $field1->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field1->shouldReceive('userCanUpdate')->andReturns(true);
        $field1->shouldReceive('saveNewChangeset')->never();
        $field2  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field2->shouldReceive('getId')->andReturns(102);
        $field2->shouldReceive('isValid')->andReturns(true);
        $field2->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field2->shouldReceive('userCanUpdate')->andReturns(true);
        $field2->shouldReceive('saveNewChangeset')->never();
        $field3  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field3->shouldReceive('getId')->andReturns(103);
        $field3->shouldReceive('isValid')->andReturns(true);
        $field3->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field3->shouldReceive('userCanUpdate')->andReturns(true);
        $field3->shouldReceive('saveNewChangeset')->never();
        $factory->shouldReceive('getUsedFields')->andReturns(array($field1, $field2, $field3));
        $factory->shouldReceive('getAllFormElementsForTracker')->andReturns(array());
        $factory->shouldReceive('getUsedArtifactLinkFields')->andReturns(array());

        $changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $changeset->shouldReceive('hasChanges')->andReturns(false);
        $changeset->shouldReceive('getValues')->andReturns(array());
        $changeset_value1 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset_value2 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset_value3 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset->shouldReceive('getValue')->with($field1)->andReturns($changeset_value1);
        $changeset->shouldReceive('getValue')->with($field2)->andReturns($changeset_value2);
        $changeset->shouldReceive('getValue')->with($field3)->andReturns($changeset_value3);

        $hierarchy_factory = \Mockery::spy(\Tracker_HierarchyFactory::class);
        stub($hierarchy_factory)->getChildren()->returns(array());

        $artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $artifact->shouldReceive('getChangesetDao')->andReturns($dao);
        $artifact->shouldReceive('getChangesetCommentDao')->andReturns($comment_dao);
        $artifact->shouldReceive('getFormElementFactory')->andReturns($factory);
        $artifact->shouldReceive('getArtifactFactory')->andReturns(\Mockery::spy(\Tracker_ArtifactFactory::class));
        $artifact->shouldReceive('getHierarchyFactory')->andReturns($hierarchy_factory);
        $artifact->shouldReceive('getReferenceManager')->andReturns(\Mockery::spy(\ReferenceManager::class));
        $artifact->shouldReceive('getTracker')->andReturns($tracker);
        $artifact->shouldReceive('getId')->andReturns(66);
        $artifact->shouldReceive('getLastChangeset')->andReturns($changeset);

        $workflow = \Mockery::spy(\Workflow::class);
        $workflow->shouldReceive('before')->never();
        $workflow->shouldReceive('validate')->andReturns(true);
        $artifact->shouldReceive('getWorkflow')->andReturns($workflow);

        $email   = null; //not annonymous user
        $comment = ''; //empty comment

        // Valid
        $fields_data = array();
        $this->expectException('Tracker_NoChangeException');
        $artifact->createNewChangeset($fields_data, $comment, $user);
    }
}

class Tracker_Artifact_createNewChangesetTest extends Tracker_ArtifactTest {

    function testCreateNewChangeset() {
        $email   = null; //not annonymous user
        $comment = 'It did solve my problem, I let you close the artifact.';

        $this->response->shouldReceive('addFeedback')->never();

        $comment_dao = \Mockery::spy(\Tracker_Artifact_Changeset_CommentDao::class);
        stub($comment_dao)->createNewVersion()->returns(true);
        $comment_dao->shouldReceive('createNewVersion')->once();

        $dao = \Mockery::spy(\Tracker_Artifact_ChangesetDao::class);
        $dao->shouldReceive('create')->with(66, 1234, null, $_SERVER['REQUEST_TIME'])->andReturn(1001);
        $dao->shouldReceive('create')->with(66, 1234, null, $_SERVER['REQUEST_TIME'])->andReturn(1002);
        $dao->shouldReceive('create')->once();

        $user = \Mockery::spy(\PFUser::class);
        $user->shouldReceive('getId')->andReturns(1234);
        $user->shouldReceive('isAnonymous')->andReturns(false);

        $tracker = \Mockery::spy(\Tracker::class);
        $tracker->shouldReceive('getGroupId')->andReturns(666);
        $tracker->shouldReceive('getItemName')->andReturns('foobar');
        $tracker->shouldReceive('getFormElements')->andReturns(array());

        $factory = \Mockery::spy(\Tracker_FormElementFactory::class);

        $field1  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field1->shouldReceive('getId')->andReturns(101);
        $field1->shouldReceive('isValid')->andReturns(true);
        $field1->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field1->shouldReceive('userCanUpdate')->andReturns(true);
        $field1->shouldReceive('saveNewChangeset')->once()->andReturns(true);
        $field2  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field2->shouldReceive('getId')->andReturns(102);
        $field2->shouldReceive('isValid')->with(Mockery::any(), '123')->andReturns(true);
        $field2->shouldReceive('isValid')->with(Mockery::any(), '456')->andReturns(false);
        $field2->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field2->shouldReceive('userCanUpdate')->andReturns(true);
        $field2->shouldReceive('saveNewChangeset')->once()->andReturns(true);
        $field3  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field3->shouldReceive('getId')->andReturns(103);
        $field3->shouldReceive('isValid')->andReturns(true);
        $field3->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field3->shouldReceive('saveNewChangeset')->once()->andReturns(true);
        $field3->shouldReceive('userCanUpdate')->andReturns(true);
        $factory->shouldReceive('getUsedFields')->andReturns(array($field1, $field2, $field3));
        $factory->shouldReceive('getAllFormElementsForTracker')->andReturns(array());

        $new_changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $new_changeset->shouldReceive('executePostCreationActions')->with([]);

        $changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $changeset->shouldReceive('hasChanges')->andReturns(true);
        $changeset->shouldReceive('getValues')->andReturns(array());
        $changeset_value1 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset_value2 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset_value3 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset->shouldReceive('getValue')->with($field1)->andReturns($changeset_value1);
        $changeset->shouldReceive('getValue')->with($field2)->andReturns($changeset_value2);
        $changeset->shouldReceive('getValue')->with($field3)->andReturns($changeset_value3);

        $reference_manager = \Mockery::spy(\ReferenceManager::class);
        $reference_manager->shouldReceive('extractCrossRef')->with([
            $comment,
            66,
            'plugin_tracker_artifact',
            666,
            $user->getId(),
            'foobar',
        ]);

        $art_factory = \Mockery::spy(\Tracker_ArtifactFactory::class);

        $hierarchy_factory = \Mockery::spy(\Tracker_HierarchyFactory::class);
        stub($hierarchy_factory)->getChildren()->returns(array());

        $artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $artifact->shouldReceive('getChangesetDao')->andReturns($dao);
        $artifact->shouldReceive('getChangesetCommentDao')->andReturns($comment_dao);
        $artifact->shouldReceive('getFormElementFactory')->andReturns($factory);
        $artifact->shouldReceive('getTracker')->andReturns($tracker);
        $artifact->shouldReceive('getId')->andReturns(66);
        $artifact->shouldReceive('getLastChangeset')->andReturns($changeset);
        $artifact->shouldReceive('getChangeset')->andReturns($new_changeset);
        $artifact->shouldReceive('getReferenceManager')->andReturns($reference_manager);
        $artifact->shouldReceive('getArtifactFactory')->andReturns($art_factory);
        $artifact->shouldReceive('getHierarchyFactory')->andReturns($hierarchy_factory);

        stub($GLOBALS['Response'])->getFeedbackErrors()->returns(array());

        stub($art_factory)->save()->returns(true);
        $art_factory->shouldReceive('save')->once();

        $workflow = \Mockery::spy(\Workflow::class);
        $workflow->shouldReceive('before')->times(2);
        $workflow->shouldReceive('validate')->andReturns(true);
        $artifact->shouldReceive('getWorkflow')->andReturns($workflow);

        // Valid
        $fields_data = array(
            102 => '123',
        );

        $artifact->createNewChangeset($fields_data, $comment, $user);

        // Not valid
        $fields_data = array(
            102 => '456',
        );

        $this->expectException('Tracker_Exception');

        $artifact->createNewChangeset($fields_data, $comment, $user);

    }

    public function itCheckThatGlobalRulesAreValid() {
        $email   = null; //not annonymous user
        $comment = 'It did solve my problem, I let you close the artifact.';

        $this->response->shouldReceive('addFeedback')->never();

        $comment_dao = \Mockery::spy(\Tracker_Artifact_Changeset_CommentDao::class);
        $comment_dao->shouldReceive('createNewVersion')->never();

        $dao = \Mockery::spy(\Tracker_Artifact_ChangesetDao::class);
        $dao->shouldReceive('create')->with(66, 1234, null, $_SERVER['REQUEST_TIME'])->andReturn(1001);
        $dao->shouldReceive('create')->with(66, 1234, null, $_SERVER['REQUEST_TIME'])->andReturn(1002);
        $dao->shouldReceive('create')->never();

        $user = \Mockery::spy(\PFUser::class);
        $user->shouldReceive('getId')->andReturns(1234);
        $user->shouldReceive('isAnonymous')->andReturns(false);

        $tracker = \Mockery::spy(\Tracker::class);
        $tracker->shouldReceive('getGroupId')->andReturns(666);
        $tracker->shouldReceive('getItemName')->andReturns('foobar');
        $tracker->shouldReceive('getFormElements')->andReturns(array());

        $factory = \Mockery::spy(\Tracker_FormElementFactory::class);

        $field1  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field1->shouldReceive('getId')->andReturns(101);
        $field1->shouldReceive('isValid')->andReturns(true);
        $field1->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field1->shouldReceive('userCanUpdate')->andReturns(true);
        $field1->shouldReceive('saveNewChangeset')->never();
        $field2  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field2->shouldReceive('getId')->andReturns(102);
        $field2->shouldReceive('isValid')->with(Mockery::any(), '123')->andReturns(true);
        $field2->shouldReceive('isValid')->with(Mockery::any(), '456')->andReturns(false);
        $field2->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field2->shouldReceive('userCanUpdate')->andReturns(true);
        $field2->shouldReceive('saveNewChangeset')->never();
        $field3  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field3->shouldReceive('getId')->andReturns(103);
        $field3->shouldReceive('isValid')->andReturns(true);
        $field3->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field3->shouldReceive('saveNewChangeset')->never();
        $field3->shouldReceive('userCanUpdate')->andReturns(true);
        $factory->shouldReceive('getUsedFields')->andReturns(array($field1, $field2, $field3));
        $factory->shouldReceive('getAllFormElementsForTracker')->andReturns(array());

        $new_changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $new_changeset->shouldReceive('executePostCreationActions')->never();

        $changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $changeset->shouldReceive('hasChanges')->andReturns(true);
        $changeset_value1 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset_value2 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset_value3 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset->shouldReceive('getValue')->with($field1)->andReturns($changeset_value1);
        $changeset->shouldReceive('getValue')->with($field2)->andReturns($changeset_value2);
        $changeset->shouldReceive('getValue')->with($field3)->andReturns($changeset_value3);
        $changeset->shouldReceive('getValues')->andReturns(array());

        $reference_manager = \Mockery::spy(\ReferenceManager::class);
        $reference_manager->shouldReceive('extractCrossRef')->with([
            $comment,
            66,
            'plugin_tracker_artifact',
            666,
            $user->getId(),
            'foobar',
        ]);

        $art_factory = \Mockery::spy(\Tracker_ArtifactFactory::class);

        $hierarchy_factory = \Mockery::spy(\Tracker_HierarchyFactory::class);
        stub($hierarchy_factory)->getChildren()->returns(array());

        $artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $artifact->shouldReceive('getChangesetDao')->andReturns($dao);
        $artifact->shouldReceive('getChangesetCommentDao')->andReturns($comment_dao);
        $artifact->shouldReceive('getFormElementFactory')->andReturns($factory);
        $artifact->shouldReceive('getTracker')->andReturns($tracker);
        $artifact->shouldReceive('getId')->andReturns(66);
        $artifact->shouldReceive('getLastChangeset')->andReturns($changeset);
        $artifact->shouldReceive('getChangeset')->andReturns($new_changeset);
        $artifact->shouldReceive('getReferenceManager')->andReturns($reference_manager);
        $artifact->shouldReceive('getArtifactFactory')->andReturns($art_factory);
        $artifact->shouldReceive('getHierarchyFactory')->andReturns($hierarchy_factory);

        $workflow = Mockery::spy(MockWorkflow_Tracker_ArtifactTest_WorkflowNoPermsOnPostActionFields::class);
        $workflow->shouldReceive('validate')->andReturns(true);
        $artifact->shouldReceive('getWorkflow')->andReturns($workflow);

        $art_factory->shouldReceive('save')->never();

        $email = null; //not annonymous user

        $fields_data = array(
            101 => '123',
        );

        $updated_fields_data_by_workflow = array(
            101 => '123',
            102 => '456'
        );
        stub($workflow)->checkGlobalRules($updated_fields_data_by_workflow, $factory)->once()->throws(new Tracker_Workflow_GlobalRulesViolationException());

        $this->expectException('Tracker_Exception');
        $artifact->createNewChangeset($fields_data, $comment, $user);
    }

    function testCreateNewChangesetWithoutNotification() {
        $email   = null; //not anonymous user
        $comment = '';

        $this->response->shouldReceive('addFeedback')->never();

        $comment_dao = \Mockery::spy(\Tracker_Artifact_Changeset_CommentDao::class);
        stub($comment_dao)->createNewVersion()->returns(true);
        $comment_dao->shouldReceive('createNewVersion')->once();

        $dao = \Mockery::spy(\Tracker_Artifact_ChangesetDao::class);
        $dao->shouldReceive('create')->with(66, 1234, null, $_SERVER['REQUEST_TIME'])->andReturn(1001);
        $dao->shouldReceive('create')->with(66, 1234, null, $_SERVER['REQUEST_TIME'])->andReturn(1002);
        $dao->shouldReceive('create')->once();

        $user = \Mockery::spy(\PFUser::class);
        $user->shouldReceive('getId')->andReturns(1234);
        $user->shouldReceive('isAnonymous')->andReturns(false);

        $tracker = \Mockery::spy(\Tracker::class);
        $tracker->shouldReceive('getGroupId')->andReturns(666);
        $tracker->shouldReceive('getItemName')->andReturns('foobar');
        $tracker->shouldReceive('getFormElements')->andReturns(array());

        $factory = \Mockery::spy(\Tracker_FormElementFactory::class);

        $field1  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field1->shouldReceive('getId')->andReturns(101);
        $field1->shouldReceive('isValid')->andReturns(true);
        $field1->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field1->shouldReceive('userCanUpdate')->andReturns(true);
        $field1->shouldReceive('saveNewChangeset')->once()->andReturns(true);
        $field2  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field2->shouldReceive('getId')->andReturns(102);
        $field2->shouldReceive('isValid')->with(Mockery::any(), '123')->andReturns(true);
        $field2->shouldReceive('isValid')->with(Mockery::any(), '456')->andReturns(false);
        $field2->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field2->shouldReceive('userCanUpdate')->andReturns(true);
        $field2->shouldReceive('saveNewChangeset')->once()->andReturns(true);
        $field3  = \Mockery::mock(\Tracker_FormElement_Field::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $field3->shouldReceive('getId')->andReturns(103);
        $field3->shouldReceive('isValid')->andReturns(true);
        $field3->shouldReceive('isValidRegardingRequiredProperty')->andReturns(true);
        $field3->shouldReceive('saveNewChangeset')->once()->andReturns(true);
        $field3->shouldReceive('userCanUpdate')->andReturns(true);
        $factory->shouldReceive('getUsedFields')->andReturns(array($field1, $field2, $field3));
        $factory->shouldReceive('getAllFormElementsForTracker')->andReturns(array());

        $new_changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $new_changeset->shouldReceive('executePostCreationActions')->never();

        $changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $changeset->shouldReceive('hasChanges')->andReturns(true);
        $changeset_value1 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset_value2 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset_value3 = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $changeset->shouldReceive('getValue')->with($field1)->andReturns($changeset_value1);
        $changeset->shouldReceive('getValue')->with($field2)->andReturns($changeset_value2);
        $changeset->shouldReceive('getValue')->with($field3)->andReturns($changeset_value3);
        $changeset->shouldReceive('getValues')->andReturns(array());

        $reference_manager = \Mockery::spy(\ReferenceManager::class);
        $reference_manager->shouldReceive('extractCrossRef')->with([
            $comment,
            66,
            'plugin_tracker_artifact',
            666,
            $user->getId(),
            'foobar',
        ]);

        $art_factory = \Mockery::spy(\Tracker_ArtifactFactory::class);

        $hierarchy_factory = \Mockery::spy(\Tracker_HierarchyFactory::class);
        stub($hierarchy_factory)->getChildren()->returns(array());

        $artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $artifact->shouldReceive('getChangesetDao')->andReturns($dao);
        $artifact->shouldReceive('getChangesetCommentDao')->andReturns($comment_dao);
        $artifact->shouldReceive('getFormElementFactory')->andReturns($factory);
        $artifact->shouldReceive('getTracker')->andReturns($tracker);
        $artifact->shouldReceive('getId')->andReturns(66);
        $artifact->shouldReceive('getLastChangeset')->andReturns($changeset);
        $artifact->shouldReceive('getChangeset')->andReturns($new_changeset);
        $artifact->shouldReceive('getReferenceManager')->andReturns($reference_manager);
        $artifact->shouldReceive('getArtifactFactory')->andReturns($art_factory);
        $artifact->shouldReceive('getHierarchyFactory')->andReturns($hierarchy_factory);

        stub($GLOBALS['Response'])->getFeedbackErrors()->returns(array());

        stub($art_factory)->save()->returns(true);
        $art_factory->shouldReceive('save')->once();

        $workflow = \Mockery::spy(\Workflow::class);
        $workflow->shouldReceive('before')->times(2);
        $workflow->shouldReceive('validate')->andReturns(true);
        $artifact->shouldReceive('getWorkflow')->andReturns($workflow);

        // Valid
        $fields_data = array(
            102 => '123',
        );

        $artifact->createNewChangeset($fields_data, $comment, $user, false);

        // Not valid
        $fields_data = array(
            102 => '456',
        );
        $this->expectException('Tracker_Exception');
        $artifact->createNewChangeset($fields_data, $comment, $user);
    }

    function testGetCommentators() {
        $c1 = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $c2 = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $c3 = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        $c4 = \Mockery::spy(\Tracker_Artifact_Changeset::class);

        $u1 = \Mockery::spy(\PFUser::class); $u1->shouldReceive('getUserName')->andReturns('sandrae');
        $u2 = \Mockery::spy(\PFUser::class); $u2->shouldReceive('getUserName')->andReturns('marc');

        $um = \Mockery::spy(\UserManager::class);
        $um->shouldReceive('getUserById')->with(101)->andReturns($u1);
        $um->shouldReceive('getUserById')->with(102)->andReturns($u2);

        $artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $artifact->shouldReceive('getChangesets')->andReturns(array($c1, $c2, $c3, $c4));
        $artifact->shouldReceive('getUserManager')->andReturns($um);

        $c1->shouldReceive('getSubmittedBy')->andReturns(101);
        $c2->shouldReceive('getSubmittedBy')->andReturns(102);
        $c2->shouldReceive('getEmail')->andReturns('titi@example.com');
        $c3->shouldReceive('getSubmittedBy')->andReturns(null);
        $c3->shouldReceive('getEmail')->andReturns('toto@example.com');
        $c4->shouldReceive('getSubmittedBy')->andReturns(null);
        $c4->shouldReceive('getEmail')->andReturns('');

        $this->assertEqual($artifact->getCommentators(), array(
            'sandrae',
            'marc',
            'toto@example.com',
        ));
    }
}

class Tracker_Artifact_ParentAndAncestorsTest extends TuleapTestCase {

    public function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();

        $this->hierarchy_factory = \Mockery::spy(\Tracker_HierarchyFactory::class);

        $this->sprint = anArtifact()->build();
        $this->sprint->setHierarchyFactory($this->hierarchy_factory);

        $this->user = aUser()->build();
    }

    public function itReturnsTheParentArtifactFromAncestors() {
        $release = anArtifact()->withId(1)->build();

        stub($this->hierarchy_factory)->getParentArtifact($this->user, $this->sprint)->returns($release);

        $this->assertEqual($release, $this->sprint->getParent($this->user));
    }

    public function itReturnsNullWhenNoAncestors() {
        stub($this->hierarchy_factory)->getParentArtifact($this->user, $this->sprint)->returns(null);

        $this->assertEqual(null, $this->sprint->getParent($this->user));
    }
}

class Tracker_Artifact_getWorkflowTest extends TuleapTestCase {

    private $workflow;
    private $artifact;

    public function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();
        $tracker_id = 123;
        $this->workflow = aWorkflow()->withTrackerId($tracker_id)->build();
        $tracker = aMockeryTracker()->withId($tracker_id)->build();
        stub($tracker)->getWorkflow()->returns($this->workflow);
        $this->artifact = anArtifact()->build();
        $this->artifact->setTracker($tracker);
    }

    public function itGetsTheWorkflowFromTheTracker() {
        $workflow = $this->artifact->getWorkflow();
        $this->assertEqual($workflow, $this->workflow);
    }

    public function itInjectsItselfInTheWorkflow() {
        $workflow = $this->artifact->getWorkflow();
        $this->assertEqual($workflow->getArtifact(), $this->artifact);
    }
}

class Tracker_Artifact_SOAPTest extends TuleapTestCase {

    private $changeset_without_comments;
    private $changeset_with_submitted_by1;
    private $changeset_with_submitted_by2;
    private $changeset_without_submitted_by;
    private $changeset_which_has_been_modified_by_another_user;

    private $tracker_id;
    private $email;

    private $timestamp1;
    private $timestamp2;
    private $timestamp3;

    private $body1;
    private $body2;
    private $body3;

    private $submitted_by1;
    private $submitted_by2;

    public function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();
        $this->tracker_id    = 123;
        $this->email         = 'martin.goyot@example.com';

        $this->timestamp1    = 1355896800;
        $this->timestamp2    = 1355896802;
        $this->timestamp3    = 1355896805;

        $this->body1         = 'coucou';
        $this->body2         = 'hibou';
        $this->body3         = 'forêt';
        $this->body4         = '';

        $this->submitted_by1 = 101;
        $this->submitted_by2 = 102;

        $this->artifact = anArtifact()->withTrackerId($this->tracker_id)->build();

        $this->changeset_with_submitted_by1                       = new Tracker_Artifact_Changeset(1, $this->artifact, $this->submitted_by1,  $this->timestamp1, null);
        $this->changeset_with_submitted_by2                       = new Tracker_Artifact_Changeset(2, $this->artifact, $this->submitted_by2,  $this->timestamp2, null);
        $this->changeset_without_submitted_by                     = new Tracker_Artifact_Changeset(3, $this->artifact, null,  $this->timestamp3, $this->email);
        $this->changeset_with_comment_with_empty_body             = new Tracker_Artifact_Changeset(4, $this->artifact, $this->submitted_by2,  $this->timestamp2, null);
        $this->changeset_with_different_submitted_by              = new Tracker_Artifact_Changeset(4, $this->artifact, $this->submitted_by2,  $this->timestamp2, null);
        $this->changeset_which_has_been_modified_by_another_user  = new Tracker_Artifact_Changeset(4, $this->artifact, $this->submitted_by2,  $this->timestamp2, null);

        $comment1 = new Tracker_Artifact_Changeset_Comment(1, $this->changeset_with_submitted_by1, 2, 3, $this->submitted_by1,  $this->timestamp1, $this->body1, 'text', 0);
        $comment2 = new Tracker_Artifact_Changeset_Comment(2, $this->changeset_with_submitted_by2, 2, 3, $this->submitted_by2,  $this->timestamp2, $this->body2, 'text', 0);
        $comment3 = new Tracker_Artifact_Changeset_Comment(3, $this->changeset_without_submitted_by, 2, 3, null,  $this->timestamp3, $this->body3, 'text', 0);
        $comment4 = new Tracker_Artifact_Changeset_Comment(4, $this->changeset_with_submitted_by2, 2, 3, $this->submitted_by2,  $this->timestamp2, $this->body4, 'text', 0);
        $comment5 = new Tracker_Artifact_Changeset_Comment(5, $this->changeset_which_has_been_modified_by_another_user, 2, 3, $this->submitted_by1,  $this->timestamp2, $this->body3, 'text', 0);

        $this->changeset_with_submitted_by1->setLatestComment($comment1);
        $this->changeset_with_submitted_by2->setLatestComment($comment2);
        $this->changeset_without_submitted_by->setLatestComment($comment3);
        $this->changeset_with_comment_with_empty_body->setLatestComment($comment4);
        $this->changeset_which_has_been_modified_by_another_user->setLatestComment($comment5);
    }

    public function itReturnsAnEmptySoapArrayWhenThereIsNoComments() {
        $changesets = array($this->changeset_with_comment_with_empty_body);
        $this->artifact->setChangesets($changesets);

        $result = $this->artifact->exportCommentsToSOAP();
        $this->assertArrayEmpty($result);
    }

    public function itReturnsASOAPArrayWhenThereAreTwoComments() {
        $changesets = array($this->changeset_with_submitted_by1, $this->changeset_with_submitted_by2);
        $this->artifact->setChangesets($changesets);

        $result = $this->artifact->exportCommentsToSOAP();
        $expected = array(
            array(
                'submitted_by' => $this->submitted_by1,
                'email'        => null,
                'submitted_on' => $this->timestamp1,
                'body'         => $this->body1,
            ),
            array(
                'submitted_by' => $this->submitted_by2,
                'email'        => null,
                'submitted_on' => $this->timestamp2,
                'body'         => $this->body2,
            )
        );

        $this->assertEqual($expected, $result);
    }

    public function itReturnsAnEmailInTheSOAPArrayWhenThereIsNoSubmittedBy() {
        $changesets = array($this->changeset_without_submitted_by);
        $this->artifact->setChangesets($changesets);

        $result = $this->artifact->exportCommentsToSOAP();
        $expected = array(array(
            'submitted_by' => null,
            'email'        => $this->email,
            'submitted_on' => $this->timestamp3,
            'body'         => $this->body3,
        ));

        $this->assertEqual($expected, $result);
    }

    public function itDoesNotReturnAnArrayWhenCommentHasAnEmptyBody() {
        $changesets = array($this->changeset_with_comment_with_empty_body);
        $this->artifact->setChangesets($changesets);

        $result = $this->artifact->exportCommentsToSOAP();
        $this->assertArrayEmpty($result);
    }

    public function itUsesChangesetSubmittedByAndNotCommentsOne() {
        $changesets = array($this->changeset_which_has_been_modified_by_another_user);
        $this->artifact->setChangesets($changesets);

        $expected = array(array(
            'submitted_by' => $this->submitted_by2,
            'email'        => null,
            'submitted_on' => $this->timestamp2,
            'body'         => $this->body3,
        ));

        $result = $this->artifact->exportCommentsToSOAP();

        $this->assertEqual($result, $expected);
    }

     public function itReturnsTheReferencesInSOAPFormat() {
        $id       = $tracker_id = $parent_id = $name = $label = $description = $use_it = $scope = $required = $notifications = $rank = 0;
        $factory  = \Mockery::spy(\CrossReferenceFactory::class);
        $artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $wiki_ref = array(
            'ref' => 'wiki #toto',
            'url' => 'http://example.com/le_link_to_teh_wiki'
        );
        $file_ref = array(
            'ref' => 'file #chapeau',
            'url' => 'http://example.com/files/chapeau'
        );
        $art_ref = array(
            'ref' => 'art #123',
            'url' => 'http://example.com/tracker/123'
        );
        $doc_ref = array(
            'ref' => 'doc #42',
            'url' => 'http://example.com/docman/42'
        );

        stub($artifact)->getCrossReferenceFactory()->returns($factory);
        stub($factory)->getFormattedCrossReferences()->returns(
            array(
                'source' => array($wiki_ref, $file_ref),
                'target' => array($art_ref),
                'both'   => array($doc_ref),
            )
        );
        $soap = $artifact->getCrossReferencesSOAPValues();
        $this->assertEqual($soap, array(
            $wiki_ref,
            $file_ref,
            $art_ref,
            $doc_ref
        ));
    }
}

class Tracker_Artifact_PostActionsTest extends TuleapTestCase {
    private $changeset_dao;

    public function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();
        $this->fields_data = array();
        $this->submitter   = aUser()->withId(74)->build();

        $this->changeset_dao  = \Mockery::spy(\Tracker_Artifact_ChangesetDao::class);
        $this->changesets  = array(new Tracker_Artifact_Changeset_Null());
        $factory     = \Mockery::spy(\Tracker_FormElementFactory::class);
        stub($factory)->getAllFormElementsForTracker()->returns(array());
        stub($factory)->getUsedFields()->returns(array());

        $this->artifact_factory = \Mockery::spy(\Tracker_ArtifactFactory::class);
        $this->workflow = \Mockery::spy(\Workflow::class);
        $this->changeset_factory  = \Mockery::spy(\Tracker_Artifact_ChangesetFactory::class);
        stub($this->changeset_factory)->getChangeset()->returns(new Tracker_Artifact_Changeset(
            123,
            aMockArtifact()->build(),
            12,
            21,
            ''
        ));
        $tracker        = mockery_stub(\Tracker::class)->getWorkflow()->returns($this->workflow);
        $this->artifact = anArtifact()
            ->withId(42)
            ->withChangesets($this->changesets)
            ->withTracker($tracker)
            ->build();

        $this->submitted_on = $_SERVER['REQUEST_TIME'];

        $fields_validator = \Mockery::spy(\Tracker_Artifact_Changeset_NewChangesetFieldsValidator::class);
        stub($fields_validator)->validate()->returns(true);

        $comment_dao = mockery_stub(\Tracker_Artifact_Changeset_CommentDao::class)->createNewVersion()->returns(true);

        $this->creator = new Tracker_Artifact_Changeset_NewChangesetCreator(
            $fields_validator,
            $factory,
            $this->changeset_dao,
            $comment_dao,
            $this->artifact_factory,
            \Mockery::spy(\EventManager::class),
            \Mockery::spy(\ReferenceManager::class),
            \Mockery::spy(\Tuleap\Tracker\FormElement\Field\ArtifactLink\SourceOfAssociationCollectionBuilder::class)
        );
    }

    public function itCallsTheAfterMethodOnWorkflowWhenCreateNewChangeset() {
        stub($this->changeset_dao)->create()->returns(true);
        stub($this->artifact_factory)->save()->returns(true);
        expect($this->workflow)->after(
            $this->fields_data,
            Mockery::on(function ($element) {
                return is_a($element, Tracker_Artifact_Changeset::class);
            }),
            end($this->changesets)
        )->once();

        $this->creator->create(
            $this->artifact,
            $this->fields_data,
            '',
            $this->submitter,
            $this->submitted_on,
            false,
            Tracker_Artifact_Changeset_Comment::TEXT_COMMENT
        );
    }

    public function itDoesNotCallTheAfterMethodOnWorkflowWhenSaveOfArtifactFailsOnNewChangeset() {
        stub($this->changeset_dao)->create()->returns(true);
        stub($this->artifact_factory)->save()->returns(false);
        expect($this->workflow)->after()->never();

        $this->expectException('Tracker_AfterSaveException');

        $this->creator->create(
            $this->artifact,
            $this->fields_data,
            '',
            $this->submitter,
            $this->submitted_on,
            false,
            Tracker_Artifact_Changeset_Comment::TEXT_COMMENT
        );
    }
}

class Tracker_Artifact_getSoapValueTest extends TuleapTestCase {
    private $artifact;
    private $user;
    private $id = 1235;
    private $tracker_id = 567;
    private $submitted_by = 891;
    private $submitted_on = 111213;
    private $use_artifact_permissions = true;
    private $last_update_date = 654683;

    public function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();
        $this->user     = \Mockery::spy(\PFUser::class);

        $this->last_changeset = \Mockery::spy(\Tracker_Artifact_Changeset::class);
        stub($this->last_changeset)->getSubmittedOn()->returns($this->last_update_date);
        stub($this->last_changeset)->getValues()->returns(array());

        $this->formelement_factory = \Mockery::spy(\Tracker_FormElementFactory::class);
        stub($this->formelement_factory)->getUsedFieldsForSoap()->returns(array());

        $this->artifact = \Mockery::mock(
            \Tracker_Artifact::class,
            array($this->id, $this->tracker_id, $this->submitted_by, $this->submitted_on, $this->use_artifact_permissions)
        )
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        stub($this->artifact)->userCanView()->returns(true);
        stub($this->artifact)->getCrossReferencesSOAPValues()->returns(array(array('ref' => 'art #123', 'url' => '/path/to/art=123')));
        $this->artifact->setChangesets(array($this->last_changeset));
        $this->artifact->setFormElementFactory($this->formelement_factory);
        $this->artifact->setTracker(aTracker()->withId($this->tracker_id)->build());
    }

    public function itReturnsEmptyArrayIfUserCannotViewArtifact() {
        $artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $artifact->setTracker(aTracker()->build());
        $artifact->setFormElementFactory($this->formelement_factory);
        $user     = \Mockery::spy(\PFUser::class);
        stub($artifact)->userCanView($user)->returns(false);

        $this->assertArrayEmpty($artifact->getSoapValue($user));
    }

    public function itReturnsDataIfUserCanViewArtifact() {
        $artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $artifact->setChangesets(array($this->last_changeset));
        $artifact->setTracker(aTracker()->build());
        $artifact->setFormElementFactory($this->formelement_factory);
        $user     = \Mockery::spy(\PFUser::class);
        stub($artifact)->userCanView($user)->returns(true);

        $artifact->shouldReceive('getCrossReferencesSOAPValues')->once()->andReturn(null);

        $this->assertArrayNotEmpty($artifact->getSoapValue($user));
    }

    public function itHasBasicArtifactInfo() {
        $soap_value = $this->artifact->getSoapValue($this->user);
        $this->assertIdentical($soap_value['artifact_id'], $this->id);
        $this->assertIdentical($soap_value['tracker_id'], $this->tracker_id);
        $this->assertIdentical($soap_value['submitted_by'], $this->submitted_by);
        $this->assertIdentical($soap_value['submitted_on'], $this->submitted_on);
    }

    public function itContainsCrossReferencesValue() {
        $soap_value = $this->artifact->getSoapValue($this->user);
        $this->assertEqual($soap_value['cross_references'][0], array('ref' => 'art #123', 'url' => '/path/to/art=123'));
    }

    public function itHasALastUpdateDate() {
        $soap_value = $this->artifact->getSoapValue($this->user);
        $this->assertIdentical($soap_value['last_update_date'], $this->last_update_date);
    }
}

class Tracker_Artifact_getSoapValueWithFieldValuesTest extends TuleapTestCase {
    private $artifact;
    private $user;
    private $field;

    public function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();
        $this->user = \Mockery::spy(\PFUser::class);

        $this->field_id = 123242;

        $this->field           = aMockField()->build();
        $this->changeset_value = \Mockery::spy(\Tracker_Artifact_ChangesetValue::class);
        $this->last_changeset  = mockery_stub(\Tracker_Artifact_Changeset::class)->getValues()->returns(array($this->field_id => $this->changeset_value));

        $this->formelement_factory = \Mockery::spy(\Tracker_FormElementFactory::class);
        stub($this->formelement_factory)->getFormElementById()->returns($this->field);

        $this->tracker = aTracker()->build();

        $this->artifact = \Mockery::mock(\Tracker_Artifact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        stub($this->artifact)->userCanView()->returns(true);
        $this->artifact->setChangesets(array($this->last_changeset));
        $this->artifact->setFormElementFactory($this->formelement_factory);
        $this->artifact->setTracker($this->tracker);
        $this->artifact->shouldReceive('getCrossReferencesSOAPValues')->once()->andReturn(null);
    }

    public function itFetchFieldFromFactory() {
        $this->formelement_factory->shouldReceive('getUsedFieldsForSoap')->with($this->tracker)->once()->andReturn([]);

        $this->artifact->getSoapValue($this->user);
    }

    public function itHasAValueFromField() {
        stub($this->formelement_factory)->getUsedFieldsForSoap()->returns(array($this->field));

        stub($this->field)->getSoapValue($this->user, $this->last_changeset)->returns('whatever')->once();

        $soap_value = $this->artifact->getSoapValue($this->user);
        $this->assertEqual($soap_value['value'][0], 'whatever');
    }

    public function itDoesntModifySoapValueIfNoFieldValues() {
        stub($this->formelement_factory)->getUsedFieldsForSoap()->returns(array($this->field));

        stub($this->field)->getSoapValue()->returns(null);

        $soap_value = $this->artifact->getSoapValue($this->user);
        $this->assertArrayEmpty($soap_value['value']);
    }
}

class Tracker_Artifact_ExportToXMLTest extends TuleapTestCase {

    private $user_manager;

    public function setUp() {
        parent::setUp();
        $this->setUpGlobalsMockery();
        $this->user_manager = \Mockery::spy(\UserManager::class);
        UserManager::setInstance($this->user_manager);

        $this->formelement_factory = \Mockery::spy(\Tracker_FormElementFactory::class);
        Tracker_FormElementFactory::setInstance($this->formelement_factory);
    }

    public function tearDown() {
        UserManager::clearInstance();
        Tracker_FormElementFactory::clearInstance();

        parent::tearDown();
    }

    public function itExportsTheArtifactToXML() {
        $user = aUser()->withId(101)->withLdapId('ldap_O1')->withUserName('user_01')->build();
        stub($this->user_manager)->getUserById(101)->returns($user);
        stub($this->formelement_factory)->getUsedFileFields()->returns(array());

        $changeset_01 = mockery_stub(\Tracker_Artifact_Changeset::class)->getsubmittedBy()->returns(101);
        $changeset_02 = mockery_stub(\Tracker_Artifact_Changeset::class)->getsubmittedBy()->returns(101);

        $project = mockery_stub(\Project::class)->getID()->returns(101);
        $tracker = aTracker()->withId(101)->withProject($project)->build();

        $artifact = anArtifact()->withTracker($tracker)
                                      ->withId(101)
                                      ->withChangesets(array($changeset_01, $changeset_02))
                                      ->build();

        $artifacts_node = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>
                                             <artifacts/>');

        $text_field_01 = mockery_stub(\Tracker_FormElement_Field_Text::class)->getName()->returns('text_01');
        stub($text_field_01)->getTracker()->returns($tracker);

        $value_01 = new Tracker_Artifact_ChangesetValue_Text(1, \Mockery::spy(\Tracker_Artifact_Changeset::class), $text_field_01, true, 'value_01', 'text');
        $value_02 = new Tracker_Artifact_ChangesetValue_Text(2, \Mockery::spy(\Tracker_Artifact_Changeset::class), $text_field_01, true, 'value_02', 'text');

        stub($changeset_01)->getArtifact()->returns($artifact);
        stub($changeset_01)->getValues()->returns(array($value_01));

        stub($changeset_02)->getArtifact()->returns($artifact);
        stub($changeset_02)->getValues()->returns(array($value_02));

        $archive = \Mockery::spy(\Tuleap\Project\XML\Export\ArchiveInterface::class);

        $user_xml_exporter      = new UserXmlExporter($this->user_manager, \Mockery::spy(\UserXMLExportedCollection::class));
        $builder                = new Tracker_XML_Exporter_ArtifactXMLExporterBuilder();
        $children_collector     = new Tracker_XML_Exporter_NullChildrenCollector();
        $file_path_xml_exporter = new Tracker_XML_Exporter_InArchiveFilePathXMLExporter();

        $artifact_xml_exporter =  $builder->build(
            $children_collector,
            $file_path_xml_exporter,
            $user,
            $user_xml_exporter,
            false
        );

        $artifact->exportToXML($artifacts_node, $archive, $artifact_xml_exporter);

        $this->assertEqual($artifacts_node->artifact['id'], 101);
    }
}
