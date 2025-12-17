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
 * @backup sysmaps
 *
 * @onBefore prepareMapsData
 */
class testFormMapProperties extends CWebTest {

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

	const MAP_SIMPLE_UPDATE = 'Map for simple update and update test';
	const MAP_CLONE = 'Map for clone and delete test';
	const CLONED_MAP = 'Cloned map';
	const HASH_SQL = 'SELECT * FROM sysmaps ORDER BY sysmapid';
	const ICON_MAPPING = 'Icon mapping for map properties';
	const XSS_EXAMPLE = '<script>alert(\'XSS\');</script>';
	const BACKGROUND_IMAGE = 'Background image for map properties';
	const MAP_URL_ADD = 'Map for update - adding URL ';

	protected static $map_update = 'Map for update test';

	public function prepareMapsData() {
		$mapping_id = CDataHelper::call('iconmap.create', [
			[
				'name' => self::ICON_MAPPING,
				'default_iconid' => 2,
				'mappings' => [
					[
						'inventory_link' => 1,
						'expression' => 'server',
						'iconid' => 3
					],
					[
						'inventory_link' => 1,
						'expression' => 'test',
						'iconid' => 4
					]
				]
			]
		])['iconmapids'][0];

		$background_id = CDataHelper::call('image.create', [
			[
				'name' => self::BACKGROUND_IMAGE,
				'imagetype' => IMAGE_TYPE_BACKGROUND,
				'image' => 'iVBORw0KGgoAAAANSUhEUgAAAGkAAAA6CAIAAAA8+uA0AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJ'.
					'cEhZcwAAEnQAABJ0Ad5mH3gAAACPSURBVHhe7dChDQAwDMCwft7Xy4emYBsGZpZq3sA37zrvOu867zrvOu867zrvOu867zr'.
					'vOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867z'.
					'rvOu867zrvOu867zrvOu867zrvugNJxGmwt/UO4QAAAABJRU5ErkJggg=='
			]
		])['imageids'][0];

		CDataHelper::call('map.create', [
			[
				'name' => self::$map_update,
				'width' => 800,
				'height' => 600,
				'highlight' => SYSMAP_HIGHLIGHT_OFF,
				'label_type' => MAP_LABEL_TYPE_LABEL
			],
			[
				'name' => self::MAP_URL_ADD,
				'width' => 800,
				'height' => 600,
				'highlight' => SYSMAP_HIGHLIGHT_OFF,
				'label_type' => MAP_LABEL_TYPE_LABEL
			],
			[
				'name' => self::MAP_SIMPLE_UPDATE,
				'width' => 10000,
				'height' => 9000,
				'iconmapid' => $mapping_id,
				'markelements' => 1,
				'highlight' => SYSMAP_HIGHLIGHT_ON,
				'expandproblem' => SYSMAP_PROBLEMS_NUMBER_CRITICAL,
				'label_format' => SYSMAP_LABEL_ADVANCED_ON,
				'label_location' => MAP_LABEL_LOC_RIGHT,
				'label_type_host' => MAP_LABEL_TYPE_CUSTOM,
				'label_string_host' => 'Host label 📰📰📰',
				'label_type_hostgroup' => MAP_LABEL_TYPE_NOTHING,
				'label_type_image' => MAP_LABEL_TYPE_LABEL,
				'label_type_map' => MAP_LABEL_TYPE_STATUS,
				'label_type_trigger' => MAP_LABEL_TYPE_NAME,
				'severity_min' => TRIGGER_SEVERITY_DISASTER,
				'show_unack' => 2,
				'show_suppressed' => 1,
				'urls' => [
					[
						'name' => '1 Host URL',
						'url' => 'test',
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST
					],
					[
						'name' => '2 Host group URL',
						'url' => 'test',
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST_GROUP
					],
					[
						'name' => '3 Map URL',
						'url' => 'test',
						'elementtype' => SYSMAP_ELEMENT_TYPE_MAP
					],
					[
						'name' => '5 Trigger URL',
						'url' => 'test',
						'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER
					],
					[
						'name' => '4 Image URL',
						'url' => 'test',
						'elementtype' => SYSMAP_ELEMENT_TYPE_IMAGE
					]
				]
			],
			[
				'name' => self::MAP_CLONE,
				'width' => 1000,
				'height' => 1000,
				'backgroundid' => $background_id,
				'iconmapid' => $mapping_id,
				'markelements' => 1,
				'highlight' => SYSMAP_HIGHLIGHT_ON,
				'expandproblem' => SYSMAP_PROBLEMS_NUMBER_CRITICAL,
				'label_format' => SYSMAP_LABEL_ADVANCED_ON,
				'label_location' => MAP_LABEL_LOC_TOP,
				'label_type_host' => MAP_LABEL_TYPE_CUSTOM,
				'label_type_hostgroup' => MAP_LABEL_TYPE_CUSTOM,
				'label_string_host' => STRING_255,
				'label_string_hostgroup' => 'Host group label 📰📰📰',
				'label_type_image' => MAP_LABEL_TYPE_LABEL,
				'label_type_map' => MAP_LABEL_TYPE_NAME,
				'label_type_trigger' => MAP_LABEL_TYPE_STATUS,
				'severity_min' => TRIGGER_SEVERITY_HIGH,
				'show_unack' => 2,
				'show_suppressed' => 1,
				'urls' => [
					[
						'name' => '1 Host URL 📰📰📰',
						'url' => 'test 📰📰📰',
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST
					],
					[
						'name' => STRING_255,
						'url' => STRING_2048,
						'elementtype' => SYSMAP_ELEMENT_TYPE_MAP
					],
					[
						'name' => '4 Host group - xss',
						'url' => self::XSS_EXAMPLE,
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST_GROUP
					],
					[
						'name' => '3 Trigger URL',
						'url' => 'test',
						'elementtype' => SYSMAP_ELEMENT_TYPE_TRIGGER
					],
					[
						'name' => '2 Image URL',
						'url' => 'test',
						'elementtype' => SYSMAP_ELEMENT_TYPE_IMAGE
					]
				]
			]
		]);
	}

