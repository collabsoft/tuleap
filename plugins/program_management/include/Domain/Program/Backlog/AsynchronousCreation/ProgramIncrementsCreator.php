<?php
/**
 * Copyright (c) Enalean, 2020 - Present. All Rights Reserved.
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

namespace Tuleap\ProgramManagement\Domain\Program\Backlog\AsynchronousCreation;

use Tuleap\DB\DBTransactionExecutor;
use Tuleap\ProgramManagement\Domain\Program\Backlog\ProgramIncrement\ArtifactCreationException;
use Tuleap\ProgramManagement\Domain\Program\Backlog\ProgramIncrement\CreateArtifact;
use Tuleap\ProgramManagement\Domain\Program\Backlog\ProgramIncrement\Source\Changeset\Values\SourceChangesetValuesCollection;
use Tuleap\ProgramManagement\Domain\Program\Backlog\ProgramIncrement\Source\Fields\BuildSynchronizedFields;
use Tuleap\ProgramManagement\Domain\Program\Backlog\ProgramIncrement\Source\Fields\FieldRetrievalException;
use Tuleap\ProgramManagement\Domain\Program\Backlog\ProgramIncrement\Source\Fields\FieldSynchronizationException;
use Tuleap\ProgramManagement\Domain\Program\Backlog\ProgramIncrement\Source\Fields\ProgramIncrementFields;
use Tuleap\ProgramManagement\Domain\Program\Backlog\TrackerCollection;

class ProgramIncrementsCreator
{
    /**
     * @var DBTransactionExecutor
     */
    private $transaction_executor;
    /**
     * @var BuildSynchronizedFields
     */
    private $synchronized_fields_adapter;
    /**
     * @var MapStatusByValue
     */
    private $status_mapper;
    /**
     * @var CreateArtifact
     */
    private $artifact_creator;

    public function __construct(
        DBTransactionExecutor $transaction_executor,
        BuildSynchronizedFields $synchronized_fields_adapter,
        MapStatusByValue $status_mapper,
        CreateArtifact $artifact_creator
    ) {
        $this->transaction_executor        = $transaction_executor;
        $this->synchronized_fields_adapter = $synchronized_fields_adapter;
        $this->status_mapper               = $status_mapper;
        $this->artifact_creator            = $artifact_creator;
    }

    /**
     * @throws ProgramIncrementArtifactCreationException
     * @throws FieldRetrievalException
     * @throws FieldSynchronizationException
     */
    public function createProgramIncrements(
        SourceChangesetValuesCollection $copied_values,
        TrackerCollection $program_increments_tracker_collection,
        \PFUser $current_user
    ): void {
        $this->transaction_executor->execute(
            function () use ($copied_values, $program_increments_tracker_collection, $current_user) {
                foreach ($program_increments_tracker_collection->getTrackers() as $program_increment_tracker) {
                    $synchronized_fields = $this->synchronized_fields_adapter->build($program_increment_tracker);
                    $mapped_status       = $this->status_mapper
                        ->mapStatusValueByDuckTyping($copied_values, $synchronized_fields->getStatusField());

                    $fields_data = ProgramIncrementFields::fromSourceChangesetValuesAndSynchronizedFields(
                        $copied_values,
                        $mapped_status,
                        $synchronized_fields
                    );
                    try {
                        $this->artifact_creator->create(
                            $program_increment_tracker,
                            $fields_data,
                            $current_user,
                            $copied_values->getSubmittedOn(),
                        );
                    } catch (ArtifactCreationException $e) {
                        throw new ProgramIncrementArtifactCreationException($copied_values->getSourceArtifactId());
                    }
                }
            }
        );
    }
}