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
require_once dirname(__FILE__).'/../traits/MacrosTrait.php';

class testPageReportsAudit extends CLegacyWebTest {

	use MacrosTrait;

	protected static $action_list = null;
	protected static $old_resourcetype = 0;

	private $actions = [
		'id:filter_actions_0' /* CAudit::ACTION_ADD */ => 'Add',
		'id:filter_actions_2' /* CAudit::ACTION_DELETE */ => 'Delete',
		'id:filter_actions_7' /* CAudit::ACTION_EXECUTE */ => 'Execute',
		'id:filter_actions_9' /* CAudit::ACTION_LOGIN_FAILED */ => 'Failed login',
		'id:filter_actions_10' /* CAudit::ACTION_HISTORY_CLEAR */ => 'History clear',
		'id:filter_actions_8' /* CAudit::ACTION_LOGIN_SUCCESS */ => 'Login',
		'id:filter_actions_4' /* CAudit::ACTION_LOGOUT */ => 'Logout',
		'id:filter_actions_1' /* CAudit::ACTION_UPDATE */ => 'Update'
	];

	private $resourcetypes = [
		-1 => 'All',
		5 /* CAudit::RESOURCE_ACTION */ => 'Action',
		38 /* CAudit::RESOURCE_AUTOREGISTRATION */ => 'Autoregistration',
		33 /* CAudit::RESOURCE_DASHBOARD */ => 'Dashboard',
		23 /* CAudit::RESOURCE_DISCOVERY_RULE */ => 'Discovery rule',
		34 /* CAudit::RESOURCE_CORRELATION */ => 'Event correlation',
		6 /* CAudit::RESOURCE_GRAPH */ => 'Graph',
		35 /* CAudit::RESOURCE_GRAPH_PROTOTYPE */ => 'Graph prototype',
		4 /* CAudit::RESOURCE_HOST */ => 'Host',
		14 /* CAudit::RESOURCE_HOST_GROUP */ => 'Host group',
		37 /* CAudit::RESOURCE_HOST_PROTOTYPEi */ => 'Host prototype',
		32 /* CAudit::RESOURCE_ICON_MAP */ => 'Icon mapping',
		16 /* CAudit::RESOURCE_IMAGE */ => 'Image',
		15 /* CAudit::RESOURCE_ITEM */ => 'Item',
		36 /* CAudit::RESOURCE_ITEM_PROTOTYPE */ => 'Item prototype',
		29 /* RESOURCE_MACRO */ => 'Macro',
		27 /* CAudit::RESOURCE_MAINTENANCE */ => 'Maintenance',
		19 /* CAudit::RESOURCE_MAP */ => 'Map',
		3 /* CAudit::RESOURCE_MEDIA_TYPE */ => 'Media type',
		39 /* CAudit::RESOURCE_MODULE */ => 'Module',
		26 /* CAudit::RESOURCE_PROXY */ => 'Proxy',
		28 /* CAudit::RESOURCE_REGEXP */ => 'Regular expression',
		25 /* CAudit::RESOURCE_SCRIPT */ => 'Script',
		18 /* CAudit::RESOURCE_IT_SERVICE */ => 'Service',
		30 /* CAudit::RESOURCE_TEMPLATE */ => 'Template',
		13 /* CAudit::RESOURCE_TRIGGER */ => 'Trigger',
		31 /* CAudit::RESOURCE_TRIGGER_PROTOTYPE */ => 'Trigger prototype',
		0 /* CAudit::RESOURCE_USER */ => 'User',
		11 /* CAudit::RESOURCE_USER_GROUP */ => 'User group',
		17 /* CAudit::RESOURCE_VALUE_MAP */ => 'Value map',
		22 /* CAudit::RESOURCE_SCENARIO */ => 'Web scenario'
	];

