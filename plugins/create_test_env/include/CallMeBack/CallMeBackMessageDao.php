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
 *
 */

namespace Tuleap\CallMeBack;

use Tuleap\DB\DataAccessObject;
use PDOException;

class CallMeBackMessageDao extends DataAccessObject
{
    public function getAll()
    {
        return $this->getDB()->run('SELECT * FROM plugin_callmeback_messages');
    }

    public function get($language_id)
    {
        $sql = 'SELECT message FROM plugin_callmeback_messages WHERE language_id = ?';
        return $this->getDB()->single($sql, [$language_id]);
    }

    /**
     * @var string $language_id
     * @var string $message
     */
    public function save($language_id, $message)
    {
        $sql = 'UPDATE plugin_callmeback_messages SET message = ? WHERE language_id = ?';
        $this->getDB()->run($sql, $message, $language_id);
    }
}
