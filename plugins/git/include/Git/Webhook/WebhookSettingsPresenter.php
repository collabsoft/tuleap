<?php
/**
 * Copyright (c) Enalean, 2016. All Rights Reserved.
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

namespace Tuleap\Git\Webhook;

class WebhookSettingsPresenter
{
    public $sections;
    public $create_buttons;
    public $description;
    public $title;
    public $last_push;
    public $url;
    public $logs;
    public $edit_hook;
    public $remove;
    public $btn_close;
    public $btn_cancel;

    public function __construct($title, $description, array $create_buttons, array $sections)
    {
        $this->title          = $title;
        $this->description    = $description;
        $this->create_buttons = $create_buttons;
        $this->sections       = $sections;

        $this->has_sections = count($sections) > 0;

        $this->last_push       = $GLOBALS['Language']->getText('plugin_git', 'settings_hooks_last_push');
        $this->url             = $GLOBALS['Language']->getText('plugin_git', 'settings_hooks_url');
        $this->empty_hooks     = $GLOBALS['Language']->getText('plugin_git', 'settings_hooks_empty_hooks');

        $this->logs            = $GLOBALS['Language']->getText('plugin_git', 'settings_hooks_logs');
        $this->edit_hook       = $GLOBALS['Language']->getText('global', 'btn_edit');
        $this->remove          = $GLOBALS['Language']->getText('plugin_git', 'settings_hooks_remove');

        $this->btn_close       = $GLOBALS['Language']->getText('global', 'btn_close');
        $this->btn_cancel      = $GLOBALS['Language']->getText('global', 'btn_cancel');
    }
}
