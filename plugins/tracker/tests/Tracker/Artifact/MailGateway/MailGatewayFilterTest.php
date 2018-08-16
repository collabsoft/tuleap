<?php
/**
 * Copyright (c) Enalean, 2017-2018. All Rights Reserved.
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

namespace Tuleap\Tracker\Artifact\MailGateway;

use TuleapTestCase;

require_once __DIR__.'/../../../bootstrap.php';

class MailGatewayFilterTest extends TuleapTestCase
{
    /**
     * @var MailGatewayFilter
     */
    private $mail_filter;

    public function setUp()
    {
        $this->mail_filter = new MailGatewayFilter();
    }
    public function itReturnsTrueWhenAutoReplyIsSetToAutoGenerated()
    {
        $incoming_mail = \Mockery::mock(IncomingMail::class);
        $incoming_mail->shouldReceive('getHeaderValue')->with('auto-submitted')->andReturns('auto-generated');
        $this->assertTrue($this->mail_filter->isAnAutoReplyMail($incoming_mail));
    }

    public function itReturnsFalseWhenAutoReplyIsSetToNoValue()
    {
        $incoming_mail = \Mockery::mock(IncomingMail::class);
        $incoming_mail->shouldReceive('getHeaderValue')->with('auto-submitted')->andReturns('no');
        $incoming_mail->shouldReceive('getHeaderValue')->with('return-path')->andReturns(false);
        $this->assertFalse($this->mail_filter->isAnAutoReplyMail($incoming_mail));
    }

    public function itReturnsTrueWhenReturnPathIsNotSet()
    {
        $incoming_mail = \Mockery::mock(IncomingMail::class);
        $incoming_mail->shouldReceive('getHeaderValue')->with('auto-submitted')->andReturns('no');
        $incoming_mail->shouldReceive('getHeaderValue')->with('return-path')->andReturns('<>');
        $this->assertTrue($this->mail_filter->isAnAutoReplyMail($incoming_mail));
    }

    public function itReturnsFalseWhenReturnPathIsSet()
    {
        $incoming_mail = \Mockery::mock(IncomingMail::class);
        $incoming_mail->shouldReceive('getHeaderValue')->with('auto-submitted')->andReturns('no');
        $incoming_mail->shouldReceive('getHeaderValue')->with('return-path')->andReturns('<mail@example.com>');
        $this->assertFalse($this->mail_filter->isAnAutoReplyMail($incoming_mail));
    }

    public function itReturnsTrueWhenAutoReplyIsAutoGeneratedAndPathIsSet()
    {
        $incoming_mail = \Mockery::mock(IncomingMail::class);
        $incoming_mail->shouldReceive('getHeaderValue')->with('auto-submitted')->andReturns('auto-generated');
        $incoming_mail->shouldReceive('getHeaderValue')->with('return-path')->andReturns('<mail@example.com>');
        $this->assertTrue($this->mail_filter->isAnAutoReplyMail($incoming_mail));
    }
}
