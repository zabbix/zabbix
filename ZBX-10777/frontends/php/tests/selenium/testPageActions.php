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

class testPageActions extends CWebTest {

	private $sqlHashAction = '';
	private $oldHashAction = '';
	private $sqlHashConditions = '';
	private $oldHashConditions = '';
	private $sqlHashOperations = '';
	private $oldHashOperations = '';
	private $sqlHashOpMessage = '';
	private $oldHashOpMessage = '';
	private $sqlHashOpMessageGrp = '';
	private $oldHashOpMessageGrp = '';
	private $sqlHashOpMessageUsr = '';
	private $oldHashOpMessageUsr = '';
	private $sqlHashOpCommand = '';
	private $oldHashOpCommand = '';
	private $sqlHashOpCommandHst = '';
	private $oldHashOpCommandHst = '';
	private $sqlHashOpCommandGrp = '';
	private $oldHashOpCommandGrp = '';
	private $sqlHashOpGroup = '';
	private $oldHashOpGroup = '';
	private $sqlHashOpTemplate = '';
	private $oldHashOpTemplate = '';
	private $sqlHashOpConditions = '';
	private $oldHashOpConditions = '';

	private function calculateHash($actionid) {
		$this->sqlHashAction = 'SELECT * FROM actions WHERE actionid='.$actionid;
		$this->oldHashAction = DBhash($this->sqlHashAction);
		$this->sqlHashConditions = 'SELECT * FROM conditions WHERE actionid='.$actionid.' AND actionid>2  ORDER BY conditionid';
		$this->oldHashConditions = DBhash($this->sqlHashConditions);
		$this->sqlHashOperations = 'SELECT * FROM operations WHERE actionid='.$actionid.' ORDER BY operationid';
		$this->oldHashOperations = DBhash($this->sqlHashOperations);
		$this->sqlHashOpMessage =
			'SELECT om.* FROM opmessage om,operations o'.
			' WHERE om.operationid=o.operationid'.
				' AND o.actionid='.$actionid.
				' ORDER BY om.operationid';
		$this->oldHashOpMessage = DBhash($this->sqlHashOpMessage);
		$this->sqlHashOpMessageGrp =
			'SELECT omg.* FROM opmessage_grp omg,operations o'.
			' WHERE omg.operationid=o.operationid'.
				' AND o.actionid='.$actionid.
				' ORDER BY omg.opmessage_grpid';
		$this->oldHashOpMessageGrp = DBhash($this->sqlHashOpMessageGrp);
		$this->sqlHashOpMessageUsr =
			'SELECT omu.* FROM opmessage_usr omu,operations o'.
			' WHERE omu.operationid=o.operationid'.
				' AND o.actionid='.$actionid.
				' ORDER BY omu.opmessage_usrid';
		$this->oldHashOpMessageUsr = DBhash($this->sqlHashOpMessageUsr);
		$this->sqlHashOpCommand =
			'SELECT oc.* FROM opcommand oc,operations o'.
			' WHERE oc.operationid=o.operationid'.
				' AND o.actionid='.$actionid.
				' ORDER BY oc.operationid';
		$this->oldHashOpCommand = DBhash($this->sqlHashOpCommand);
		$this->sqlHashOpCommandHst =
			'SELECT och.* FROM opcommand_hst och,operations o'.
			' WHERE och.operationid=o.operationid'.
				' AND o.actionid='.$actionid.
				' ORDER BY och.opcommand_hstid';
		$this->oldHashOpCommandHst = DBhash($this->sqlHashOpCommandHst);
		$this->sqlHashOpCommandGrp =
			'SELECT ocg.* FROM opcommand_grp ocg,operations o'.
			' WHERE ocg.operationid=o.operationid'.
				' AND o.actionid='.$actionid.
				' ORDER BY ocg.opcommand_grpid';
		$this->oldHashOpCommandGrp = DBhash($this->sqlHashOpCommandGrp);
		$this->sqlHashOpGroup =
			'SELECT og.* FROM opgroup og,operations o'.
			' WHERE og.operationid=o.operationid'.
				' AND o.actionid='.$actionid.
				' ORDER BY og.opgroupid';
		$this->oldHashOpGroup = DBhash($this->sqlHashOpGroup);
		$this->sqlHashOpTemplate =
			'SELECT ot.* FROM optemplate ot,operations o'.
			' WHERE ot.operationid=o.operationid'.
				' AND o.actionid='.$actionid.
				' ORDER BY ot.optemplateid';
		$this->oldHashOpTemplate = DBhash($this->sqlHashOpTemplate);
		$this->sqlHashOpConditions =
			'SELECT oc.* FROM opconditions oc,operations o'.
			' WHERE oc.operationid=o.operationid'.
				' AND o.actionid='.$actionid.
				' ORDER BY oc.opconditionid';
		$this->oldHashOpConditions = DBhash($this->sqlHashOpConditions);
	}

