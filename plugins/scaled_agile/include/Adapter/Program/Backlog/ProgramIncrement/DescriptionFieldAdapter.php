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

namespace Tuleap\ScaledAgile\Adapter\Program\Backlog\ProgramIncrement;

use Tuleap\ScaledAgile\Program\Backlog\ProgramIncrement\Source\Fields\BuildField;
use Tuleap\ScaledAgile\Program\Backlog\ProgramIncrement\Source\Fields\Field;
use Tuleap\ScaledAgile\Program\Backlog\ProgramIncrement\Source\Fields\FieldRetrievalException;
use Tuleap\ScaledAgile\ScaledAgileTracker;

final class DescriptionFieldAdapter implements BuildField
{
    /**
     * @var \Tracker_Semantic_DescriptionFactory
     */
    private $description_factory;

    public function __construct(
        \Tracker_Semantic_DescriptionFactory $description_factory
    ) {
        $this->description_factory = $description_factory;
    }

    /**
     * @throws FieldRetrievalException
     */
    public function build(ScaledAgileTracker $replication_tracker_data): Field
    {
        $description_field = $this->description_factory->getByTracker($replication_tracker_data->getFullTracker())->getField();
        if (! $description_field) {
            throw new FieldRetrievalException($replication_tracker_data->getTrackerId(), "Description");
        }

        return new Field($description_field);
    }
}
