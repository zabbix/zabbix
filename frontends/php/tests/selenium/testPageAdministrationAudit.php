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

	private $actions = [
		-1 => 'All',
		AUDIT_ACTION_LOGIN => 'Login',
		AUDIT_ACTION_LOGOUT => 'Logout',
		AUDIT_ACTION_ADD => 'Add',
		AUDIT_ACTION_UPDATE => 'Update',
		AUDIT_ACTION_DELETE => 'Delete',
		AUDIT_ACTION_ENABLE => 'Enable',
		AUDIT_ACTION_DISABLE => 'Disable'
	];

	private $resourcetypes = [
		-1 => 'All',
		AUDIT_RESOURCE_ACTION => 'Action',
		AUDIT_RESOURCE_APPLICATION => 'Application',
		AUDIT_RESOURCE_ZABBIX_CONFIG => 'Configuration of Zabbix',
		AUDIT_RESOURCE_DISCOVERY_RULE => 'Discovery rule',
		AUDIT_RESOURCE_GRAPH => 'Graph',
		AUDIT_RESOURCE_GRAPH_ELEMENT => 'Graph element',
		AUDIT_RESOURCE_HOST => 'Host',
		AUDIT_RESOURCE_HOST_GROUP => 'Host group',
		AUDIT_RESOURCE_IT_SERVICE => 'IT service',
		AUDIT_RESOURCE_IMAGE => 'Image',
		AUDIT_RESOURCE_ITEM => 'Item',
		AUDIT_RESOURCE_MACRO => 'Macro',
		AUDIT_RESOURCE_MAINTENANCE => 'Maintenance',
		AUDIT_RESOURCE_MAP => 'Map',
		AUDIT_RESOURCE_MEDIA_TYPE => 'Media type',
		AUDIT_RESOURCE_PROXY => 'Proxy',
		AUDIT_RESOURCE_REGEXP => 'Regular expression',
		AUDIT_RESOURCE_SCENARIO => 'Web scenario',
		AUDIT_RESOURCE_SCREEN => 'Screen',
		AUDIT_RESOURCE_SCRIPT => 'Script',
		AUDIT_RESOURCE_SLIDESHOW => 'Slide show',
		AUDIT_RESOURCE_TEMPLATE => 'Template',
		AUDIT_RESOURCE_TRIGGER => 'Trigger',
		AUDIT_RESOURCE_TRIGGER_PROTOTYPE => 'Trigger prototype',
		AUDIT_RESOURCE_USER => 'User',
		AUDIT_RESOURCE_USER_GROUP => 'User group',
		AUDIT_RESOURCE_VALUE_MAP => 'Value map'
	];

	public function testPageAdministrationAudit_CheckLayout() {
		$this->zbxTestLogin('auditlogs.php');
		$this->zbxTestCheckTitle('Audit log');
		$this->zbxTestAssertElementPresentId('config');

		$this->zbxTestCheckHeader('Audit log');
		$this->zbxTestTextPresent(['Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details']);

		$this->zbxTestAssertElementPresentId('alias');
		$this->zbxTestAssertElementPresentXpath("//input[@id='alias' and @maxlength='255']");
		$this->zbxTestAssertElementPresentId('btn1');

		$this->zbxTestDropdownHasOptions('action', $this->actions);
		$this->zbxTestDropdownHasOptions('resourcetype', $this->resourcetypes);
	}

	public static function auditActions() {
		return [
			['action' => AUDIT_ACTION_LOGIN, 'resourcetype' => AUDIT_RESOURCE_USER],
			['action' => AUDIT_ACTION_LOGOUT, 'resourcetype' => AUDIT_RESOURCE_USER],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_USER],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_USER],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_USER],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_HOST],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_HOST],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_HOST],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_HOST_GROUP],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_HOST_GROUP],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_HOST_GROUP],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_IT_SERVICE],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_IT_SERVICE],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_IT_SERVICE],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_IMAGE],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_IMAGE],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_IMAGE],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_ITEM],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_ITEM],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_ITEM],
			['action' => AUDIT_ACTION_DISABLE, 'resourcetype' => AUDIT_RESOURCE_ITEM],
			['action' => AUDIT_ACTION_ENABLE, 'resourcetype' => AUDIT_RESOURCE_ITEM],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_TRIGGER],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_TRIGGER],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_TRIGGER],
			['action' => AUDIT_ACTION_DISABLE, 'resourcetype' => AUDIT_RESOURCE_TRIGGER],
			['action' => AUDIT_ACTION_ENABLE, 'resourcetype' => AUDIT_RESOURCE_TRIGGER],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_GRAPH],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_GRAPH],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_GRAPH],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_ACTION],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_ACTION],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_ACTION],
			['action' => AUDIT_ACTION_DISABLE, 'resourcetype' => AUDIT_RESOURCE_ACTION],
			['action' => AUDIT_ACTION_ENABLE, 'resourcetype' => AUDIT_RESOURCE_ACTION],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_APPLICATION],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_APPLICATION],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_APPLICATION],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_DISCOVERY_RULE],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_DISCOVERY_RULE],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_DISCOVERY_RULE],
			['action' => AUDIT_ACTION_DISABLE, 'resourcetype' => AUDIT_RESOURCE_DISCOVERY_RULE],
			['action' => AUDIT_ACTION_ENABLE, 'resourcetype' => AUDIT_RESOURCE_DISCOVERY_RULE],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_MACRO],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_MACRO],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_MACRO],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_MAINTENANCE],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_MAINTENANCE],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_MAINTENANCE],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_MAP],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_MAP],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_MAP],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_MEDIA_TYPE],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_MEDIA_TYPE],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_MEDIA_TYPE],
			['action' => AUDIT_ACTION_DISABLE, 'resourcetype' => AUDIT_RESOURCE_MEDIA_TYPE],
			['action' => AUDIT_ACTION_ENABLE, 'resourcetype' => AUDIT_RESOURCE_MEDIA_TYPE],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_REGEXP],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_REGEXP],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_REGEXP],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_SCENARIO],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_SCENARIO],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_SCENARIO],
			['action' => AUDIT_ACTION_DISABLE, 'resourcetype' => AUDIT_RESOURCE_SCENARIO],
			['action' => AUDIT_ACTION_ENABLE, 'resourcetype' => AUDIT_RESOURCE_SCENARIO],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_SCREEN],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_SCREEN],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_SCREEN],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_SCRIPT],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_SCRIPT],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_SCRIPT],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_SLIDESHOW],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_SLIDESHOW],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_SLIDESHOW],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_VALUE_MAP],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_VALUE_MAP],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_VALUE_MAP],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_ZABBIX_CONFIG]
		];
	}

	/**
	* @dataProvider auditActions
	*/
	public function testPageAdministrationAudit_Filter($action, $resourcetype) {
		$this->zbxTestLogin('auditlogs.php');
		$this->zbxTestCheckTitle('Audit log');
		$this->zbxTestAssertElementPresentId('config');

		$this->zbxTestInputType('alias', '');
		$this->zbxTestDropdownSelect('action', $this->actions[$action]);
		$this->zbxTestDropdownSelect('resourcetype', $this->resourcetypes[$resourcetype]);

		$this->zbxTestClickWait('filter_set');
		$this->zbxTestCheckHeader('Audit log');
	}

}
