<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/testAuditMaintenance.php';
require_once dirname(__FILE__).'/testAuditAction.php';
require_once dirname(__FILE__).'/testAuditUserGroups.php';
require_once dirname(__FILE__).'/testAuditEventCorrelation.php';
require_once dirname(__FILE__).'/testAuditDashboard.php';
require_once dirname(__FILE__).'/testAuditScheduledReport.php';
//require_once dirname(__FILE__).'/testAuditSettings.php';
//require_once dirname(__FILE__).'/testAuditAutoregistration.php';
require_once dirname(__FILE__).'/testAuditProxy.php';
require_once dirname(__FILE__).'/testAuditUser.php';

use PHPUnit\Framework\TestSuite;

class AuditLogTests {
	public static function suite() {
		$suite = new TestSuite('auditlog');

		$suite->addTestSuite('testAuditMaintenance');
		$suite->addTestSuite('testAuditAction');
		$suite->addTestSuite('testAuditUserGroups');
		$suite->addTestSuite('testAuditEventCorrelation');
		$suite->addTestSuite('testAuditDashboard');
		$suite->addTestSuite('testAuditScheduledReport');
//		$suite->addTestSuite('testAuditSettings');
//		$suite->addTestSuite('testAuditAutoregistration');
		$suite->addTestSuite('testAuditProxy');
		$suite->addTestSuite('testAuditUser');

		return $suite;
	}
}
