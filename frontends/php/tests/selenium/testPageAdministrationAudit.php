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

class testPageAdministrationAudit extends CWebTest {

	public function testPageAdministrationAudit_CheckLayout() {
		$this->zbxTestLogin('auditlogs.php');
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('Logs');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		// input field "User"
		$this->assertElementPresent('alias');
		$this->assertAttribute("//input[@id='alias']/@maxlength", '255');
		$this->assertAttribute("//input[@id='alias']/@size", '20');
		$this->assertElementPresent('btn1');

		// checking "action" drop-down
		$this->assertElementPresent("//select[@id='action']/option[text()='All']");
		$this->assertElementPresent("//select[@id='action']/option[text()='Login']");
		$this->assertElementPresent("//select[@id='action']/option[text()='Logout']");
		$this->assertElementPresent("//select[@id='action']/option[text()='Add']");
		$this->assertElementPresent("//select[@id='action']/option[text()='Update']");
		$this->assertElementPresent("//select[@id='action']/option[text()='Delete']");
		$this->assertElementPresent("//select[@id='action']/option[text()='Enable']");
		$this->assertElementPresent("//select[@id='action']/option[text()='Disable']");

		// checking "resource" drop-down
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='All']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Action']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Application']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Configuration of Zabbix']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Discovery rule']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Graph']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Graph element']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Host']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Host group']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='IT service']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Image']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Item']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Macro']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Maintenance']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Map']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Media type']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Node']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Proxy']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Regular expression']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Scenario']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Screen']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Script']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Slide show']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Template']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Trigger']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Trigger prototype']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='User']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='User group']");
		$this->assertElementPresent("//select[@id='resourcetype']/option[text()='Value map']");

	}

	public static function allLogsForAddUser() {
		return DBdata(
			'SELECT a.clock,u.alias,a.ip,a.details'.
			' FROM auditlog a,users u'.
			' WHERE u.userid=a.userid'.
				" AND u.alias='Admin'".
				' AND a.action=0'.
				' AND resourcetype=0'.
			' LIMIT 1');
	}

	/**
	* @dataProvider allLogsForAddUser
	*/
	public function testPageAdministrationAudit_AddUser($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');

		$user = 'Admin';
		$this->input_type('alias', $user);
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'User');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$details = $auditlog['details'];

		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		// User alias [Admin2] name [Admin2] surname [Admin2]
		$this->zbxTestTextPresent(array($today, $user, $ip, 'User', 'Added', '0', "$details"));
	}

	public static function allLogsForUpdateUser() {
		return DBdata(
				'SELECT a.clock,u.alias,a.ip,a.details'.
				' FROM auditlog a,users u'.
				' WHERE u.userid=a.userid'.
					" AND u.alias='Admin'".
					' AND a.action=1'.
					' AND resourcetype=0'.
				' LIMIT 1');
	}

	/**
	* @dataProvider allLogsForUpdateUser
	*/
	public function testPageAdministrationAudit_UpdateUser($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'User');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$details = $auditlog['details'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array($today, 'Admin', $ip, 'User', 'Updated', $details));
	}

	public static function allLogsForDeleteUser() {
		return DBdata(
				'SELECT a.clock,a.details,u.alias,a.ip,a.resourceid'.
				' FROM auditlog a,users u'.
				' WHERE u.userid=a.userid'.
					" AND u.alias='Admin'".
					' AND a.action=2'.
					' AND resourcetype=0'.
				' LIMIT 1'
		);
	}

	/**
	* @dataProvider allLogsForDeleteUser
	*/
	public function testPageAdministrationAudit_DeleteUser($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'User');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$details = $auditlog['details'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array($today, 'Admin', $ip, 'User', 'Deleted', $details));

	}

	public static function allLogsForAddHost() {
		return DBdata(
				'SELECT a.clock,a.details,a.resourcename,u.alias,a.ip'.
				' FROM auditlog a,users u'.
				' WHERE u.userid=a.userid'.
					" AND u.alias='Admin'".
					' AND a.action=0'.
					' AND resourcetype=4'.
				' LIMIT 1'
		);
	}

	/**
	* @dataProvider allLogsForAddHost
	*/
	public function testPageAdministrationAudit_AddHost($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Host');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['resourcename'];
		$today = date('d M Y H:i:s', $time);

		//$this->zbxTestTextPresent($today);
		$this->zbxTestTextPresent(array($today, 'Admin', $ip, 'Host', 'Added', $details));

	}

	public static function allLogsForUpdateHost() {

		return DBdata("SELECT a.clock, u.alias, a.ip, a.resourceid, a.resourcename FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=4 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateHost
	*/
	public function testPageAdministrationAudit_UpdateHost($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Host');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		$id = $auditlog['resourceid'];
		$descr = $auditlog['resourcename'];

		// TODO: can check also for the host status change in the "Details" column, i.e. "hosts.status: 0 => 1"
		$this->zbxTestTextPresent(array($today, 'Admin', $ip, 'Host', 'Updated', $id, $descr, 'hosts.status:'));

	}

	public static function allLogsForDeleteHost() {
		return DBdata("SELECT a.clock, a.details, a.resourcename, u.alias, a.ip, a.resourceid, a.resourcename FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND resourcetype=4 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteHost
	*/
	public function testPageAdministrationAudit_DeleteHost($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Host');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['resourcename'];
		$today = date('d M Y H:i:s', $time);
		$id = $auditlog['resourceid'];
		$descr = $auditlog['resourcename'];

		$this->zbxTestTextPresent(array($today, 'Admin', $ip, 'Host', 'Deleted', $id, $descr));

	}

	// TODO: add tests for checking enable/disable host

	public static function allLogsForAddHG() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=14 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddHG
	*/
	public function testPageAdministrationAudit_AddHG($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Host group');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['resourcename'];
		$today = date('d M Y H:i:s', $time);

		// $this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Host group', 'Added', '$details'));
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Host group', 'Added', $auditlog['resourceid'], $auditlog['resourcename']));

	}

	public static function allLogsForUpdateHG() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=14 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateHG
	*/
	public function testPageAdministrationAudit_UpdateHG($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Host group');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['resourcename'];
		$today = date('d M Y H:i:s', $time);
		//$oldvalue = $auditlog_details['oldvalue'];

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Host group', 'Updated', $auditlog['resourceid'], $auditlog['resourcename'], 'groups.name:', $details));
		//$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Host group', 'Added', $auditlog['resourceid'], $auditlog['resourcename'], 'groups.name: '.$oldvalue.'=>'.$details));

	}

	public static function allLogsForDeleteHG() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=14 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteHG
	*/
	public function testPageAdministrationAudit_DeleteHG($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Host group');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['resourcename'];
		$today = date('d M Y H:i:s', $time);
		//$oldvalue = $auditlog_details['oldvalue'];

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Host group', 'Deleted', $auditlog['resourceid'], $auditlog['resourcename']));

	}

	public static function allLogsForAddService() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=18 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddService
	*/
	public function testPageAdministrationAudit_AddService($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'IT service');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'IT service', 'Added', $auditlog['resourceid'], $details));

	}

	public static function allLogsForUpdateService() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=18 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateService
	*/
	public function testPageAdministrationAudit_UpdateService($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'IT service');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'IT service', 'Updated', $auditlog['resourceid'], $details));

	}

	public static function allLogsForDeleteService() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=18 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteService
	*/
	public function testPageAdministrationAudit_DeleteService($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'IT service');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'IT service', 'Deleted', $auditlog['resourceid'], $details));

	}

	public static function allLogsForAddImage() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=16 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddImage
	*/
	public function testPageAdministrationAudit_AddImage($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Image');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Image', 'Added', $auditlog['resourceid'], $details));

	}

	public static function allLogsForUpdateImage() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=16 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateImage
	*/
	public function testPageAdministrationAudit_UpdateImage($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Image');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Image', 'Updated', $auditlog['resourceid'], $details));

	}

	public static function allLogsForDeleteImage() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=16 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteImage
	*/
	public function testPageAdministrationAudit_DeleteImage($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Image');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Image', 'Deleted', $auditlog['resourceid'], $details));

	}

	public static function allLogsForAddItem() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=15 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddItem
	*/
	public function testPageAdministrationAudit_AddItem($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Item');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Item', 'Added', $auditlog['resourceid'], 'Item added'));

	}

	public static function allLogsForUpdateItem() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=15 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateItem
	*/
	public function testPageAdministrationAudit_UpdateItem($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Item');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Item', 'Updated', $auditlog['resourceid'], 'Item updated'));

	}

	public static function allLogsForDeleteItem() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=15 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteItem
	*/
	public function testPageAdministrationAudit_DeleteItem($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Item');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Item', 'Deleted', $auditlog['resourceid'], 'Item deleted', $details));

	}

	public static function allLogsForDisableItem() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=15 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDisableItem
	*/
	public function testPageAdministrationAudit_DisableItem($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		// at this moment there is not implemented "Enable/Disable" action, it is recorded as "Update" action
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Item');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Item', 'Updated', $auditlog['resourceid'], $auditlog['resourcename'], 'items.status: 0 => 1'));

	}

	public static function allLogsForEnableItem() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=15 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForEnableItem
	*/
	public function testPageAdministrationAudit_EnableItem($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		// at this moment there is not implemented "Enable/Disable" action, it is recorded as "Update" action
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Item');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Item', 'Updated', $auditlog['resourceid'], $auditlog['resourcename'], 'items.status: 1 => 0'));

	}

	public static function allLogsForAddTrigger() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=13 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddTrigger
	*/
	public function testPageAdministrationAudit_AddTrigger($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Trigger');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Trigger', 'Added', $auditlog['resourceid'], $auditlog['resourcename'], ''));

	}

	public static function allLogsForDisableTrigger() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=13 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDisableTrigger
	*/
	public function testPageAdministrationAudit_DisableTrigger($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		// at this moment there is not implemented "Enable/Disable" action, it is recorded as "Update" action
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Trigger');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Trigger', 'Updated', $auditlog['resourceid'], $auditlog['resourcename'], 'triggers.status: 0 => 1'));

	}

	public static function allLogsForEnableTrigger() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=13 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForEnableTrigger
	*/
	public function testPageAdministrationAudit_EnableTrigger($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		// at this moment there is not implemented "Enable/Disable" action, it is recorded as "Update" action
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Trigger');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Trigger', 'Updated', $auditlog['resourceid'], $auditlog['resourcename'], 'triggers.status: 1 => 0'));

	}

	public static function allLogsForAddGraph() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=6 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddGraph
	*/
	public function testPageAdministrationAudit_AddGraph($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Graph');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Graph', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateGraph() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=6 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateGraph
	*/
	public function testPageAdministrationAudit_UpdateGraph($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Graph');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Graph', 'Updated', $auditlog['resourceid'], $auditlog['details']));

	}

	public static function allLogsForDeleteGraph() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=6 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteGraph
	*/
	public function testPageAdministrationAudit_DeleteGraph($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Graph');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Graph', 'Deleted', $auditlog['resourceid'], $auditlog['resourceid'], $auditlog['details']));

	}

	public static function allLogsForAddAction() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=5 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddAction
	*/
	public function testPageAdministrationAudit_AddAction($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Action');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Action', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateAction() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=5 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateAction
	*/
	public function testPageAdministrationAudit_UpdateAction($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Action');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Action', 'Updated', $auditlog['resourceid'], $auditlog['details']));

	}

	public static function allLogsForDeleteAction() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=5 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteAction
	*/
	public function testPageAdministrationAudit_DeleteAction($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Action');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Action', 'Deleted', $auditlog['resourceid'], 'Action deleted', $auditlog['details']));

	}

	public static function allLogsForDisableAction() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=5 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDisableAction
	*/
	public function testPageAdministrationAudit_DisableAction($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		// at this moment there is not implemented "Enable/Disable" action, it is recorded as "Update" action
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Action');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Action', 'Updated', $auditlog['resourceid'], '', "$details"));

	}

	public static function allLogsForEnableAction() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=5 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForEnableAction
	*/
	public function testPageAdministrationAudit_EnableAction($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		// at this moment there is not implemented "Enable/Disable" action, it is recorded as "Update" action
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Action');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Action', 'Updated', $auditlog['resourceid'], '', "$details"));

	}

	public static function allLogsForAddApplication() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=12 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddApplication
	*/
	public function testPageAdministrationAudit_AddApplication($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Application');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Application', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateApplication() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=12 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateApplication
	*/
	public function testPageAdministrationAudit_UpdateApplication($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Application');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Application', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForDeleteApplication() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=12 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteApplication
	*/
	public function testPageAdministrationAudit_DeleteApplication($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Application');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Application', 'Deleted', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForAddDRule() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=23 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddDRule
	*/
	public function testPageAdministrationAudit_AddDRule ($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Discovery rule');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Discovery rule', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateDRule() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=23 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateDRule
	*/
	public function testPageAdministrationAudit_UpdateDRule($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Discovery rule');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Discovery rule', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForDeleteDRule() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=23 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteDRule
	*/
	public function testPageAdministrationAudit_DeleteDRule($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Discovery rule');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Discovery rule', 'Deleted', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForDisableDRule() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=23 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDisableDRule
	*/
	public function testPageAdministrationAudit_DisableDRule($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		// at this moment there is not implemented "Enable/Disable" action, it is recorded as "Update" action
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Discovery rule');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Discovery rule', 'Updated', $auditlog['resourceid'], '', "$details"));

	}

	public static function allLogsForEnableDRule() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=23 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForEnableDRule
	*/
	public function testPageAdministrationAudit_EnableDRule($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		// at this moment there is not implemented "Enable/Disable" action, it is recorded as "Update" action
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Discovery rule');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Discovery rule', 'Updated', $auditlog['resourceid'], '', "$details"));

	}

	public static function allLogsForAddMacro() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=29 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddMacro
	*/
	public function testPageAdministrationAudit_AddMacro ($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Macro');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Macro', 'Added', $auditlog['resourceid'], '{$B} ⇒ abcd', ''));

	}

	public static function allLogsForUpdateMacro() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=29 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateMacro
	*/
	public function testPageAdministrationAudit_UpdateMacro($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Macro');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Macro', 'Updated', $auditlog['resourceid'], '{$B} ⇒ xyz', ''));

	}

	public static function allLogsForAddMaintenance() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=27 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddMaintenance
	*/
	public function testPageAdministrationAudit_AddMaintenance ($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Maintenance');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Maintenance', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateMaintenance() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=27 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateMaintenance
	*/
	public function testPageAdministrationAudit_UpdateMaintenance($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Maintenance');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Maintenance', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForDeleteMaintenance() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=27 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteMaintenance
	*/
	public function testPageAdministrationAudit_DeleteMaintenance($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Maintenance');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Maintenance', 'Deleted', $auditlog['resourceid'], '',$auditlog['details']));

	}

	public static function allLogsForAddMap() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=19 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddMap
	*/
	public function testPageAdministrationAudit_AddMap ($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Map');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Map', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateMap() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=19 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateMap
	*/
	public function testPageAdministrationAudit_UpdateMap($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Map');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Map', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForDeleteMap() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=19 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteMap
	*/
	public function testPageAdministrationAudit_DeleteMap($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Map');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Map', 'Deleted', $auditlog['resourceid'], '',$auditlog['details']));

	}

	public static function allLogsForAddMediaType() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=3 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddMediaType
	*/
	public function testPageAdministrationAudit_AddMediaType ($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Media type');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Media type', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateMediaType() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=3 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateMediaType
	*/
	public function testPageAdministrationAudit_UpdateMediaType($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Media type');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Media type', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForDisableMediaType() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=3 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDisableMediaType
	*/
	public function testPageAdministrationAudit_DisableMediaType($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Media type');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Media type', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForEnableMediaType() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=3 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForEnableMediaType
	*/
	public function testPageAdministrationAudit_EnableMediaType($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Media type');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Media type', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForDeleteMediaType() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=3 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteMediaType
	*/
	public function testPageAdministrationAudit_DeleteMediaType($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Media type');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Media type', 'Deleted', $auditlog['resourceid'], '',$auditlog['details']));

	}

	public static function allLogsForAddScenario() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=22 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddScenario
	*/
	public function testPageAdministrationAudit_AddScenario ($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Scenario');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Scenario', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateScenario() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=22 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateScenario
	*/
	public function testPageAdministrationAudit_UpdateScenario($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Scenario');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Scenario', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForDisableScenario() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=22 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDisableScenario
	*/
	public function testPageAdministrationAudit_DisableScenario($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		// at this moment there is not implemented "Enable/Disable" action, it is recorded as "Update" action
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Scenario');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Scenario', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Scenario', 'Updated', $auditlog['resourceid'], '', 'Scenario disabled'));

	}

	public static function allLogsForEnableScenario() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=22 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForEnableScenario
	*/
	public function testPageAdministrationAudit_EnableScenario($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		// at this moment there is not implemented "Enable/Disable" action, it is recorded as "Update" action
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Scenario');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$details = $auditlog['details'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Scenario', 'Updated', $auditlog['resourceid'], '', "$details"));
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Scenario', 'Updated', $auditlog['resourceid'], '', 'Scenario activated'));

	}

	public static function allLogsForAddScreen() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=20 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddScreen
	*/
	public function testPageAdministrationAudit_AddScreen($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Screen');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Screen', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateScreen() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=20 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateScreen
	*/
	public function testPageAdministrationAudit_UpdateScreen($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Screen');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Screen', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForDeleteScreen() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=20 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteScreen
	*/
	public function testPageAdministrationAudit_DeleteScreen($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Screen');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Screen', 'Deleted', $auditlog['resourceid'], $auditlog['resourcename'], ''));

	}

	public static function allLogsForAddScript() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=25 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddScript
	*/
	public function testPageAdministrationAudit_AddScript ($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Script');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Script', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateScript() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=25 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateScript
	*/
	public function testPageAdministrationAudit_UpdateScript($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Script');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Script', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForDeleteScript() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=25 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteScript
	*/
	public function testPageAdministrationAudit_DeleteScript($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Script');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Script', 'Deleted', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForAddSlideshow() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=24 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddSlideshow
	*/
	public function testPageAdministrationAudit_AddSlideshow ($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Slide show');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Slide show', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateSlideshow() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=24 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateSlideshow
	*/
	public function testPageAdministrationAudit_UpdateSlideshow($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Slide show');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Slide show', 'Updated', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForDeleteSlideshow() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=24 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteSlideshow
	*/
	public function testPageAdministrationAudit_DeleteSlideshow($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Slide show');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Slide show', 'Deleted', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForAddValuemap() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=0 AND a.resourcetype=17 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForAddValuemap
	*/
	public function testPageAdministrationAudit_AddValuemap ($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Add');
		$this->zbxTestDropdownSelect('resourcetype', 'Value map');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Value map', 'Added', $auditlog['resourceid'], '', $auditlog['details']));

	}

	public static function allLogsForUpdateValuemap() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=17 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateValuemap
	*/
	public function testPageAdministrationAudit_UpdateValuemap($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Value map');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);

		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Value map', 'Updated', $auditlog['resourceid'], '', ''));

	}

	public static function allLogsForDeleteValuemap() {
		return DBdata("SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=2 AND a.resourcetype=17 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForDeleteValuemap
	*/
	public function testPageAdministrationAudit_DeleteValuemap($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Delete');
		$this->zbxTestDropdownSelect('resourcetype', 'Value map');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Value map', 'Deleted', $auditlog['resourceid'], '', ''));

	}

	public static function allLogsForUpdateConfig() {
		return DBdata("SELECT a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.details FROM auditlog a, users u WHERE u.userid=a.userid AND u.alias='Admin' AND a.action=1 AND a.resourcetype=2 LIMIT 1");
	}

	/**
	* @dataProvider allLogsForUpdateConfig
	*/
	public function testPageAdministrationAudit_UpdateConfig($auditlog) {
		$this->zbxTestLogin('auditlogs.php?period=3600&stime='.date('YmdHis', $auditlog['clock'] - 1800));
		$this->checkTitle('Audit logs');
		$this->assertElementPresent('config');

		$this->zbxTestTextPresent('AUDIT LOGS');
		$this->zbxTestTextPresent('LOGS');
		$this->zbxTestTextPresent(array('Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details'));

		$this->assertElementPresent('alias');
		$this->assertElementPresent('btn1');

		$this->input_type('alias', 'Admin');
		$this->zbxTestDropdownSelect('action', 'Update');
		$this->zbxTestDropdownSelect('resourcetype', 'Configuration of Zabbix');

		$this->zbxTestClickWait('filter');

		$ip = $auditlog['ip'];
		$alias = $auditlog['alias'];
		$time = $auditlog['clock'];
		$today = date('d M Y H:i:s', $time);
		$this->zbxTestTextPresent(array("$today", 'Admin', "$ip", 'Configuration of Zabbix', 'Updated', $auditlog['resourceid'], $auditlog['details']));
	}

}
