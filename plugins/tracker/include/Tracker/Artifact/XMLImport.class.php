<?php
/**
 * Copyright (c) Enalean, 2014 - 2018. All Rights Reserved.
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

use Tuleap\Tracker\FormElement\Field\ArtifactLink\Nature\NatureDao;

class Tracker_Artifact_XMLImport {

    /** @var boolean */
    private $send_notifications;

    /** @var XML_RNGValidator */
    private $rng_validator;

    /** @var Tracker_ArtifactCreator */
    private $artifact_creator;

    /** @var Tracker_Artifact_Changeset_NewChangesetCreatorBase */
    private $new_changeset_creator;

    /** @var Tracker_FormElementFactory */
    private $formelement_factory;

    /** @var User\XML\Import\IFindUserFromXMLReference */
    private $user_finder;

    /** @var Tracker_FormElement_Field_List_Bind_Static_ValueDao */
    private $static_value_dao;

    /** @var WrapperLogger */
    private $logger;

    /** @var Tracker_ArtifactFactory  */
    private $tracker_artifact_factory;

    /** @var NatureDao  */
    private $nature_dao;

    public function __construct(
        XML_RNGValidator $rng_validator,
        Tracker_ArtifactCreator $artifact_creator,
        Tracker_Artifact_Changeset_NewChangesetCreatorBase $new_changeset_creator,
        Tracker_FormElementFactory $formelement_factory,
        User\XML\Import\IFindUserFromXMLReference $user_finder,
        Tracker_FormElement_Field_List_Bind_Static_ValueDao $static_value_dao,
        Logger $logger,
        $send_notifications,
        Tracker_ArtifactFactory $tracker_artifact_factory,
        NatureDao $nature_dao
    ) {
        $this->rng_validator            = $rng_validator;
        $this->artifact_creator         = $artifact_creator;
        $this->new_changeset_creator    = $new_changeset_creator;
        $this->formelement_factory      = $formelement_factory;
        $this->user_finder              = $user_finder;
        $this->static_value_dao         = $static_value_dao;
        $this->logger                   = new WrapperLogger($logger, 'XML import');
        $this->send_notifications       = $send_notifications;
        $this->tracker_artifact_factory = $tracker_artifact_factory;
        $this->nature_dao               = $nature_dao;
    }

    public function importFromArchive(Tracker $tracker, Tracker_Artifact_XMLImport_XMLImportZipArchive $archive) {
        $archive->extractFiles();
        $xml = simplexml_load_string($archive->getXML());

        $extraction_path   = $archive->getExtractionPath();
        $xml_field_mapping = new TrackerXmlFieldsMapping_InSamePlatform();

        $this->importFromXML($tracker, $xml, $extraction_path, $xml_field_mapping);

        $archive->cleanUp();
    }

    public function importFromFile(Tracker $tracker, $xml_file_path) {
        $xml_security      = new XML_Security();
        $xml               = $xml_security->loadFile($xml_file_path);
        $xml_file_path     = "";
        $xml_field_mapping = new TrackerXmlFieldsMapping_InSamePlatform();

        $this->importFromXML(
            $tracker, $xml, $xml_file_path, $xml_field_mapping
        );
    }

    /**
     * Import a full tracker from XML. This function will not import artifact
     * links between trackers. If you need it, use the two methods
     * importBareArtifactsFromXML() first to generate the mapping for all the
     * trackers and then importArtifactChangesFromXML().
     *
     * @param Tracker $tracker
     * @param SimpleXMLElement $xml_element
     * @param $extraction_path
     * @param TrackerXmlFieldsMapping $xml_fields_mapping
     * @return bool for success or failure
     */
    public function importFromXML(
        Tracker $tracker,
        SimpleXMLElement $xml_element,
        $extraction_path,
        TrackerXmlFieldsMapping $xml_fields_mapping
    ) {
        $artifacts_id_mapping = new Tracker_XML_Importer_ArtifactImportedMapping();
        try {
            $this->rng_validator->validate($xml_element, realpath(dirname(TRACKER_BASE_DIR) . '/www/resources/artifacts.rng'));
            $artifacts = $this->importBareArtifactsFromXML(
                $tracker,
                $xml_element,
                $extraction_path,
                $xml_fields_mapping,
                $artifacts_id_mapping);
            return $this->importArtifactChangesFromXML(
                $tracker,
                $xml_element,
                $extraction_path,
                $xml_fields_mapping,
                $artifacts_id_mapping,
                $artifacts);
        } catch (Exception $exception) {
            $this->logger->error("".get_class($exception).': '.$exception->getMessage().' in '.$exception->getFile().' L'.$exception->getLine());
            echo ("".get_class($exception).': '.$exception->getMessage().' in '.$exception->getFile().' L'.$exception->getLine());
            return false;
        }
    }

    /**
     * Import bare artifacts without any changeset
     * Fill up $artifacts_id_mapping with a mapping from old ids to new ids
     *
     * @param Tracker $tracker
     * @param SimpleXMLElement $xml_element
     * @param $extraction_path
     * @param TrackerXmlFieldsMapping $xml_fields_mapping
     * @param Tracker_XML_Importer_ArtifactImportedMapping $artifacts_id_mapping
     * @return array of bare artifacts or null on error
     * @throws Tracker_Artifact_Exception_XMLImportException
     */
    public function importBareArtifactsFromXML(
        Tracker $tracker,
        SimpleXMLElement $xml_element,
        $extraction_path,
        TrackerXmlFieldsMapping $xml_fields_mapping,
        Tracker_XML_Importer_ArtifactImportedMapping &$artifacts_id_mapping
    ) {
        $tracker->getWorkflow()->disable();
        $artifacts = array();
        foreach (iterator_to_array($xml_element->artifact, false) as $i => $artifact_xml) {
            $artifact = $this->importBareArtifact($tracker, $artifact_xml);

            if ($artifact) {
                $artifacts[$i] = $artifact;
                $artifacts_id_mapping->add((string)$artifact_xml['id'], $artifact->getId());
            }

        }
        return $artifacts;
    }

    /**
     * Import changesets from a n array of bare artifacts
     * @param Tracker $tracker
     * @param SimpleXMLElement $xml_element
     * @param $extraction_path
     * @param TrackerXmlFieldsMapping $xml_fields_mapping
     * @param Tracker_XML_Importer_ArtifactImportedMapping $artifacts_id_mapping
     * @param array $artifacts
     * @return true
     */
    public function importArtifactChangesFromXML(
        Tracker $tracker,
        SimpleXMLElement $xml_element,
        $extraction_path,
        TrackerXmlFieldsMapping $xml_fields_mapping,
        Tracker_XML_Importer_ArtifactImportedMapping $artifacts_id_mapping,
        array $artifacts
    ) {
        $tracker->getWorkflow()->disable();
        foreach (iterator_to_array($xml_element->artifact, false) as $i => $artifact_xml) {
            $fields_data_builder = $this->createFieldsDataBuilder(
                $tracker,
                $artifact_xml,
                $extraction_path,
                $xml_fields_mapping,
                $artifacts_id_mapping);

            if (isset($artifacts[$i])) {
                $this->importChangesets($artifacts[$i], $artifact_xml, $fields_data_builder);
            }

        }
        return true;
    }

    /**
     * @param Tracker $tracker
     * @param SimpleXMLElement $artifact_xml
     * @param $extraction_path
     * @param TrackerXmlFieldsMapping $xml_fields_mapping
     * @param Tracker_XML_Importer_ArtifactImportedMapping $artifacts_id_mapping
     * @return Tracker_Artifact_XMLImport_ArtifactFieldsDataBuilder
     */
    public function createFieldsDataBuilder(
        Tracker $tracker,
        SimpleXMLElement $artifact_xml,
        $extraction_path,
        TrackerXmlFieldsMapping $xml_fields_mapping,
        Tracker_XML_Importer_ArtifactImportedMapping $artifacts_id_mapping
    ) {
        $files_importer = new Tracker_Artifact_XMLImport_CollectionOfFilesToImportInArtifact($artifact_xml);
        return new Tracker_Artifact_XMLImport_ArtifactFieldsDataBuilder(
            $this->formelement_factory,
            $this->user_finder,
            $tracker,
            $files_importer,
            $extraction_path,
            $this->static_value_dao,
            $this->logger,
            $xml_fields_mapping,
            $artifacts_id_mapping,
            $this->tracker_artifact_factory,
            $this->nature_dao
        );
    }

    /**
     * @param Tracker $tracker
     * @param SimpleXMLElement $xml_artifact
     * @return Tracker_Artifact|null The created artifact
     * @throws Tracker_Artifact_Exception_XMLImportException
     */
    public function importBareArtifact(
        Tracker $tracker,
        SimpleXMLElement $xml_artifact
    ) {
        $this->logger->info('art #'.(string)$xml_artifact['id'].' with '.count($xml_artifact->changeset).' changesets ');
        if (count($xml_artifact->changeset) > 0) {
            $changesets      = array_values($this->getSortedBySubmittedOn($xml_artifact->changeset));
            $first_changeset = count($changesets) ? $changesets[0] : null;
            $artifact = $this->artifact_creator->createBare(
                $tracker,
                $this->getSubmittedBy($first_changeset),
                $this->getSubmittedOn($first_changeset));
            $this->logger->info("--> new artifact {$artifact->getId()}");
            return $artifact;
        }
    }

    public function importChangesets(
        Tracker_Artifact $artifact,
        SimpleXMLElement $xml_artifact,
        Tracker_Artifact_XMLImport_ArtifactFieldsDataBuilder $fields_data_builder
    ) {
        $this->logger->push('art #'.(string)$xml_artifact['id']);
        $nb_changesets = count($xml_artifact->changeset);
        $this->logger->debug("Changeset(s) to create: " . $nb_changesets);
        if ($nb_changesets > 0) {
            $this->importAllChangesetsBySubmitionDate($artifact, $xml_artifact->changeset, $fields_data_builder);
        }
        $this->logger->pop();
    }

    private function getSortedBySubmittedOn(SimpleXMLElement $changesets) {
        $changeset_array = array();
        foreach ($changesets as $changeset) {
            $timestamp = $this->getSubmittedOn($changeset);
            if (! isset($changeset_array[$timestamp])) {
                $changeset_array[$timestamp] = array($changeset);
            } else {
                $changeset_array[$timestamp][] = $changeset;
            }
        }
        ksort($changeset_array, SORT_NUMERIC);
        return $this->flattenChangesetArray($changeset_array);
    }

    private function flattenChangesetArray(array $changesets_per_timestamp) {
        $changesets = array();
        foreach ($changesets_per_timestamp as $changeset_per_timestamp) {
            foreach ($changeset_per_timestamp as $changeset) {
                $changesets[] = $changeset;
            }
        }
        return $changesets;
    }

    private function importAllChangesetsBySubmitionDate(
        Tracker_Artifact $artifact,
        SimpleXMLElement $xml_changesets,
        Tracker_Artifact_XMLImport_ArtifactFieldsDataBuilder $fields_data_builder
    ) {
        $xml_changesets = $this->getSortedBySubmittedOn($xml_changesets);
        $count = 0;
        $this->logger->info('art #'.$artifact->getId());
        foreach($xml_changesets as $xml_changeset) {
            try {
                if($count === 0) {
                    $this->logger->debug("initial changeset");
                    $res = $this->importFirstChangeset($artifact, $xml_changeset, $fields_data_builder);
                    if (!$res) {
                        $this->importFakeFirstChangeset($artifact, $xml_changeset);
                    }
                } else {
                    $this->logger->debug("changeset $count");
                    $this->importRemainingChangeset($artifact, $xml_changeset, $fields_data_builder);
                }
            } catch (Tracker_NoChangeException $exception) {
                $this->logger->warn("No Change for changeset $count");
            } catch (Exception $exception) {
                $this->logger->warn("Unexpected error at changeset $count: ".$exception->getMessage());
            }
            $count++;
        }
    }

    private function importFirstChangeset(
        Tracker_Artifact $artifact,
        SimpleXMLElement $xml_changeset,
        Tracker_Artifact_XMLImport_ArtifactFieldsDataBuilder $fields_data_builder
    ) {
        $submitted_by = $this->getSubmittedBy($xml_changeset);
        $fields_data = $fields_data_builder->getFieldsData($xml_changeset, $submitted_by, $artifact);
        if (count($fields_data) === 0) {
            return null;
        }

        return $this->artifact_creator->createFirstChangeset(
            $artifact->getTracker(),
            $artifact,
            $fields_data,
            $submitted_by,
            $this->getSubmittedOn($xml_changeset),
            false
        );
    }

    private function importFakeFirstChangeset(
        Tracker_Artifact $artifact,
        SimpleXMLElement $xml_changeset
    ) {
        $submitted_by = $this->getSubmittedBy($xml_changeset);

        $this->logger->warn("Failed to create artifact with first changeset, create a fake one instead: ".$GLOBALS['Response']->getAndClearRawFeedback());
        return $this->artifact_creator->createFirstChangeset(
            $artifact->getTracker(),
            $artifact,
            array(),
            $submitted_by,
            $this->getSubmittedOn($xml_changeset),
            false
        );
    }

    private function importRemainingChangeset(
        Tracker_Artifact $artifact,
        SimpleXMLElement $xml_changeset,
        Tracker_Artifact_XMLImport_ArtifactFieldsDataBuilder $fields_data_builder
    ) {
        $initial_comment_body   = '';
        $initial_comment_format = Tracker_Artifact_Changeset_Comment::TEXT_COMMENT;
        if (isset($xml_changeset->comments) && count($xml_changeset->comments->comment) > 0) {
            $initial_comment_body   = (string)$xml_changeset->comments->comment[0]->body;
            $initial_comment_format = (string)$xml_changeset->comments->comment[0]->body['format'];
        }

        $submitted_by = $this->getSubmittedBy($xml_changeset);
        $changeset = $this->new_changeset_creator->create(
            $artifact,
            $fields_data_builder->getFieldsData($xml_changeset, $submitted_by, $artifact),
            $initial_comment_body,
            $submitted_by,
            $this->getSubmittedOn($xml_changeset),
            $this->send_notifications,
            $initial_comment_format
        );
        if ($changeset) {
            $this->updateComments($changeset, $xml_changeset);
        } else {
            $this->logger->warn("Impossible to create changeset: ".$GLOBALS['Response']->getAndClearRawFeedback());
        }
    }

    private function updateComments(Tracker_Artifact_Changeset $changeset, SimpleXMLElement $xml_changeset) {
        if (isset($xml_changeset->comments) && count($xml_changeset->comments->comment) > 1) {
            $all_comments = $xml_changeset->comments->comment;
            for ($i = 1; $i < count($all_comments); ++$i) {
                $changeset->updateCommentWithoutNotification(
                    (string)$all_comments[$i]->body,
                    $this->getSubmittedBy($all_comments[$i]),
                    (string)$all_comments[$i]->body['format'],
                    $this->getSubmittedOn($all_comments[$i])
                );
            }
        }
    }

    /**
     * @return \PFUser
     */
    private function getSubmittedBy(SimpleXMLElement $xml_changeset) {
        return $this->user_finder->getUser($xml_changeset->submitted_by);
    }

    private function getSubmittedOn(SimpleXMLElement $xml_changeset) {
        $time = strtotime((string)$xml_changeset->submitted_on);
        if ($time !== false) {
            return $time;
        }
        throw new Tracker_Artifact_Exception_XMLImportException("Invalid date format not ISO8601: ".(string)$xml_changeset->submitted_on);
    }

    /**
     * @param Tracker $tracker
     * @param SimpleXMLElement $xml_artifact
     * @return Tracker_Artifact|null
     * @throws Tracker_Artifact_Exception_XMLImportException
     */
    public function importArtifactWithAllDataFromXMLContent(
        Tracker $tracker,
        SimpleXMLElement $xml_artifact
    ) {
        if (count($xml_artifact->changeset) > 0) {
            $changesets      = array_values($this->getSortedBySubmittedOn($xml_artifact->changeset));
            $first_changeset = count($changesets) ? $changesets[0] : null;
            $artifact = $this->artifact_creator->createBareWithAllData(
                $tracker,
                (int) $xml_artifact['id'],
                $this->getSubmittedOn($first_changeset),
                $this->getSubmittedBy($first_changeset)->getId()
            );

            if ($artifact) {
                $fields_data_builder = $this->createFieldsDataBuilder(
                    $tracker,
                    $xml_artifact,
                    '',
                    new TrackerXmlFieldsMapping_InSamePlatform(),
                    new Tracker_XML_Importer_ArtifactImportedMapping()
                );

                $this->importAllChangesetsBySubmitionDate($artifact, $xml_artifact->changeset, $fields_data_builder);
                return $artifact;
            }
        }

        return null;
    }
}
