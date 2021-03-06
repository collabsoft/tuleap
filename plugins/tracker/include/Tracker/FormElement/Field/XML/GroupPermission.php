<?php
/*
 * Copyright (c) Enalean, 2021-Present. All Rights Reserved.
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

declare(strict_types=1);

namespace Tuleap\Tracker\FormElement\Field\XML;

abstract class GroupPermission
{
    /**
     * @var string
     * @readonly
     */
    public $ugroup_name;
    /**
     * @var \Tracker_FormElement::PERMISSION_*
     * @readonly
     */
    public $type;

    /**
     * @param \Tracker_FormElement::PERMISSION_* $type
     */
    public function __construct(string $ugroup_name, string $type)
    {
        $this->ugroup_name = $ugroup_name;
        $this->type        = $type;
    }
}
