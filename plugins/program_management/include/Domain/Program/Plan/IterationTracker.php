<?php
/**
 * Copyright (c) Enalean, 2021 - Present. All Rights Reserved.
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

namespace Tuleap\ProgramManagement\Domain\Program\Plan;

use Tuleap\ProgramManagement\Domain\Program\ProgramTrackerException;

/**
 * @psalm-immutable
 */
final class IterationTracker
{
    /**
     * @var int
     */
    public $id;
    /**
     * @var ?string
     */
    public $label;
    /**
     * @var ?string
     */
    public $sub_label;

    private function __construct(int $id, ?string $label, ?string $sub_label)
    {
        $this->id        = $id;
        $this->label     = $label;
        $this->sub_label = $sub_label;
    }

    /**
     * @throws ProgramTrackerException
     */
    public static function fromPlanIterationChange(
        BuildTracker $tracker_builder,
        PlanIterationChange $iteration_representation,
        int $project_id
    ): self {
        $tracker_builder->checkTrackerIsValid($iteration_representation->tracker_id, $project_id);

        return new self(
            $iteration_representation->tracker_id,
            $iteration_representation->label,
            $iteration_representation->sub_label
        );
    }
}
