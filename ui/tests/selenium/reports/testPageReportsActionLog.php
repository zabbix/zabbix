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

require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

class testPageReportsActionLog extends CLegacyWebTest {

	public function testPageReportsActionLog_CheckLayout() {
		// from: 2012-02-20 09:00:00
		// to: 2014-02-19 09:00:00
		// dates can be in relative format, example: now-1y/y, now-1w, now
		$this->zbxTestLogin('auditacts.php?from=2012-02-20+09%3A00%3A00&to=2014-02-19+09%3A00%3A00');
		$this->zbxTestCheckTitle('Action log');
		$this->zbxTestAssertElementPresentId('config');
		$this->zbxTestCheckHeader('Action log');

		$this->zbxTestTextPresent('Recipient');
		$this->zbxTestAssertElementPresentId('filter_userids__ms');
		$this->zbxTestAssertElementPresentXpath("//button[@name='filter_set']");
		$this->zbxTestAssertElementPresentXpath("//button[contains(text(),'Reset')]");
		$this->zbxTestTextPresent(['Time', 'Action','Type', 'Status', 'Recipient', 'Message', 'Status', 'Info']);

	}

	public static function allAuditActions() {
		return CDBHelper::getDataProvider('SELECT * FROM alerts ORDER BY alertid LIMIT 7');
	}

	/**
	* @dataProvider allAuditActions
	*/
	public function testPageReportsActionLog_CheckValues($auditactions) {
		$time = $auditactions['clock'];
		$today = date("Y-m-d H:i:s", $time);

		$this->zbxTestLogin('auditacts.php?'.http_build_query([
			'from' => date('Y-m-d H:i:s', $time - 3600),
			'to' => date('Y-m-d H:i:s', $time + 3600)
		]));
		$this->zbxTestCheckTitle('Action log');
		$this->zbxTestAssertElementPresentId('config');
		$this->zbxTestCheckHeader('Action log');

		$status = '';
		$type = '';
		$retries = '';

		if ($auditactions['status'] == 1 && $auditactions['alerttype'] == 0) {
			$status = 'Sent';
		}
		if ($auditactions['status'] == 0 && $auditactions['alerttype'] == 0 && $auditactions['retries'] == 0) {
			$status = 'Failed';
		}
		if ($auditactions['status'] == 0 && $auditactions['alerttype'] == 0 && $auditactions['retries'] <> 0) {
			$status = 'In progress';
		}
		if ($auditactions['status'] == 1 && $auditactions['alerttype'] == 1) {
			$status = 'Executed';
		}

		$sql = 'SELECT mt.name FROM media_type mt, alerts a WHERE a.mediatypeid = mt.mediatypeid AND a.alertid='.zbx_dbstr($auditactions['alertid']);
		$type = DBfetch(DBselect($sql));

		if ($auditactions['status'] == 1) {
			$retries = '';
		}
		if ($status == 'In progress' || $status == 'not sent') {
			$retries = $auditactions['retries'];
		}

		$message = str_replace('>', '&gt;', $auditactions['message']);
		$subject = str_replace('>', '&gt;', $auditactions['subject']);
		$info = str_replace('"', '&amp;quot;', $auditactions['error']);

		$this->zbxTestTextPresent(
				[
					$today,
					CTestArrayHelper::get($type, 'name'),
					$status,
					$retries,
					$auditactions['sendto'],
					$subject,
					$message,
					$info
				]
		);

		$this->zbxTestExpandFilterTab();
		$this->zbxTestClickButtonMultiselect('filter_userids_');
		$this->zbxTestLaunchOverlayDialog('Users');
		$this->zbxTestClickLinkText('guest');
		$this->zbxTestClickXpathWait("//button[@name='filter_set']");
		$this->zbxTestTextPresent('No data found.');

		$this->zbxTestClickButtonText('Reset');
	}
}
