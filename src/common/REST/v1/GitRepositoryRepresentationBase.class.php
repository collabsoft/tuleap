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
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace Tuleap\REST\v1;

class GitRepositoryRepresentationBase
{
    const ROUTE = 'git';

    const FIELDS_BASIC = 'basic';
    const FIELDS_ALL   = 'all';

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $uri;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $path;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $last_update_date;

    /**
     * @var \Tuleap\Git\REST\v1\GitRepositoryPermissionRepresentation | null
     */
    public $permissions = null;

    /**
     * @var \Tuleap\Git\REST\v1\GerritServerRepresentation | null
     */
    public $server = null;

    /**
     * @var string
     */
    public $html_url;

    /**
     * @var array
     */
    public $additional_information;
}
