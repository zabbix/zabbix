<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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

	protected static $dashboardid;
	protected static $acktime;
	protected static $time;

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

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
		$item_types = (in_array($widget, ['Item navigator', 'Item history', 'Honeycomb']))
			? ['Binary item', 'Character item', 'Float item', 'Log item', 'Text item', 'Unsigned item', 'Unsigned_dependent item']
			: ['Character item', 'Float item', 'Log item', 'Text item', 'Unsigned item', 'Unsigned_dependent item'];

		switch ($widget) {
			case 'Top hosts':
			case 'Item history':
				$widget_form->getFieldContainer('Columns')->query('button:Add')->one()->waitUntilClickable()->click();
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
	 * Function which checks widget contents depending on its settings.
	 *
	 * @param string    $data       data provider
	 * @param string    $widget     widget type
	 * @param array     $headers    array of headers in widget table
	 */
	protected function checkWidgetDisplay($data, $widget, $headers) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.static::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dialog = CDashboardElement::find()->one()->edit()->addWidget();
		$form = $dialog->asForm();

		// Fill widget filter.
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL($widget)]);
		$form->fill($data['fields']);

		if (array_key_exists('Tags', $data)) {
			$form->getField('id:evaltype')->fill(CTestArrayHelper::get($data['Tags'], 'evaluation', 'And/Or'));
			$form->getField('id:tags_table_tags')->asMultifieldTable()->fill($data['Tags']['tags']);
		}

		// Fill Columns field.
		if (array_key_exists('Columns', $data)) {
			foreach ($data['Columns'] as $column) {
				$form->getFieldContainer('Columns')->query('button:Add')->one()->waitUntilClickable()->click();
				$column_overlay = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$column_overlay_form = $column_overlay->asForm();
				$column_overlay_form->fill($column['fields']);

				foreach (['Highlights', 'Thresholds'] as $table_field) {
					if (array_key_exists($table_field, $column)) {
						foreach ($column[$table_field] as $highlight) {
							$column_overlay_form->getFieldContainer($table_field)->query('button:Add')->one()
									->waitUntilClickable()->click();
							$column_overlay_form->fill($highlight);
						}
					}
				}

				$column_overlay->getFooter()->query('button:Add')->waitUntilClickable()->one()->click();

				if (array_key_exists('column_error', $data)) {
					break;
				}

				$column_overlay->waitUntilNotVisible();
				$form->waitUntilReloaded();
			}
		}

		$form->submit();

		// Check saved dashboard.
		$dialog->ensureNotPresent();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Assert widget's table.
		$dashboard->getWidget($data['fields']['Name'])->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		// Change time for actual value, because it cannot be used in data provider.
		if ($widget === 'Problems') {
			foreach ($data['result'] as &$row) {
				if (CTestArrayHelper::get($row, 'Time')) {
					$row['Time'] = date('H:i:s', static::$time);
				}
				unset($row);
			}

			// Check clicks on Acknowledge and Actions icons and hints' contents.
			if (CTestArrayHelper::get($data, 'actions')) {
				foreach ($data['actions'] as $problem => $action) {
					$action_cell = $table->findRow('Problem • Severity', $problem)->getColumn('Actions');

					foreach ($action as $class => $hint_rows) {
						$icon = $action_cell->query('xpath:.//*['.CXPathHelper::fromClass($class).']')->one();
						$this->assertTrue($icon->isVisible());

						if ($class !== 'color-positive') {
							// Click on icon and open hint.
							$icon->click();
							$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()
									->waitUntilReady()->one();
							$hint_table = $hint->query('class:list-table')->asTable()->waitUntilVisible()->one();

							// Check rows in hint's table.
							foreach ($hint_table->getRows() as $i => $row) {
								$hint_rows[$i]['Time'] = ($hint_rows[$i]['Time'] === 'acknowledged')
									? date('Y-m-d H:i:s', static::$acktime)
									: date('Y-m-d H:i:s', static::$time);
								$row->assertValues($hint_rows[$i]);
							}

							$hint->close();
						}
					}
				}
			}

			if (CTestArrayHelper::get($data['fields'], 'Show timeline')) {
				$this->assertTrue($table->query('class:timeline-td')->exists());
			}

			if (CTestArrayHelper::get($data, 'check_tag_ellipsis')) {
				foreach ($data['check_tag_ellipsis'] as $problem => $ellipsis_text) {
					$table->findRow('Problem • Severity', $problem)->getColumn('Tags')->query('class:zi-more')
							->waitUntilClickable()->one()->click();
					$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()
							->waitUntilVisible()->one();
					$this->assertEquals($ellipsis_text, $hint->getText());
					$hint->close();
				}
			}

			// Check eye icon for suppressed problem.
			if (CTestArrayHelper::get($data, 'check_suppressed_icon')) {
				$table->findRow('Problem • Severity', $data['check_suppressed_icon']['problem'])->getColumn('Info')
						->query('class:zi-eye-off')->waitUntilClickable()->one()->click();
				$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()
						->waitUntilVisible()->one();
				$this->assertEquals($data['check_suppressed_icon']['text'], $hint->getText());
				$hint->close();
			}
		}

		// When there are shown fewer lines than filtered, table appears unusual and doesn't fit for framework functions.
		if (CTestArrayHelper::get($data['fields'], 'Show lines')) {
			$this->assertEquals(count($data['result']) + 1, $table->getRows()->count());

			// Assert table rows.
			$result = [];
			for ($i = 0; $i < count($data['result']); $i++) {
				$result[] = $table->getRow($i)->getColumn('Problem • Severity')->getText();
			}

			$this->assertEquals($data['result'], $result);

			// Assert table stats.
			$this->assertEquals($data['stats'], $table->getRow(count($data['result']))->getText());
		}
		elseif (empty($data['result'])) {
			$this->assertTableData();
		}
		else {
			$this->assertTableHasData($data['result']);
		}

		// Assert table headers depending on widget settings.
		$this->assertEquals($headers, $table->getHeadersText());
	}

	/**
	 * Function for deletion widgets from test dashboard after case.
	 */
	public static function deleteWidgets() {
		DBexecute('DELETE FROM widget'.
			' WHERE dashboard_pageid'.
			' IN (SELECT dashboard_pageid'.
				' FROM dashboard_page'.
				' WHERE dashboardid='.static::$dashboardid.
			')'
		);
	}
}
