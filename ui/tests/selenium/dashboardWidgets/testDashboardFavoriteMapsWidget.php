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


require_once __DIR__.'/../../include/CWebTest.php';

/**
 * @backup profiles
 *
 * @onBefore prepareDashboardData
 */
class testDashboardFavoriteMapsWidget extends CWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

	const MAP_NAME = 'Test map for favourite widget';
	const DELETE_WIDGET = 'Favorite maps widget to delete';
	const CANCEL_WIDGET = 'Widget for testing cancel button';

	protected static $dashboardid;
	protected static $dashboard_url;
	protected static $mapid;
	protected static $edit_widget = 'Widget for update';
	protected static $default_values = [
		'Name' => '',
		'Show header' => true,
		'Refresh interval' => 'Default (15 minutes)'
	];

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	const SQL = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid'.
			' ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_itemid, wf.value_graphid';

	public static function prepareDashboardData() {
		// Create dashboard with Favorite maps widgets.
		self::$dashboardid = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard with favorite maps widget',
				'private' => 1,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'favmaps',
								'name' => self::$edit_widget,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'favmaps',
								'name' => self::DELETE_WIDGET,
								'x' => 0,
								'y' => 5,
								'width' => 12,
								'height' => 4
							],
							[
								'type' => 'favmaps',
								'name' => self::CANCEL_WIDGET,
								'x' => 0,
								'y' => 10,
								'width' => 12,
								'height' => 4
							]
						]
					]
				]
			]
		])['dashboardids'][0];

		self::$dashboard_url = 'zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid;

		// Create host for map.
		$hosts = CDataHelper::call('host.create', [
			[
				'host' => 'Map host',
				'groups' => ['groupid' => 4] // Zabbix servers.
			]
		]);

		// Create map.
		$maps = CDataHelper::call('map.create', [
			[
				'name' => self::MAP_NAME,
				'width' => 500,
				'height' => 500,
				'selements' => [
					[
						'elements' => [['hostid' => $hosts['hostids'][0]]],
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'iconid_off' => 186
					]
				]
			]
		]);
		self::$mapid = $maps['sysmapids'][0];
	}

	// Add to favorites.
	public function testDashboardFavoriteMapsWidget_AddFavoriteMap() {
		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$this->page->assertHeader('Maps');
		$this->query('link', self::MAP_NAME)->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();
		$button = $this->query('xpath://button[@id="addrm_fav"]')->waitUntilVisible()->one();
		$this->assertEquals('Add to favorites', $button->getAttribute('title'));
		$button->waitUntilClickable()->click();
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favorites']);

		$this->page->login()->open(self::$dashboard_url)->waitUntilReady();
		$widget = CDashboardElement::find()->one()->getWidget(self::$edit_widget)->waitUntilReady()->getContent();

		$this->assertEquals('zabbix.php?action=map.view&sysmapid='.self::$mapid,
				$widget->query('link', self::MAP_NAME)->one()->getAttribute('href')
		);

		$this->assertEquals(1, CDBHelper::getCount('SELECT null FROM profiles WHERE idx='.
				zbx_dbstr('web.favorite.sysmapids').' AND value_id='.zbx_dbstr(self::$mapid))
		);
	}

	public function testDashboardFavoriteMapsWidget_RemoveFavoriteMaps() {
		$favorite_maps = CDBHelper::getAll('SELECT value_id FROM profiles WHERE idx='.zbx_dbstr('web.favorite.sysmapids'));

		$this->page->login()->open(self::$dashboard_url)->waitUntilReady();
		$widget = CDashboardElement::find()->waitUntilReady()->one()->getWidget(self::$edit_widget)->getContent();

		foreach ($favorite_maps as $map) {
			// Added variable due to External Hook.
			$xpath = './/button[@data-sysmapid='.CXPathHelper::escapeQuotes($map['value_id']);
			$remove_item = $widget->query('xpath', $xpath.' and contains(@onclick, "rm4favorites")]')->waituntilClickable()->one();
			$remove_item->click();
			$remove_item->waitUntilNotVisible();
		}

		$this->assertEquals('No maps added.', $widget->query('class:no-data-message')->waitUntilVisible()->one()->getText());
		$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM profiles WHERE idx='.zbx_dbstr('web.favorite.sysmapids')));
	}

	public function testDashboardFavoriteMapsWidget_Layout() {
		$this->page->login()->open(self::$dashboard_url)->waitUntilReady();
		$dialog = CDashboardElement::find()->one()->edit()->addWidget();
		$form = $dialog->asForm();

		$this->assertEquals('Add widget', $dialog->getTitle());

		// Set widget type to "Favorite maps".
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Favorite maps')]);

		// Check default field values.
		$form->checkValue([
			'Name' => '',
			'Refresh interval' => 'Default (15 minutes)',
			'id:show_header' => true
		]);

		// Check available options for "Refresh interval".
		$this->assertEquals(['Default (15 minutes)', 'No refresh', '10 seconds', '30 seconds','1 minute', '2 minutes',
				'10 minutes', '15 minutes'], $form->getField('Refresh interval')->asDropdown()->getOptions()->asText()
		);

		// Verify visible field labels.
		$this->assertEquals(['Type', 'Show header', 'Name', 'Refresh interval'],
				array_values($form->getLabels(CElementFilter::VISIBLE)->asText())
		);

		// Verify that both Apply and Cancel buttons are clickable.
		$this->assertEquals(2, $dialog->getFooter()->query('button', ['Add', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);

		// Check max length of the "Name" input field.
		foreach (['maxlength' => 255, 'placeholder' => 'default'] as $attribute => $value) {
			$this->assertEquals($value, $form->getField('Name')->getAttribute($attribute));
		}

		$dialog->close();
		CDashboardElement::find()->one()->cancelEditing();
	}

	public static function getFavoriteMapsWidgetData() {
		return [
			// #0 Special characters in name.
			[
				[
					'fields' => [
						'Name' => '⭐㖵㖶 🙃 㓈㓋',
						'Show header' => true,
						'Refresh interval' => 'No refresh'
					]
				]
			],
			// #2 Leading + trailing spaces.
			[
				[
					'fields' => [
						'Name' => '  Leading and trailing  ',
						'Refresh interval' => '1 minute'
					],
					'trim' => true
				]
			],
			// #3 Hidden header.
			[
				[
					'fields' => [
						'Name' => 'Header hidden',
						'Show header' => false
					]
				]
			],
			// #4 Max refresh.
			[
				[
					'fields' => [
						'Name' => 'Max refresh',
						'Refresh interval' => '15 minutes'
					]
				]
			]
		];
	}

	public static function getFavoriteMapsWidgetDefaultData() {
		return [
			// #0 Default widget.
			[
				[]
			]
		];
	}

	/**
	 * @dataProvider getFavoriteMapsWidgetDefaultData
	 * @dataProvider getFavoriteMapsWidgetData
	 */
	public function testDashboardFavoriteMapsWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	public function testDashboardFavoriteMapsWidget_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::SQL);
		$this->page->login()->open(self::$dashboard_url)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->getWidget(self::$edit_widget)->edit();
		$form->submit()->waitUntilStalled();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * @dataProvider getFavoriteMapsWidgetData
	 */
	public function testDashboardFavoriteMapsWidget_Update($data) {
		$this->checkWidgetForm($data, true);
	}

	public function testDashboardFavoriteMapsWidget_Delete() {
		$this->page->login()->open(self::$dashboard_url)->waitUntilReady();

		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget(self::DELETE_WIDGET);
		$dashboard->deleteWidget(self::DELETE_WIDGET);
		$widget->waitUntilNotPresent();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		$this->assertFalse($dashboard->getWidget(self::DELETE_WIDGET, false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT null FROM widget_field wf'.
				' LEFT JOIN widget w ON w.widgetid=wf.widgetid WHERE w.name='.zbx_dbstr(self::DELETE_WIDGET)
		));
	}

	public static function getCancelData() {
		return [
			// Cancel update widget.
			[
				[
					'update' => true,
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'update' => true,
					'save_widget' => false,
					'save_dashboard' => true
				]
			],
			// Cancel create widget.
			[
				[
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'save_widget' => false,
					'save_dashboard' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testDashboardFavoriteMapsWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash(self::SQL);
		$new_name = 'Cancel test - favorite maps';

		$this->page->login()->open(self::$dashboard_url)->waitUntilReady();

		$dashboard = CDashboardElement::find()->one()->edit();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update')) {
			$form = $dashboard->getWidget(self::CANCEL_WIDGET)->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Favorite maps')]);
		}

		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '10 minutes'
		]);

		// Save or cancel widget.
		if (CTestArrayHelper::get($data, 'save_widget')) {
			$form->submit();
			$this->assertTrue($dashboard->getWidget($new_name)->isVisible());
		}
		else {
			COverlayDialogElement::find()->one()->close(true);

			if (CTestArrayHelper::get($data, 'update')) {
				foreach ([self::CANCEL_WIDGET => true, $new_name => false] as $name => $valid) {
					$dashboard->getWidget($name, false)->isValid($valid);
				}
			}

			$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		}

		// Save or cancel dashboard changes.
		if (CTestArrayHelper::get($data, 'save_dashboard')) {
			$dashboard->save();
		}
		else {
			$dashboard->cancelEditing();
		}

		// Assert no DB change.
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}
	/**
	 * Checks the widget form configuration.
	 */
	protected function checkWidgetForm($data, $update = false) {
		$data['fields'] = CTestArrayHelper::get($data, 'fields', self::$default_values);

		$this->page->login()->open(self::$dashboard_url)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		if ($update) {
			$form = $dashboard->edit()->getWidget(self::$edit_widget)->edit()->asForm();
		}
		else {
			$form = $dashboard->edit()->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Favorite maps')]);
		}

		$form->fill($data['fields']);
		$values = $form->getFields()->asValues();
		$form->submit();
		$form->waitUntilStalled();
		$this->page->waitUntilReady();
		$dashboard->save()->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Trim leading/trailing spaces from expected results if necessary.
		if (array_key_exists('trim', $data)) {
			$data['fields']['Name'] = trim($data['fields']['Name']);
		}

		// If name is empty string it is replaced by default name.
		$header = (CTestArrayHelper::get($data, 'fields.Name', '') === '')
			? 'Favorite maps'
			: $data['fields']['Name'];

		$widget = $dashboard->getWidget($header);

		if ($update) {
			self::$edit_widget = $header;

			if (array_key_exists('Refresh interval', $data['fields'])) {
				self::$default_values['Refresh interval'] = $data['fields']['Refresh interval'];
			}
		}

		// Check widgets count.
		$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

		// Check new widget update interval.
		if (CTestArrayHelper::get($data, 'fields.Refresh interval') === 'Default (15 minutes)') {
			$refresh = '15 minutes';
		}
		else {
			$default_interval = ($update) ? self::$default_values['Refresh interval'] : '15 minutes';
			$refresh = CTestArrayHelper::get($data, 'fields.Refresh interval', $default_interval);
		}

		$this->assertEquals($refresh, $widget->getRefreshInterval());
		CPopupMenuElement::find()->one()->close();

		// Check new widget form fields and values in frontend.
		$saved_form = $widget->edit();
		$this->assertEquals($values, $saved_form->getFields()->asValues());
		$saved_form->checkValue($data['fields']);
		COverlayDialogElement::find()->one()->close();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
	}
}