	private function verifyHash() {
		$this->assertEquals($this->oldHashAction, DBhash($this->sqlHashAction),
				'Chuck Norris: Action update changed data in table "actions".');
		$this->assertEquals($this->oldHashConditions, DBhash($this->sqlHashConditions),
				'Chuck Norris: Action update changed data in table "conditions".');
		$this->assertEquals($this->oldHashOperations, DBhash($this->sqlHashOperations),
				'Chuck Norris: Action update changed data in table "operations".');
		$this->assertEquals($this->oldHashOpMessage, DBhash($this->sqlHashOpMessage),
				'Chuck Norris: Action update changed data in table "opmessage".');
		$this->assertEquals($this->oldHashOpMessageGrp, DBhash($this->sqlHashOpMessageGrp),
				'Chuck Norris: Action update changed data in table "opmessage_grp".');
		$this->assertEquals($this->oldHashOpMessageUsr, DBhash($this->sqlHashOpMessageUsr),
				'Chuck Norris: Action update changed data in table "opmessage_usr".');
		$this->assertEquals($this->oldHashOpCommand, DBhash($this->sqlHashOpCommand),
				'Chuck Norris: Action update changed data in table "opcommand".');
		$this->assertEquals($this->oldHashOpCommandHst, DBhash($this->sqlHashOpCommandHst),
				'Chuck Norris: Action update changed data in table "opcommand_hst".');
		$this->assertEquals($this->oldHashOpCommandGrp, DBhash($this->sqlHashOpCommandGrp),
				'Chuck Norris: Action update changed data in table "opcommand_grp".');
		$this->assertEquals($this->oldHashOpGroup, DBhash($this->sqlHashOpGroup),
				'Chuck Norris: Action update changed data in table "opgroup".');
		$this->assertEquals($this->oldHashOpTemplate, DBhash($this->sqlHashOpTemplate),
				'Chuck Norris: Action update changed data in table "optemplate".');
		$this->assertEquals($this->oldHashOpConditions, DBhash($this->sqlHashOpConditions),
				'Chuck Norris: Action update changed data in table "opconditions".');
	}

	public static function allEventSources() {
		return [
			[EVENT_SOURCE_TRIGGERS],
			[EVENT_SOURCE_DISCOVERY],
			[EVENT_SOURCE_AUTO_REGISTRATION],
			[EVENT_SOURCE_INTERNAL]
		];
	}

	public static function allActions() {
		return DBdata(
			'SELECT actionid,eventsource,name,status'.
			' FROM actions'.
			' ORDER BY actionid'
		);
	}