	public static function auditActions() {
		return [
			['action' => 'id:filter_actions_8' /* CAudit::ACTION_LOGIN */, 'resourcetype' => 0 /* CAudit::RESOURCE_USER */],
			['action' => 'id:filter_actions_4' /* CAudit::ACTION_LOGOUT */, 'resourcetype' => 0 /* CAudit::RESOURCE_USER */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 0 /* CAudit::RESOURCE_USER */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 0 /* CAudit::RESOURCE_USER */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 0 /* CAudit::RESOURCE_USER */],
			['action' => 'id:filter_actions_9' /* CAudit::ACTION_LOGIN_FAILED */, 'resourcetype' => 0 /* CAudit::RESOURCE_USER */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 4 /* CAudit::RESOURCE_HOST */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 4 /* CAudit::RESOURCE_HOST */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 4 /* CAudit::RESOURCE_HOST */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 14 /* CAudit::RESOURCE_HOST_GROUP */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 14 /* CAudit::RESOURCE_HOST_GROUP */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 14 /* CAudit::RESOURCE_HOST_GROUP */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 18 /* CAudit::RESOURCE_IT_SERVICE */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 18 /* CAudit::RESOURCE_IT_SERVICE */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 18 /* CAudit::RESOURCE_IT_SERVICE */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 16 /* CAudit::RESOURCE_IMAGE */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 16 /* CAudit::RESOURCE_IMAGE */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 16 /* CAudit::RESOURCE_IMAGE */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 15 /* CAudit::RESOURCE_ITEM */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 15 /* CAudit::RESOURCE_ITEM */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 15 /* CAudit::RESOURCE_ITEM */],
			['action' => 'id:filter_actions_10' /* CAudit::ACTION_HISTORY_CLEAR */, 'resourcetype' => 15 /* CAudit::RESOURCE_ITEM */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 13 /* CAudit::RESOURCE_TRIGGER */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 13 /* CAudit::RESOURCE_TRIGGER */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 13 /* CAudit::RESOURCE_TRIGGER */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 6 /* CAudit::RESOURCE_GRAPH */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 6 /* CAudit::RESOURCE_GRAPH */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 6 /* CAudit::RESOURCE_GRAPH */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 5 /* CAudit::RESOURCE_ACTION */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 5 /* CAudit::RESOURCE_ACTION */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 5 /* CAudit::RESOURCE_ACTION */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 23 /* CAudit::RESOURCE_DISCOVERY_RULE */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 23 /* CAudit::RESOURCE_DISCOVERY_RULE */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 23 /* CAudit::RESOURCE_DISCOVERY_RULE */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 29 /* RESOURCE_MACRO */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 29 /* RESOURCE_MACRO */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 29 /* RESOURCE_MACRO */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 27 /* CAudit::RESOURCE_MAINTENANCE */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 27 /* CAudit::RESOURCE_MAINTENANCE */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 27 /* CAudit::RESOURCE_MAINTENANCE */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 19 /* CAudit::RESOURCE_MAP */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 19 /* CAudit::RESOURCE_MAP */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 19 /* CAudit::RESOURCE_MAP */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 3 /* CAudit::RESOURCE_MEDIA_TYPE */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 3 /* CAudit::RESOURCE_MEDIA_TYPE */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 3 /* CAudit::RESOURCE_MEDIA_TYPE */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 28 /* CAudit::RESOURCE_REGEXP */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 28 /* CAudit::RESOURCE_REGEXP */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 28 /* CAudit::RESOURCE_REGEXP */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 22 /* CAudit::RESOURCE_SCENARIO */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 22 /* CAudit::RESOURCE_SCENARIO */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 22 /* CAudit::RESOURCE_SCENARIO */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 25 /* CAudit::RESOURCE_SCRIPT */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 25 /* CAudit::RESOURCE_SCRIPT */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 25 /* CAudit::RESOURCE_SCRIPT */],
			['action' => 'id:filter_actions_7' /* CAudit::ACTION_EXECUTE */, 'resourcetype' => 25 /* CAudit::RESOURCE_SCRIPT */],
			['action' => 'id:filter_actions_0' /* CAudit::ACTION_ADD */, 'resourcetype' => 17 /* CAudit::RESOURCE_VALUE_MAP */],
			['action' => 'id:filter_actions_1' /* CAudit::ACTION_UPDATE */, 'resourcetype' => 17 /* CAudit::RESOURCE_VALUE_MAP */],
			['action' => 'id:filter_actions_2' /* CAudit::ACTION_DELETE */, 'resourcetype' => 17 /* CAudit::RESOURCE_VALUE_MAP */]
		];
	}

	private function getFormAndActionList() {
		$this->page->login()->open('zabbix.php?action=auditlog.list')->waitUntilReady();
		$this->page->assertTitle('Audit log');
		$this->page->assertHeader('Audit log');
		$form = $this->query('name:zbx_filter')->asForm()->waitUntilVisible()->one();

		if (!($form->getField('Users')->isVisible())) {
			$this->query('id:ui-id-2')->waitUntilClickable()->one()->click();
		}

		if (self::$action_list === null) {
			self::$action_list = $this->query('id:filter-actions')->one()->asCheckboxList();
		}

		return $form;
	}