	public function testFormMapProperties_Layout() {
		$this->page->login()->open('sysmaps.php?form=Create+map')->waitUntilReady();
		$this->page->assertTitle('Configuration of network maps');
		$this->page->assertHeader('Network maps');
		$form = $this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one();

		// Check tabs, and that correct one is selected by default.
		$this->assertEquals(['Map', 'Sharing'], $form->getTabs());
		$this->assertEquals('Map', $form->getSelectedTab());

		// Check that correct labels are visible.
		$hidden_map_labels = ['Host group label type', '', 'Host label type', '', 'Trigger label type', '',
			'Map label type', '', 'Image label type', '', 'Type', 'List of user group shares', 'List of user shares'
		];
		$sharing_labels = ['Type', 'List of user group shares', 'List of user shares'];
		$map_labels = ['Owner', 'Name', 'Width', 'Height', 'Background image', 'Background scale',
			'Automatic icon mapping', 'Icon highlight', 'Mark elements on trigger status change', 'Display problems',
			'Advanced labels', 'Map element label type', 'Map element label location', 'Show map element labels',
			'Show link labels', 'Problem display', 'Minimum severity', 'Show suppressed problems', 'URLs'
		];

		$this->assertEquals($map_labels, array_values($form->getLabels(CElementFilter::VISIBLE)->asText()));
		$this->assertEquals($hidden_map_labels, array_values($form->getLabels(CElementFilter::NOT_VISIBLE)->asText()));

		// Check the required fields of the Map form.
		$this->assertEquals(['Owner', 'Name', 'Width', 'Height'], $form->getRequiredLabels());

		// Check the default values of the fields.
		$default_values = [
			'Owner' => 'Admin (Zabbix Administrator)',
			'Name' => '',
			'Width' => '800',
			'Height' => '600',
			'Background image' => 'No image',
			'Background scale' => 'Proportionally',
			'Automatic icon mapping' => '<manual>',
			'Icon highlight' => false,
			'Mark elements on trigger status change' => false,
			'Display problems' => 'Expand single problem',
			'Advanced labels' => false,
			'Map element label type' => 'Label',
			'Map element label location' => 'Bottom',
			'Show map element labels' => 'Always',
			'Show link labels' => 'Always',
			'Problem display' => 'All',
			'Minimum severity' => 'Not classified',
			'Show suppressed problems' => false,
			'id:urls_0_name' => '',
			'id:urls_0_url' => '',
			'xpath:.//input[@name="urls[0][elementtype]"]' => '0'
		];
		$form->checkValue($default_values);

		// Check attributes.
		$form->fill(['Owner' => '']);
		$inputs = [
			'id:userid_ms' => [
				'placeholder' => 'type here to search'
			],
			'Name' => [
				'maxlength' => '128'
			],
			'Width' => [
				'maxlength' => '5'
			],
			'Height' => [
				'maxlength' => '5'
			],
			'id:urls_0_name' => [
				'maxlength' => '255'
			],
			'id:urls_0_url' => [
				'maxlength' => '2048'
			]
		];
		foreach ($inputs as $field => $attributes) {
			$this->assertTrue($form->getField($field)->isAttributePresent($attributes));
		}

		$this->assertTrue($form->query('button:Select')->one()->isClickable());

		// Check dropdown values.
		$dropdowns = [
			'Background image' => ['No image', self::BACKGROUND_IMAGE],
			'Automatic icon mapping' => ['<manual>', self::ICON_MAPPING, 'Icon mapping for update', 'Icon mapping one',
				'Icon mapping testForm update expression', 'Icon mapping to check clone functionality',
				'Icon mapping to check delete functionality', 'used_by_map'
			],
			'Map element label type' => ['Label', 'IP address', 'Element name', 'Status only', 'Nothing'],
			'Map element label location' => ['Bottom', 'Left', 'Right', 'Top'],
			'Problem display' => ['All', 'Separated', 'Unacknowledged only'],
			'xpath:.//z-select[@name="urls[0][elementtype]"]' => ['Host', 'Host group', 'Image', 'Map', 'Trigger']
		];
		foreach ($dropdowns as $field => $options) {
			$this->assertEquals($options, $form->getField($field)->getOptions()->asText());
		}

		$form->getField('Advanced labels')->check();
		$this->assertTrue($form->getField('Map element label type')->isVisible(false));

		$dropdowns_advanced_labels = [
			'Host group label type' => ['Label', 'Element name', 'Status only', 'Nothing', 'Custom label'],
			'Host label type' => ['Label', 'IP address', 'Element name', 'Status only', 'Nothing', 'Custom label'],
			'Trigger label type' => ['Label', 'Element name', 'Status only', 'Nothing', 'Custom label'],
			'Map label type' => ['Label', 'Element name', 'Status only', 'Nothing', 'Custom label'],
			'Image label type' => ['Label', 'Element name', 'Nothing', 'Custom label']
		];
		foreach ($dropdowns_advanced_labels as $field => $options) {
			$this->assertEquals($options, $form->getField($field)->getOptions()->asText());
		}

		// Check custom label attributes and values.
		$form->fill([
			'Host group label type' => 'Custom label',
			'Host label type' => 'Custom label',
			'Trigger label type'=> 'Custom label',
			'Map label type' => 'Custom label',
			'Image label type' => 'Custom label'
		]);

		$textarea_xpath = [
			'xpath:.//textarea[@name="label_string_hostgroup"]',
			'xpath:.//textarea[@name="label_string_host"]',
			'xpath:.//textarea[@name="label_string_trigger"]',
			'xpath:.//textarea[@name="label_string_map"]',
			'xpath:.//textarea[@name="label_string_image"]'
		];
		foreach ($textarea_xpath as $textarea) {
			$field = $form->getField($textarea);
			$this->assertEquals('', $field->getValue());

			foreach (['rows' => 7, 'maxlength' => 255, 'spellcheck' => 'false'] as $attribute => $value) {
				$this->assertEquals($value, $field->getAttribute($attribute));
			}
		}

		// Check radio buttons.
		$radiobuttons = [
			'Background scale' => ['None', 'Proportionally'],
			'Display problems' => ['Expand single problem', 'Number of problems',
				'Number of problems and expand most critical one'
			],
			'Show map element labels' => ['Always', 'Auto hide'],
			'Show link labels' => ['Always', 'Auto hide'],
			'Minimum severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster']
		];
		foreach ($radiobuttons as $field => $options) {
			$this->assertEquals($options, $form->getField($field)->getLabels()->asText());
		}

