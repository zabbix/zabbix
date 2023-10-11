<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/behaviors/CMacrosBehavior.php';

class testPageReportsAudit extends CLegacyWebTest {

	/**
	 * Attach Behavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMacrosBehavior::class];
	}

	private $actions = [
		-1 => 'All',
		AUDIT_ACTION_ADD => 'Add',
		AUDIT_ACTION_DELETE => 'Delete',
		AUDIT_ACTION_DISABLE => 'Disable',
		AUDIT_ACTION_ENABLE => 'Enable',
		AUDIT_ACTION_EXECUTE => 'Execute',
		AUDIT_ACTION_LOGIN => 'Login',
		AUDIT_ACTION_LOGOUT => 'Logout',
		AUDIT_ACTION_UPDATE => 'Update'
	];

	private $resourcetypes = [
		-1 => 'All',
		AUDIT_RESOURCE_ACTION => 'Action',
		AUDIT_RESOURCE_APPLICATION => 'Application',
		AUDIT_RESOURCE_AUTOREGISTRATION => 'Autoregistration',
		AUDIT_RESOURCE_ZABBIX_CONFIG => 'Configuration of Zabbix',
		AUDIT_RESOURCE_DASHBOARD => 'Dashboard',
		AUDIT_RESOURCE_DISCOVERY_RULE => 'Discovery rule',
		AUDIT_RESOURCE_CORRELATION => 'Event correlation',
		AUDIT_RESOURCE_GRAPH => 'Graph',
		AUDIT_RESOURCE_GRAPH_ELEMENT => 'Graph element',
		AUDIT_RESOURCE_GRAPH_PROTOTYPE => 'Graph prototype',
		AUDIT_RESOURCE_HOST => 'Host',
		AUDIT_RESOURCE_HOST_GROUP => 'Host group',
		AUDIT_RESOURCE_HOST_PROTOTYPE => 'Host prototype',
		AUDIT_RESOURCE_ICON_MAP => 'Icon mapping',
		AUDIT_RESOURCE_IMAGE => 'Image',
		AUDIT_RESOURCE_ITEM => 'Item',
		AUDIT_RESOURCE_ITEM_PROTOTYPE => 'Item prototype',
		AUDIT_RESOURCE_MACRO => 'Macro',
		AUDIT_RESOURCE_MAINTENANCE => 'Maintenance',
		AUDIT_RESOURCE_MAP => 'Map',
		AUDIT_RESOURCE_MEDIA_TYPE => 'Media type',
		AUDIT_RESOURCE_MODULE => 'Module',
		AUDIT_RESOURCE_PROXY => 'Proxy',
		AUDIT_RESOURCE_REGEXP => 'Regular expression',
		AUDIT_RESOURCE_SCREEN => 'Screen',
		AUDIT_RESOURCE_SCRIPT => 'Script',
		AUDIT_RESOURCE_IT_SERVICE => 'Service',
		AUDIT_RESOURCE_SLIDESHOW => 'Slide show',
		AUDIT_RESOURCE_TEMPLATE => 'Template',
		AUDIT_RESOURCE_TRIGGER => 'Trigger',
		AUDIT_RESOURCE_TRIGGER_PROTOTYPE => 'Trigger prototype',
		AUDIT_RESOURCE_USER => 'User',
		AUDIT_RESOURCE_USER_GROUP => 'User group',
		AUDIT_RESOURCE_VALUE_MAP => 'Value map',
		AUDIT_RESOURCE_SCENARIO => 'Web scenario'
	];

	public function testPageReportsAudit_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=auditlog.list');
		$this->zbxTestCheckTitle('Audit log');
		$this->zbxTestAssertElementPresentId('config');

		$this->zbxTestCheckHeader('Audit log');
		$this->zbxTestTextPresent(['Time', 'User', 'IP', 'Resource', 'Action', 'ID', 'Description', 'Details']);
		$this->zbxTestExpandFilterTab();
		$this->zbxTestAssertElementPresentId('filter_userids__ms');
		$this->zbxTestAssertElementPresentId('filter_resourceid');
		$this->zbxTestAssertElementPresentXpath("//input[@id='filter_resourceid' and @maxlength='255']");

		$this->zbxTestDropdownHasOptions('filter_action', $this->actions);
		$this->zbxTestDropdownHasOptions('filter_resourcetype', $this->resourcetypes);
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
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_IMAGE],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_ITEM],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_ITEM],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_ITEM],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_TRIGGER],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_TRIGGER],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_TRIGGER],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_GRAPH],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_GRAPH],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_GRAPH],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_ACTION],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_ACTION],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_ACTION],
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
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_REGEXP],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_REGEXP],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_REGEXP],
			['action' => AUDIT_ACTION_ADD, 'resourcetype' => AUDIT_RESOURCE_SCENARIO],
			['action' => AUDIT_ACTION_UPDATE, 'resourcetype' => AUDIT_RESOURCE_SCENARIO],
			['action' => AUDIT_ACTION_DELETE, 'resourcetype' => AUDIT_RESOURCE_SCENARIO],
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
	public function testPageReportsAudit_Filter($action, $resourcetype) {
		$this->zbxTestLogin('zabbix.php?action=auditlog.list');
		$this->zbxTestCheckTitle('Audit log');
		$this->zbxTestAssertElementPresentId('config');

		$this->zbxTestExpandFilterTab();
		$this->zbxTestMultiselectClear('filter_userids_');
		$this->zbxTestDropdownSelectWait('filter_resourcetype', $this->resourcetypes[$resourcetype]);
		$this->zbxTestDropdownSelectWait('filter_action', $this->actions[$action]);

		$this->zbxTestClickXpathWait("//form[@name='zbx_filter']//button[@name='filter_set']");
		$this->zbxTestCheckHeader('Audit log');
	}

	/**
	 * Check whether actions are enabled or disabled depending on the selected resource.
	 */
	public function testPageReportsAudit_ActionsState() {
		$this->page->login()->open('zabbix.php?action=auditlog.list');
		$form = $this->query('name:zbx_filter')->asForm()->one();

		$actions = $form->getField('Action')->getOptions();
		foreach ($this->resourcetypes as $type) {
			$form->fill(['Resource' => $type]);
			$enabled = array_values($actions->filter(new CElementFilter(CElementFilter::ATTRIBUTES_NOT_PRESENT, ['disabled']))->asText());
			$disabled = array_values($actions->filter(new CElementFilter(CElementFilter::ATTRIBUTES_PRESENT, ['disabled']))->asText());

			switch ($type) {
				case 'All':
					$this->assertEquals(array_values($this->actions), $enabled);
					break;

				case 'Authentication':
				case 'Configuration of Zabbix':
					$this->assertEquals(['Add', 'Delete', 'Disable', 'Enable', 'Execute', 'Login', 'Logout'], $disabled);
					break;

				case 'Discovery rule':
				case 'Host':
					$this->assertEquals(['Execute', 'Login', 'Logout'], $disabled);
					break;

				case 'Event correlation':
					$this->assertEquals(['All', 'Delete'], $enabled);
					break;

				case 'Housekeeping':
				case 'Image':
				case 'Settings':
					$this->assertEquals(['All', 'Update'], $enabled);
					break;

				case 'Script':
					$this->assertEquals(['Disable', 'Enable', 'Login', 'Logout'], $disabled);
					break;

				case 'User':
					$this->assertEquals(['Disable', 'Enable', 'Execute'], $disabled);
					break;

				default:
					$this->assertEquals(['Disable', 'Enable', 'Execute', 'Login', 'Logout'], $disabled);
			}
		}
	}

	/**
	 * @backupOnce globalmacro
	 */
	public function testPageReportsAudit_UpdateMacroDescription() {
		// Update Macro description.
		$this->page->login()->open('zabbix.php?action=macros.edit');
		$form = $this->query('name:macrosForm')->asForm()->one();

		$macros = [
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 0,
				'description' => 'New Updated Description'
			]
		];

		$this->fillMacros($macros);
		$form->submit();
		$message = CMessageElement::find()->waitUntilVisible()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Macros updated', $message->getTitle());

		// Check Audit record about global macro update.
		$this->page->open('zabbix.php?action=auditlog.list');
		$this->query('button:Reset')->waitUntilVisible()->one()->click();
		$rows = $this->query('class:list-table')->asTable()->one()->getRows();
		// Get first row data.
		$row = $rows->get(0);

		$audit = [
			'User' => 'Admin',
			'Resource' => 'Macro',
			'Action' => 'Update',
			'ID' => 11,
			'Details' => "globalmacro.description: Test description 1 => New Updated Description"
		];

		foreach ($audit as $column => $value) {
			$text = $row->getColumnData($column, $value);
			$this->assertEquals($value, $text);
		}
	}
}
