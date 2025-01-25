<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


require_once dirname(__FILE__).'/../../include/CWebTest.php';

class testWidgets extends CWebTest {
	const HOST_ALL_ITEMS = 'Host for all item value types';
	const TABLE_SELECTOR = 'xpath://form[@name="itemform"]//table';
	const USER_MACRO = '{$USER.MACRO}';
	const USER_MACRO_VALUE = 'Macro function Test 12345';
	const USER_SECRET_MACRO = '{$SECRET.MACRO}';
	const MACRO_CHAR = '{$MACRO.CHAR}';
	const MACRO_HTML_ENCODE = '{$MACRO.HTML.ENCODE}';
	const MACRO_HTML_ENCODE_VALUE = '<a href="test.url">"test&"</a>';
	const MACRO_HTML_DECODE = '{$MACRO.HTML.DECODE}';
	const MACRO_HTML_DECODE_VALUE = '&lt;a href=&quot;test.url&quot;&gt;&quot;test&amp;&quot;&lt;/a&gt;';
	const MACRO_CHAR_VALUE = '000 ЙщфхжЖŽzŠsšĒĀīī🌴 ₰₰₰';
	const MACRO_URL_ENCODE = '{$MACRO.URL.ENCODE}';
	const MACRO_URL_ENCODE_VALUE = 'h://test.com/macro?functions=urlencode&urld=a🎸';
	const MACRO_URL_DECODE = '{$MACRO.URL.DECODE}';
	const MACRO_URL_DECODE_VALUE = 'h%3A%2F%2Ftest.com%2Fmacro%3Ffunctions%3Durlencode%26urld%3Da%F0%9F%8E%B8';

	protected static $dashboardid;

	/**
	 * Gets widget and widget_field tables to compare hash values, excludes widget_fieldid because it can change.
	 */
	const SQL = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid'.
			' ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_itemid,'.
			' wf.value_graphid, wf.value_hostid';

	/**
	 * Function which checks that only permitted item types are accessible for widgets.
	 *
	 * @param string    $url       url provided which needs to be opened
	 * @param string    $widget    name of widget type
	 */
	public function checkAvailableItems($url, $widget) {
		$this->page->login()->open($url)->waitUntilReady();

		// Open widget form dialog.
		$widget_dialog = CDashboardElement::find()->one()->waitUntilReady()->edit()->addWidget();
		$widget_form = $widget_dialog->asForm();
		$widget_form->fill(['Type' => CFormElement::RELOADABLE_FILL($widget)]);

		// Assign the dialog from where the last Select button will be clicked.
		$select_dialog = $widget_dialog;

		// Item types expected in items table. For the most cases theses are all items except of Binary and dependent.
		$item_types = (in_array($widget, ['Item navigator', 'Item history', 'Honeycomb', 'Top hosts']))
			? ['Binary item', 'Character item', 'Float item', 'Log item', 'Text item', 'Unsigned item', 'Unsigned_dependent item']
			: ['Character item', 'Float item', 'Log item', 'Text item', 'Unsigned item', 'Unsigned_dependent item'];

		switch ($widget) {
			case 'Top hosts':
			case 'Item history':
				$container = ($widget === 'Top hosts') ? 'Columns' : 'Items';
				$widget_form->getFieldContainer($container)->query('button:Add')->one()->waitUntilClickable()->click();
				$column_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$select_dialog = $column_dialog;
				break;

			case 'Clock':
				$widget_form->fill(['Time type' => CFormElement::RELOADABLE_FILL('Host time')]);
				$this->assertTrue($widget_form->getField('Item')->isVisible());
				break;

			case 'Graph':
			case 'Gauge':
			case 'Pie chart':
				// For Graph, Gauge and Pie chart only numeric items are available.
				$item_types = ['Float item', 'Unsigned item', 'Unsigned_dependent item'];
				break;

			case 'Graph prototype':
				$widget_form->fill(['Source' => 'Simple graph prototype']);
				$this->assertTrue($widget_form->getField('Item prototype')->isVisible());

				// For Graph prototype only numeric item prototypes are available.
				$item_types = ['Float item prototype', 'Unsigned item prototype', 'Unsigned_dependent item prototype'];
				break;

			case 'Honeycomb':
				$select_dialog = $widget_form->getFieldContainer('Item patterns');
				break;
		}

		if ($widget === 'Item navigator') {
			$select_button = 'xpath:(.//button[text()="Select"])[3]';
		}
		else {
			$select_button = ($widget === 'Graph' || $widget === 'Pie chart')
				? 'xpath:(.//button[text()="Select"])[2]'
				: 'button:Select';
		}

		$select_dialog->query($select_button)->one()->waitUntilClickable()->click();

		// Open the dialog where items will be tested.
		$items_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$this->assertEquals($widget === 'Graph prototype' ? 'Item prototypes' : 'Items', $items_dialog->getTitle());

		// Find the table where items will be expected.
		$table = $items_dialog->query(self::TABLE_SELECTOR)->asTable()->one()->waitUntilVisible();

		// Fill the host name and check the table.
		$items_dialog->query('class:multiselect-control')->asMultiselect()->one()->fill(self::HOST_ALL_ITEMS);
		$table->waitUntilReloaded();
		$this->assertTableDataColumn($item_types, 'Name', self::TABLE_SELECTOR);

		// Close all dialogs.
		$dialogs = COverlayDialogElement::find()->all();

		$dialog_count = $dialogs->count();
		for ($i = $dialog_count - 1; $i >= 0; $i--) {
			$dialogs->get($i)->close(true);
		}
	}