		// Check link to mappings.
		$mappings_url = $form->query('link:show icon mappings')->one();
		$this->assertTrue($mappings_url->isClickable());
		$this->assertEquals('zabbix.php?action=iconmap.list', $mappings_url->getAttribute('href'));
		$mappings_url->click();
		$this->page->switchBrowserWindow(1)->assertHeader('Icon mapping');
		$this->page->switchBrowserWindow(0);

		// Check URL table.
		$url_table = $form->getField('URLs')->asTable();
		$this->assertEquals(['Name', 'URL', 'Element', ''], $url_table->getHeadersText());

		foreach (['Add', 'Remove'] as $button) {
			$this->assertTrue($url_table->query('button', $button)->one()->isClickable());
		}

		foreach (['id:add', 'id:cancel'] as $button) {
			$this->assertTrue($form->query($button)->one()->isClickable());
		}

		// Switch tab to Sharing, and check the form fields.
		$form->selectTab('Sharing');
		$this->assertEquals($sharing_labels, array_values($form->getLabels(CElementFilter::VISIBLE)->asText()));

		$sharing_type = $form->getField('Type');
		$this->assertEquals(['Private', 'Public'], $sharing_type->getLabels()->asText());
		$this->assertEquals('Private', $sharing_type->getValue());

		$tables = [
			'List of user group shares' => ['User groups', 'Permissions', 'Action'],
			'List of user shares' => ['Users', 'Permissions', 'Action']
		];
		foreach ($tables as $label => $expected_headers) {
			$table_element = $form->getField($label)->asTable();
			$this->assertEquals($expected_headers, $table_element->getHeadersText());
			$button_add = $table_element->query('button:Add')->one();
			$this->assertTrue($button_add->isClickable());

			// Add user group / user to check remove and hidden radio buttons.
			$button_add->click();
			$name = ($label === 'List of user group shares') ? 'Internal' : 'Admin';
			COverlayDialogElement::find()->one()->waitUntilReady()->query('link', $name)->one()->click();
			COverlayDialogElement::ensureNotPresent();

			$this->assertTrue($table_element->query('button:Remove')->one()->isClickable());
			$permissions_radio_button = $table_element->query('class:radio-list-control')->one()->asSegmentedRadio();
			$this->assertEquals(['Read-only', 'Read-write'], $permissions_radio_button->getLabels()->asText());
			$this->assertEquals('Read-only', $permissions_radio_button->getText());
		}

