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
require_once __DIR__.'/../behaviors/CMessageBehavior.php';

/**
 * @backup config
 */
class testFormAdministrationGeneralGeomaps extends CWebTest {

	private $sql = 'SELECT * FROM config';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	public function getLayoutData() {
		return [
			// #0.
			[
				[
					'Tile provider' => 'OpenStreetMap Mapnik',
					'Tile URL' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
					'Max zoom level' => 19
				]
			],
			// #1.
			[
				[
					'Tile provider' => 'OpenTopoMap',
					'Tile URL' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
					'Max zoom level' => 17
				]
			],
			// #2.
			[
				[
					'Tile provider' => 'Stamen Toner Lite',
					'Tile URL' => 'https://stamen-tiles-{s}.a.ssl.fastly.net/toner-lite/{z}/{x}/{y}{r}.png',
					'Max zoom level' => 20
				]
			],
			// #3.
			[
				[
					'Tile provider' => 'Stamen Terrain',
					'Tile URL' => 'https://stamen-tiles-{s}.a.ssl.fastly.net/terrain/{z}/{x}/{y}{r}.png',
					'Max zoom level' => 18
				]
			],
			//#4.
			[
				[
					'Tile provider' => 'USGS US Topo',
					'Tile URL' => 'https://basemap.nationalmap.gov/arcgis/rest/services/USGSTopo/MapServer/tile/{z}/{y}/{x}',
					'Max zoom level' => 20
				]
			],
			// #5.
			[
				[
					'Tile provider' => 'USGS US Imagery',
					'Tile URL' => 'https://basemap.nationalmap.gov/arcgis/rest/services/USGSImageryOnly/MapServer/tile/{z}/{y}/{x}',
					'Max zoom level' => 20
				]
			],
			// #6.
			[
				[
					'Tile provider' => 'Other',
					'Tile URL' => '',
					'Attribution text' => '',
					'Max zoom level' => ''
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testFormAdministrationGeneralGeomaps_Layout($data) {
		$this->page->login()->open('zabbix.php?action=geomaps.edit');
		$form = $this->query('id:geomaps-form')->asForm()->one();

		$form->fill(['Tile provider' => $data['Tile provider']]);
		$form->checkValue($data);

		/**
		 * Check form attributes only for last case.
		 */
		if ($data['Tile provider'] === 'Other') {
			// Check dropdown options presence.
			$this->assertEquals(['OpenStreetMap Mapnik', 'OpenTopoMap', 'Stamen Toner Lite', 'Stamen Terrain',
				'USGS US Topo', 'USGS US Imagery', 'Other'], $form->getField('Tile provider')->asDropdown()
				->getOptions()->asText()
			);

			// Open hintboxes and compare text.
			$hintboxes = [
				'Tile URL' => "The URL template is used to load and display the tile layer on geographical maps.".
					"\n".
					"\nExample: https://{s}.example.com/{z}/{x}/{y}{r}.png".
					"\n".
					"\nThe following placeholders are supported:".
					"\n{s} represents one of the available subdomains;".
					"\n{z} represents zoom level parameter in the URL;".
					"\n{x} and {y} represent tile coordinates;".
					"\n{r} can be used to add \"@2x\" to the URL to load retina tiles.",
				'Attribution text' => 'Tile provider attribution data displayed in a small text box on the map.',
				'Max zoom level' => 'Maximum zoom level of the map.'
			];

			foreach ($hintboxes as $field => $text) {
				$form->getLabel($field)->query('xpath:./button[@data-hintbox]')->one()->click();
				$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilPresent()->one();
				$this->assertEquals($text, $hint->getText());
				$hint->asOverlayDialog()->close();
			}

			// Check Service tab fields' maxlength.
			$limits = [
				'Tile URL' => 2048,
				'Attribution text' => 1024,
				'Max zoom level' => 2
			];
			foreach ($limits as $field => $max_length) {
				$this->assertEquals($max_length, $form->getField($field)->getAttribute('maxlength'));
			}
		}

		$fields = array_keys($data);
		if ($data['Tile provider'] !== 'Other') {
			// Take all fields except dropdown and check they are disabled.
			unset($fields[0]);
			foreach ($fields as $field) {
				$this->assertFalse($form->getField($field)->isEnabled());
			}
		}
		else {
			foreach ($fields as $field) {
				$this->assertTrue($form->getField($field)->isEnabled());
			}
		}
	}

	public function getFormData() {
		return [
			// #0.
			[
				[
					'fields' => [
						'Tile provider' => 'OpenStreetMap Mapnik'
					],
					'db' => 'OpenStreetMap.Mapnik'
				]
			],
			// #1.
			[
				[
					'fields' => [
						'Tile provider' => 'OpenTopoMap'
					],
					'db' => 'OpenTopoMap'
				]
			],
			// #2.
			[
				[
					'fields' => [
						'Tile provider' => 'Stamen Toner Lite'
					],
					'db' => 'Stamen.TonerLite'
				]
			],
			// #3.
			[
				[
					'fields' => [
						'Tile provider' => 'Stamen Terrain'
					],
					'db' => 'Stamen.Terrain'
				]
			],
			// #4.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Tile provider' => 'Other'
					],
					'error' => [
						'Incorrect value for field "geomaps_tile_url": cannot be empty.',
						'Incorrect value for field "geomaps_max_zoom": cannot be empty.'
					]
				]
			],
			// #5.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => '123',
						'Max zoom level' => ''
					],
					'error' => 'Incorrect value for field "geomaps_max_zoom": cannot be empty.'
				]
			],
			// #6.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => 'bbb',
						'Max zoom level' => 0
					],
					'error' => 'Incorrect value for field "geomaps_max_zoom": value must be no less than "1".'
				]
			],
			// #7.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => 'bbb',
						'Max zoom level' => 31
					],
					'error' => 'Incorrect value for field "geomaps_max_zoom": value must be no greater than "30".'
				]
			],
			// #8.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => 'bbb',
						'Max zoom level' => 'aa'
					],
					'error' => 'Incorrect value for field "geomaps_max_zoom": value must be no less than "1".'
				]
			],
			// #9.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => 'bbb',
						'Max zoom level' => '!%:'
					],
					'error' => 'Incorrect value for field "geomaps_max_zoom": value must be no less than "1".'
				]
			],
			// #10.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => 'bbb',
						'Max zoom level' => -1
					],
					'error' => 'Incorrect value for field "geomaps_max_zoom": value must be no less than "1".'
				]
			],
			// #11.
			[
				[
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => 'bbb',
						'Max zoom level' => 29
					]
				]
			],
			// #12.
			[
				[
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => 'bbb',
						'Attribution text' => 'aaa',
						'Max zoom level' => 20
					]
				]
			],
			// #13.
			[
				[
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => '111',
						'Attribution text' => '222',
						'Max zoom level' => 1
					]
				]
			],
			// #14.
			[
				[
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => 'йцу',
						'Attribution text' => 'кен',
						'Max zoom level' => 7
					]
				]
			],
			// #15.
			[
				[
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => 'https://tileserver.memomaps.de/tilegen/{z}/{x}/{y}.png',
						'Attribution text' => 'Map <a href="https://memomaps.de/">memomaps.de</a> '.
							'<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, '.
							'map data &copy; <a href="https://www.openstreetmap.org/copyright">'.
							'OpenStreetMap</a> contributors',
						'Max zoom level' => 13
					]
				]
			],
			// #16.
			[
				[
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => '     bbb           ',
						'Attribution text' => '    aaa    ',
						'Max zoom level' => 29
					],
					'trim' => true
				]
			],
			// #17.
			[
				[
					'fields' => [
						'Tile provider' => 'Other',
						'Tile URL' => '     bbb           ',
						'Attribution text' => '',
						'Max zoom level' => 29
					],
					'trim' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getFormData
	 */
	public function testFormAdministrationGeneralGeomaps_Form($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$this->page->login()->open('zabbix.php?action=geomaps.edit');
		$form = $this->query('id:geomaps-form')->waitUntilReady()->asForm()->one();
		$form->fill($data['fields']);
		$form->submit();
		$this->page->waitUntilReady();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot update configuration', $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Configuration updated');

			// Check values in frontend form.
			$this->page->login()->open('zabbix.php?action=geomaps.edit');
			$form->invalidate();

			// Remove leading and trailing spaces from data for assertion.
			if (CTestArrayHelper::get($data, 'trim', false)) {
				$data['fields']['Tile URL'] = trim($data['fields']['Tile URL']);

				if (array_key_exists('Attribution text', $data['fields'])) {
					$data['fields']['Attribution text'] = trim($data['fields']['Attribution text']);
				}
			}

			$form->checkValue($data['fields']);

			// Check db values.
			if ($data['fields']['Tile provider'] === 'Other') {
				$expected_db = [
					'geomaps_tile_provider' => '',
					'geomaps_tile_url' => $data['fields']['Tile URL'],
					'geomaps_attribution' => CTestArrayHelper::get($data['fields'], 'Attribution text', ''),
					'geomaps_max_zoom' => $data['fields']['Max zoom level']
				];
			}
			else {
				$expected_db = [
					'geomaps_tile_provider' => $data['db'],
					'geomaps_tile_url' => '',
					'geomaps_attribution' => '',
					'geomaps_max_zoom' => 0
				];
			}

			$this->assertEquals($expected_db, CDBHelper::getRow('SELECT geomaps_tile_provider, geomaps_tile_url, '.
				'geomaps_attribution, geomaps_max_zoom FROM config'
			));
		}
	}
}
