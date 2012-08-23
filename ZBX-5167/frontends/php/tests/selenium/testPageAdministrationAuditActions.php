<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageAdministrationAuditActions extends CWebTest {

	public function testPageAdministrationAuditActions_CheckLayout() {

		$this->login('auditacts.php?stime=20130207090000&period=63072000');
		$this->checkTitle('Audit');
		$this->assertElementPresent('config');
		$this->ok('AUDIT ACTIONS');
		$this->ok('ACTIONS');

		$this->click('flicker_icon_l');

		$this->ok('Recipient');
		$this->assertElementPresent('alias');
		$this->assertAttribute("//input[@id='alias']/@maxlength", '255');
		$this->assertAttribute("//input[@id='alias']/@size", '20');
		$this->assertElementPresent('btn1');
		$this->assertElementPresent('filter');
		$this->assertElementPresent('filter_rst');
		$this->ok(array('Time', 'Type', 'Status', 'Retries left', 'Recipient(s)', 'Message', 'Error'));

	}

	public static function allAuditActions() {
		return DBdata('SELECT * FROM alerts');
	}

	/**
	* @dataProvider allAuditActions
	*/
	public function testPageAdministrationAuditActions_CheckValues($auditactions) {

		$this->login('auditacts.php?stime=20130207090000&period=63072000');
		$this->checkTitle('Audit');
		$this->assertElementPresent('config');
		$this->ok('AUDIT ACTIONS');
		$this->ok('ACTIONS');

		$this->click('flicker_icon_l');
		$time = $auditactions['clock'];
		$today = date("d M Y H:i:s", $time);

		$status = '';
		$type = '';
		$retries = '';

		if ($auditactions['status'] == 1 && $auditactions['alerttype'] == 0) {
			$status = 'sent';
		}
		if ($auditactions['status'] == 0 && $auditactions['alerttype'] == 0 && $auditactions['retries'] == 0) {
			$status = 'not sent';
		}
		if ($auditactions['status'] == 0 && $auditactions['alerttype'] == 0 && $auditactions['retries'] <> 0) {
			$status = 'In progress';
		}
		if ($auditactions['status'] == 1 && $auditactions['alerttype'] == 1) {
			$status = 'executed';
		}

		$sql = 'SELECT mt.description FROM media_type mt, alerts a WHERE a.mediatypeid = mt.mediatypeid AND a.alerttype=0';
		$type = DBfetch(DBselect($sql));

		if ($auditactions['status'] == 1) {
			$retries = '';
		}
		if ($status == 'In progress' || $status == 'not sent') {
			$retries = $auditactions['retries'];
		}

		$this->ok(array($today, $type['description'], $status, $retries, $auditactions['sendto'], $auditactions['subject'], $auditactions['message'], $auditactions['error']));

		// checking that there are no records in the report for 'guest' user
		$this->click('flicker_icon_l');
		$this->input_type('alias', 'guest');
		$this->click("id=filter");
		$this->wait();
		$this->ok('No actions defined.');

		$this->click('flicker_icon_l');
		$this->click('filter_rst');
		$this->wait();

	}
}
?>
