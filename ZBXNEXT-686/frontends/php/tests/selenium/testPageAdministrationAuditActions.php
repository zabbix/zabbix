<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageAdministrationAuditActions extends CWebTest {

	public function testPageAdministrationAuditActions_CheckLayout() {

		$this->zbxTestLogin('auditacts.php?stime=20120220090000&period=63072000');
		$this->zbxTestCheckTitle('Action log');
		$this->zbxTestAssertElementPresentId('config');
		$this->zbxTestCheckHeader('Action log');

		$this->zbxTestTextPresent('Recipient');
		$this->zbxTestAssertElementPresentId('alias');
		$this->zbxTestAssertElementPresentXpath("//input[@id='alias' and @maxlength='255']");
		$this->zbxTestAssertElementPresentId('btn1');
		$this->zbxTestAssertElementPresentId('filter_set');
		$this->zbxTestAssertElementPresentXpath("//button[contains(text(),'Reset')]");
		$this->zbxTestTextPresent(['Time', 'Action','Type', 'Status', 'Recipient(s)', 'Message', 'Status', 'Info']);

	}

	public static function allAuditActions() {
		return DBdata('SELECT * FROM alerts ORDER BY alertid LIMIT 7');
	}

	/**
	* @dataProvider allAuditActions
	*/
	public function testPageAdministrationAuditActions_CheckValues($auditactions) {

		$this->zbxTestLogin('auditacts.php?stime=20120220090000&period=63072000');
		$this->zbxTestCheckTitle('Action log');
		$this->zbxTestAssertElementPresentId('config');
		$this->zbxTestCheckHeader('Action log');

		$time = $auditactions['clock'];
		$today = date("Y-m-d H:i:s", $time);

		$status = '';
		$type = '';
		$retries = '';

		if ($auditactions['status'] == 1 && $auditactions['alerttype'] == 0) {
			$status = 'Sent';
		}
		if ($auditactions['status'] == 0 && $auditactions['alerttype'] == 0 && $auditactions['retries'] == 0) {
			$status = 'Not sent';
		}
		if ($auditactions['status'] == 0 && $auditactions['alerttype'] == 0 && $auditactions['retries'] <> 0) {
			$status = 'In progress';
		}
		if ($auditactions['status'] == 1 && $auditactions['alerttype'] == 1) {
			$status = 'Executed';
		}

		$sql = 'SELECT mt.description FROM media_type mt, alerts a WHERE a.mediatypeid = mt.mediatypeid AND a.alerttype=0';
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
					$type['description'],
					$status,
					$retries,
					$auditactions['sendto'],
					$subject,
					$message,
					$info
				]
		);

		$this->zbxTestInputType('alias', 'guest');
		$this->zbxTestClickWait('filter_set');
		$this->zbxTestTextPresent('No data found.');

		$this->zbxTestClickButtonText('Reset');
	}
}
