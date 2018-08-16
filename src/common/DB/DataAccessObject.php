<?php
/**
 * Copyright (c) Enalean, 2018. All Rights Reserved.
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

namespace Tuleap\DB;

use ParagonIE\EasyDB\EasyDB;

class DataAccessObject
{
    /**
     * @var EasyDB
     */
    private $db;

    public function __construct()
    {
        $this->db = DBFactory::getMainTuleapDB();
    }

    /**
     * @return EasyDB
     */
    protected function getDB()
    {
        return $this->db;
    }

    /**
     * Returns the number of affected rows by the LAST query.
     * Must be called immediately after performing a query.
     *
     * @return int
     */
    public function foundRows()
    {
        return $this->db->single('SELECT FOUND_ROWS()');
    }
}
