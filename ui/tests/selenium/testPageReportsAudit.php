<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
require_once dirname(__FILE__).'/traits/MacrosTrait.php';

class testPageReportsAudit extends CLegacyWebTest {

	use MacrosTrait;

	private $actions = [
		-1 => 'All',
		CAudit::ACTION_ADD => 'Add',
		CAudit::ACTION_DELETE => 'Delete',
		CAudit::ACTION_EXECUTE => 'Execute',
		CAudit::ACTION_LOGIN => 'Login',
		CAudit::ACTION_LOGOUT => 'Logout',
		CAudit::ACTION_UPDATE => 'Update'
	];

	private $resourcetypes = [
		-1 => 'All',
		CAudit::RESOURCE_ACTION => 'Action',
		CAudit::RESOURCE_AUTOREGISTRATION => 'Autoregistration',
		CAudit::RESOURCE_DASHBOARD => 'Dashboard',
		CAudit::RESOURCE_DISCOVERY_RULE => 'Discovery rule',
		CAudit::RESOURCE_CORRELATION => 'Event correlation',
		CAudit::RESOURCE_GRAPH => 'Graph',
		CAudit::RESOURCE_GRAPH_PROTOTYPE => 'Graph prototype',
		CAudit::RESOURCE_HOST => 'Host',
		CAudit::RESOURCE_HOST_GROUP => 'Host group',
		CAudit::RESOURCE_HOST_PROTOTYPE => 'Host prototype',
		CAudit::RESOURCE_ICON_MAP => 'Icon mapping',
		CAudit::RESOURCE_IMAGE => 'Image',
		CAudit::RESOURCE_ITEM => 'Item',
		CAudit::RESOURCE_ITEM_PROTOTYPE => 'Item prototype',
		CAudit::RESOURCE_MACRO => 'Macro',
		CAudit::RESOURCE_MAINTENANCE => 'Maintenance',
		CAudit::RESOURCE_MAP => 'Map',
		CAudit::RESOURCE_MEDIA_TYPE => 'Media type',
		CAudit::RESOURCE_MODULE => 'Module',
		CAudit::RESOURCE_PROXY => 'Proxy',
		CAudit::RESOURCE_REGEXP => 'Regular expression',
		CAudit::RESOURCE_SCRIPT => 'Script',
		CAudit::RESOURCE_IT_SERVICE => 'Service',
		CAudit::RESOURCE_TEMPLATE => 'Template',
		CAudit::RESOURCE_TRIGGER => 'Trigger',
		CAudit::RESOURCE_TRIGGER_PROTOTYPE => 'Trigger prototype',
		CAudit::RESOURCE_USER => 'User',
		CAudit::RESOURCE_USER_GROUP => 'User group',
		CAudit::RESOURCE_VALUE_MAP => 'Value map',
		CAudit::RESOURCE_SCENARIO => 'Web scenario'
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
			['action' => CAudit::ACTION_LOGIN, 'resourcetype' => CAudit::RESOURCE_USER],
			['action' => CAudit::ACTION_LOGOUT, 'resourcetype' => CAudit::RESOURCE_USER],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_USER],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_USER],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_USER],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_HOST],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_HOST],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_HOST],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_HOST_GROUP],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_HOST_GROUP],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_HOST_GROUP],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_IT_SERVICE],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_IT_SERVICE],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_IT_SERVICE],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_IMAGE],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_ITEM],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_ITEM],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_ITEM],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_TRIGGER],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_TRIGGER],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_TRIGGER],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_GRAPH],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_GRAPH],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_GRAPH],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_ACTION],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_ACTION],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_ACTION],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_DISCOVERY_RULE],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_DISCOVERY_RULE],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_DISCOVERY_RULE],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_MACRO],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_MACRO],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_MACRO],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_MAINTENANCE],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_MAINTENANCE],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_MAINTENANCE],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_MAP],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_MAP],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_MAP],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_MEDIA_TYPE],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_MEDIA_TYPE],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_MEDIA_TYPE],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_REGEXP],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_REGEXP],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_REGEXP],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_SCENARIO],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_SCENARIO],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_SCENARIO],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_SCRIPT],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_SCRIPT],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_SCRIPT],
			['action' => CAudit::ACTION_ADD, 'resourcetype' => CAudit::RESOURCE_VALUE_MAP],
			['action' => CAudit::ACTION_UPDATE, 'resourcetype' => CAudit::RESOURCE_VALUE_MAP],
			['action' => CAudit::ACTION_DELETE, 'resourcetype' => CAudit::RESOURCE_VALUE_MAP]
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
					$this->assertEquals(['Add', 'Delete', 'Execute', 'Login', 'Logout'], $disabled);
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
					$this->assertEquals(['Login', 'Logout'], $disabled);
					break;

				case 'User':
					$this->assertEquals(['Execute'], $disabled);
					break;

				default:
					$this->assertEquals(['Execute', 'Login', 'Logout'], $disabled);
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
			'Details' => "usermacro.description: Test description 1 => New Updated Description"
		];

		foreach ($audit as $column => $value) {
			$text = $row->getColumnData($column, $value);
			$this->assertEquals($value, $text);
		}
	}
}