	/**
	 * Replace macro {date} with specified date in YYYY-MM-DD format for specified fields and for item data to be inserted in DB.
	 *
	 * @param array		$data				data provider
	 * @param string	$new_date			dynamic date that is converted into YYYY-MM-DD format and replaces the {date} macro
	 * @param array		$impacted_fields	array of fields that require to replace the {date} macro with a static date
	 *
	 * return array
	 */
	public function replaceDateMacroInData ($data, $new_date, $impacted_fields) {
		$new_date = date('Y-m-d', strtotime($new_date));

		if (array_key_exists('column_fields', $data)) {
			foreach ($data['column_fields'] as &$column) {
				foreach ($impacted_fields as $field) {
					$column[$field] = str_replace('{date}', $new_date, $column[$field]);
				}
			}
		}
		else {
			foreach ($impacted_fields as $field) {
				$data['fields'][$field] = str_replace('{date}', $new_date, $data['fields'][$field]);
			}
		}

		foreach ($data['item_data'] as &$item_value) {
			$item_value['time'] = str_replace('{date}', $new_date, $item_value['time']);
		}
		unset($item_value);

		return $data;
	}

	/**
	 * Check if row with entity name is highlighted on click.
	 *
	 * @param string		$widget_name		widget name
	 * @param string		$entity_name		name of item or host
	 * @param boolean 		$dashboard_edit		true if dashboard is in edit mode
	 */
	public function checkRowHighlight($widget_name, $entity_name, $dashboard_edit = false) {
		$widget = $dashboard_edit
			? CDashboardElement::find()->one()->edit()->getWidget($widget_name)
			: CDashboardElement::find()->one()->getWidget($widget_name);

		$widget->waitUntilReady();
		$locator = 'xpath://div[contains(@class,"node-is-selected")]';
		$this->assertFalse($widget->query($locator)->one(false)->isValid());
		$widget->query('xpath://span[@title="'.$entity_name.'"]')->waitUntilReady()->one()->click();
		$this->assertTrue($widget->query($locator)->one()->isVisible());
	}