	public function testPageReportsAudit_CheckLayout() {
		$form = $this->getFormAndActionList();
		$this->assertEquals(['Users', 'Resource', 'Resource ID', 'Recordset ID', 'Actions'],$form->getLabels()->asText());
		$this->assertTrue(!array_diff($this->actions, self::$action_list->getLabels()->asText()));

		$filter_fields_limits = [
			'Resource ID' => 255,
			'Recordset ID' => 255
		];
		foreach ($filter_fields_limits as $field => $max_length) {
			$this->assertEquals($max_length, $form->getField($field)->getAttribute('maxlength'));
		}

		$this->zbxTestDropdownHasOptions('filter_resourcetype', $this->resourcetypes);

		foreach (['Apply', 'Reset'] as $button) {
			$this->assertTrue($this->query("xpath://div[@class=\"filter-forms\"]//button[text()=".
					CXPathHelper::escapeQuotes($button)."]")->one()->isClickable()
			);
		}
	}

	/**
	 * !!! This scenario is very questionable, it checks nothing. After Audit task is ready, please consider to
	 *  check filter results after submitting.
	 *
	 * @dataProvider auditActions
	 */
	public function testPageReportsAudit_Filter($action, $resourcetype) {
		$form = $this->getFormAndActionList();
		$form->getField('Users')->asMultiselect()->clear();
		$form->getField('Resource')->asDropdown()->select($this->resourcetypes[$resourcetype]);

		if (self::$old_resourcetype != $this->resourcetypes[$resourcetype]) {
			self::$action_list->uncheckAll();
		}

		self::$action_list->check($this->actions[$action]);
		$form->submit();
		$this->page->assertHeader('Audit log');
		self::$old_resourcetype = $this->resourcetypes[$resourcetype];
	}

	/**
	 * Check whether actions are enabled or disabled depending on the selected resource.
	 */
	public function testPageReportsAudit_ActionsState() {
		$form = $this->getFormAndActionList();
		$actions = self::$action_list->getCheckboxes();

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
					$this->assertEquals(['Add', 'Delete', 'Execute', 'Failed login', 'Login', 'Logout'], $disabled);
					break;

				case 'Discovery rule':
				case 'Host':
					$this->assertEquals(['Execute', 'Failed login','History clear', 'Login', 'Logout'], $disabled);
					break;

				case 'Image':
				case 'Event correlation':
					$this->assertEquals(['Add', 'Delete', 'Update'], $enabled);
					break;

				case 'Housekeeping':
				case 'Settings':
					$this->assertEquals(['Update'], $enabled);
					break;

				case 'Script':
					$this->assertEquals(['Failed login', 'History clear', 'Login', 'Logout'], $disabled);
					break;

				case 'User':
					$this->assertEquals(['Execute', 'History clear'], $disabled);
					break;

				case 'Item':
					$this->assertEquals(['Execute', 'Failed login', 'Login', 'Logout'], $disabled);
					break;

				default:
					$this->assertEquals(['Execute', 'Failed login', 'History clear', 'Login', 'Logout'], $disabled);
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
		// TODO after ZBX-19918 fix: remove line with long auditlog link and uncomment 2 lines below.
//		$this->page->open('zabbix.php?action=auditlog.list');
//		$this->query('button:Reset')->waitUntilVisible()->one()->click();
		$this->page->open('zabbix.php?action=auditlog.list&from=now-1h&to=now&filter_resourcetype='.
				'-1&filter_resourceid=11&filter_action=-1&filter_recordsetid=&filter_set=1');
		$rows = $this->query('class:list-table')->asTable()->one()->getRows();
		// Get first row data.
		$row = $rows->get(0);

		$audit = [
			'User' => 'Admin',
			'Resource' => 'Macro',
			'Action' => 'Update',
			'ID' => 11,
			'Details' => "Description: {\$1}\n\nusermacro.description: Test description 1 => New Updated Description"
		];

		foreach ($audit as $column => $value) {
			$text = $row->getColumnData($column, $value);
			$this->assertEquals($value, $text);
		}
	}
}
