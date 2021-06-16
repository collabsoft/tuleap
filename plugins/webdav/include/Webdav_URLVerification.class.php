<?php
/**
 * Copyright (c) Enalean 2016 - Present. All rights reserved.
 * Copyright (c) STMicroelectronics, 2010. All Rights Reserved.
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

/**
 * Check the URL validity for WebDAV plugin
 */
class Webdav_URLVerification extends URLVerification
{

    protected $webdavHost;

    /**
     * Constructor of the class
     *
     * @param String $host
     */
    public function __construct($host)
    {
        $this->webdavHost = $host;
    }

    /**
     * Returns the WebDAV host
     *
     * @return String
     */
    public function getWebDAVHost()
    {
        return $this->webdavHost;
    }

    /**
     * Checks if the URL is valid or not and throw an error if needed.
     *
     * Assume it's an url to be taken into account by this class. The conditions are:
     * - The used host is defined as webdav host
     * - The webdav host is different of default host (defined by sys_default_domain or sys_https_host)
     *
     * For the second point, this is to avoid the webdav URL checker override
     * default url checker for the web part. For instance, if sys_default_domain is example.com
     * and webdav host is also example.com, the webdav url verification will be used to test
     * access to example.com/tracker/... instead of default url checker.
     *
     * @see URLVerification#assertValidUrl($server)
     *
     * @param Array $server
     *
     * @return void
     */
    public function assertValidUrl($server, HTTPRequest $request, ?Project $project = null)
    {
        if (self::isRequestForDedicatedWebdavHost($server, $this->getWebDAVHost())) {
            if (! $request->isSecure() && ForgeConfig::get('sys_https_host')) {
                $this->forbiddenError();
            }
        } else {
            parent::assertValidUrl($server, $request);
        }
    }

    public static function isRequestForDedicatedWebdavHost(array $server, string $webdav_host): bool
    {
        return $webdav_host
            && strcmp($server['HTTP_HOST'] ?? '', $webdav_host) === 0
            && strcmp($webdav_host, ForgeConfig::get('sys_default_domain')) !== 0
            && strcmp($webdav_host, ForgeConfig::get('sys_https_host')) !== 0;
    }

    /**
     * Used to return HTTP/1.1 403 Forbidden
     *
     * @return void
     */
    public function forbiddenError()
    {
        header('HTTP/1.1 403 Forbidden: HTTPS required instead of HTTP');
        exit;
    }
}