	/**
	 * Opens widget edit form and fills in data.
	 *
	 * @param string		$dashboardid		dashboard id
	 * @param string		$widget_name		widget name
	 * @param array			$configuration    	widget parameter(s)
	 */
	public function setWidgetConfiguration($dashboardid, $widget_name, $configuration = []) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();
		$form = $dashboard->getWidget($widget_name)->edit()->asForm();
		$form->fill($configuration);
		$form->submit();
	}

	/**
	 * Function for deletion widgets from test dashboard after case.
	 */
	public static function deleteWidgets() {
		DBexecute('DELETE FROM widget'.
			' WHERE dashboard_pageid'.
			' IN (SELECT dashboard_pageid'.
				' FROM dashboard_page'.
				' WHERE dashboardid='.zbx_dbstr(static::$dashboardid).
			')'
		);
	}

	/**
	 * Get the number of interfaces by their type or status, depending on the layout of the Host availability widget.
	 * For horizontal layout interface type is passed to the function, but for vertical layout - interface status.
	 *
	 * @param array		$data    	widget configuration
	 * @param string	$header		host availability widget column header
	 *
	 * @return array
	 */
	public function getExpectedInterfaceCountFromDB($data, $header) {
		$db_interfaces = [
			'type' => [
				'Agent (active)' => 5,
				'Agent (passive)' => 1,
				'SNMP' => 2,
				'IPMI' => 3,
				'JMX' => 4
			],
			'interface' => [
				'Agent (active)' => 'available',
				'Agent (passive)' => 'available',
				'SNMP' => 'available',
				'IPMI' => 'available',
				'JMX' => 'available'
			],
			'status' => [
				'Unknown' => 0,
				'Available' => 1,
				'Not available' => 2
			]
		];
		// Select unique hostids for certain type of interfaces
		$interfaces_sql = 'SELECT DISTINCT(hostid) FROM interface WHERE type IN (';
		// Select hostids for host entries that are not templates or proxies and that are not host prototypes.
		$hosts_sql = 'SELECT hostid FROM hosts WHERE status IN (0,1) AND flags!=2';
		// Construct sql for horizontal widget layout.
		if (CTestArrayHelper::get($data, 'Layout', 'Horizontal') === 'Horizontal') {
			$total_sql = $interfaces_sql.$db_interfaces['type'][$header].') AND hostid IN ('.$hosts_sql;
			// Filter out hosts in maintenance if the flag 'Include hosts in maintenance' is not set.
			if (CTestArrayHelper::get($data, 'Include hosts in maintenance', false) === false) {
				$total_sql = $total_sql.' AND maintenance_status=0';
			}

			if ($header === 'Agent (active)') {
				$db_values = [
					'Available' => '0',
					'Not available' => '0',
					'Mixed' => '-',
					'Unknown' => '0',
					'Total' => '0'
				];
			}
			else {
				// Add interface status flag. Possible values: 0 - unknown, 1 - available, 2 - not available.
				$db_values = [
					'Available' => CDBHelper::getCount($total_sql.' AND '.$db_interfaces['interface'][$header].'=1)'),
					'Not available' => CDBHelper::getCount($total_sql.' AND '.$db_interfaces['interface'][$header].'=2)'),
					'Mixed' => '0',
					'Unknown' => CDBHelper::getCount($total_sql.' AND '.$db_interfaces['interface'][$header].'=0)'),
					'Total' => CDBHelper::getCount($total_sql.')')
				];
			}
		}
		// Construct sql for vertical widget layout.
		else {
			// Filter out hosts in maintenance if the flag 'Include hosts in maintenance' is not set.
			if (CTestArrayHelper::get($data, 'Include hosts in maintenance', false) === false) {
				$hosts_sql = $hosts_sql.' AND maintenance_status=0';
			}
			// The SQL for Total interface number doesn't use interface status and needs to be constructed separately.
			if ($header === 'Total'){
				$db_values = [
					'Total Hosts' => CDBHelper::getCount($interfaces_sql.'1,2,3,4) AND hostid IN ('.$hosts_sql.')'),
					'Agent (passive)' => CDBHelper::getCount($interfaces_sql.'1) AND hostid IN ('.$hosts_sql.')'),
					'SNMP' => CDBHelper::getCount($interfaces_sql.'2) AND hostid IN ('.$hosts_sql.')'),
					'IPMI' => CDBHelper::getCount($interfaces_sql.'3) AND hostid IN ('.$hosts_sql.')'),
					'JMX' => CDBHelper::getCount($interfaces_sql.'4) AND hostid IN ('.$hosts_sql.')')
				];
			}
			else {
				// Add interface status flag based on interface type.
				if ($header === 'Mixed') {
					$db_values = [
						'Total Hosts' => '0',
						'Agent (passive)' => '0',
						'SNMP' => '0',
						'IPMI' => '0',
						'JMX' => '0'
					];
				}
				else {
					$db_values = [
						'Total Hosts' => CDBHelper::getCount($interfaces_sql.'1,2,3,4) AND available='.
								$db_interfaces['status'][$header].' AND hostid IN ('.$hosts_sql.')'),
						'Agent (passive)' => CDBHelper::getCount($interfaces_sql.'1) AND available='.
								$db_interfaces['status'][$header].' AND hostid IN ('.$hosts_sql.')'),
						'SNMP' => CDBHelper::getCount($interfaces_sql.'2) AND available='.
								$db_interfaces['status'][$header].' AND hostid IN ('.$hosts_sql.')'),
						'IPMI' => CDBHelper::getCount($interfaces_sql.'3) AND available='.
								$db_interfaces['status'][$header].' AND hostid IN ('.$hosts_sql.')'),
						'JMX' => CDBHelper::getCount($interfaces_sql.'4) AND available='.
								$db_interfaces['status'][$header].' AND hostid IN ('.$hosts_sql.')')
					];
				}
			}
		}

		return $db_values;
	}

	/**
	 * Assert range input attributes.
	 *
	 * @param CFormElement $form               parent form
	 * @param string       $field              id or label of the range input
	 * @param array        $expected_values    the attribute values expected
	 */
	public function assertRangeSliderParameters($form, $field, $expected_values) {
		$path = (COverlayDialogElement::find()->one()->asForm()->getField('Type')->getText() == 'Pie chart')
			? 'xpath:.//'
			: 'xpath://div/';
		$range = $form->getField($field)->query($path.'input[@type="range"]')->one();

		foreach ($expected_values as $attribute => $expected_value) {
			$this->assertEquals($expected_value, $range->getAttribute($attribute));
		}
	}
}
