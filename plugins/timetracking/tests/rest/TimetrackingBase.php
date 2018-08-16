<?php
/**
 * Copyright (c) Enalean, 2018. All rights reserved
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/
 */

namespace Tuleap\Timetracking\REST;

use REST_TestDataBuilder;
use RestBase;

class TimetrackingBase extends RestBase
{

    const PROJECT_NAME = 'test-timetracking';
    const TRACKER_NAME = 'timetracking_testing';

    protected $tracker_timetracking;
    protected $timetracking_artifact_ids;

    public function setUp()
    {
        parent::setUp();
        $project_id                      = $this->getProjectId(self::PROJECT_NAME);
        $this->tracker_timetracking      = $this->tracker_ids[ $project_id ][ self::TRACKER_NAME ];
        $this->timetracking_artifact_ids = $this->getArtifacts($this->tracker_timetracking);
    }
}
