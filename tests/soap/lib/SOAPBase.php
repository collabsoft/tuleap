<?php
/**
 * Copyright (c) Enalean, 2015 - 2017. All rights reserved
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

class SOAPBase extends PHPUnit_Framework_TestCase {

    protected $server_base_url;
    protected $base_wsdl;
    protected $project_wsdl;
    protected $server_name;
    protected $server_port;
    protected $login;
    protected $password;
    protected $context;

    /** @var SoapClient */
    protected $soap_base;

    /** @var SoapClient */
    protected $soap_project;

    public function setUp() {
        parent::setUp();

        $this->login              = SOAP_TestDataBuilder::TEST_USER_1_NAME;
        $this->password           = SOAP_TestDataBuilder::TEST_USER_1_PASS;
        $this->server_base_url    = 'https://localhost/soap/?wsdl';
        $this->server_project_url = 'https://localhost/soap/project/?wsdl';
        $this->base_wsdl          = '/soap/codendi.wsdl.php';
        $this->server_name        = 'localhost';
        $this->server_port        = '443';

        $this->context = stream_context_create([
            'ssl' => [
                // set some SSL/TLS specific options
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        // Connecting to the soap's tracker client
        $this->soap_base = new SoapClient(
            $this->server_base_url,
            array('cache_wsdl' => WSDL_CACHE_NONE, 'exceptions' => 1, 'trace' => 1, 'stream_context' => $this->context)
        );

        // Connecting to the soap's tracker client
        $this->soap_project = new SoapClient(
            $this->server_project_url,
            array('cache_wsdl' => WSDL_CACHE_NONE, 'stream_context' => $this->context)
        );
    }

    /**
     * @return string
     */
    protected function getSessionHash() {
        // Establish connection to the server
        return $this->soap_base->login($this->login, $this->password)->session_hash;
    }

}
