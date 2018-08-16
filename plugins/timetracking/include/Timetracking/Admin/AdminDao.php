<?php
/**
 * Copyright (c) Enalean, 2017 - 2018. All Rights Reserved.
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

namespace Tuleap\Timetracking\Admin;

use Tuleap\DB\DataAccessObject;

class AdminDao extends DataAccessObject
{
    public function enableTimetrackingForTracker($tracker_id)
    {
        $sql = 'REPLACE INTO plugin_timetracking_enabled_trackers
                VALUES (?)';

        $this->getDB()->run($sql, $tracker_id);
    }

    public function disableTimetrackingForTracker($tracker_id)
    {
        $sql = 'DELETE FROM plugin_timetracking_enabled_trackers
                WHERE tracker_id = ?';

        $this->getDB()->run($sql, $tracker_id);
    }

    public function isTimetrackingEnabledForTracker($tracker_id)
    {
        $sql = 'SELECT NULL
                FROM plugin_timetracking_enabled_trackers
                WHERE tracker_id = ?';

        $this->getDB()->run($sql, $tracker_id);

        return $this->foundRows() > 0;
    }
}