		// Re-check the presence of the Add and Cancel buttons.
		foreach (['id:add', 'id:cancel'] as $button) {
			$this->assertTrue($form->query($button)->one()->isClickable());
		}
	}

	public function getMapCommonData() {
		return [
			// #0 Missing mandatory parameter - Name.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => ''
					],
					'incorrect_data' => true,
					'error_details' => 'Incorrect value for field "Name": cannot be empty.'
				]
			],
			// #1 Single space used in Name field.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => ' '
					],
					'incorrect_data' => true,
					'error_details' => 'Incorrect value for field "Name": cannot be empty.'
				]
			],
			// #2 Create with already existing name.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => self::MAP_CLONE
					],
					'error_details' => 'Map "'.self::MAP_CLONE.'" already exists.'
				]
			],
			// #3 Missing mandatory parameter - Owner.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Owner' => '',
						'Name' => 'Test - no owner'
					],
					'error_details' => 'Map owner cannot be empty.'
				]
			],
			// #4 Missing mandatory parameter - Width.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Test - empty width',
						'Width' => ''
					],
					'error_details' => 'Incorrect "width" value for map "Test - empty width".'
				]
			],
			// #5 Missing mandatory parameter - Height.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Test - empty height',
						'Height' => ''
					],
					'error_details' => 'Incorrect "height" value for map "Test - empty height".'
				]
			],
			// #6 Incorrect width value - 0.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Test - 0 width',
						'Width' => 0
					],
					'error_details' => 'Incorrect "width" value for map "Test - 0 width".'
				]
			],
			// #7 Incorrect height value - 0.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Test - 0 height',
						'Height' => 0
					],
					'error_details' => 'Incorrect "height" value for map "Test - 0 height".'
				]
			],
			// #8 Incorrect width value - 65536.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Test - width 65536',
						'Width' => 65536
					],
					'incorrect_data' => true,
					'error_details' => 'Incorrect value "65536" for "Width" field: must be between 0 and 65535.'
				]
			],
			// #9 Incorrect height value - 65536.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Test - height 65536',
						'Height' => 65536
					],
					'incorrect_data' => true,
					'error_details' => 'Incorrect value "65536" for "Height" field: must be between 0 and 65535.'
				]
			],
			// #10 Non-numeric width value.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Test - height char',
						'Width' => 'test'
					],
					'error_details' => 'Incorrect "width" value for map "Test - height char".'
				]
			],
			// #11 Non-numeric height value.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Test - height char',
						'Height' => 'test'
					],
					'error_details' => 'Incorrect "height" value for map "Test - height char".'
				]
			],
			// #12 Empty custom label - Host groups.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Empty custom label',
						'Advanced labels' => true,
						'Host group label type' => 'Custom label'
					],
					'error_details' => 'Custom label for map "Empty custom label" elements of type "host group"'.
							' may not be empty.'
				]
			],
			// #13 Empty custom label - Host.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Empty custom label',
						'Advanced labels' => true,
						'Host label type' => 'Custom label'
					],
					'error_details' => 'Custom label for map "Empty custom label" elements of type "host"'.
							' may not be empty.'
				]
			],
			// #14 Empty custom label - Trigger.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Empty custom label',
						'Advanced labels' => true,
						'Trigger label type' => 'Custom label'
					],
					'error_details' => 'Custom label for map "Empty custom label" elements of type "trigger"'.
							' may not be empty.'
				]
			],
			// #15 Empty custom label - Map.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Empty custom label',
						'Advanced labels' => true,
						'Map label type' => 'Custom label'
					],
					'error_details' => 'Custom label for map "Empty custom label" elements of type "map"'.
							' may not be empty.'
				]
			],
			// #16 Empty custom label - Image.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Empty custom label',
						'Advanced labels' => true,
						'Image label type' => 'Custom label'
					],
					'error_details' => 'Custom label for map "Empty custom label" elements of type "image"'.
							' may not be empty.'
				]
			],
			// #17 Empty URL field.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Empty URL'
					],
					'urls' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => 'TEST',
							'URL' => ''
						]
					],
					'error_details' => 'URL should have both "name" and "url" fields for map "Empty URL".'
				]
			],
			// #18 Empty URL name field.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Empty URL'
					],
					'urls' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => '',
							'URL' => 'TEST'
						]
					],
					'error_details' => 'URL should have both "name" and "url" fields for map "Empty URL".'
				]
			],
			// #19 Non-unique URL name.
			[
				[
					'expected' => TEST_BAD,
					'map_properties' => [
						'Name' => 'Non-unique URL'
					],
					'urls' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => 'TEST',
							'URL' => 'URL-1'
						],
						[
							'action' => USER_ACTION_ADD,
							'Name' => 'TEST',
							'URL' => 'URL-2'
						]
					],
					'error_details' => 'URL name should be unique for map "Non-unique URL".'
				]
			],
			// #20 Mandatory fields.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Map create with mandatory fields'
					]
				]
			],
			// #21 Leading and trailing spaces.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => '   Map create with leading and trailing spaces   ',
						'Width' => ' 800 ',
						'Height' => ' 600 ',
						'Advanced labels' => true,
						'Host group label type' => 'Custom label',
						'id:label_string_hostgroup' => '  Test host group custom label ',
						'Host label type' => 'Custom label',
						'id:label_string_host' => '  Test host custom label ',
						'Trigger label type' => 'Custom label',
						'id:label_string_trigger' => '  Test trigger custom label ',
						'Map label type' => 'Custom label',
						'id:label_string_map' => '  Test map custom label ',
						'Image label type' => 'Custom label',
						'id:label_string_image' => '  Test image custom label '
					],
					'urls' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => '  Test url ',
							'URL' => '  Test url ',
							'Element' => 'Host group'
						]
					],
					'result_urls' => [
						'URLs' => [
							[
								'name' => 'Test url',
								'url' => 'Test url',
								'elementtype' => 'Host group'
							]
						]
					],
					'trim' => [
						'Name',
						'Width',
						'Height',
						'id:label_string_hostgroup',
						'id:label_string_host',
						'id:label_string_trigger',
						'id:label_string_map',
						'id:label_string_image'
					]
				]
			],
			// #22 Maximum string length.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => STRING_128,
						'Width' => '65535',
						'Height' => '65535',
						'Display problems' => 'Number of problems',
						'Advanced labels' => true,
						'Host group label type' => 'Custom label',
						'id:label_string_hostgroup' => STRING_255,
						'Host label type' => 'Custom label',
						'id:label_string_host' => STRING_255,
						'Trigger label type' => 'Custom label',
						'id:label_string_trigger' => STRING_255,
						'Map label type' => 'Custom label',
						'id:label_string_map' => STRING_255,
						'Image label type' => 'Custom label',
						'id:label_string_image' => STRING_255
					],
					'urls' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => STRING_255,
							'URL' => STRING_2048,
							'Element' => 'Host'
						]
					],
					'result_urls' => [
						'URLs' => [
							[
								'name' => STRING_255,
								'url' => STRING_2048,
								'elementtype' => 'Host'
							]
						]
					]
				]
			],
			// #23 XSS imitation text.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => self::XSS_EXAMPLE.' update',
						'Width' => '1000',
						'Height' => '1000',
						'Advanced labels' => true,
						'Host group label type' => 'Custom label',
						'id:label_string_hostgroup' => self::XSS_EXAMPLE,
						'Host label type' => 'Custom label',
						'id:label_string_host' => self::XSS_EXAMPLE,
						'Trigger label type' => 'Custom label',
						'id:label_string_trigger' => self::XSS_EXAMPLE,
						'Map label type' => 'Custom label',
						'id:label_string_map' => self::XSS_EXAMPLE,
						'Image label type' => 'Custom label',
						'id:label_string_image' => self::XSS_EXAMPLE
					],
					'urls' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => self::XSS_EXAMPLE,
							'URL' => self::XSS_EXAMPLE,
							'Element' => 'Host'
						]
					],
					'result_urls' => [
						'URLs' => [
							[
								'name' => self::XSS_EXAMPLE,
								'url' => self::XSS_EXAMPLE,
								'elementtype' => 'Host'
							]
						]
					]
				]
			],
			// #24 Non-default parameters #1.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Owner' => ['guest'],
						'Name' => 'Non-default parameters sysmap 1',
						'Width' => '100',
						'Height' => '200',
						'Background image' => self::BACKGROUND_IMAGE,
						'Background scale' => 'None',
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems and expand most critical one',
						'Advanced labels' => false,
						'Map element label type' => 'Nothing',
						'Map element label location' => 'Top',
						'Show map element labels' => 'Auto hide',
						'Show link labels' => 'Auto hide',
						'Problem display' => 'Unacknowledged only',
						'Minimum severity' => 'Disaster',
						'Show suppressed problems' => true
					]
				]
			],
			// #25 Non-default parameters #2.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Owner' => ['guest'],
						'Name' => 'Non-default parameters sysmap 2',
						'Width' => '100',
						'Height' => '200',
						'Background image' => self::BACKGROUND_IMAGE,
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems',
						'Map element label type' => 'Status only',
						'Map element label location' => 'Right',
						'Problem display' => 'Separated',
						'Minimum severity' => 'High',
						'Show suppressed problems' => true
					]
				]
			],
			// #26 Non-default parameters #3.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Owner' => ['guest'],
						'Name' => 'Non-default parameters sysmap 3',
						'Width' => '100',
						'Height' => '200',
						'Background scale' => 'None',
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems',
						'Map element label type' => 'Element name',
						'Map element label location' => 'Left',
						'Problem display' => 'Separated',
						'Minimum severity' => 'Average',
						'Show suppressed problems' => true
					]
				]
			],
			// #27 Non-default parameters #4.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Owner' => ['guest'],
						'Name' => 'Non-default parameters sysmap 4',
						'Width' => '100',
						'Height' => '200',
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems',
						'Map element label type' => 'IP address',
						'Problem display' => 'Separated',
						'Minimum severity' => 'Warning',
						'Show suppressed problems' => true
					]
				]
			],
			// #28 Advanced labels - Nothing.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Advanced labels: Nothing',
						'Advanced labels' => true,
						'Host group label type' => 'Nothing',
						'Host label type' => 'Nothing',
						'Trigger label type' => 'Nothing',
						'Map label type' => 'Nothing',
						'Image label type' => 'Nothing',
						'Minimum severity' => 'Information'
					]
				]
			],
			// #29 Advanced labels - Element name.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Advanced labels: Element name',
						'Advanced labels' => true,
						'Host group label type' => 'Element name',
						'Host label type' => 'Element name',
						'Trigger label type' => 'Element name',
						'Map label type' => 'Element name',
						'Image label type' => 'Element name'
					]
				]
			],
			// #30 Advanced labels - Status only.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Advanced labels: Status only',
						'Advanced labels' => true,
						'Host group label type' => 'Status only',
						'Host label type' => 'Status only',
						'Trigger label type' => 'Status only',
						'Map label type' => 'Status only',
						'Image label type' => 'Element name'
					]
				]
			],
			// #31 Advanced labels - Label.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Advanced labels: Label',
						'Advanced labels' => true,
						'Host group label type' => 'Label',
						'Host label type' => 'Label',
						'Trigger label type' => 'Label',
						'Map label type' => 'Label',
						'Image label type' => 'Label'
					]
				]
			],
			// #32 Advanced labels - different label types.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Advanced labels: different options',
						'Advanced labels' => true,
						'Host group label type' => 'Label',
						'Host label type' => 'IP address',
						'Trigger label type' => 'Element name',
						'Map label type' => 'Status only',
						'Image label type' => 'Custom label',
						'id:label_string_image' => 'Image custom label'
					]
				]
			],
			// #33 Different type URLs.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Sysmap with multiple URLs'.microtime()
					],
					'urls' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => '1 Host URL',
							'URL' => 'http://test1-url@zabbix.com'
						],
						[
							'action' => USER_ACTION_ADD,
							'Name' => '2 Group URL',
							'URL' => 'http://test2-url@zabbix.com',
							'Element' => 'Host group'
						],
						[
							'action' => USER_ACTION_ADD,
							'Name' => '3 Image URL',
							'URL' => 'http://test3-url@zabbix.com',
							'Element' => 'Image'
						],
						[
							'action' => USER_ACTION_ADD,
							'Name' => '4 Map URL',
							'URL' => 'http://test4-url@zabbix.com',
							'Element' => 'Map'
						],
						[
							'action' => USER_ACTION_ADD,
							'Name' => '5 Trigger URL',
							'URL' => 'http://test5-url@zabbix.com',
							'Element' => 'Trigger'
						]
					],
					'result_urls' => [
						'URLs' => [
							[
								'name' => '1 Host URL',
								'url' => 'http://test1-url@zabbix.com',
								'elementtype' => 'Host'
							],
							[
								'name' => '2 Group URL',
								'url' => 'http://test2-url@zabbix.com',
								'elementtype' => 'Host group'
							],
							[
								'name' => '3 Image URL',
								'url' => 'http://test3-url@zabbix.com',
								'elementtype' => 'Image'
							],
							[
								'name' => '4 Map URL',
								'url' => 'http://test4-url@zabbix.com',
								'elementtype' => 'Map'
							],
							[
								'name' => '5 Trigger URL',
								'url' => 'http://test5-url@zabbix.com',
								'elementtype' => 'Trigger'
							]
						]
					]
				]
			],
			// #34 Sorting by name of URLs.
			[
				[
					'expected' => TEST_GOOD,
					'update_map' => self::MAP_URL_ADD,
					'map_properties' => [
						'Name' => 'URL sorting'.microtime()
					],
					'urls' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => 'Zabbix sysmap',
							'URL' => 'test'
						],
						[
							'action' => USER_ACTION_ADD,
							'Name' => '!test',
							'URL' => 'test'
						],
						[
							'action' => USER_ACTION_ADD,
							'Name' => '9 sysmap',
							'URL' => 'test'
						],
						[
							'action' => USER_ACTION_ADD,
							'Name' => 'Administration map',
							'URL' => 'test'
						],
						[
							'action' => USER_ACTION_ADD,
							'Name' => '02223',
							'URL' => 'test'
						],
						[
							'action' => USER_ACTION_ADD,
							'Name' => '🤖🤖🤖',
							'URL' => 'test'
						],
						[
							'action' => USER_ACTION_ADD,
							'Name' => 'ĀĒļķņЙЭ test special character',
							'URL' => 'test'
						]
					],
					'result_urls' => [
						'URLs' => [
							[
								'name' => '!test',
								'url' => 'test',
								'elementtype' => 'Host'
							],
							[
								'name' => '9 sysmap',
								'url' => 'test',
								'elementtype' => 'Host'
							],
							[
								'name' => '02223',
								'url' => 'test',
								'elementtype' => 'Host'
							],
							[
								'name' => 'Administration map',
								'url' => 'test',
								'elementtype' => 'Host'
							],
							[
								'name' => 'Zabbix sysmap',
								'url' => 'test',
								'elementtype' => 'Host'
							],
							[
								'name' => 'ĀĒļķņЙЭ test special character',
								'url' => 'test',
								'elementtype' => 'Host'
							],
							[
								'name' => '🤖🤖🤖',
								'url' => 'test',
								'elementtype' => 'Host'
							]
						]
					]
				]
			]
		];
	}

	public function testFormMapProperties_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::HASH_SQL);
		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();
		$table->findRow('Name', self::MAP_SIMPLE_UPDATE)->query('link:Properties')->one()->click();
		$form = $this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one();
		$form->submit()->waitUntilStalled();
		$this->assertMessage(TEST_GOOD, 'Network map updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::HASH_SQL));
	}

	/**
	 * @dataProvider getMapCommonData
	 * @dataProvider getMapUpdateData
	 */
	public function testFormMapProperties_Update($data) {
		$this->checkSysmapForm($data, true);
	}

	/**
	 * @dataProvider getMapCommonData
	 */
	public function testFormMapProperties_Create($data) {
		$this->checkSysmapForm($data);
	}

	public function getMapUpdateData() {
		return [
			// #35 Update - delete URLs and change other possible fields.
			[
				[
					'expected' => TEST_GOOD,
					'update_map' => self::MAP_SIMPLE_UPDATE,
					'map_properties' => [
						'Name' => 'Remove URLs'
					],
					'urls' => [
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						],
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						]
					],
					'result_urls' => [
						'URLs' => [
							[
								'name' => '',
								'url' => '',
								'elementtype' => 'Host'
							]
						]
					]
				]
			]
		];
	}

	public function testFormMapProperties_CancelCreate() {
		$old_hash = CDBHelper::getHash(self::HASH_SQL);
		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$this->query('button:Create map')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		$this->query('button:Cancel')->one()->click();
		$this->page->waitUntilReady();

		// Check that user is returned to maps page.
		$this->page->assertHeader('Maps');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::HASH_SQL));
	}

	public function testFormMapProperties_Clone() {
		// Expected parameters of the cloned map.
		$data = [
			'Owner' => ['Admin (Zabbix Administrator)'],
			'Name' => self::CLONED_MAP,
			'Width' => '1000',
			'Height' => '1000',
			'Background image' => self::BACKGROUND_IMAGE,
			'Background scale' => 'Proportionally',
			'Automatic icon mapping' => self::ICON_MAPPING,
			'Icon highlight' => true,
			'Mark elements on trigger status change' => true,
			'Display problems' => 'Number of problems and expand most critical one',
			'Advanced labels' => true,
			'Host group label type' => 'Custom label',
			'Host label type' => 'Custom label',
			'Trigger label type' => 'Status only',
			'Map label type' => 'Element name',
			'Image label type' => 'Label',
			'id:label_string_host' => STRING_255,
			'id:label_string_hostgroup' => 'Host group label 📰📰📰',
			'Map element label location' => 'Top',
			'Show map element labels' => 'Always',
			'Show link labels' => 'Always',
			'Problem display' => 'Separated',
			'Minimum severity' => 'High',
			'Show suppressed problems' => true,
			'URLs' => [
				[
					'name' => '1 Host URL 📰📰📰',
					'url' => 'test 📰📰📰',
					'elementtype' => 'Host'
				],
				[
					'name' => '2 Image URL',
					'url' => 'test',
					'elementtype' => 'Image'
				],
				[
					'name' => '3 Trigger URL',
					'url' => 'test',
					'elementtype' => 'Trigger'
				],
				[
					'name' => '4 Host group - xss',
					'url' => self::XSS_EXAMPLE,
					'elementtype' => 'Host group'
				],
				[
					'name' => STRING_255,
					'url' => STRING_2048,
					'elementtype' => 'Map'
				]
			]
		];

		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();
		$table->findRow('Name', self::MAP_CLONE)->query('link:Properties')->one()->click();
		$form = $this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one();
		$form->query('button:Clone')->one()->click();
		$form->fill(['Name' => self::CLONED_MAP]);
		$form->submit()->waitUntilStalled();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Network map added');

		// Re-open cloned map and check configuration.
		$table->findRow('Name', self::CLONED_MAP)->query('link:Properties')->one()->click();
		$form->checkValue($data);

		// Check that cloned map is present in the database.
		$this->assertEquals(1, CDBHelper::getCount('SELECT sysmapid FROM sysmaps WHERE name='.zbx_dbstr(self::CLONED_MAP)));
	}

	public function testFormMapProperties_Delete() {
		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();
		$table->findRow('Name', self::MAP_CLONE)->query('link:Properties')->one()->click();
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Network map deleted');

		// Check the presence of the map in the list and database.
		$this->assertFalse($table->findRow('Name', self::MAP_CLONE, true)->isPresent());
		$this->assertEquals(0, CDBHelper::getCount('SELECT sysmapid FROM sysmaps WHERE name='.zbx_dbstr(self::MAP_CLONE)));
	}

	/**
	 * Perform sysmap's creation or update and verify the result.
	 *
	 * @param boolean $update	updating is performed
	 */
	protected function checkSysmapForm($data, $update = false) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::HASH_SQL);
		}

		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		if ($update) {
			$update_map = CTestArrayHelper::get($data, 'update_map', self::$map_update);
			$table->findRow('Name', $update_map)->query('link:Properties')->one()->click();
		}
		else {
			$this->query('button:Create map')->one()->click();
		}

		$form = $this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one();
		$form->fill($data['map_properties']);

		if (array_key_exists('urls', $data)) {
			$form->query('class:table-forms-separator')->asMultifieldTable()->one()->fill($data['urls']);
		}

		// Save the input values for later check, that only updated fields are affected.
		$values = $form->getFields()->filter(CElementFilter::VISIBLE)->asValues();
		$form->submit()->waitUntilStalled();

		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage(TEST_BAD, (CTestArrayHelper::get($data, 'incorrect_data')
					? 'Page received incorrect data'
					: (($update) ? 'Cannot update network map' : 'Cannot add network map')),
					$data['error_details']
			);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash(self::HASH_SQL));
		}
		else {
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, $update ? 'Network map updated' : 'Network map added');

			// Trim leading and trailing spaces from expected results if necessary.
			if (CTestArrayHelper::get($data, 'trim')) {
				$data = CTestArrayHelper::trim($data);
			}

			$row = $table->findRow('Name', $data['map_properties']['Name']);
			if ($update) {
				self::$map_update = $data['map_properties']['Name'];
			}

			$row->query('link:Properties')->one()->click();
			$saved_form = $this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one();
			$saved_form->checkValue($data['map_properties']);

			if (array_key_exists('result_urls', $data)) {
				$saved_form->checkValue($data['result_urls']);
			}

			// Overwrite the previously saved URLs for cases with sorting and removing URLs.
			if (CTestArrayHelper::get($data, 'update_map')) {
				$values['URLs'] = $data['result_urls']['URLs'];
			}

			if (CTestArrayHelper::get($data, 'trim')) {
				CTestArrayHelper::trim($values);
			}

			// Check that unchanged fields are not affected.
			$this->assertEquals($values, $saved_form->getFields()->filter(CElementFilter::VISIBLE)->asValues());
		}
	}
}