	/**
	* @dataProvider allEventSources
	*/
	public function testPageActions_CheckLayout($eventsource) {
		$this->zbxTestLogin('actionconf.php?eventsource='.$eventsource);
		$this->zbxTestCheckTitle('Configuration of actions');

		$this->zbxTestCheckHeader('Actions');
		$this->zbxTestTextPresent('Event source');
		$this->zbxTestTextPresent('Displaying');

		$eventsources = [
			EVENT_SOURCE_TRIGGERS => 'Triggers',
			EVENT_SOURCE_DISCOVERY => 'Discovery',
			EVENT_SOURCE_AUTO_REGISTRATION => 'Auto registration',
			EVENT_SOURCE_INTERNAL => 'Internal'
		];

		$this->zbxTestDropdownAssertSelected('eventsource', $eventsources[$eventsource]);
		$this->zbxTestDropdownHasOptions('eventsource', $eventsources);

		$this->zbxTestTextPresent(['Name', 'Conditions', 'Operations', 'Status']);

		$dbResult = DBselect(
			'SELECT name,status'.
			' FROM actions'.
			' WHERE eventsource='.$eventsource.
			' ORDER BY actionid'
		);

		while ($dbRow = DBfetch($dbResult)) {
			$statusStr = ($dbRow['status'] == ACTION_STATUS_ENABLED ? 'Enabled' : 'Disabled');

			$this->zbxTestTextPresent([$dbRow['name'], $statusStr]);
		}

		$this->zbxTestTextPresent(['Enable', 'Disable', 'Delete']);
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActions_SimpleUpdate($action) {
		$this->calculateHash($action['actionid']);

		$this->zbxTestLogin('actionconf.php?eventsource='.$action['eventsource']);
		$this->zbxTestClickLinkText($action['name']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestTextPresent('Action updated');
		$this->zbxTestTextPresent($action['name']);

		$this->verifyHash();
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActions_SingleEnableDisable($action) {
		DBexecute("UPDATE usrgrp SET debug_mode = 0 WHERE usrgrpid = 7");

		$this->sqlHashAction = 'SELECT * FROM actions WHERE actionid<>'.$action['actionid'].' ORDER BY actionid';
		$this->oldHashAction = DBhash($this->sqlHashAction);

		$this->zbxTestLogin('actionconf.php?eventsource='.$action['eventsource']);
		$this->zbxTestCheckTitle('Configuration of actions');

		switch ($action['status']) {
			case ACTION_STATUS_ENABLED:
				$this->zbxTestClickXpathWait("//a[contains(@onclick,'actionid[]=".$action['actionid']."')]");
				$this->zbxTestTextPresent('Action disabled');
				$newStatus = ACTION_STATUS_DISABLED;
				break;
			case ACTION_STATUS_DISABLED:
				$this->zbxTestClickXpath("//a[contains(@onclick,'actionid[]=".$action['actionid']."')]");
				$this->zbxTestTextPresent('Action enabled');
				$newStatus = ACTION_STATUS_ENABLED;
				break;
			default:
				$this->assertTrue(false);
		}

		$this->zbxTestCheckTitle('Configuration of actions');

		$this->assertEquals(1, DBcount(
			'SELECT NULL'.
			' FROM actions'.
			' WHERE actionid='.$action['actionid'].
				' AND status='.$newStatus
		));

		$this->assertEquals($this->oldHashAction, DBhash($this->sqlHashAction));

		DBexecute("UPDATE usrgrp SET debug_mode = 1 WHERE usrgrpid = 7");
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActions_MassDisable($action) {
		$this->sqlHashAction = 'SELECT * FROM actions WHERE actionid<>'.$action['actionid'].' ORDER BY actionid';
		$this->oldHashAction = DBhash($this->sqlHashAction);

		$this->zbxTestLogin('actionconf.php?eventsource='.$action['eventsource']);
		$this->zbxTestCheckTitle('Configuration of actions');

		$this->zbxTestCheckboxSelect('g_actionid_'.$action['actionid']);
		$this->zbxTestClickButton('action.massdisable');

		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestTextPresent('Action disabled');
		$this->zbxTestTextPresent('Disabled');

		$this->assertEquals(1, DBcount(
			'SELECT NULL'.
			' FROM actions'.
			' WHERE actionid='.$action['actionid'].
				' AND status='.ACTION_STATUS_DISABLED
		));

		$this->assertEquals($this->oldHashAction, DBhash($this->sqlHashAction));
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActions_MassEnable($action) {
		$this->sqlHashAction = 'SELECT * FROM actions WHERE actionid<>'.$action['actionid'].' ORDER BY actionid';
		$this->oldHashAction = DBhash($this->sqlHashAction);

		$this->zbxTestLogin('actionconf.php?eventsource='.$action['eventsource']);
		$this->zbxTestCheckTitle('Configuration of actions');

		$this->zbxTestCheckboxSelect('g_actionid_'.$action['actionid']);
		$this->zbxTestClickButton('action.massenable');

		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestTextPresent('Action enabled');
		$this->zbxTestTextPresent('Enabled');

		$this->assertEquals(1, DBcount(
			'SELECT NULL'.
			' FROM actions'.
			' WHERE actionid='.$action['actionid'].
				' AND status='.ACTION_STATUS_ENABLED
		));

		$this->assertEquals($this->oldHashAction, DBhash($this->sqlHashAction));
	}

	public function testPageActions_Backup() {
		DBsave_tables('actions');
	}

	/**
	* @dataProvider allActions
	*/
	public function testPageActions_MassDelete($action) {
		$this->sqlHashAction = 'SELECT * FROM actions WHERE actionid<>'.$action['actionid'].' ORDER BY actionid';
		$this->oldHashAction = DBhash($this->sqlHashAction);

		$this->zbxTestLogin('actionconf.php?eventsource='.$action['eventsource']);
		$this->zbxTestCheckTitle('Configuration of actions');

		$this->zbxTestCheckboxSelect('g_actionid_'.$action['actionid']);
		$this->zbxTestClickButton('action.massdelete');

		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of actions');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Selected actions deleted');

		$this->assertEquals(0, DBcount('SELECT * FROM actions WHERE actionid='.$action['actionid']));

		$this->assertEquals($this->oldHashAction, DBhash($this->sqlHashAction));
	}

	public function testPageActions_Restore() {
		DBrestore_tables('actions');
	}

}
