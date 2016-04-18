<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

class testPageTemplates extends CWebTest {
	// Returns all templates
	public static function allTemplates() {
		return DBdata("select * from hosts where status in (".HOST_STATUS_TEMPLATE.')');
	}

	/**
	* @dataProvider allTemplates
	*/
	public function testPageTemplates_CheckLayout($template) {
		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'Templates');
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('TEMPLATES');
		$this->zbxTestTextPresent('Displaying');

		// header
		$this->zbxTestTextPresent(array('Templates', 'Applications', 'Items', 'Triggers', 'Graphs', 'Screens', 'Discovery', 'Linked templates', 'Linked to'));

		// data
		$this->zbxTestTextPresent(array($template['name']));
		$this->zbxTestDropdownHasOptions('go',
				array('Export selected', 'Delete selected', 'Delete selected with linked elements'));
	}

	/**
	* @dataProvider allTemplates
	*/
	public function testPageTemplates_SimpleUpdate($template) {
		$host = $template['host'];
		$name = $template['name'];

		$sqlTemplate = "select * from hosts where host='$host'";
		$oldHashTemplate = DBhash($sqlTemplate);
		$sqlHosts = "select * from hosts order by hostid";
		$oldHashHosts = DBhash($sqlHosts);
		$sqlItems = "select * from items order by itemid";
		$oldHashItems = DBhash($sqlItems);
		$sqlTriggers = "select * from triggers order by triggerid";
		$oldHashTriggers = DBhash($sqlTriggers);

		$this->zbxTestLogin('templates.php');
		$this->zbxTestDropdownSelectWait('groupid', 'all');

		$this->checkTitle('Configuration of templates');

		$this->zbxTestTextPresent($name); // link is present on the screen?
		$this->zbxTestClickWait('link='.$name);
		$this->zbxTestClickWait('save');
		$this->checkTitle('Configuration of templates');
		$this->zbxTestTextPresent('Template updated');
		$this->zbxTestTextPresent("$name");
		$this->zbxTestTextPresent('TEMPLATES');

		$this->assertEquals($oldHashTemplate, DBhash($sqlTemplate));
		$this->assertEquals($oldHashHosts, DBhash($sqlHosts));
		$this->assertEquals($oldHashItems, DBhash($sqlItems));
		$this->assertEquals($oldHashTriggers, DBhash($sqlTriggers));
	}

	public function testPageTemplates_Create() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_Import() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_MassExportAll() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_MassExport() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_MassDeleteAll() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_MassDelete() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_MassDeleteWithLinkedElementsAll() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_MassDeleteWithLinkedElements() {
// TODO
		$this->markTestIncomplete();
	}

	public function testPageTemplates_Sorting() {
// TODO
		$this->markTestIncomplete();
	}
}
