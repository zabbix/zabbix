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


require_once dirname(__FILE__) . '/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../common/testWidgets.php';

/**
 * @backup widget, profiles
 *
 * @dataSource AllItemValueTypes
 *
 * @onBefore prepareClockWidgetData
 */

class testDashboardClockWidget extends testWidgets {

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
	 * Id of the dashboard with widgets.
	 *
	 * @var integer
	 */
	protected static $dashboardid;

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid '.
			' ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_itemid, wf.value_graphid';

	/**
	 * Create data for autotests which use ClockWidget.
	 *
	 * @return array
	 */
	public static function prepareClockWidgetData() {
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Host group for clock widget'
			]
		]);
		$hostgrpid = CDataHelper::getIds('name');

		CDataHelper::call('host.create', [
			'host' => 'Host for clock widget',
			'groups' => [
				[
					'groupid' => $hostgrpid['Host group for clock widget']
				]
			],
			'interfaces' => [
				'type'=> 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '192.168.3.217',
				'dns' => '',
				'port' => '10050'
			]
		]);
		$hostid = CDataHelper::getIds('host');
		$interfaceid = CDBHelper::getValue('SELECT interfaceid FROM interface WHERE hostid='.
				$hostid['Host for clock widget']
		);

		CDataHelper::call('item.create', [
			[
				'hostid' => $hostid['Host for clock widget'],
				'name' => 'Item for clock widget',
				'key_' => 'system.localtime[local]',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => $interfaceid,
				'delay' => '5s'
			],
			[
				'hostid' => $hostid['Host for clock widget'],
				'name' => 'Item for clock widget 2',
				'key_' => 'system.localtime[local2]',
				'type' => 0,
				'value_type' => 1,
				'interfaceid' => $interfaceid,
				'delay' => '5s'
			]
		]);
		$itemid = CDataHelper::getIds('name');

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for creating clock widgets',
				'display_period' => 60,
				'auto_start' => 0,
				'pages' => [
					[
						'name' => 'First page',
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'DeleteClock',
								'x' => 12,
								'y' => 0,
								'width' => 10,
								'height' => 3
							],
							[
								'type' => 'clock',
								'name' => 'CancelClock',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 3
							],
							[
								'type' => 'clock',
								'name' => 'LayoutClock',
								'x' => 22,
								'y' => 0,
								'width' => 12,
								'height' => 3,
								'fields' => [
									[
										'type' => 4,
										'name' => 'itemid',
										'value' => $itemid['Item for clock widget']
									],
									[
										'type' => 0,
										'name' => 'time_type',
										'value' => 2
									]
								]
							]
						]
					],
					[
						'name' => 'Second page'
					]
				]
			],
			[
				'name' => 'Dashboard for updating clock widgets',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'UpdateClock',
								'x' => 0,
								'y' => 0,
								'width' => 18,
								'height' => 4
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = CDataHelper::getIds('name');
	}

	/**
	 * Check clock widgets layout.
	 */
	public function testDashboardClockWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating clock widgets']);
		$dialog = CDashboardElement::find()->one()->edit()->addWidget();
		$form = $dialog->asForm();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Clock')]);

		$form->checkValue([
			'Name' => '',
			'Refresh interval' => 'Default (15 minutes)',
			'Time type' => 'Local time',
			'Clock type' => 'Analog',
			'id:show_header' => true
		]);

		// Check fields "Refresh interval" and "Time type" values.
		$dropdowns = [
			'Refresh interval' => ['Default (15 minutes)',  'No refresh', '10 seconds', '30 seconds', '1 minute',
				'2 minutes', '10 minutes',  '15 minutes'
			],
			'Time type' => ['Local time', 'Server time', 'Host time']
		];

		foreach ($dropdowns as $field => $options) {
			$this->assertEquals($options, $form->getField($field)->asDropdown()->getOptions()->asText());
		}

		// Check that it's possible to select host items, when time type is "Host Time".
		$fields = ['Type', 'Show header', 'Name', 'Refresh interval', 'Time type', 'Clock type'];

		foreach (['Local time', 'Server time', 'Host time'] as $type) {
			$form->fill(['Time type' => CFormElement::RELOADABLE_FILL($type)]);

			/**
			 * If the clock widgets type equals to "Host time", then additional field appears - 'Item',
			 * which requires to select item of the "Host", in this case array_splice function allows us to put
			 * this fields name into the array. Positive offset (5) starts from the beginning of the array,
			 * while - (0) length parameter - specifies how many elements will be removed.
			 */
			if ($type === 'Host time') {
				array_splice($fields, 5, 0, ['Item']);
				$form->checkValue(['Item' => '']);
				$form->isRequired('Item');
			}

			$this->assertEquals($fields, array_values($form->getLabels(CElementFilter::VISIBLE)->asText()));
		}

		// Check if Apply and Cancel button are clickable and there are two of them.
		$dialog->invalidate();
		$this->assertEquals(2, $dialog->getFooter()->query('button', ['Add', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);

		// Check fields' visibility depending on Analog or Digital clock type.
		foreach (['Analog' => false, 'Digital' => true] as $type => $status) {
			$form->fill(['Clock type' => $type]);

			// Check "Advanced configuration" visibility.
			$form->getField('Advanced configuration')->isVisible($status);

			// Check Show checkboxes visibility and values. (Only Time is checked by default).
			foreach (['show_1' => false, 'show_2' => true, 'show_3' => false] as $id => $checked) {
				$checkbox = $form->query('id', $id)->asCheckbox()->one();
				$this->assertTrue($checkbox->isVisible($status));
				$this->assertTrue($checkbox->isChecked($checked));
			}

			if ($status) {
				// Check that advanced configuration is closed.
				$form->checkValue(['Advanced configuration' => false]);

				$this->assertTrue($form->isRequired('Show'));

				// Open "Advanced configuration" block to check its fields.
				$form->fill(['Advanced configuration' => true]);

				// Check that only Background colour and Time fields are visible (because only Time checkbox is checked).
				// There are two labels "Time zone", so the xpath is used for the container.
				foreach (['Background colour' => true, 'Date' => false, 'Time' => true,
							'xpath:.//div[@class="fields-group fields-group-tzone"]' => false] as $name => $visible) {
					$this->assertTrue($form->getField($name)->isVisible($visible));
				}

				// Fill other Show checkboxes and get other Advanced config fields.
				$form->fill(['id:show_1' => true, 'id:show_3' => true]);

				$advanced_configuration = [
					'Date' => ['id:date_bold' => false, 'id:date_color' => null],
					'Time' => ['id:time_bold' => false, 'id:time_color' => null,
							'id:time_sec' => true, 'id:time_format' => '24-hour'
					],
					// This is Time zone field found by xpath, because we have one more field with Time zone label.
					'xpath:.//div[@class="fields-group fields-group-tzone"]' => [
							'id:tzone_bold' => false, 'id:tzone_color' => null,
							'id:tzone_timezone' => 'Local default: '.CDateTimeHelper::getTimeZoneFormat('Europe/Riga'),
							'id:tzone_format' => 'Short'
					]
				];

				// Check Advanced config fields depending on Time type.
				foreach (['Local time', 'Server time', 'Host time'] as $type) {
					$form->fill(['Time type' => CFormElement::RELOADABLE_FILL($type)]);
					$form->fill(['Advanced configuration' => true]);

					// Check that with Host time 'Time zone' and 'Format' fields disappear.
					if ($type === 'Host time') {
						$advanced_configuration['xpath:.//div[@class="fields-group fields-group-tzone"]'] =
								['id:tzone_bold' => false, 'id:tzone_color' => null];

						foreach (['id:tzone_timezone', 'id:tzone_format'] as $id) {
							$this->assertFalse($form->getField($id)->isVisible());
						}
					}

					// Check Advanced fields' visibility and values.
					foreach ($advanced_configuration as $field => $config) {
						$advanced_field = $form->getField($field);
						$this->assertTrue($advanced_field->isClickable());

						foreach ($config as $id => $value) {
							$advanced_subfield = $form->getField($id);
							$this->assertEquals($value, $advanced_subfield->getValue());
							$this->assertTrue($advanced_subfield->isEnabled());
						}
					}
				}

				// Check form fields' maximal lengths.
				$this->assertEquals(255, $form->getField('Name')->getAttribute('maxlength'));

				// Now remove the Time checkbox from Show field and check that only its Advanced config disappeared.
				$form->fill(['id:show_2' => false]);

				foreach ( ['Date' => true, 'Time' => false, 'xpath:.//div[@class="fields-group fields-group-tzone"]' => true]
						as $name => $visible) {
					$this->assertTrue($form->getField($name)->isVisible($visible));
				}
			}
		}

		$this->assertEquals(['Item', 'Show'], $form->getRequiredLabels());

		$dialog->close();
	}

	/**
	 * Function checks specific scenario when Clock widget has "Time type" as "Host time"
	 * and name for widget itself isn't provided, after creating widget, host name should be displayed on widget as
	 * the widget name.
	 */
	public function testDashboardClockWidget_CheckClockWidgetsName() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating clock widgets']);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget('LayoutClock')->edit();
		$form->fill(['Name' => '']);
		$this->query('button', 'Apply')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$dashboard->waitUntilReady();
		$this->assertTrue($dashboard->getWidget('Host for clock widget')->isValid());
		$dashboard->getWidget('Host for clock widget')->edit()->fill(['Name' => 'LayoutClock']);
		$this->query('button', 'Apply')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals('LayoutClock', $dashboard->getWidget('LayoutClock')->getHeaderText());
	}

	public static function getClockWidgetCommonData() {
		return [
			// #0 Name and show header change.
			[
				[
					'check_dialog_properties' => true,
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => true,
						'Name' => 'Name and show header name'
					]
				]
			],
			// #1 Refresh interval change.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Refresh interval' => '10 seconds',
						'Name' => 'Refresh interval change name'
					]
				]
			],
			// #2 Time type changed to Server time.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Time type changed to Server time',
						'Time type' => CFormElement::RELOADABLE_FILL('Server time')
					]
				]
			],
			// #3 Time type changed to Local time.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Time type changed to Local time',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time')
					]
				]
			],
			// #4 Time type and refresh interval changed.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Type' => 'Clock',
						'Time type' => CFormElement::RELOADABLE_FILL('Server time'),
						'Refresh interval' => '10 seconds',
						'Name' => 'Time type and refresh interval changed'
					]
				]
			],
			// #5 Empty name added.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => ''
					]
				]
			],
			// #6 Symbols/numbers name added.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '!@#$%^&*()1234567890-='
					]
				]
			],
			// #7 Cyrillic added in name.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Имя кирилицей'
					]
				]
			],
			// #8 all fields changed.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => true,
						'Name' => 'Updated_name',
						'Refresh interval' => '10 minutes',
						'Time type' => CFormElement::RELOADABLE_FILL('Server time')
					]
				]
			],
			// #9 Host time without item.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show header' => false,
						'Name' => 'ClockWithoutItem',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time'),
						'Clock type' => 'Analog'
					],
					'Error message' => [
						'Invalid parameter "Item": cannot be empty.'
					]
				]
			],
			// #10 Time type with item.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Time type with item',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time'),
						'Item' => 'Item for clock widget'
					]
				]
			],
			// #11 Update item.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Update item',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time'),
						'Item' => 'Item for clock widget 2'
					]
				]
			],
			// #12.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => true,
						'Name' => 'HostTimeClock',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time'),
						'Item' => 'Item for clock widget',
						'Clock type' => 'Analog'
					]
				]
			],
			// #13.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => true,
						'Name' => 'LocalTimeClock123',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
						'Clock type' => 'Analog'
					]
				]
			],
			// #14.
			[
				[
					'expected' => TEST_GOOD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => '1233212',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
						'Clock type' => 'Analog'
					]
				]
			],
			// #15.
			[
				[
					'expected' => TEST_GOOD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => 'DigitalClock',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
						'Clock type' => 'Digital',
						'id:show_1' => true,
						'id:show_2' => false,
						'id:show_3' => false
					]
				]
			],
			// #16.
			[
				[
					'expected' => TEST_GOOD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => 'DigitalClock2',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
						'Clock type' => 'Digital',
						'id:show_1' => true,
						'id:show_2' => true,
						'id:show_3' => false
					]
				]
			],
			// #17.
			[
				[
					'expected' => TEST_GOOD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => 'DigitalClock3',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
						'Clock type' => 'Digital',
						'id:show_1' => true,
						'id:show_2' => true,
						'id:show_3' => true
					]
				]
			],
			// #18.
			[
				[
					'expected' => TEST_GOOD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => 'DigitalClock4',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
						'Clock type' => 'Digital',
						'id:show_1' => true,
						'id:show_2' => false,
						'id:show_3' => false,
						'Advanced configuration' => true
					]
				]
			],
			// #19.
			[
				[
					'expected' => TEST_GOOD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => 'DigitalClock5',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
						'Clock type' => 'Digital',
						'id:show_1' => true,
						'id:show_2' => false,
						'id:show_3' => false,
						'Advanced configuration' => true,
						'Background colour' => 'FFEB3B',
						'id:date_bold' => true,
						'xpath://button[@id="lbl_date_color"]/..' => 'F57F17'
					]
				]
			],
			// #20.
			[
				[
					'expected' => TEST_GOOD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => 'DigitalClock6',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
						'Clock type' => 'Digital',
						'id:show_1' => true,
						'id:show_2' => true,
						'id:show_3' => false,
						'Advanced configuration' => true,
						'Background colour' => '7B1FA2',
						'id:date_bold' => true,
						'xpath://button[@id="lbl_date_color"]/..' => '002B4D',
						'id:time_bold' => false,
						'xpath://button[@id="lbl_time_color"]/..' => '00897B',
						'id:time_sec' => true,
						'id:time_format' => '24-hour'
					]
				]
			],
			// #21.
			[
				[
					'expected' => TEST_GOOD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => 'DigitalClock7',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
						'Clock type' => 'Digital',
						'id:show_1' => true,
						'id:show_2' => true,
						'id:show_3' => false,
						'Advanced configuration' => true,
						'Background colour' => '43A047',
						'id:date_bold' => true,
						'xpath://button[@id="lbl_date_color"]/..' => '64B5F6',
						'id:time_bold' => true,
						'xpath://button[@id="lbl_time_color"]/..' => '180D49',
						'id:time_sec' => false,
						'id:time_format' => '12-hour'
					]
				]
			],
			// #22.
			[
				[
					'expected' => TEST_GOOD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => 'DigitalClock8',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
						'Clock type' => 'Digital',
						'id:show_1' => true,
						'id:show_2' => true,
						'id:show_3' => true,
						'Advanced configuration' => true,
						'Background colour' => 'C62828',
						'id:date_bold' => true,
						'xpath://button[@id="lbl_date_color"]/..' => 'FDD835',
						'id:time_bold' => true,
						'xpath://button[@id="lbl_time_color"]/..' => '1B5E20',
						'id:time_sec' => false,
						'id:time_format' => '12-hour',
						'id:tzone_bold' => false,
						'xpath://button[@id="lbl_tzone_color"]/..' => '06081F',
						'xpath://button[@id="label-tzone_timezone"]/..'
								=> CDateTimeHelper::getTimeZoneFormat('Atlantic/Stanley'),
						'id:time_format' => '24-hour'
					]
				]
			],
			// #23.
			[
				[
					'expected' => TEST_GOOD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => 'DigitalClock9',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
						'Clock type' => 'Digital',
						'id:show_1' => true,
						'id:show_2' => true,
						'id:show_3' => true,
						'Advanced configuration' => true,
						'Background colour' => '001819',
						'id:date_bold' => true,
						'xpath://button[@id="lbl_date_color"]/..' => '607D8B',
						'id:time_bold' => true,
						'xpath://button[@id="lbl_time_color"]/..' => '1565C0',
						'id:time_sec' => false,
						'id:time_format' => '12-hour',
						'id:tzone_bold' => true,
						'xpath://button[@id="lbl_tzone_color"]/..' => 'CDDC39',
						'xpath://button[@id="label-tzone_timezone"]/..'
								=> CDateTimeHelper::getTimeZoneFormat('Africa/Bangui'),
						'id:time_format' => '12-hour'
					]
				]
			],
			// #24.
			[
				[
					'expected' => TEST_BAD,
					'second_page' => true,
					'fields' => [
						'Show header' => true,
						'Name' => 'DigitalClock11',
						'Refresh interval' => '30 seconds',
						'Time type' => CFormElement::RELOADABLE_FILL('Host time'),
						'Clock type' => 'Digital',
						'id:show_1' => true,
						'id:show_2' => true,
						'id:show_3' => true,
						'Advanced configuration' => true,
						'Background colour' => '001819',
						'id:date_bold' => true,
						'xpath://button[@id="lbl_date_color"]/..' => '607D8B',
						'id:time_bold' => true,
						'xpath://button[@id="lbl_time_color"]/..' => '1565C0',
						'id:time_sec' => false,
						'id:time_format' => '12-hour',
						'id:tzone_bold' => true,
						'xpath://button[@id="lbl_tzone_color"]/..' => 'CDDC39'
					],
					'Error message' => [
						'Invalid parameter "Item": cannot be empty.'
					]
				],
				// #25.
				[
					[
						'expected' => TEST_BAD,
						'second_page' => true,
						'fields' => [
							'Clock type' => 'Digital',
							'id:show_1' => false,
							'id:show_2' => false,
							'id:show_3' => false,
							'id:show_4' => false
						],
						'Error message' => [
							'Invalid parameter "Show": at least one option must be selected.'
						]
					]
				]
			]
		];
	}

	/**
	 * Function for checking Clock widget form.
	 *
	 * @param array      $data      data provider
	 * @param boolean    $update    true if update scenario, false if create
	 */
	public function checkFormClockWidget($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$linkid = $update
			?  self::$dashboardid['Dashboard for updating clock widgets']
			:  self::$dashboardid['Dashboard for creating clock widgets'];

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$linkid);
		$dashboard = CDashboardElement::find()->one()->waitUntilVisible();

		if (array_key_exists('second_page', $data) && $update === false) {
			$dashboard->selectPage('Second page');
			$dashboard->invalidate();
		}

		$form = $update
			? $dashboard->getWidgets()->last()->edit()
			: $dashboard->edit()->addWidget()->asForm();
		$dialog = COverlayDialogElement::find()->one();

		if (CTestArrayHelper::get($data, 'check_dialog_properties', false) && $update === true) {
			$this->assertEquals('Edit widget', $dialog->getTitle());
			$form->checkValue(['Type' => 'Clock']);
		}

		if (!$update) {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Clock')]);
		}

		$form->fill($data['fields']);
		$form->submit();

		if ($data['expected'] === TEST_GOOD) {
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			/**
			 * After saving dashboard, it returns you to first page, if widget created in 2nd page,
			 * then it needs to be opened.
			 */
			if (array_key_exists('second_page', $data) && $update === false) {
				$dashboard->selectPage('Second page');
				$dashboard->invalidate();
			}

			if (array_key_exists('Item', $data['fields'])) {
				$data['fields'] = array_replace($data['fields'], ['Item' => 'Host for clock widget: '.
						$data['fields']['Item']]);
			}

			// Check that widget updated.
			$dashboard->edit();
			$widget = $dashboard->getWidgets()->last()->edit();

			// Open "Advanced configuration" block if it was filled with data.
			if (CTestArrayHelper::get($data, 'fields.Advanced configuration', false)) {
				// After form submit "Advanced configuration" is closed.
				$widget->checkValue(['Advanced configuration' => false]);
				$widget->fill(['Advanced configuration' => true]);
			}
			$widget->checkValue($data['fields']);

			// Check that widget is saved in DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT *'.
				' FROM widget w'.
				' WHERE EXISTS ('.
					' SELECT NULL'.
					' FROM dashboard_page dp'.
					' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
						' AND dp.dashboardid='.$linkid.
						' AND w.name ='.zbx_dbstr(CTestArrayHelper::get($data['fields'], 'Name', '')).
				')'
			));
		}
		else {
			$this->assertMessage(TEST_BAD, null, $data['Error message']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
		}

		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Function for checking Clock Widgets creation.
	 *
	 * @param array $data    data provider
	 *
	 * @dataProvider getClockWidgetCommonData
	 */
	public function testDashboardClockWidget_Create($data) {
		$this->checkFormClockWidget($data);
	}

	/**
	 * Function for checking Clock Widgets successful update.
	 *
	 * @param array $data    data provider
	 *
	 * @dataProvider getClockWidgetCommonData
	 */
	public function testDashboardClockWidget_Update($data) {
		$this->checkFormClockWidget($data, true);
	}

	public function testDashboardClockWidget_SimpleUpdate() {
		$this->checkNoChanges();
	}

	public static function getCancelData() {
		return [
			// Cancel creating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => true,
					'save_dashboard' => true
				]
			],
			// Cancel updating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => false,
					'save_dashboard' => true
				]
			],
			// Create widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => true,
					'save_dashboard' => false
				]
			],
			// Update widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => false,
					'save_dashboard' => false
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testDashboardClockWidget_Cancel($data) {
		$this->checkNoChanges($data['cancel_form'], $data['create_widget'], $data['save_dashboard']);
	}

	/**
	 * Function for checking canceling form or submitting without any changes.
	 *
	 * @param boolean $cancel            true if cancel scenario, false if form is submitted
	 * @param boolean $create            true if create scenario, false if update
	 * @param boolean $save_dashboard    true if dashboard will be saved, false if not
	 */
	private function checkNoChanges($cancel = false, $create = false, $save_dashboard = true) {
		$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating clock widgets']);

		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $create
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget('CancelClock')->edit();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if (!$create) {
			$values = $form->getFields()->asValues();
		}
		else {
			$form->fill(['Type' => 'Clock']);
		}

		if ($cancel || !$save_dashboard) {
			$form->fill([
				'Name' => 'Widget to be cancelled',
				'Refresh interval' => '10 minutes',
				'Time type' => CFormElement::RELOADABLE_FILL('Local time'),
				'Clock type' => 'Digital',
				'id:show_1' => true,
				'id:show_2' => false,
				'id:show_3' => false,
				'Advanced configuration' => true,
				'Background colour' => '001819'
			]);
		}

		if ($cancel) {
			$dialog->query('button:Cancel')->one()->click();
		}
		else {
			$form->submit();
		}

		COverlayDialogElement::ensureNotPresent();

		if (!$cancel) {
			$dashboard->getWidget(!$save_dashboard ? 'Widget to be cancelled' : 'CancelClock')->waitUntilReady();
		}

		if ($save_dashboard) {
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
		else {
			$dashboard->cancelEditing();
		}

		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());

		// Check that updating widget form values did not change in frontend.
		if (!$create && !$save_dashboard) {
			$this->assertEquals($values, $dashboard->getWidget('CancelClock')->edit()->getFields()->asValues());
			COverlayDialogElement::find()->one()->close();
		}

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Check clock widgets deletion.
	 */
	public function testDashboardClockWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating clock widgets']);
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->edit()->getWidget('DeleteClock');
		$this->assertTrue($widget->isEditable());
		$dashboard->deleteWidget('DeleteClock');
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget('DeleteClock', false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT *'.
			' FROM widget_field wf'.
			' LEFT JOIN widget w'.
			' ON w.widgetid=wf.widgetid'.
			' WHERE w.name='.zbx_dbstr('DeleteClock')
		));
	}

	/**
	 * Check if binary items are not available for Clock widget.
	 */
	public function testDashboardClockWidget_CheckAvailableItems() {
		$url = 'zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid['Dashboard for updating clock widgets'];
		$this->checkAvailableItems($url, 'Clock');
	}
}
