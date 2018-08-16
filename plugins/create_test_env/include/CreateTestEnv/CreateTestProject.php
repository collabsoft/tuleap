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

namespace Tuleap\CreateTestEnv;

class CreateTestProject
{
    const DEFAULT_ARCHIVE = 'sample-project';

    private $user_name;
    private $user_realname;

    private $full_name;
    private $unix_name;
    private $archive_dir_name;

    public function __construct($user_name, $user_realname, $archive_dir_name)
    {
        $this->user_name        = $user_name;
        $this->user_realname    = $user_realname;
        $this->archive_dir_name = basename($archive_dir_name);
    }

    /**
     * @return \SimpleXMLElement
     * @throws Exception\InvalidProjectFullNameException
     * @throws Exception\InvalidProjectUnixNameException
     */
    public function generateXML()
    {
        $engine = new \Mustache_Engine();
        $xml_str = $engine->render(
            file_get_contents($this->getProjectXMLFilePath()),
            new CreateTestProjectPresenter(
                $this->getProjectUnixName(),
                $this->getProjectFullName(),
                $this->user_name,
                date('c')
            )
        );
        return simplexml_load_string($xml_str);
    }

    public function getProjectXMLFilePath()
    {
        $etc_base_dir = \ForgeConfig::get('sys_custompluginsroot').'/'.\create_test_envPlugin::NAME.'/resources';
        $project_xml_path = $etc_base_dir.'/'.$this->archive_dir_name.'/project.xml';
        if (file_exists($project_xml_path)) {
            return $project_xml_path;
        }

        return __DIR__.'/../../resources/sample-project/project.xml';
    }

    /**
     * @return string
     * @throws Exception\InvalidProjectFullNameException
     */
    public function getProjectFullName()
    {
        if ($this->full_name === null) {
            $full_name = $this->generateProjectFullName();
            $rule = new \Rule_ProjectFullName();
            if (! $rule->isValid($full_name)) {
                throw new Exception\InvalidProjectFullNameException($rule->getErrorMessage());
            }
            $this->full_name = $full_name;
        }
        return $this->full_name;
    }

    public function generateProjectFullName()
    {
        return substr('Test project for '.$this->user_name, 0, 40);
    }

    /**
     * @return string
     * @throws Exception\InvalidProjectUnixNameException
     */
    public function getProjectUnixName()
    {
        if ($this->unix_name === null) {
            $unix_name = $this->generateProjectUnixName();
            $rule = new \Rule_ProjectName();
            if (! $rule->isValid($unix_name)) {
                throw new Exception\InvalidProjectUnixNameException($rule->getErrorMessage());
            }
            $this->unix_name = $unix_name;
        }
        return $this->unix_name;
    }

    public function generateProjectUnixName()
    {
        return 'test-for-'.strtr($this->user_name, '_.', '--');
    }
}
