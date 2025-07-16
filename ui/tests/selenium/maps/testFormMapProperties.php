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
require_once __DIR__.'/../behaviors/CTableBehavior.php';

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

	const MAP_UPDATE = 'Map for simple update and update test';
	const MAP_CLONE = 'Map for clone and delete test';
	const CLONED_MAP = 'Cloned map';
	const HASH_SQL = 'SELECT * FROM sysmaps ORDER BY sysmapid';
	const ICON_MAPPING = 'Icon mapping for map properties';
	const XSS_EXAMPLE = '<script>alert(\'XSS\');</script>';
	const BACKGROUND_IMAGE = 'Background image for map properties';

	protected static $map_update = 'Map for update test';

	public function prepareMapsData() {
		CDataHelper::call('iconmap.create', [
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
		]);

		$mapping_ids = CDataHelper::getIds('name');

		CDataHelper::call('image.create', [
			[
				'name' => self::BACKGROUND_IMAGE,
				'imagetype' => IMAGE_TYPE_BACKGROUND,
				'image' => 'iVBORw0KGgoAAAANSUhEUgAAAGkAAAA6CAIAAAA8+uA0AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJ'.
					'cEhZcwAAEnQAABJ0Ad5mH3gAAACPSURBVHhe7dChDQAwDMCwft7Xy4emYBsGZpZq3sA37zrvOu867zrvOu867zrvOu867zr'.
					'vOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867z'.
					'rvOu867zrvOu867zrvOu867zrvugNJxGmwt/UO4QAAAABJRU5ErkJggg=='
			]
		]);

		$background_ids = CDataHelper::getIds('name');

		CDataHelper::call('map.create', [
			[
				'name' => self::$map_update,
				'width' => 800,
				'height' => 600,
				'highlight' => SYSMAP_HIGHLIGHT_OFF,
				'label_type' => MAP_LABEL_TYPE_LABEL,
				'urls' => [
					[
						'name' => '1 Host URL',
						'url' => 'test',
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST
					]
					/*
					 * Uncomment additional URLs, when ZBX-26683 is fixed.
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
					 */
				]
			],
			[
				'name' => self::MAP_UPDATE,
				'width' => 10000,
				'height' => 9000,
				'iconmapid' => $mapping_ids[self::ICON_MAPPING],
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
					]
					/*
					 * Uncomment additional URL check, when ZBX-26683 is fixed. Update test case, if necessary
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
					 */
				]
			],
			[
				'name' => self::MAP_CLONE,
				'width' => 1000,
				'height' => 1000,
				'backgroundid' => $background_ids[self::BACKGROUND_IMAGE],
				'iconmapid' => $mapping_ids[self::ICON_MAPPING],
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
					]
					/*
					 * Uncomment additional URL check, when ZBX-26683 is fixed. Update test case, if necessary
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
					 */
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
		$hidden_map_labels = [
			'Host group label type',
			'',
			'Host label type',
			'',
			'Trigger label type',
			'',
			'Map label type',
			'',
			'Image label type',
			'',
			'Type',
			'List of user group shares',
			'List of user shares'
		];
		$sharing_labels = ['Type', 'List of user group shares', 'List of user shares'];
		$map_labels = [
			'Owner',
			'Name',
			'Width',
			'Height',
			'Background image',
			'Automatic icon mapping',
			'Icon highlight',
			'Mark elements on trigger status change',
			'Display problems',
			'Advanced labels',
			'Map element label type',
			'Map element label location',
			'Problem display',
			'Minimum severity',
			'Show suppressed problems',
			'URLs'
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
			'Automatic icon mapping' => '<manual>',
			'Icon highlight' => false,
			'Mark elements on trigger status change' => false,
			'Display problems' => 'Expand single problem',
			'Advanced labels' => false,
			'Map element label type' => 'Label',
			'Map element label location' => 'Bottom',
			'Problem display' => 'All',
			'Minimum severity' => 'Not classified',
			'Show suppressed problems' => false,
			'id:urls_0_name' => '',
			'id:urls_0_url' => '',
			'xpath://input[@name="urls[0][elementtype]"]' => '0'
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
			'Automatic icon mapping' => [
				'<manual>',
				self::ICON_MAPPING,
				'Icon mapping for update',
				'Icon mapping one',
				'Icon mapping testForm update expression',
				'Icon mapping to check clone functionality',
				'Icon mapping to check delete functionality',
				'used_by_map'
			],
			'Map element label type' => ['Label', 'IP address', 'Element name', 'Status only', 'Nothing'],
			'Map element label location' => ['Bottom', 'Left', 'Right', 'Top'],
			'Problem display' => ['All', 'Separated', 'Unacknowledged only'],
			'xpath://z-select[@name="urls[0][elementtype]"]' => ['Host', 'Host group', 'Image', 'Map', 'Trigger']
		];

		$dropdowns_advanced_labels = [
			'Host group label type' => ['Label', 'Element name', 'Status only', 'Nothing', 'Custom label'],
			'Host label type' => ['Label', 'IP address', 'Element name', 'Status only', 'Nothing', 'Custom label'],
			'Trigger label type' => ['Label', 'Element name', 'Status only', 'Nothing', 'Custom label'],
			'Map label type' => ['Label', 'Element name', 'Status only', 'Nothing', 'Custom label'],
			'Image label type' => ['Label', 'Element name', 'Nothing', 'Custom label']
		];

		foreach ($dropdowns as $field => $options) {
			($field == 'xpath://z-select[@name="urls[0][elementtype]"]')
				? $this->assertEquals($options, $form->getField($field)->asDropdown()->getOptions()->asText())
				: $this->assertEquals($options, $form->getField($field)->getOptions()->asText());
		}

		$form->getField('Advanced labels')->check();

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

		$custom_label_xpath = [
			'xpath://textarea[@name="label_string_hostgroup"]',
			'xpath://textarea[@name="label_string_host"]',
			'xpath://textarea[@name="label_string_trigger"]',
			'xpath://textarea[@name="label_string_map"]',
			'xpath://textarea[@name="label_string_image"]'
		];

		foreach($custom_label_xpath as $xpath) {
			$field = $form->getField($xpath);
			$this->assertEquals('', $field->getValue());
			$this->assertEquals('false', $field->getAttribute('spellcheck'));
			// TODO: When ZBX-26089 is merged, uncomment maxlength attribute check.
//			$this->assertEquals(255, $field->getAttribute('maxlength'));
		}

		// Check radio buttons.
		$radiobuttons = [
			'Display problems' => [
				'Expand single problem',
				'Number of problems',
				'Number of problems and expand most critical one'
			],
			'Minimum severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster']
		];

		foreach ($radiobuttons as $field => $options) {
			$this->assertEquals($options, $form->getField($field)->getLabels()->asText());
		}

		// Check custom label attributes and values.
		$form->fill([
			'Host group label type' => 'Custom label',
			'Host label type' => 'Custom label',
			'Trigger label type' => 'Custom label',
			'Map label type' => 'Custom label',
			'Image label type' => 'Custom label'
			]
		);

		// Check link to mappings.
		$mappings_url = $form->query('link:show icon mappings')->one();
		$this->assertTrue($mappings_url->isClickable());
		$this->assertEquals('zabbix.php?action=iconmap.list', $mappings_url->getAttribute('href'));
		$mappings_url->click();
		$this->page->switchBrowserWindow(1)->assertHeader('Icon mapping');
		$this->page->switchBrowserWindow(0);

		// Check URL table.
		$url_table = $form->getField('URLs')->asTable();
		$this->assertEquals(['Name', 'URL', 'Element', 'Action'], $url_table->getHeadersText());

		foreach (['Add', 'Remove'] as $button) {
			$this->assertTrue($url_table->query('button:'.$button)->one()->isClickable());
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
			COverlayDialogElement::find()->one()->waitUntilReady()->query('link:'.$name)->one()->click();
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

	public function getMapValidationData() {
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
						'Name' => self::MAP_UPDATE
					],
					'error_details' => 'Map "'.self::MAP_UPDATE.'" already exists.'
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
					'error_details' => 'Custom label for map "Empty custom label" elements of type "host group" '.
							'may not be empty.'
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
					'error_details' => 'Custom label for map "Empty custom label" elements of type "host" '.
							'may not be empty.'
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
					'error_details' => 'Custom label for map "Empty custom label" elements of type "trigger" '.
							'may not be empty.'
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
					'error_details' => 'Custom label for map "Empty custom label" elements of type "map" '.
							'may not be empty.'
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
					'error_details' => 'Custom label for map "Empty custom label" elements of type "image" '.
							'may not be empty.'
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
			]
		];
	}

	public function getMapCreateData() {
		return [
			// #20 Create with mandatory fields.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Map create with mandatory fields'
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Map create with mandatory fields',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => false,
						'Map element label type' => 'Label',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => '',
								'URL' => '',
								'Element' => 'Host'
							]
						]
					]
				]
			],
			// #21 Create with leading and trailing spaces.
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
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Map create with leading and trailing spaces',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'id:label_string_hostgroup' => 'Test host group custom label',
						'id:label_string_host' => 'Test host custom label',
						'id:label_string_trigger' => 'Test trigger custom label',
						'id:label_string_map' => 'Test map custom label',
						'id:label_string_image' => 'Test image custom label',
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => 'Test url',
								'URL' => 'Test url',
								'Element' => 'Host group'
							]
						]
					]
				]
			],
			// #22 Create with maximum string length.
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
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => STRING_128,
						'Width' => '65535',
						'Height' => '65535',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Number of problems',
						'Advanced labels' => true,
						'id:label_string_hostgroup' => STRING_255,
						'id:label_string_host' => STRING_255,
						'id:label_string_trigger' => STRING_255,
						'id:label_string_map' => STRING_255,
						'id:label_string_image' => STRING_255,
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => STRING_255,
								'URL' => STRING_2048,
								'Element' => 'Host'
							]
						]
					]
				]
			],
			/**
			 * TODO: Uncomment test case, when ZBX-26089 is fixed. Update test case if necessary.
			// # Create with string length which exceeds maximum allowed value.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => STRING_6000,
						'Width' => '65535',
						'Height' => '65535',
						'Advanced labels' => true,
						'Host group label type' => 'Custom label',
						'id:label_string_hostgroup' => STRING_6000,
						'Host label type' => 'Custom label',
						'id:label_string_host' => STRING_6000,
						'Trigger label type' => 'Custom label',
						'id:label_string_trigger' => STRING_6000,
						'Map label type' => 'Custom label',
						'id:label_string_map' => STRING_6000,
						'Image label type' => 'Custom label',
						'id:label_string_image' => STRING_6000,
					],
					'urls' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => STRING_6000,
							'URL' => STRING_6000,
							'Element' => 'Host'
						]
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => STRING_128,
						'Width' => '65535',
						'Height' => '65535',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'id:label_string_hostgroup' => STRING_255,
						'id:label_string_host' => STRING_255,
						'id:label_string_trigger' => STRING_255,
						'id:label_string_map' => STRING_255,
						'id:label_string_image' => STRING_255,
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => STRING_255,
								'URL' => STRING_2048,
								'Element' => 'Host'
							]
						]
					]
				]
			],
			 */
			// #23 Create with non-default parameters #1.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Owner' => ['guest'],
						'Name' => 'Non-default parameters sysmap 1',
						'Width' => '100',
						'Height' => '200',
						'Background image' => self::BACKGROUND_IMAGE,
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems and expand most critical one',
						'Map element label type' => 'Nothing',
						'Map element label location' => 'Top',
						'Problem display' => 'Unacknowledged only',
						'Minimum severity' => 'Disaster',
						'Show suppressed problems' => true
					],
					'result' => [
						'Owner' => ['guest'],
						'Name' => 'Non-default parameters sysmap 1',
						'Width' => '100',
						'Height' => '200',
						'Background image' => self::BACKGROUND_IMAGE,
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems and expand most critical one',
						'Advanced labels' => false,
						'Map element label type' => 'Nothing',
						'Map element label location' => 'Top',
						'Problem display' => 'Unacknowledged only',
						'Minimum severity' => 'Disaster',
						'Show suppressed problems' => true,
						'URLs' => [
							[
								'Name' => '',
								'URL' => '',
								'Element' => 'Host'
							]
						]
					]
				]
			],
			// #24 Create with non-default parameters #2.
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
					],
					'result' => [
						'Owner' => ['guest'],
						'Name' => 'Non-default parameters sysmap 2',
						'Width' => '100',
						'Height' => '200',
						'Background image' => self::BACKGROUND_IMAGE,
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems',
						'Advanced labels' => false,
						'Map element label type' => 'Status only',
						'Map element label location' => 'Right',
						'Problem display' => 'Separated',
						'Minimum severity' => 'High',
						'Show suppressed problems' => true,
						'URLs' => [
							[
								'Name' => '',
								'URL' => '',
								'Element' => 'Host'
							]
						]
					]
				]
			],
			// #25 Create with non-default parameters #3.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Owner' => ['guest'],
						'Name' => 'Non-default parameters sysmap 3',
						'Width' => '100',
						'Height' => '200',
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems',
						'Map element label type' => 'Element name',
						'Map element label location' => 'Left',
						'Problem display' => 'Separated',
						'Minimum severity' => 'Average',
						'Show suppressed problems' => true
					],
					'result' => [
						'Owner' => ['guest'],
						'Name' => 'Non-default parameters sysmap 3',
						'Width' => '100',
						'Height' => '200',
						'Background image' => 'No image',
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems',
						'Advanced labels' => false,
						'Map element label type' => 'Element name',
						'Map element label location' => 'Left',
						'Problem display' => 'Separated',
						'Minimum severity' => 'Average',
						'Show suppressed problems' => true,
						'URLs' => [
							[
								'Name' => '',
								'URL' => '',
								'Element' => 'Host'
							]
						]
					]
				]
			],
			// #26 Create with non-default parameters #4.
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
					],
					'result' => [
						'Owner' => ['guest'],
						'Name' => 'Non-default parameters sysmap 4',
						'Width' => '100',
						'Height' => '200',
						'Background image' => 'No image',
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems',
						'Advanced labels' => false,
						'Map element label type' => 'IP address',
						'Map element label location' => 'Bottom',
						'Problem display' => 'Separated',
						'Minimum severity' => 'Warning',
						'Show suppressed problems' => true,
						'URLs' => [
							[
								'Name' => '',
								'URL' => '',
								'Element' => 'Host'
							]
						]
					]
				]
			],
			// #27 Advanced labels - Nothing.
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
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Advanced labels: Nothing',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'Host group label type' => 'Nothing',
						'Host label type' => 'Nothing',
						'Trigger label type' => 'Nothing',
						'Map label type' => 'Nothing',
						'Image label type' => 'Nothing',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Information',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => '',
								'URL' => '',
								'Element' => 'Host'
							]
						]
					]
				]
			],
			// #28 Advanced labels - Element name.
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
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Advanced labels: Element name',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'Host group label type' => 'Element name',
						'Host label type' => 'Element name',
						'Trigger label type' => 'Element name',
						'Map label type' => 'Element name',
						'Image label type' => 'Element name',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => '',
								'URL' => '',
								'Element' => 'Host'
							]
						]
					]
				]
			],
			// #29 Advanced labels - Status only.
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
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Advanced labels: Status only',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'Host group label type' => 'Status only',
						'Host label type' => 'Status only',
						'Trigger label type' => 'Status only',
						'Map label type' => 'Status only',
						'Image label type' => 'Element name',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => '',
								'URL' => '',
								'Element' => 'Host'
							]
						]
					]
				]
			],
			// #30 Advanced labels - Label.
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
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Advanced labels: Label',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'Host group label type' => 'Label',
						'Host label type' => 'Label',
						'Trigger label type' => 'Label',
						'Map label type' => 'Label',
						'Image label type' => 'Label',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => '',
								'URL' => '',
								'Element' => 'Host'
							]
						]
					]
				]
			],
			// #31 Advanced labels - different label types.
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
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Advanced labels: different options',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'Host group label type' => 'Label',
						'Host label type' => 'IP address',
						'Trigger label type' => 'Element name',
						'Map label type' => 'Status only',
						'Image label type' => 'Custom label',
						'id:label_string_image' => 'Image custom label',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => '',
								'URL' => '',
								'Element' => 'Host'
							]
						]
					]
				]
			],
			// #32 Check creation of different type URLs.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Sysmap with multiple URLs'
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
						]
						/**
						 * TODO: Uncomment test case, when ZBX-26683 is fixed. Update test case if necessary.
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
						 */
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Sysmap with multiple URLs',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => false,
						'Map element label type' => 'Label',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => '1 Host URL',
								'URL' => 'http://test1-url@zabbix.com',
								'Element' => 'Host'
							],
							[
								'Name' => '2 Group URL',
								'URL' => 'http://test2-url@zabbix.com',
								'Element' => 'Host group'
							]
							/**
							 * TODO: Uncomment test case, when ZBX-26683 is fixed. Update test case if necessary.
							[
								'Name' => '3 Image URL',
								'URL' => 'http://test3-url@zabbix.com',
								'Element' => 'Image'
							],
							[
								'Name' => '4 Map URL',
								'URL' => 'http://test4-url@zabbix.com',
								'Element' => 'Map'
							],
							[
								'Name' => '5 Trigger URL',
								'URL' => 'http://test5-url@zabbix.com',
								'Element' => 'Trigger'
							]
							 */
						]
					]
				]
			]
			/**
			 * TODO: Uncomment test case, when ZBX-26683 is fixed. Update test case if necessary.
			// #33 Check sorting by name of URLs.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'URL sorting'
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
							'Name' => '012345',
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
						]
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'URL sorting',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => false,
						'Map element label type' => 'Label',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => '012345',
								'URL' => 'test',
								'Element' => 'Host'
							],
							[
								'Name' => '02223',
								'URL' => 'test',
								'Element' => 'Host'
							],
							[
								'Name' => '9 sysmap',
								'URL' => 'test',
								'Element' => 'Host'
							],
							[
								'Name' => 'Administration map',
								'URL' => 'test',
								'Element' => 'Host'
							],
							[
								'Name' => 'Zabbix sysmap',
								'URL' => 'test',
								'Element' => 'Host'
							]
						]
					]
				]
			]
			 */
		];
	}

	/**
	 * @dataProvider getMapValidationData
	 * @dataProvider getMapCreateData
	 */
	public function testFormMapProperties_Create($data) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::HASH_SQL);
		}

		$this->page->login()->open('sysmaps.php?form=Create+map')->waitUntilReady();
		$form = $this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one();
		$form->fill($data['map_properties']);

		if(array_key_exists('urls', $data)) {
			$form->query('class:table-forms-separator')->asMultifieldTable()->one()->fill($data['urls']);
		}

		$form->submit();

		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage(TEST_BAD, (array_key_exists('incorrect_data', $data)
					? 'Page received incorrect data'
					: 'Cannot add network map'),
					$data['error_details']
			);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash(self::HASH_SQL));
		}
		else {
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Network map added');
			$table = $this->query('class:list-table')->asTable()->one();
			$table->findRow('Name', $data['result']['Name'])->query('link:Properties')->one()->click();
			$this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one()->checkValue($data['result']);
		}
	}

	public function testFormMapProperties_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::HASH_SQL);
		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();
		$table->findRow('Name', self::MAP_UPDATE)->query('link:Properties')->one()->click();
		$form = $this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one();
		$form->submit();
		$this->assertMessage(TEST_GOOD, 'Network map updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::HASH_SQL));
	}

	public function getMapUpdateData() {
		return [
			// #20 Update to check trailing of spaces and different symbols of input type text fields.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => '  🌑🌑🌑 Update: trailing, leading spaces, symbols: ĀāŅņШщЙй㐤㐃 🌕🌕🌕 ',
						'Width' => ' 800 ',
						'Height' => ' 600 ',
						'Advanced labels' => true,
						'Host group label type' => 'Custom label',
						'id:label_string_hostgroup' => ' Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃 ',
						'Host label type' => 'Custom label',
						'id:label_string_host' => '   Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃   ',
						'Trigger label type' => 'Custom label',
						'id:label_string_trigger' => ' Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃 ',
						'Map label type' => 'Custom label',
						'id:label_string_map' => ' Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃 ',
						'Image label type' => 'Custom label',
						'id:label_string_image' => ' Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃 '
					],
					'urls' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => ' URL ₥₳₽1 name 📡 ',
							'URL' => ' URL ₥₳₽1 📡 ',
							'Element' => 'Host'
						]
						/*
						 * Uncomment additional URL check, when ZBX-26683 is fixed. Update test case, if necessary
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Name' => ' URL ₥₳₽2 name 📡 ',
							'URL' => ' URL ₥₳₽2 📡 ',
							'Element' => 'Host group'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'Name' => ' URL ₥₳₽3 name 📡 ',
							'URL' => ' URL ₥₳₽3 📡 ',
							'Element' => 'Image'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 3,
							'Name' => ' URL ₥₳₽4 name 📡 ',
							'URL' => ' URL ₥₳₽4 📡 ',
							'Element' => 'Map'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 4,
							'Name' => ' URL ₥₳₽5 name 📡 ',
							'URL' => ' URL ₥₳₽5 📡 ',
							'Element' => 'Trigger'
						]
						 */
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => '🌑🌑🌑 Update: trailing, leading spaces, symbols: ĀāŅņШщЙй㐤㐃 🌕🌕🌕',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'id:label_string_hostgroup' => 'Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃',
						'id:label_string_host' => 'Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃',
						'id:label_string_trigger' => 'Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃',
						'id:label_string_map' => 'Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃',
						'id:label_string_image' => 'Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃',
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => 'URL ₥₳₽1 name 📡',
								'URL' => 'URL ₥₳₽1 📡',
								'Element' => 'Host'
							]
							/*
							 * Uncomment additional URL check, when ZBX-26683 is fixed. Update test case, if necessary
							[
								'Name' => 'URL ₥₳₽2 name 📡',
								'URL' => 'URL ₥₳₽2 📡',
								'Element' => 'Host group'
							]
							[
								'Name' => 'URL ₥₳₽3 name 📡',
								'URL' => 'URL ₥₳₽3 📡',
								'Element' => 'Image'
							],
							[
								'Name' => 'URL ₥₳₽4 name 📡',
								'URL' => 'URL ₥₳₽4 📡',
								'Element' => 'Map'
							],
							[
								'Name' => 'URL ₥₳₽5 name 📡',
								'URL' => 'URL ₥₳₽5 📡',
								'Element' => 'Trigger'
							]
							 */
						]
					]
				]
			],
			// #21 Update with maximum string length, width, height values.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Update: maximum possible sysmap name length 128 characters test:'.STRING_64,
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
							'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 1 :'.STRING_128,
							'URL' => STRING_2048,
							'Element' => 'Host'
						]
						/*
						 * Uncomment additional URL check, when ZBX-26683 is fixed. Update test case, if necessary
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 2 :'.STRING_128,
							'URL' => STRING_2048,
							'Element' => 'Host group'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 3 :'.STRING_128,
							'URL' => STRING_2048,
							'Element' => 'Image'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 3,
							'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 4 :'.STRING_128,
							'URL' => STRING_2048,
							'Element' => 'Map'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 4,
							'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 5 :'.STRING_128,
							'URL' => STRING_2048,
							'Element' => 'Trigger'
						]
						 */
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Update: maximum possible sysmap name length 128 characters test:'.STRING_64,
						'Width' => '65535',
						'Height' => '65535',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Number of problems',
						'Advanced labels' => true,
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'id:label_string_hostgroup' => STRING_255,
						'id:label_string_host' => STRING_255,
						'id:label_string_trigger' => STRING_255,
						'id:label_string_map' => STRING_255,
						'id:label_string_image' => STRING_255,
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 1 :'.
									STRING_128,
								'URL' => STRING_2048,
								'Element' => 'Host'
							]
							/*
							 * Uncomment additional URL check, when ZBX-26683 is fixed. Update test case, if necessary
							[
								'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 2 :'.
									STRING_128,
								'URL' => STRING_2048,
								'Element' => 'Host group'
							],
							[
								'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 3 :'.
									STRING_128,
								'URL' => STRING_2048,
								'Element' => 'Image'
							],
							[
								'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 4 :'.
									STRING_128,
								'URL' => STRING_2048,
								'Element' => 'Map'
							],
							[
								'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 5 :'.
									STRING_128,
								'URL' => STRING_2048,
								'Element' => 'Trigger'
							]
							 */
						]
					]
				]
			],
			// #22 Update with XSS imitation text.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => self::XSS_EXAMPLE.' update',
						'Width' => '1000',
						'Height' => '1000',
						'Display problems' => 'Number of problems',
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
						/*
						 * Uncomment additional URL check, when ZBX-26683 is fixed. Update test case, if necessary
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'Name' => self::XSS_EXAMPLE.' 1',
							'URL' => self::XSS_EXAMPLE,
							'Element' => 'Host group'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'Name' => self::XSS_EXAMPLE.' 2',
							'URL' => self::XSS_EXAMPLE,
							'Element' => 'Image'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 3,
							'Name' => self::XSS_EXAMPLE.' 3',
							'URL' => self::XSS_EXAMPLE,
							'Element' => 'Map'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 4,
							'Name' => self::XSS_EXAMPLE.' 4',
							'URL' => self::XSS_EXAMPLE,
							'Element' => 'Trigger'
						]
						 */
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => self::XSS_EXAMPLE.' update',
						'Width' => '1000',
						'Height' => '1000',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Number of problems',
						'Advanced labels' => true,
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'id:label_string_hostgroup' => self::XSS_EXAMPLE,
						'id:label_string_host' => self::XSS_EXAMPLE,
						'id:label_string_trigger' => self::XSS_EXAMPLE,
						'id:label_string_map' => self::XSS_EXAMPLE,
						'id:label_string_image' => self::XSS_EXAMPLE,
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => self::XSS_EXAMPLE,
								'URL' => self::XSS_EXAMPLE,
								'Element' => 'Host'
							]
							/*
							 * Uncomment additional URL check, when ZBX-26683 is fixed. Update test case, if necessary
							[
								'Name' => self::XSS_EXAMPLE.' 1',
								'URL' => self::XSS_EXAMPLE,
								'Element' => 'Host group'
							],
							[
								'Name' => self::XSS_EXAMPLE.' 2',
								'URL' => self::XSS_EXAMPLE,
								'Element' => 'Image'
							],
							[
								'Name' => self::XSS_EXAMPLE.' 3',
								'URL' => self::XSS_EXAMPLE,
								'Element' => 'Map'
							],
							[
								'Name' => self::XSS_EXAMPLE.' 4',
								'URL' => self::XSS_EXAMPLE,
								'Element' => 'Trigger'
							]
							 */
						]
					]
				]
			],
			/**
			 * TODO: Uncomment test case, when ZBX-26089 is fixed. Update test case if necessary.
			// # Update with string length which exceeds maximum allowed value.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => STRING_64.' Update: maximum possible sysmap name length 128 characters test!',
						'Width' => '65535',
						'Height' => '65535',
						'Advanced labels' => true,
						'Host group label type' => 'Custom label',
						'id:label_string_hostgroup' => STRING_6000,
						'Host label type' => 'Custom label',
						'id:label_string_host' => STRING_6000,
						'Trigger label type' => 'Custom label',
						'id:label_string_trigger' => STRING_6000,
						'Map label type' => 'Custom label',
						'id:label_string_map' => STRING_6000,
						'Image label type' => 'Custom label',
						'id:label_string_image' => STRING_6000,
						'id:urls_0_name' => STRING_6000,
						'id:urls_0_url' => STRING_6000
					],
					'urls' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => STRING_6000,
							'URL' => STRING_6000,
							'Element' => 'Host'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => STRING_6000,
							'URL' => STRING_6000,
							'Element' => 'Host group'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => STRING_6000,
							'URL' => STRING_6000,
							'Element' => 'Image'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => STRING_6000,
							'URL' => STRING_6000,
							'Element' => 'Map'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'Name' => STRING_6000,
							'URL' => STRING_6000,
							'Element' => 'Trigger'
						]
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => STRING_64.' Update: maximum possible sysmap name length 128 characters test',
						'Width' => '65535',
						'Height' => '65535',
						'Background image' => 'No image',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'id:label_string_hostgroup' => STRING_255,
						'id:label_string_host' => STRING_255,
						'id:label_string_trigger' => STRING_255,
						'id:label_string_map' => STRING_255,
						'id:label_string_image' => STRING_255,
						'Map element label location' => 'Bottom',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => STRING_255,
								'URL' => STRING_2048,
								'Element' => 'Host'
							],
							[
								'Name' => STRING_255,
								'URL' => STRING_2048,
								'Element' => 'Host group'
							],
							[
								'Name' => STRING_255,
								'URL' => STRING_2048,
								'Element' => 'Image'
							],
							[
								'Name' => STRING_255,
								'URL' => STRING_2048,
								'Element' => 'Map'
							],
							[
								'Name' => STRING_255,
								'URL' => STRING_2048,
								'Element' => 'Trigger'
							]
						]
					]
				]
			],
			 */
			// #23 Update - change advanced label fields of existing map.
			[
				[
					'expected' => TEST_GOOD,
					'map_name' => self::MAP_UPDATE,
					'map_properties' => [
						'Host group label type' => 'Label',
						'Host label type' => 'IP address',
						'Trigger label type' => 'Status only',
						'Map label type' => 'Nothing',
						'Image label type' => 'Custom label',
						'id:label_string_image' => 'Update labels check'
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => self::MAP_UPDATE,
						'Width' => '10000',
						'Height' => '9000',
						'Background image' => 'No image',
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems and expand most critical one',
						'Advanced labels' => true,
						'Host group label type' => 'Label',
						'Host label type' => 'IP address',
						'Trigger label type' => 'Status only',
						'Map label type' => 'Nothing',
						'Image label type' => 'Custom label',
						'id:label_string_image' => 'Update labels check',
						'Map element label location' => 'Right',
						'Problem display' => 'Separated',
						'Minimum severity' => 'Disaster',
						'Show suppressed problems' => true,
						'URLs' => [
							[
								'Name' => '1 Host URL',
								'URL' => 'test',
								'Element' => 'Host'
							]
							/*
							 * Uncomment additional URL check, when ZBX-26683 is fixed. Update test case, if necessary
							[
								'Name' => '2 Host group URL',
								'URL' => 'test',
								'Element' => 'Host group'
							],
							[
								'Name' => '3 Map URL',
								'URL' => 'test',
								'Element' => 'Map'
							],
							[
								'Name' => '4 Image URL',
								'URL' => 'test',
								'Element' => 'Image'
							],
							[
								'Name' => '5 Trigger URL',
								'URL' => 'test',
								'Element' => 'Trigger'
							]
							 */
						]
					]
				]
			],
			// #4 Update - change other possible fields.
			[
				[
					'expected' => TEST_GOOD,
					'map_name' => self::MAP_UPDATE,
					'remove_urls' => true,
					'map_properties' => [
						'Owner' => 'guest',
						'Background image' => self::BACKGROUND_IMAGE,
						'Display problems' => 'Number of problems',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Advanced labels' => false,
						'Map element label type' => 'Element name',
						'Map element label location' => 'Left',
						'Problem display' => 'Unacknowledged only',
						'Minimum severity' => 'Information',
						'Show suppressed problems' => false
					],
					'urls' => [
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						]
						/*
						 * Uncomment additional URL check, when ZBX-26683 is fixed. Update test case, if necessary
						[
							'action' => USER_ACTION_REMOVE,
							'index' => 0
						]
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
						 */
					],
					'result' => [
						'Owner' => ['guest'],
						'Name' => self::MAP_UPDATE,
						'Width' => '10000',
						'Height' => '9000',
						'Background image' => self::BACKGROUND_IMAGE,
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Number of problems',
						'Advanced labels' => false,
						'Map element label type' => 'Element name',
						'Map element label location' => 'Left',
						'Problem display' => 'Unacknowledged only',
						'Minimum severity' => 'Information',
						'Show suppressed problems' => false,
						'URLs' => [
							[
								'Name' => '',
								'URL' => '',
								'Element' => 'Host'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getMapValidationData
	 * @dataProvider getMapUpdateData
	 */
	public function testFormMapProperties_Update($data) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::HASH_SQL);
		}

		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();
		$table->findRow('Name', (array_key_exists('map_name', $data) ? $data['map_name'] : self::$map_update))
				->query('link:Properties')->one()->click();
		$form = $this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one();
		$form->fill($data['map_properties']);

		if(array_key_exists('urls', $data)) {
			$form->query('class:table-forms-separator')->asMultifieldTable()->one()->fill($data['urls']);
		}

		$form->submit();

		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage(TEST_BAD, (array_key_exists('incorrect_data', $data)
					? 'Page received incorrect data'
					: 'Cannot update network map'),
					$data['error_details']
			);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash(self::HASH_SQL));
		}
		else {
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Network map updated');
			$table = $this->query('class:list-table')->asTable()->one();
			$table->findRow('Name', $data['result']['Name'])->query('link:Properties')->one()->click();
			$this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one()->checkValue($data['result']);
			self::$map_update = $this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one()->getField('Name')
					->getValue();
		}
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
			'result' => [
				'Owner' => ['Admin (Zabbix Administrator)'],
				'Name' => self::CLONED_MAP,
				'Width' => '1000',
				'Height' => '1000',
				'Background image' => self::BACKGROUND_IMAGE,
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
				'Problem display' => 'Separated',
				'Minimum severity' => 'High',
				'Show suppressed problems' => true,
				'URLs' => [
					[
						'Name' => '1 Host URL 📰📰📰',
						'URL' => 'test 📰📰📰',
						'Element' => 'Host'
					]
					/*
					 * Uncomment additional URL check, when ZBX-26683 is fixed. Update test case, if necessary
					[
						'Name' => '2 Image URL',
						'URL' => 'test',
						'Element' => 'Image'
					],
					[
						'Name' => '3 Trigger URL',
						'URL' => 'test',
						'Element' => 'Trigger'
					],
					[
						'Name' => '4 Host group - xss',
						'URL' => self::XSS_EXAMPLE,
						'Element' => 'Host group'
					],
					[
						'Name' => STRING_255,
						'URL' => STRING_2048,
						'Element' => 'Map'
					]
					 */
				]
			]
		];

		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();
		$table->findRow('Name', self::MAP_CLONE)->query('link:Properties')->one()->click();
		$form = $this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one();
		$form->query('button:Clone')->one()->click();
		$form->fill(['Name' => self::CLONED_MAP]);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Network map added');

		// Re-open cloned map and check configuration.
		$table->findRow('Name', self::CLONED_MAP)->query('link:Properties')->one()->click();
		$this->query('id:sysmap-form')->waitUntilPresent()->asForm()->one()->checkValue($data['result']);

		// Check that cloned map is present in the database.
		$this->assertEquals(1, CDBHelper::getCount('SELECT sysmapid FROM sysmaps WHERE name=\''.self::CLONED_MAP.'\''));
	}

	public function testFormMapProperties_Delete() {
		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();
		$table->findRow('Name', self::MAP_CLONE)->query('link:Properties')->one()->click();
		$this->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Network map deleted');

		// Check the pressence of the map in the list and database.
		$this->assertFalse($table->findRow('Name', self::MAP_CLONE, true)->isPresent());
		$this->assertEquals(0, CDBHelper::getCount('SELECT sysmapid FROM sysmaps WHERE name=\''.self::MAP_CLONE.'\''));
	}
}
