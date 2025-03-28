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

	protected static $map_update = 'Map for update test';
	const MAP_UPDATE = 'Map for simple update and update test';
	const MAP_CLONE = 'Map for clone and delete test';
	const CLONED_MAP = 'Cloned map';
	const HASH_SQL = 'SELECT * FROM sysmaps ORDER BY sysmapid';
	const ICON_MAPPING = 'Icon mapping for map properties';
	const XSS_EXAMPLE = '<script>alert(\'XSS\');</script>';
	const BACKGROUND_IMAGE = 'Background image for map properties';

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

		CDataHelper::call('image.create', [
			[
				'name' => self::BACKGROUND_IMAGE,
				'imagetype' => 2,
				'image' => 'iVBORw0KGgoAAAANSUhEUgAAAGkAAAA6CAIAAAA8+uA0AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJ'.
					'cEhZcwAAEnQAABJ0Ad5mH3gAAACPSURBVHhe7dChDQAwDMCwft7Xy4emYBsGZpZq3sA37zrvOu867zrvOu867zrvOu867zr'.
					'vOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867zrvOu867z'.
					'rvOu867zrvOu867zrvOu867zrvugNJxGmwt/UO4QAAAABJRU5ErkJggg=='
			]
		]);

		$id_mapping = CDBHelper::getValue('SELECT iconmapid FROM icon_map WHERE name='.zbx_dbstr(self::ICON_MAPPING));
		$id_background = CDBHelper::getValue('SELECT imageid FROM images WHERE name='.zbx_dbstr(self::BACKGROUND_IMAGE));

		CDataHelper::call('map.create', [
			[
				'name' => self::$map_update,
				'width' => 800,
				'height' => 600,
				'highlight' => 0,
				'label_type' => 0
			],
			[
				'name' => self::MAP_UPDATE,
				'width' => 10000,
				'height' => 9000,
				'iconmapid' => $id_mapping,
				'markelements' => 1,
				'highlight' => 1,
				'expandproblem' => 2,
				'label_format' => 1,
				'label_location' => 2,
				'label_type_host' => 5,
				'label_string_host' => 'Host label 📰📰📰',
				'label_type_hostgroup' => 4,
				'label_type_image' => 0,
				'label_type_map' => 3,
				'label_type_trigger' => 2,
				'severity_min' => 5,
				'show_unack' => 2,
				'show_suppressed' => 1,
				'urls' => [
					[
						'name' => '1 Host URL',
						'url' => 'test',
						'elementtype' => 0
					],
					[
						'name' => '2 Host group URL',
						'url' => 'test',
						'elementtype' => 3
					],
					[
						'name' => '3 Map URL',
						'url' => 'test',
						'elementtype' => 1
					],
					[
						'name' => '5 Trigger URL',
						'url' => 'test',
						'elementtype' => 2
					],
					[
						'name' => '4 Image URL',
						'url' => 'test',
						'elementtype' => 4
					]
				]
			],
			[
				'name' => self::MAP_CLONE,
				'width' => 1000,
				'height' => 1000,
				'backgroundid' => $id_background,
				'iconmapid' => $id_mapping,
				'markelements' => 1,
				'highlight' => 1,
				'expandproblem' => 2,
				'label_format' => 1,
				'label_location' => 3,
				'label_type_host' => 5,
				'label_type_hostgroup' => 5,
				'label_string_host' => STRING_255,
				'label_string_hostgroup' => 'Host group label 📰📰📰',
				'label_type_image' => 0,
				'label_type_map' => 2,
				'label_type_trigger' => 3,
				'severity_min' => 4,
				'show_unack' => 2,
				'show_suppressed' => 1,
				'urls' => [
					[
						'name' => '1 Host URL 📰📰📰',
						'url' => 'test 📰📰📰',
						'elementtype' => 0
					],
					[
						'name' => STRING_255,
						'url' => STRING_2048,
						'elementtype' => 1
					],
					[
						'name' => '4 Host group - xss',
						'url' => self::XSS_EXAMPLE,
						'elementtype' => 3
					],
					[
						'name' => '3 Trigger URL',
						'url' => 'test',
						'elementtype' => 2
					],
					[
						'name' => '2 Image URL',
						'url' => 'test',
						'elementtype' => 4
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
			'Background scale',
			'Automatic icon mapping',
			'Icon highlight',
			'Mark elements on trigger status change',
			'Display problems',
			'Advanced labels',
			'Map element label type',
			'Map element label location',
			'Show map element labels',
			'Show link labels',
			'Problem display',
			'Minimum severity',
			'Show suppressed problems',
			'URLs'
		];

		$this->assertEquals($map_labels, array_values($form->getLabels()->filter(CElementFilter::VISIBLE)->asText()));
		$this->assertEquals($hidden_map_labels, array_values($form->getLabels()
				->filter(CElementFilter::NOT_VISIBLE)->asText())
		);

		// Check all the fields of the Map form.
		$this->assertEquals(['Owner', 'Name', 'Width', 'Height'], $form->getRequiredLabels());
		$this->assertEquals(['Admin (Zabbix Administrator)'], $form->getField('Owner')->getValue());
		$this->assertTrue($form->query('button:Select')->one()->isClickable());

		$name_field = $form->getField('Name');
		$this->assertEquals(128, $name_field->getAttribute('maxlength'));
		$this->assertEquals('', $name_field->getText());

		foreach (['Width', 'Height'] as $field) {
			$form_field = $form->getField($field);
			$this->assertEquals(5, $form_field->getAttribute('maxlength'));
			$this->assertEquals(($field == 'Width') ? 800 : 600, $form_field->getValue());
		}

		$image_field = $form->getField('Background image');
		$this->assertEquals('No image', $image_field->getValue());
		$this->assertEquals(['No image', self::BACKGROUND_IMAGE], $image_field->getOptions()->asText());

		$background_scale = $form->getField('Background scale');
		$this->assertEquals('Proportionally', $background_scale->getText());
		$this->assertEquals(['None', 'Proportionally'], $background_scale->getLabels()->asText());

		$mapping_field = $form->getField('Automatic icon mapping');
		$mappings = [
			'<manual>',
			self::ICON_MAPPING,
			'Icon mapping for update',
			'Icon mapping one',
			'Icon mapping testForm update expression',
			'Icon mapping to check clone functionality',
			'Icon mapping to check delete functionality',
			'used_by_map'
		];
		$this->assertEquals('<manual>', $mapping_field->getValue());
		$this->assertEquals($mappings, $mapping_field->getOptions()->asText());

		$mappings_url = $form->query('link:show icon mappings')->one();
		$this->assertTrue($mappings_url->isClickable());
		$this->assertEquals('zabbix.php?action=iconmap.list', $mappings_url->getAttribute('href'));

		$this->assertFalse($form->getField('Icon highlight')->isChecked());
		$this->assertFalse($form->getField('Mark elements on trigger status change')->isChecked());

		$display_problems = $form->getField('Display problems');
		$this->assertEquals(['Expand single problem', 'Number of problems', 'Number of problems and expand most critical one'],
				$display_problems->getLabels()->asText()
		);
		$this->assertEquals('Expand single problem', $display_problems->getText());
		$advanced_labels = $form->getField('Advanced labels');
		$this->assertFalse($advanced_labels->isChecked());

		$label_options_map = ['Label', 'IP address', 'Element name', 'Status only', 'Nothing'];
		$label_options = ['Label', 'Element name', 'Status only', 'Nothing', 'Custom label'];
		$label_options_host = ['Label', 'IP address', 'Element name', 'Status only', 'Nothing', 'Custom label'];
		$label_options_image = ['Label', 'Element name', 'Nothing', 'Custom label'];
		$map_element_label_type = $form->getField('Map element label type');
		$this->assertEquals($label_options_map, $map_element_label_type->getOptions()->asText());
		$this->assertEquals('Label', $map_element_label_type->getText());

		// Expand the advanced labels options, check that label type dropdown is hidden and new fields are present.
		$advanced_labels->check();
		$this->assertFalse($map_element_label_type->isVisible());
		$label_types = [
			'Host group label type' => 'label_string_hostgroup',
			'Host label type' => 'label_string_host',
			'Trigger label type' => 'label_string_trigger',
			'Map label type' => 'label_string_map',
			'Image label type' => 'label_string_image'
		];

		foreach ($label_types as $type => $id) {
			$field = $form->getField($type);
			$options = ($type !== 'Host label type' && $type !== 'Image label type')
				? $label_options
				: (($type == 'Host label type')
				? $label_options_host
				: $label_options_image);
			$this->assertEquals($options, $field->getOptions()->asText());
			$this->assertEquals('Element name', $field->getText());

			// Check that text area appears on custom label option.
			$field->select('Custom label');
			$text_area = $form->query('id:'.$id)->one();
			$this->assertTrue($text_area->isVisible());
			$this->assertEquals('false', $text_area->getAttribute('spellcheck'));
		}

		$label_location = $form->getField('Map element label location');
		$this->assertEquals(['Bottom', 'Left', 'Right', 'Top'], $label_location->getOptions()->asText());
		$this->assertEquals('Bottom', $label_location->getText());

		foreach (['Show map element labels', 'Show link labels'] as $field_name) {
			$show_label = $form->getField($field_name);
			$this->assertEquals('Always', $show_label->getText());
			$this->assertEquals(['Always', 'Auto hide'], $show_label->getLabels()->asText());
		}

		$problem_display = $form->getField('Problem display');
		$this->assertEquals(['All', 'Separated', 'Unacknowledged only'], $problem_display->getOptions()->asText());
		$this->assertEquals('All', $problem_display->getText());

		$problem_severity = $form->getField('Minimum severity');
		$this->assertEquals(['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
				$problem_severity->getLabels()->asText()
		);
		$this->assertEquals('Not classified', $problem_severity->getText());

		$this->assertFalse($form->getField('Show suppressed problems')->isChecked());

		$url_table = $form->getField('URLs')->asTable();
		$this->assertEquals(['Name', 'URL', 'Element', ''], $url_table->getHeadersText());

		foreach (['Add', 'Remove'] as $button) {
			$this->assertTrue($url_table->query('button:'.$button)->one()->isClickable());
		}

		foreach (['urls_0_name', 'urls_0_url'] as $id) {
			$url_field = $url_table->query('id:'.$id)->one();
			$this->assertEquals(($id == 'urls_0_name') ? 255 : 2048, $url_field->getAttribute('maxlength'));
			$this->assertEquals('', $url_field->getValue());
		}

		$type = $url_table->query('name:urls[0][elementtype]')->asDropdown()->one();
		$this->assertEquals(['Host', 'Host group', 'Image', 'Map', 'Trigger'], $type->getOptions()->asText());
		$this->assertEquals('Host', $type->getText());

		foreach (['id:add', 'id:cancel'] as $button) {
			$this->assertTrue($form->query($button)->one()->isClickable());
		}

		// Switch tab to Sharing, and check the form fields.
		$form->selectTab('Sharing');
		$this->assertEquals($sharing_labels, array_values($form->getLabels()->filter(CElementFilter::VISIBLE)->asText()));

		$sharing_type = $form->getField('Type');
		$this->assertEquals(['Private', 'Public'], $sharing_type->getLabels()->asText());
		$this->assertEquals('Private', $sharing_type->getValue());

		$user_group_shares = $form->getField('List of user group shares')->asTable();
		$user_shares = $form->getField('List of user shares')->asTable();

		foreach([$user_group_shares, $user_shares] as $table) {
			$this->assertEquals([($table = $user_group_shares) ? 'User groups' : 'Users', 'Permissions', 'Action'],
					$table->getHeadersText()
			);
			$this->assertTrue($table->query('button:Add')->one()->isClickable());
		}

		// Re-check the pressence of the Add and Cancel buttons.
		foreach (['id:add', 'id:cancel'] as $button) {
			$this->assertTrue($form->query($button)->one()->isClickable());
		}
	}

	public function getMapValidationData() {
		return [
			// #0 Missing madatory parameter - Name.
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
						'id' => 0,
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
							'id' => 0,
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
							'id' => 0,
							'Name' => 'TEST',
							'URL' => 'URL-1'
						],
						[
							'id' => 1,
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
			// #0 Create with mandatory fields.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Map create with mandaroty fields'
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Map create with mandaroty fields',
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
						'URLs' => []
					]
				]
			],
			// #1 Create with leading and trailing spaces.
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
							'id' => 0,
							'Name' => '  Test url ',
							'URL' => '  Test url '
						]
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Map create with leading and trailing spaces',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Background scale' => 'Proportionally',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'' => 'Test image custom label',
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'Map element label location' => 'Bottom',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => []
					],
					'expected_urls' => [
						[
							'id' => 0,
							'name' => 'Test url',
							'url' => 'Test url',
							'element_type' => 0
						]
					],
					'custom_labels' => [
						'id:label_string_hostgroup' => 'Test host group custom label',
						'id:label_string_host' => 'Test host custom label',
						'id:label_string_trigger' => 'Test trigger custom label',
						'id:label_string_map' => 'Test map custom label',
						'id:label_string_image' => 'Test image custom label'
					]
				]
			],
			// #2 Create with maximum string length.
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
							'id' => 0,
							'Name' => STRING_255,
							'URL' => STRING_2048
						]
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => STRING_128,
						'Width' => '65535',
						'Height' => '65535',
						'Background image' => 'No image',
						'Background scale' => 'Proportionally',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Number of problems',
						'Advanced labels' => true,
						'' => STRING_255,
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'Map element label location' => 'Bottom',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => []
					],
					'expected_urls' => [
						[
							'id' => 0,
							'name' => STRING_255,
							'url' => STRING_2048,
							'element_type' => 0
						]

					],
					'custom_labels' => [
						'id:label_string_hostgroup' => STRING_255,
						'id:label_string_host' => STRING_255,
						'id:label_string_trigger' => STRING_255,
						'id:label_string_map' => STRING_255,
						'id:label_string_image' => STRING_255
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
						'id:urls_0_name' => STRING_6000,
						'id:urls_0_url' => STRING_6000
					],
					'urls' => [
						[
							'id' => 0,
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
						'Background scale' => 'Proportionally',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'' => STRING_255,
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'Map element label location' => 'Bottom',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => []
					],
					'expected_urls' => [
						[
							'id' => 0,
							'name' => STRING_255,
							'url' => STRING_2048,
							'element_type' => 0
						]
					],
					'custom_labels' => [
						'id:label_string_hostgroup' => STRING_255,
						'id:label_string_host' => STRING_255,
						'id:label_string_trigger' => STRING_255,
						'id:label_string_map' => STRING_255,
						'id:label_string_image' => STRING_255
					]
				]
			]
			 */
			// #3 Create with non-default parameters #1.
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
						'Map element label type' => 'Nothing',
						'Map element label location' => 'Top',
						'Show map element labels' => 'Auto hide',
						'Show link labels' => 'Auto hide',
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
						'Show suppressed problems' => true,
						'URLs' => []
					]
				]
			],
			// #4 Create with non-default parameters #2.
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
						'Background scale' => 'Proportionally',
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems',
						'Advanced labels' => false,
						'Map element label type' => 'Status only',
						'Map element label location' => 'Right',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'Separated',
						'Minimum severity' => 'High',
						'Show suppressed problems' => true,
						'URLs' => []
					]
				]
			],
			// #5 Create with non-default parameters #3.
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
					],
					'result' => [
						'Owner' => ['guest'],
						'Name' => 'Non-default parameters sysmap 3',
						'Width' => '100',
						'Height' => '200',
						'Background image' => 'No image',
						'Background scale' => 'None',
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems',
						'Advanced labels' => false,
						'Map element label type' => 'Element name',
						'Map element label location' => 'Left',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'Separated',
						'Minimum severity' => 'Average',
						'Show suppressed problems' => true,
						'URLs' => []
					]
				]
			],
			// #6 Create with non-default parameters #4.
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
						'Background scale' => 'Proportionally',
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems',
						'Advanced labels' => false,
						'Map element label type' => 'IP address',
						'Map element label location' => 'Bottom',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'Separated',
						'Minimum severity' => 'Warning',
						'Show suppressed problems' => true,
						'URLs' => []
					]
				]
			],
			// #7 Custom labels - Nothing.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Custom labels: Nothing',
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
						'Name' => 'Custom labels: Nothing',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Background scale' => 'Proportionally',
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
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Information',
						'Show suppressed problems' => false,
						'URLs' => []
					]
				]
			],
			// #8 Custom labels - Element name.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Custom labels: Element name',
						'Advanced labels' => true,
						'Host group label type' => 'Element name',
						'Host label type' => 'Element name',
						'Trigger label type' => 'Element name',
						'Map label type' => 'Element name',
						'Image label type' => 'Element name'
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Custom labels: Element name',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Background scale' => 'Proportionally',
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
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => []
					]
				]
			],
			// #9 Custom labels - Status only.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Custom labels: Status only',
						'Advanced labels' => true,
						'Host group label type' => 'Status only',
						'Host label type' => 'Status only',
						'Trigger label type' => 'Status only',
						'Map label type' => 'Status only',
						'Image label type' => 'Element name'
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Custom labels: Status only',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Background scale' => 'Proportionally',
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
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => []
					]
				]
			],
			// #10 Custom labels - Label.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Custom labels: Label',
						'Advanced labels' => true,
						'Host group label type' => 'Label',
						'Host label type' => 'Label',
						'Trigger label type' => 'Label',
						'Map label type' => 'Label',
						'Image label type' => 'Label'
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Custom labels: Label',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Background scale' => 'Proportionally',
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
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => []
					]
				]
			],
			// #11 Custom labels - different label types.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Custom labels: different options',
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
						'Name' => 'Custom labels: different options',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Background scale' => 'Proportionally',
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
						'' => 'Image custom label',
						'Map element label location' => 'Bottom',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => []
					],
					'custom_labels' => [
						'id:label_string_image' => 'Image custom label'
					]
				]
			],
			// #12 Check creation of different type URLs.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'Sysmap with multiple URLs'
					],
					'urls' => [
						[
							'id' => 0,
							'Name' => 'Host URL',
							'URL' => 'http://test1-url@zabbix.com'
						],
						[
							'id' => 1,
							'Name' => 'Group URL',
							'URL' => 'http://test2-url@zabbix.com',
							'Element' => 'Host group'
						],
						[
							'id' => 2,
							'Name' => 'Image URL',
							'URL' => 'http://test3-url@zabbix.com',
							'Element' => 'Image'
						],
						[
							'id' => 3,
							'Name' => 'Map URL',
							'URL' => 'http://test4-url@zabbix.com',
							'Element' => 'Map'
						],
						[
							'id' => 4,
							'Name' => 'Trigger URL',
							'URL' => 'http://test5-url@zabbix.com',
							'Element' => 'Trigger'
						]
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Sysmap with multiple URLs',
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
						'URLs' => []
					],
					'expected_urls' => [
						[
							'id' => 1,
							'name' => 'Host URL',
							'url' => 'http://test1-url@zabbix.com',
							'element_type' => 0
						],
						[
							'id' => 0,
							'name' => 'Group URL',
							'url' => 'http://test2-url@zabbix.com',
							'element_type' => 3
						],
						[
							'id' => 2,
							'name' => 'Image URL',
							'url' => 'http://test3-url@zabbix.com',
							'element_type' => 4
						],
						[
							'id' => 3,
							'name' => 'Map URL',
							'url' => 'http://test4-url@zabbix.com',
							'element_type' => 1
						],
						[
							'id' => 4,
							'name' => 'Trigger URL',
							'url' => 'http://test5-url@zabbix.com',
							'element_type' => 2
						]
					]
				]
			],
			// #13 Check sorting by name of URLs.
			[
				[
					'expected' => TEST_GOOD,
					'map_properties' => [
						'Name' => 'URL sorting'
					],
					'urls' => [
						[
							'id' => 0,
							'Name' => 'Zabbix sysmap',
							'URL' => 'test'
						],
						[
							'id' => 1,
							'Name' => '012345',
							'URL' => 'test'
						],
						[
							'id' => 2,
							'Name' => '9 sysmap',
							'URL' => 'test'
						],
						[
							'id' => 3,
							'Name' => 'ĀĀāāāĒĒēēēŽŽžžŅŅķķķййЖЖ',
							'URL' => 'test'
						],
						[
							'id' => 4,
							'Name' => 'Administration map',
							'URL' => 'test'
						],
						[
							'id' => 5,
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
						'URLs' => []
					],
					'expected_urls' => [
						[
							'id' => 0,
							'name' => '012345',
							'url' => 'test',
							'element_type' => 0
						],
						[
							'id' => 1,
							'name' => '02223',
							'url' => 'test',
							'element_type' => 0
						],
						[
							'id' => 2,
							'name' => '9 sysmap',
							'url' => 'test',
							'element_type' => 0
						],
						[
							'id' => 3,
							'name' => 'ĀĀāāāĒĒēēēŽŽžžŅŅķķķййЖЖ',
							'url' => 'test',
							'element_type' => 0
						],
						[
							'id' => 4,
							'name' => 'Administration map',
							'url' => 'test',
							'element_type' => 0
						],
						[
							'id' => 5,
							'name' => 'Zabbix sysmap',
							'url' => 'test',
							'element_type' => 0
						]
					]
				]
			]
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

		// Add URLS.
		if (array_key_exists('urls', $data)) {
			foreach ($data['urls'] as $url) {

				// Click add link button.
				if ($url['id'] !== 0) {
					$this->query('id:add-url')->one()->click();
				}

				// Add URL data.
				$form->query('id:urls_'.$url['id'].'_name')->one()->fill($url['Name']);
				$form->query('id:urls_'.$url['id'].'_url')->one()->fill($url['URL']);
				$form->getField('name:urls['.$url['id'].'][elementtype]')->select((array_key_exists('Element', $url))
						? $url['Element']
						: 'Host'
				);
			}
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
			$this->assertEquals($data['result'], $form->getFields()->filter(CElementFilter::VISIBLE)->asValues());

			// Check map URLs.
			if (array_key_exists('expected_urls', $data)) {
				foreach ($data['expected_urls'] as $expected_url) {
					$this->assertEquals($expected_url['name'], $form->query('id:urls_'.$expected_url['id'].'_name')
							->one()->getValue()
					);
					$this->assertEquals($expected_url['url'], $form->query('id:urls_'.$expected_url['id'].'_url')
							->one()->getValue()
					);
					$this->assertEquals($expected_url['element_type'],
							$form->query('name:urls['.$expected_url['id'].'][elementtype]')->one()->getValue()
					);
				}
			}

			// Check custom labels.
			if (array_key_exists('custom_labels', $data)) {
				foreach ($data['custom_labels'] as $id => $value) {
					$this->assertEquals($value, $form->query($id)->one()->getValue());
				}
			}
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
			// #0 Update to check trailing of spaces and different symbols of input type text fields.
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
							'id' => 0,
							'Name' => ' URL ₥₳₽1 name 📡 ',
							'URL' => ' URL ₥₳₽1 📡 ',
							'Element' => 'Host'
						],
						[
							'id' => 1,
							'Name' => ' URL ₥₳₽2 name 📡 ',
							'URL' => ' URL ₥₳₽2 📡 ',
							'Element' => 'Host group'
						],
						[
							'id' => 2,
							'Name' => ' URL ₥₳₽3 name 📡 ',
							'URL' => ' URL ₥₳₽3 📡 ',
							'Element' => 'Image'
						],
						[
							'id' => 3,
							'Name' => ' URL ₥₳₽4 name 📡 ',
							'URL' => ' URL ₥₳₽4 📡 ',
							'Element' => 'Map'
						],
						[
							'id' => 4,
							'Name' => ' URL ₥₳₽5 name 📡 ',
							'URL' => ' URL ₥₳₽5 📡 ',
							'Element' => 'Trigger'
						]
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => '🌑🌑🌑 Update: trailing, leading spaces, symbols: ĀāŅņШщЙй㐤㐃 🌕🌕🌕',
						'Width' => '800',
						'Height' => '600',
						'Background image' => 'No image',
						'Background scale' => 'Proportionally',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'' => 'Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃',
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'Map element label location' => 'Bottom',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => []
					],
					'expected_urls' => [
						[
							'id' => 0,
							'name' => 'URL ₥₳₽1 name 📡',
							'url' => 'URL ₥₳₽1 📡',
							'element_type' => 0
						],
						[
							'id' => 1,
							'name' => 'URL ₥₳₽2 name 📡',
							'url' => 'URL ₥₳₽2 📡',
							'element_type' => 3
						],
						[
							'id' => 2,
							'name' => 'URL ₥₳₽3 name 📡',
							'url' => 'URL ₥₳₽3 📡',
							'element_type' => 4
						],
						[
							'id' => 3,
							'name' => 'URL ₥₳₽4 name 📡',
							'url' => 'URL ₥₳₽4 📡',
							'element_type' => 1
						],
						[
							'id' => 4,
							'name' => 'URL ₥₳₽5 name 📡',
							'url' => 'URL ₥₳₽5 📡',
							'element_type' => 2
						]
					],
					'custom_labels' => [
						'id:label_string_hostgroup' => 'Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃',
						'id:label_string_host' => 'Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃',
						'id:label_string_trigger' => 'Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃',
						'id:label_string_map' => 'Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃',
						'id:label_string_image' => 'Label: 🌑🌑🌑1234ĀāŅņШщЙй㐤㐃'
					]
				]
			],
			// #1 Update with maximum string length, width, height values.
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
							'id' => 0,
							'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 1 :'
								.STRING_128,
							'URL' => STRING_2048,
							'Element' => 'Host'
						],
						[
							'id' => 1,
							'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 2 :'
								.STRING_128,
							'URL' => STRING_2048,
							'Element' => 'Host group'
						],
						[
							'id' => 2,
							'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 3 :'
								.STRING_128,
							'URL' => STRING_2048,
							'Element' => 'Image'
						],
						[
							'id' => 3,
							'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 4 :'
								.STRING_128,
							'URL' => STRING_2048,
							'Element' => 'Map'
						],
						[
							'id' => 4,
							'Name' => STRING_64.'Update: maximum possible urlname length 255 characters test 5 :'
								.STRING_128,
							'URL' => STRING_2048,
							'Element' => 'Trigger'
						]
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => 'Update: maximum possible sysmap name length 128 characters test:'.STRING_64,
						'Width' => '65535',
						'Height' => '65535',
						'Background image' => 'No image',
						'Background scale' => 'Proportionally',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Number of problems',
						'Advanced labels' => true,
						'' => STRING_255,
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'Map element label location' => 'Bottom',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => []
					],
					'expected_urls' => [
						[
							'id' => 0,
							'name' => STRING_64.'Update: maximum possible urlname length 255 characters test 1 :'
								.STRING_128,
							'url' => STRING_2048,
							'element_type' => 0
						],
						[
							'id' => 1,
							'name' => STRING_64.'Update: maximum possible urlname length 255 characters test 2 :'
								.STRING_128,
							'url' => STRING_2048,
							'element_type' => 3
						],
						[
							'id' => 2,
							'name' => STRING_64.'Update: maximum possible urlname length 255 characters test 3 :'
								.STRING_128,
							'url' => STRING_2048,
							'element_type' => 4
						],
						[
							'id' => 3,
							'name' => STRING_64.'Update: maximum possible urlname length 255 characters test 4 :'
								.STRING_128,
							'url' => STRING_2048,
							'element_type' => 1
						],
						[
							'id' => 4,
							'name' => STRING_64.'Update: maximum possible urlname length 255 characters test 5 :'
								.STRING_128,
							'url' => STRING_2048,
							'element_type' => 2
						]
					],
					'custom_labels' => [
						'id:label_string_hostgroup' => STRING_255,
						'id:label_string_host' => STRING_255,
						'id:label_string_trigger' => STRING_255,
						'id:label_string_map' => STRING_255,
						'id:label_string_image' => STRING_255
					]
				]
			],
			// #2 Update with XSS imitation text.
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
							'id' => 0,
							'Name' => self::XSS_EXAMPLE,
							'URL' => self::XSS_EXAMPLE,
							'Element' => 'Host'
						],
						[
							'id' => 1,
							'Name' => self::XSS_EXAMPLE.' 1',
							'URL' => self::XSS_EXAMPLE,
							'Element' => 'Host group'
						],
						[
							'id' => 2,
							'Name' => self::XSS_EXAMPLE.' 2',
							'URL' => self::XSS_EXAMPLE,
							'Element' => 'Image'
						],
						[
							'id' => 3,
							'Name' => self::XSS_EXAMPLE.' 3',
							'URL' => self::XSS_EXAMPLE,
							'Element' => 'Map'
						],
						[
							'id' => 4,
							'Name' => self::XSS_EXAMPLE.' 4',
							'URL' => self::XSS_EXAMPLE,
							'Element' => 'Trigger'
						]
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => self::XSS_EXAMPLE.' update',
						'Width' => '1000',
						'Height' => '1000',
						'Background image' => 'No image',
						'Background scale' => 'Proportionally',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Number of problems',
						'Advanced labels' => true,
						'' => '<script>alert(\'XSS\');</script>',
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'Map element label location' => 'Bottom',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => []
					],
					'expected_urls' => [
						[
							'id' => 0,
							'name' => self::XSS_EXAMPLE,
							'url' => self::XSS_EXAMPLE,
							'element_type' => 0
						],
						[
							'id' => 1,
							'name' => self::XSS_EXAMPLE.' 1',
							'url' => self::XSS_EXAMPLE,
							'element_type' => 3
						],
						[
							'id' => 2,
							'name' => self::XSS_EXAMPLE.' 2',
							'url' => self::XSS_EXAMPLE,
							'element_type' => 4
						],
						[
							'id' => 3,
							'name' => self::XSS_EXAMPLE.' 3',
							'url' => self::XSS_EXAMPLE,
							'element_type' => 1
						],
						[
							'id' => 4,
							'name' => self::XSS_EXAMPLE.' 4',
							'url' => self::XSS_EXAMPLE,
							'element_type' => 2
						]
					],
					'custom_labels' => [
						'id:label_string_hostgroup' => self::XSS_EXAMPLE,
						'id:label_string_host' => self::XSS_EXAMPLE,
						'id:label_string_trigger' => self::XSS_EXAMPLE,
						'id:label_string_map' => self::XSS_EXAMPLE,
						'id:label_string_image' => self::XSS_EXAMPLE
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
							'id' => 0,
							'Name' => STRING_6000,
							'URL' => STRING_6000,
							'Element' => 'Host'
						]
					],
					'result' => [
						'Owner' => ['Admin (Zabbix Administrator)'],
						'Name' => STRING_64.' Update: maximum possible sysmap name length 128 characters test',
						'Width' => '65535',
						'Height' => '65535',
						'Background image' => 'No image',
						'Background scale' => 'Proportionally',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Expand single problem',
						'Advanced labels' => true,
						'' => STRING_255,
						'Host group label type' => 'Custom label',
						'Host label type' => 'Custom label',
						'Trigger label type' => 'Custom label',
						'Map label type' => 'Custom label',
						'Image label type' => 'Custom label',
						'Map element label location' => 'Bottom',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'All',
						'Minimum severity' => 'Not classified',
						'Show suppressed problems' => false,
						'URLs' => []
					],
					'expected_urls' => [
						[
							'id' => 0,
							'name' => STRING_255,
							'url' => STRING_2048,
							'element_type' => 0
						]
					],
					'custom_labels' => [
						'id:label_string_hostgroup' => STRING_255,
						'id:label_string_host' => STRING_255,
						'id:label_string_trigger' => STRING_255,
						'id:label_string_map' => STRING_255,
						'id:label_string_image' => STRING_255
					]
				]
			]
			 */
			// #3 Update - change advanced label fields of existing map.
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
						'Background scale' => 'Proportionally',
						'Automatic icon mapping' => self::ICON_MAPPING,
						'Icon highlight' => true,
						'Mark elements on trigger status change' => true,
						'Display problems' => 'Number of problems and expand most critical one',
						'Advanced labels' => true,
						'' => 'Update labels check',
						'Host group label type' => 'Label',
						'Host label type' => 'IP address',
						'Trigger label type' => 'Status only',
						'Map label type' => 'Nothing',
						'Image label type' => 'Custom label',
						'Map element label location' => 'Right',
						'Show map element labels' => 'Always',
						'Show link labels' => 'Always',
						'Problem display' => 'Separated',
						'Minimum severity' => 'Disaster',
						'Show suppressed problems' => true,
						'URLs' => []
					],
					'expected_urls' => [
						[
							'id' => 1,
							'name' => '2 Host group URL',
							'url' => 'test',
							'element_type' => 3
						],
						[
							'id' => 0,
							'name' => '1 Host URL',
							'url' => 'test',
							'element_type' => 0
						],
						[
							'id' => 3,
							'name' => '4 Image URL',
							'url' => 'test',
							'element_type' => 4
						],
						[
							'id' => 2,
							'name' => '3 Map URL',
							'url' => 'test',
							'element_type' => 1
						],
						[
							'id' => 4,
							'name' => '5 Trigger URL',
							'url' => 'test',
							'element_type' => 2
						]
					],
					'custom_labels' => [
						'id:label_string_image' => 'Update labels check'
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
						'Background scale' => 'None',
						'Display problems' => 'Number of problems',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Number of problems',
						'Advanced labels' => false,
						'Map element label type' => 'Element name',
						'Map element label location' => 'Left',
						'Show map element labels' => 'Auto hide',
						'Show link labels' => 'Auto hide',
						'Problem display' => 'Unacknowledged only',
						'Minimum severity' => 'Information',
						'Show suppressed problems' => false
					],
					'result' => [
						'Owner' => ['guest'],
						'Name' => self::MAP_UPDATE,
						'Width' => '10000',
						'Height' => '9000',
						'Background image' => self::BACKGROUND_IMAGE,
						'Background scale' => 'None',
						'Automatic icon mapping' => '<manual>',
						'Icon highlight' => false,
						'Mark elements on trigger status change' => false,
						'Display problems' => 'Number of problems',
						'Advanced labels' => false,
						'Map element label type' => 'Element name',
						'Map element label location' => 'Left',
						'Show map element labels' => 'Auto hide',
						'Show link labels' => 'Auto hide',
						'Problem display' => 'Unacknowledged only',
						'Minimum severity' => 'Information',
						'Show suppressed problems' => false,
						'URLs' => []
					],
					'expected_urls' => [
						[
							'id' => 1,
							'name' => '2 Host group URL',
							'url' => 'test',
							'element_type' => 3
						],
						[
							'id' => 0,
							'name' => '1 Host URL',
							'url' => 'test',
							'element_type' => 0
						],
						[
							'id' => 3,
							'name' => '4 Image URL',
							'url' => 'test',
							'element_type' => 4
						],
						[
							'id' => 2,
							'name' => '3 Map URL',
							'url' => 'test',
							'element_type' => 1
						],
						[
							'id' => 4,
							'name' => '5 Trigger URL',
							'url' => 'test',
							'element_type' => 2
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
		$url_count = $form->query('button:Remove')->count();

		// Add URLs.
		if (array_key_exists('urls', $data)) {
			foreach ($data['urls'] as $url) {

				// Click add link button.
				if ($url['id'] !== 0 && $url_count === 1) {
					$this->query('id:add-url')->one()->click();
				}

				// Add URL data.
				$form->query('id:urls_'.$url['id'].'_name')->one()->fill($url['Name']);
				$form->query('id:urls_'.$url['id'].'_url')->one()->fill($url['URL']);
				$form->getField('name:urls['.$url['id'].'][elementtype]')->select((array_key_exists('Element', $url))
						? $url['Element']
						: 'Host'
				);
			}
		}

		// Remove URLs.
		if (array_key_exists('remove_urls', $data)) {
			foreach ($data['expected_urls'] as $url) {
				$table = $this->query('class:table-forms-separator')->asTable()->one();
				$row = $table->query('id:url-row-'.$url['id'])->one();
				$row->query('button:Remove')->one()->click();
			}
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
			$this->assertEquals($data['result'], $form->getFields()->filter(CElementFilter::VISIBLE)->asValues());

			// Check map URLs.
			if (array_key_exists('expected_urls', $data) && array_key_exists('remove_urls', $data) === false) {
				foreach ($data['expected_urls'] as $expected_url) {
					$this->assertEquals($expected_url['name'], $form->query('id:urls_'.$expected_url['id'].'_name')
							->one()->getValue()
					);
					$this->assertEquals($expected_url['url'], $form->query('id:urls_'.$expected_url['id'].'_url')
							->one()->getValue()
					);
					$this->assertEquals($expected_url['element_type'],
							$form->query('name:urls['.$expected_url['id'].'][elementtype]')->one()->getValue()
					);
				}
			}

			// Check that only one empty URL row is present, in case if all URLs were removed.
			if (array_key_exists('remove_urls', $data)) {
				$url_table = $form->getField('URLs')->asTable();
				$this->assertEquals(1, $url_table->query('button:Remove')->count());
				$this->assertEquals('Host', $url_table->query('name:urls[0][elementtype]')->asDropdown()->one()
						->getText()
				);

				foreach (['urls_0_name', 'urls_0_url'] as $id) {
					$url_field = $url_table->query('id:'.$id)->one();
					$this->assertEquals('', $url_field->getValue());
				}
			}

			// Check custom labels.
			if (array_key_exists('custom_labels', $data)) {
				foreach ($data['custom_labels'] as $id => $value) {
					$this->assertEquals($value, $form->query($id)->one()->getValue());
				}
			}

		self::$map_update = $form->getField('Name')->getValue();

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
				'Map element label location' => 'Top',
				'Show map element labels' => 'Always',
				'Show link labels' => 'Always',
				'Problem display' => 'Separated',
				'Minimum severity' => 'High',
				'Show suppressed problems' => true,
				'URLs' => []
			],
			'expected_urls' => [
				[
					'id' => 4,
					'name' => STRING_255,
					'url' => STRING_2048,
					'element_type' => 1
				],
				[
					'id' => 0,
					'name' => '1 Host URL 📰📰📰',
					'url' => 'test 📰📰📰',
					'element_type' => 0
				],
				[
					'id' => 1,
					'name' => '2 Image URL',
					'url' => 'test',
					'element_type' => 4
				],
				[
					'id' => 3,
					'name' => '4 Host group - xss',
					'url' => self::XSS_EXAMPLE,
					'element_type' => 3
				],
				[
					'id' => 2,
					'name' => '3 Trigger URL',
					'url' => 'test',
					'element_type' => 2
				]
			],
			'custom_labels' => [
				'id:label_string_host' => STRING_255,
				'id:label_string_hostgroup' => 'Host group label 📰📰📰'
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
		$this->assertEquals($data['result'], $form->getFields()->filter(CElementFilter::VISIBLE)->asValues());

		// Check map URLs.
		foreach ($data['expected_urls'] as $expected_url) {
			$this->assertEquals($expected_url['name'], $form->query('id:urls_'.$expected_url['id'].'_name')
					->one()->getValue()
			);
			$this->assertEquals($expected_url['url'], $form->query('id:urls_'.$expected_url['id'].'_url')
					->one()->getValue()
			);
			$this->assertEquals($expected_url['element_type'],
					$form->query('name:urls['.$expected_url['id'].'][elementtype]')->one()->getValue()
			);
		}

		foreach ($data['custom_labels'] as $id => $value) {
			$this->assertEquals($value, $form->query($id)->one()->getValue());
		}

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
