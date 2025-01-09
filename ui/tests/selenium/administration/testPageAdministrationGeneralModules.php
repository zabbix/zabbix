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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

/**
 * @backup module, widget
 */
class testPageAdministrationGeneralModules extends CWebTest {

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

	private static $dashboardid;
	private static $template_dashboardid;
	private static $hostid;

	const TEMPLATEID = 50000;
	const ITEMID = 400410;
	const INACCESSIBLE_TEXT = 'No permissions to referred object or it does not exist!';
	const INACCESSIBLE_XPATH = 'xpath:.//div[contains(@class, "dashboard-widget-inaccessible")]';
	const HOSTNAME = 'Host for widget module test';

	private static $widget_descriptions = [
		'Action log' => 'Displays records about executed action operations (notifications, remote commands).',
		'Clock' => 'Displays local, server, or specified host time.',
		'Discovery status' => 'Displays the status summary of the active network discovery rules.',
		'Favorite graphs' => 'Displays shortcuts to the most needed graphs (marked as favorite).',
		'Favorite maps' => 'Displays shortcuts to the most needed network maps (marked as favorite).',
		'Gauge' => 'Displays the value of a single item as gauge.',
		'Geomap' => 'Displays hosts as markers on a geographical map.',
		'Graph' => 'Displays data of up to 50 items as line, points, staircase, or bar charts.',
		'Graph (classic)' => 'Displays a single custom graph or a simple graph.',
		'Graph prototype' => 'Displays a grid of graphs created by low-level discovery from either a graph prototype or '.
				'an item prototype.',
		'Honeycomb' => 'Displays item values as a honeycomb.',
		'Host availability' => 'Displays the host count by status (available/unavailable/unknown).',
		'Host card' => 'Displays the most relevant host information.',
		'Host navigator' => 'Displays host hierarchy with ability to control other widgets based on selected host.',
		'Item history' => 'Displays the latest data for the selected items with an option to add progress bar visualizations, '.
				'customize report columns, and display images for binary data types.',
		'Item navigator' => 'Displays item hierarchy with ability to control other widgets based on selected item.',
		'Item value' => 'Displays the value of a single item prominently.',
		'Map' => 'Displays either a single configured network map or one of the configured network maps in the map '.
				'navigation tree.',
		'Map navigation tree' => 'Allows to build a hierarchy of existing maps and display problem statistics for each '.
				'included map and map group.',
		'Pie chart' => 'Displays item values as a pie or doughnut chart.',
		'Problem hosts' => 'Displays the problem count by host group and the highest problem severity within a group.',
		'Problems' => 'Displays currently open problems with quick access links to the problem details.',
		'Problems by severity' => 'Displays the problem count by severity.',
		'SLA report' => 'Displays SLA reports.',
		'System information' => 'Displays the current status and system statistics of the Zabbix server and its '.
				'associated components.',
		'Top hosts' => 'Displays top N hosts that have the highest or the lowest item value (for example, CPU load) '.
				'with an option to add progress-bar visualizations and customize report columns.',
		'Top items' => 'Displays the latest item data and current status of each item for selected hosts.',
		'Top triggers' => 'Displays top N triggers that have the most problems within the period of evaluation,'.
				' sorted by the number of problems.',
		'Trigger overview' => 'Displays trigger states for selected hosts.',
		'URL' => 'Displays the content retrieved from the specified URL.',
		'Web monitoring' => 'Displays the status summary of the active web monitoring scenarios.'
	];

	/**
	 * Creates dashboards with widgets and defines the corresponding dashboard IDs.
	 */
	public static function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for widget module testing',
				'private' => 0,
				'auto_start' => 0,
				'pages' => [
					[
						'name' => 'Map page',
						'widgets' => [
							[
								'type' => 'navtree',
								'name' => 'Awesome map tree',
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 4,
								'fields' => [
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'GZCSV'
									],
									[
										'type' => 1,
										'name' => 'navtree.1.name',
										'value' => 'Awesome map'
									],
									[
										'type' => 8,
										'name' => 'navtree.1.sysmapid',
										'value' => 1
									]
								]
							],
							[
								'type' => 'map',
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 4,
								'fields' => [
									[
										'type' => 1,
										'name' => 'sysmapid._reference',
										'value' => 'GZCSV._mapid'
									]
								]
							],
							[
								'type' => 'favgraphs',
								'x' => 18,
								'y' => 4,
								'width' => 18,
								'height' => 4
							]
						]
					],
					[
						'name' => 'Alarm clock page',
						'widgets' => [
							[
								'type' => 'clock345',
								'x' => 0,
								'y' => 0,
								'width' => 18,
								'height' => 4
							],
							[
								'type' => 'favgraphs',
								'x' => 18,
								'y' => 0,
								'width' => 18,
								'height' => 4
							]
						]
					],
					[
						'name' => 'System info page',
						'widgets' => [
							[

								'type' => 'favgraphs',
								'x' => 0,
								'y' => 0,
								'width' => 18,
								'height' => 4
							],
							[
								'type' => 'systeminfo',
								'x' => 18,
								'y' => 0,
								'width' => 18,
								'height' => 4
							]
						]
					],
					[
						'name' => 'Widget communication page',
						'widgets' => [
							[
								'type' => 'problemhosts',
								'name' => 'Problem hosts hostgroup broadcaster',
								'x' => 0,
								'y' => 0,
								'width' => 20,
								'height' => 6,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'IDDQD'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'honeycomb',
								'name' => 'Honeycomb host and item broadcaster',
								'x' => 0,
								'y' => 6,
								'width' => 20,
								'height' => 6,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'items.0',
										'value' => 'Available memory'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'ICARE'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'geomap',
								'name' => 'Geomap listener',
								'x' => 22,
								'y' => 0,
								'width' => 20,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'groupids._reference',
										'value' => 'IDDQD._hostgroupids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'default_view',
										'value' => '56.9,24.1,5'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'PINTA'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'problems',
								'name' => 'Problems listener',
								'x' => 22,
								'y' => 4,
								'width' => 20,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'hostids._reference',
										'value' => 'ICARE._hostids'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'NODAY'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							],
							[
								'type' => 'gauge',
								'name' => 'Gauge listener',
								'x' => 22,
								'y' => 8,
								'width' => 20,
								'height' => 4,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'itemid._reference',
										'value' => 'ICARE._itemid'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'min',
										'value' => '0'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'max',
										'value' => '10'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'rf_rate',
										'value' => 0
									]
								]
							]
						]
					],
					[
						'name' => 'System info page',
						'widgets' => [
							[

								'type' => 'favgraphs',
								'x' => 0,
								'y' => 0,
								'width' => 18,
								'height' => 4
							],
							[
								'type' => 'systeminfo',
								'x' => 18,
								'y' => 0,
								'width' => 18,
								'height' => 4
							]
						]
					],
					[
						'name' => 'Empty widget page',
						'widgets' => [
							[
								'type' => 'favgraphs',
								'x' => 0,
								'y' => 0,
								'width' => 18,
								'height' => 4
							],
							[
								'type' => 'emptyWidget',
								'x' => 18,
								'y' => 0,
								'width' => 18,
								'height' => 4
							]
						]
					]
				]
			]
		]);

		self::$dashboardid = $response['dashboardids'][0];

		$template_responce = CDataHelper::call('templatedashboard.create', [
			[
				'templateid' => self::TEMPLATEID,
				'name' => 'Templated dashboard for module widgets',
				'auto_start' => 0,
				'pages' => [
					[
						'name' => 'Default clock page',
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'Default clock',
								'width' => 18,
								'height' => 4
							],
							[
								'type' => 'item',
								'x' => 18,
								'y' => 0,
								'width' => 18,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'itemid',
										'value' => self::ITEMID
									]
								]
							]
						]
					],
					[
						'name' => 'Alarm clock page',
						'widgets' => [
							[
								'type' => 'clock',
								'name' => 'Clock widget',
								'width' => 18,
								'height' => 4
							],
							[
								'type' => 'clock345',
								'x' => 18,
								'y' => 0,
								'width' => 18,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'time_type',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'tzone_timezone',
										'value' => 'local'
									]
								]
							]
						]
					]
				]
			]
		]);

		self::$template_dashboardid = $template_responce['dashboardids'][0];

		$host_responce = CDataHelper::createHosts([
			[
				'host' => self::HOSTNAME,
				'interfaces' => [
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => '10050'
				],
				'groups' => [
					'groupid' => 7
				],
				'status' => HOST_STATUS_MONITORED,
				'templates' => [
					'templateid' => self::TEMPLATEID
				]
			]
		]);

		self::$hostid = $host_responce['hostids'][self::HOSTNAME];
	}

	public function testPageAdministrationGeneralModules_Layout() {
		$modules = [
			[
				'Name' => '1st Module name',
				'Version' => '1',
				'Author' => '1st Module author',
				'Description' => '1st Module description',
				'Status' => 'Disabled'
			],
			[
				'Name' => '2nd Module name !@#$%^&*()_+',
				'Version' => 'two !@#$%^&*()_+',
				'Author' => '2nd Module author !@#$%^&*()_+',
				'Description' => 'Module description !@#$%^&*()_+',
				'Status' => 'Disabled'
			],
			[
				'Name' => '4th Module',
				'Version' => '',
				'Author' => '',
				'Description' => '',
				'Status' => 'Disabled'
			],
			[
				'Name' => '5th Module',
				'Version' => '',
				'Author' => '',
				'Description' => 'Adding top-level and sub-level menu',
				'Status' => 'Disabled'
			],
			[
				'Name' => 'Clock2',
				'Version' => '1.1',
				'Author' => 'Zabbix QA department',
				'Description' => '',
				'Status' => 'Disabled'
			],
			[
				'Name' => 'Test CSRF token',
				'Version' => '0.1',
				'Author' => '',
				'Description' => 'Test CSRF token support for modules',
				'Status' => 'Disabled'
			],
			[
				'Name' => 'Empty widget',
				'Version' => '1.0',
				'Author' => 'Some Zabbix employee',
				'Description' => '',
				'Status' => 'Disabled'
			],
			[
				'Name' => 'шестой модуль',
				'Version' => 'бета 2',
				'Author' => 'Работник Заббикса',
				'Description' => 'Удалить "Reports" из меню верхнего уровня, а так же удалить "Maps" из секции "Monitoring".',
				'Status' => 'Disabled'
			]
		];

		// Create an array with widget modules that should be present by default.
		$widget_modules = [];
		$i = 0;

		foreach (self::$widget_descriptions as $name => $description) {
			$widget_modules[$i]['Name'] = $name;
			$widget_modules[$i]['Version'] = '1.0';
			$widget_modules[$i]['Author'] = 'Zabbix';
			$widget_modules[$i]['Description'] = $description;
			$widget_modules[$i]['Status'] = 'Enabled';

			$i++;
		}

		// Open modules page and check header.
		$this->page->login()->open('zabbix.php?action=module.list');
		$this->assertEquals('Modules', $this->query('tag:h1')->one()->getText());

		// Check status of buttons on the modules page.
		foreach (['Scan directory' => true, 'Enable' => false, 'Disable' => false] as $button => $enabled) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}

		$table = $this->query('class:list-table')->asTable()->one();

		// Check that only widget modules are present until the 'Scan directory' button is pressed.
		$this->assertTableData($widget_modules);

		$count = $table->getRows()->count();
		$this->assertTableStats($count);

		$this->assertEquals('0 selected', $this->query('id:selected_count')->one()->getText());
		// Check modules table headers.
		$headers = $table->getHeadersText();
		// Remove empty element from headers array.
		array_shift($headers);
		$this->assertSame(['Name', 'Version', 'Author', 'Description', 'Status'], $headers);

		// Load modules.
		$this->loadModules();
		$all_modules = array_merge($widget_modules, $modules);
		$total_count = count($all_modules);

		// Sort column contents ascending.
		usort($all_modules, function($a, $b) {
			return strcmp($a['Name'], $b['Name']);
		});

		// Check parameters of modules in the modules table.
		$this->assertTableData($all_modules);

		$count = CDBHelper::getCount('SELECT moduleid FROM module');
		$this->assertEquals('Displaying '.$total_count.' of '.$total_count.' found', $this->query('class:table-stats')
				->one()->getText()
		);

		// Load modules again and check that no new modules were added.
		$this->loadModules(false);
		$this->assertEquals('Displaying '.$count.' of '.$count.' found', $this->query('class:table-stats')->one()->getText());
	}

	public function getModuleDetails() {
		return [
			// Module 1.
			[
				[
					'Name' => '1st Module name',
					'Version' => '1',
					'Author' => '1st Module author',
					'Description' => '1st Module description',
					'Directory' => 'modules/module_number_1',
					'Namespace' => 'Modules\Example_A',
					'URL' => 'https://www.1st_module_URL.com',
					'Enabled' => false
				]
			],
			// Module 2.
			[
				[
					'Name' => '2nd Module name !@#$%^&*()_+',
					'Version' => 'two !@#$%^&*()_+',
					'Author' => '2nd Module author !@#$%^&*()_+',
					'Description' => 'Module description !@#$%^&*()_+',
					'Directory' => 'modules/module_number_2',
					'Namespace' => 'Modules\Example_B',
					'URL' => 'https://www.!@#$%^&*()_+.com',
					'Enabled' => false
				]
			],
			// Module 4.
			[
				[
					'Name' => '4th Module',
					'Version' => '',
					'Author' => '-',
					'Description' => '-',
					'Directory' => 'modules/module_number_4',
					'Namespace' => 'Modules\Example_A',
					'URL' => '-',
					'Enabled' => false
				]
			],
			// Module 5.
			[
				[
					'Name' => '5th Module',
					'Version' => '',
					'Author' => '-',
					'Description' => 'Adding top-level and sub-level menu',
					'Directory' => 'modules/module_number_5',
					'Namespace' => 'Modules\Example_E',
					'URL' => '-',
					'Enabled' => false
				]
			],
			// Clock2.
			[
				[
					'Name' => 'Clock2',
					'Version' => '1.1',
					'Author' => 'Zabbix QA department',
					'Description' => '-',
					'Directory' => 'modules/clock32',
					'Namespace' => 'Modules\Clock2',
					'URL' => '-',
					'Enabled' => false
				]
			],
			// Empty widget.
			[
				[
					'Name' => 'Empty widget',
					'Version' => '1.0',
					'Author' => 'Some Zabbix employee',
					'Description' => '-',
					'Directory' => 'modules/emptyWidget',
					'Namespace' => 'Modules\emptyWidget',
					'URL' => '-',
					'Enabled' => false
				]
			],
			// Module 6.
			[
				[
					'Name' => 'шестой модуль',
					'Version' => 'бета 2',
					'Author' => 'Работник Заббикса',
					'Description' => 'Удалить "Reports" из меню верхнего уровня, а так же удалить "Maps" из секции "Monitoring".',
					'Directory' => 'modules/module_number_6',
					'Namespace' => 'Modules\Example_F',
					'URL' => '-',
					'Enabled' => false
				]
			],
			// CSRF check module.
			[
				[
					'Name' => 'Test CSRF token',
					'Version' => '0.1',
					'Author' => '-',
					'Description' => 'Test CSRF token support for modules',
					'Directory' => 'modules/module_number_7',
					'Namespace' => 'Modules\CSRF',
					'URL' => '-',
					'Enabled' => false
				]
			]
		];
	}

	/**
	 * @dataProvider getModuleDetails
	 *
	 * @depends testPageAdministrationGeneralModules_Layout
	 */
	public function testPageAdministrationGeneralModules_Details($data) {
		// Open corresponding module from the modules table.
		$this->page->login()->open('zabbix.php?action=module.list');
		$this->query('link', $data['Name'])->waitUntilVisible()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->asForm();
		// Check value af every field in Module details form.
		foreach ($data as $key => $value) {
			$this->assertEquals($value, $form->getFieldContainer($key)->getText());
		}

		$dialog->close();
	}

	public function getModuleData() {
		return [
			// Enable only 1st module - '1st Module' entry added under Monitoring.
			[
				[
					[
						'module_name' => '1st Module name',
						'menu_entries' => [
							[
								'name' => '1st Module',
								'action' => 'first.module',
								'message' => 'If You see this message - 1st module is working'
							]
						]
					]
				]
			],
			// Enable only 2nd Module - '2nd Module' entry added under Monitoring.
			[
				[
					[
						'module_name' => '2nd Module name !@#$%^&*()_+',
						'menu_entries' => [
							[
								'name' => '2nd Module',
								'action' => 'second.module',
								'message' => '2nd module is also working'
							]
						]
					]
				]
			],
			// Enable both 1st and 2nd module - '1st Module' and '2nd Module' entries added under Monitoring.
			[
				[
					[
						'module_name' => '1st Module name',
						'menu_entries' => [
							[
								'name' => '1st Module',
								'action' => 'first.module',
								'message' => 'If You see this message - 1st module is working'
							]
						]
					],
					[
						'module_name' => '2nd Module name !@#$%^&*()_+',
						'menu_entries' => [
							[
								'name' => '2nd Module',
								'action' => 'second.module',
								'message' => '2nd module is also working'
							]
						]
					]
				]
			],
			// Attempting to enable two modules that use identical namespace.
			[
				[
					[
						'module_name' => '1st Module name',
						'menu_entries' => [
							[
								'name' => '1st Module',
								'action' => 'first.module',
								'message' => 'If You see this message - 1st module is working'
							]
						]
					],
					[
						'expected' => TEST_BAD,
						'module_name' =>'4th Module',
						'menu_entries' => [
							[
								'name' => '4th Module',
								'action' => 'forth.module'
							]
						],
						'error_details' => 'Identical namespace (Modules\Example_A) is used by modules located at '.
								'modules/module_number_1, modules/module_number_4.'
					]
				]
			],
			// Enable 5th Module - Module 5 menu top level menu is added with 3 entries.
			[
				[
					[
						'module_name' => '5th Module',
						'top_menu_entry' => 'Module 5 menu',
						'menu_entries' => [
							[
								'name' => 'Your profile',
								'action' => 'userprofile.edit',
								'message' => 'User profile: Zabbix Administrator',
								'check_disabled' => false
							],
							[
								'name' => 'пятый модуль',
								'action' => 'fifth.module',
								'message' => 'Если ты это читаешь то 5ый модуль работает'
							],
							[
								'name' => 'Module list',
								'action' => 'module.list',
								'message' => 'Modules',
								'check_disabled' => false
							]
						]
					]
				]
			],
			// Enable шестой модуль - Top level menu Reports and menu entry Maps are removed.
			[
				[
					[
						'module_name' => 'шестой модуль',
						'remove' => true,
						'top_menu_entry' => 'Reports',
						'menu_entry' => 'Maps'
					]
				]
			],
			// Enable Test CSRF token module and check that it works.
			[
				[
					[
						'module_name' => 'Test CSRF token',
						'top_menu_entry' => 'Administration',
						'menu_entries' => [
							[
								'name' => 'CSRF test',
								'action' => 'csrftoken.form',
								'form' => 'xpath://input[@name="_csrf_token"]/parent::form',
								'message' => 'CSRF token validation succeeded.'
							]
						]
					]
				]
			]
		];
	}

	/**
	 * @backupOnce module
	 *
	 * @dataProvider getModuleData
	 *
	 * @depends testPageAdministrationGeneralModules_Layout
	 */
	public function testPageAdministrationGeneralModules_EnableDisable($data) {
		$this->page->login()->open('zabbix.php?action=module.list');

		foreach (['list', 'form'] as $view) {
			// This block is separate because one of the cases requires one module to be enabled before the other to succeed.
			foreach ($data as $module) {
				// Enable module and check the success or error message.
				$this->enableModule($module, $view);
			}

			// In case if module should be enabled, check that changes took place and then disable each enabled module.
			foreach ($data as $module) {
				if (CTestArrayHelper::get($module, 'expected', TEST_GOOD) === TEST_GOOD) {
					$this->assertModuleEnabled($module);
					$this->disableModule($module, $view);
					$this->assertModuleDisabled($module);
				}
			}
		}
	}

	public function getFilterData() {
		return [
			// Exact name match.
			[
				[
					'filter' => [
						'Name' => '1st Module name'
					],
					'expected' => [
						'1st Module name'
					]
				]
			],
			// Partial name match for all 3 modules.
			[
				[
					'filter' => [
						'Name' => 'Module'
					],
					'expected' => [
						'1st Module name',
						'2nd Module name !@#$%^&*()_+',
						'4th Module',
						'5th Module'
					]
				]
			],
			// Partial name match with space in between.
			[
				[
					'filter' => [
						'Name' => 'le n'
					],
					'expected' => [
						'1st Module name',
						'2nd Module name !@#$%^&*()_+'
					]
				]
			],
			// Filter by various characters in name.
			[
				[
					'filter' => [
						'Name' => '!@#$%^&*()_+'
					],
					'expected' => [
						'2nd Module name !@#$%^&*()_+'
					]
				]
			],
			// Exact name match with leading and trailing spaces.
			[
				[
					'filter' => [
						'Name' => '  4th Module  '
					],
					'expected' => [
						'4th Module'
					]
				]
			],
			// Retrieve only Enabled modules.
			[
				[
					'filter' => [
						'Status' => 'Enabled'
					],
					'expected' => array_merge(['2nd Module name !@#$%^&*()_+'], array_keys(self::$widget_descriptions))
				]
			],
			// Retrieve only Disabled modules.
			[
				[
					'filter' => [
						'Status' => 'Disabled'
					],
					'expected' => [
						'1st Module name',
						'4th Module',
						'5th Module',
						'Clock2',
						'Empty widget',
						'Test CSRF token',
						'шестой модуль'
					]
				]
			],
			// Retrieve only Disabled modules that have 'name' string in their name.
			[
				[
					'filter' => [
						'Name' => 'name',
						'Status' => 'Disabled'
					],
					'expected' => [
						'1st Module name'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 *
	 * @depends testPageAdministrationGeneralModules_Layout
	 */
	public function testPageAdministrationGeneralModules_Filter($data) {
		$this->page->login()->open('zabbix.php?action=module.list');

		// Before checking the filter one of the modules needs to be enabled.
		$table = $this->query('class:list-table')->asTable()->one();
		$row = $table->findRow('Name', '2nd Module name !@#$%^&*()_+');
		if ($row->getColumn('Status')->getText() !== 'Enabled') {
			$row->query('link:Disabled')->one()->click();
		}

		// Apply and submit the filter from data provider.
		$form = $this->query('name:zbx_filter')->asForm()->one();
		$form->fill($data['filter']);
		$form->submit();
		$this->page->waitUntilReady();
		// Check (using module name) that only the expected filters are returned in the list.
		$this->assertTableDataColumn(CTestArrayHelper::get($data, 'expected'));
		// Reset the filter and check that all loaded modules are displayed.
		$this->query('button:Reset')->one()->click();
		$count = CDBHelper::getCount('SELECT moduleid FROM module');
		$this->assertEquals('Displaying '.$count.' of '.$count.' found', $this->query('class:table-stats')->one()->getText());
	}

	/**
	 * @depends testPageAdministrationGeneralModules_Layout
	 */
	public function testPageAdministrationGeneralModules_SimpleUpdate() {
		$sql = 'SELECT * FROM module ORDER BY moduleid';
		$initial_hash = CDBHelper::getHash($sql);

		// Open one of the modules and update it without making any changes.
		$this->page->login()->open('zabbix.php?action=module.list');
		$this->query('link:1st Module name')->waitUntilVisible()->one()->click();
		$this->page->waitUntilReady();
		$this->query('button:Update')->one()->click();

		$this->assertMessage(TEST_GOOD, 'Module updated');
		// Check that Module has been updated and that there are no changes took place.
		$this->assertEquals($initial_hash, CDBHelper::getHash($sql));
	}

	/**
	 * @depends testPageAdministrationGeneralModules_Layout
	 */
	public function testPageAdministrationGeneralModules_Cancel() {
		$sql = 'SELECT * FROM module ORDER BY moduleid';
		$initial_hash = CDBHelper::getHash($sql);

		// Open the module update of which is going to be cancelled.
		$this->page->login()->open('zabbix.php?action=module.list');
		$this->query('link:1st Module name')->waitUntilVisible()->one()->click();
		$this->page->waitUntilReady();

		// Edit module status and Cancel the update.
		$this->query('id:status')->asCheckbox()->one()->check();
		$this->query('button:Cancel')->one()->click();
		$this->page->waitUntilReady();

		// Check that Module has been updated and that there are no changes took place.
		$this->assertEquals($initial_hash, CDBHelper::getHash($sql));
	}

	public function getWidgetModuleData() {
		return [
			// Custom widget with JS, css and pre-defined widget type name
			[
				[
					'module_name' => 'Clock2',
					'widget_name' => 'Local',
					'widget_type' => 'ALARM CLOCK',
					'page' => 'Alarm clock page',
					'refresh_rate' => '1 minute'
				]
			],
			// Existing default widget.
			[
				[
					'module_name' => 'System information',
					'widget_name' => 'System information',
					'page' => 'System info page'
				]
			],
			// Existing default widget on which another widget is dependent.
			[
				[
					'module_name' => 'Map navigation tree',
					'widget_name' => 'Awesome map tree',
					'dependent_widgets' => ['Map'],
					'page' => 'Map page'
				]
			],
			// Disabling widget module from which another widget listens for hostgroup.
			[
				[
					'module_name' => 'Problem hosts',
					'widget_name' => 'Problem hosts hostgroup broadcaster',
					'dependent_widgets' => ['Geomap listener'],
					'page' => 'Widget communication page'
				]
			],
			// Disabling widget module from which other widgets listen for host and item.
			[
				[
					'module_name' => 'Honeycomb',
					'widget_name' => 'Honeycomb host and item broadcaster',
					'dependent_widgets' => ['Problems listener', 'Gauge listener'],
					'page' => 'Widget communication page'
				]
			],
			// Custom widget with minimal contents.
			[
				[
					'module_name' => 'Empty widget',
					'widget_name' => 'Empty widget',
					'page' => 'Empty widget page',
					'refresh_rate' => '2 minutes'
				]
			],
			// Existing default widget on template dashboard.
			[
				[
					'module_name' => 'Clock',
					'widget_name' => 'Default clock',
					'template' => true,
					'page' => 'Default clock page'
				]
			],
			// Custom widget with minimal contents.
			[
				[
					'module_name' => 'Clock2',
					'widget_name' => 'Server',
					'widget_type' => 'ALARM CLOCK',
					'template' => true,
					'not_available' => 'Empty widget',
					'page' => 'Alarm clock page'
				]
			]
		];
	}

	/**
	 * @onBeforeOnce prepareDashboardData
	 *
	 * @depends testPageAdministrationGeneralModules_Layout
	 *
	 * @dataProvider getWidgetModuleData
	 */
	public function testPageAdministrationGeneralModules_ChangeWidgetModuleStatus($module) {
		$this->page->login()->open('zabbix.php?action=module.list');

		// Determine the original status of the modules to be checked. Scenarios with mixed statuses are not considered.
		$initial_status = $this->query('class:list-table')->asTable()->one()->findRow('Name', $module['module_name'])
				->getColumn('Status')->getText();

		if ($initial_status === 'Disabled') {
			$this->enableModule($module, 'list');
			$this->checkWidgetModuleStatus($module);
			$this->disableModule($module, 'list');
			$this->checkWidgetModuleStatus($module, 'disabled');
		}
		else {
			$this->disableModule($module, 'list');
			$this->checkWidgetModuleStatus($module, 'disabled');
			$this->enableModule($module, 'list');
			$this->checkWidgetModuleStatus($module);
		}
	}

	public function getWidgetDimensions() {
		return [
			// Widget with pre-defined dimensions.
			[
				[
					'module_name' => 'Clock2',
					'widget_name' => 'Local',
					'widget_type' => 'ALARM CLOCK',
					'enable' => true,
					'page' => 'Map page',
					'dimensions' => ['width: 33.3333%', 'height: 280px']
				]
			],
			// Widget with pre-defined dimensions on template.
			[
				[
					'module_name' => 'Clock2',
					'widget_name' => 'Local',
					'widget_type' => 'ALARM CLOCK',
					'page' => 'Alarm clock page',
					'template' => true,
					'dimensions' => ['width: 33.3333%', 'height: 280px']
				]
			],
			// Widget with default dimensions.
			[
				[
					'module_name' => 'Empty widget',
					'widget_name' => 'Empty widget',
					'widget_type' => 'Empty widget',
					'enable' => true,
					'page' => 'Map page',
					'dimensions' => ['width: 50%', 'height: 350px']
				]
			]
		];
	}

	/**
	 *
	 * @depends testPageAdministrationGeneralModules_ChangeWidgetModuleStatus
	 *
	 * @dataProvider getWidgetDimensions
	 */
	public function testPageAdministrationGeneralModules_CheckWidgetDimensions($data) {
		$this->page->login();

		if (array_key_exists('enable', $data)) {
			$this->page->open('zabbix.php?action=module.list');
			$this->enableModule($data, 'list');
		}

		$this->checkWidgetDimensions($data);

		// Cancel editing dashboard not to interfere with following cases from data provider.
		$this->query('link:Cancel')->one()->click();
	}

	/**
	 * Add a widget of a specific type to dashboard or template dashboard and check its default dimensions.
	 *
	 * @param array	$data	data provider.
	 */
	private function checkWidgetDimensions($data) {
		// Open required dashboard page in edit mode.
		$url = (array_key_exists('template', $data))
			? 'zabbix.php?action=template.dashboard.edit&dashboardid='.self::$template_dashboardid
			: 'zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid;
		$this->page->open($url)->waitUntilReady();

		$dashboard = CDashboardElement::find()->one()->waitUntilVisible();
		$dashboard->selectPage($data['page']);

		if (!array_key_exists('template', $data)) {
			$dashboard->edit();
		}

		// Add widget from the data provider.
		$widget_form = $dashboard->addWidget()->asForm();
		$widget_form->fill(['Type' => CFormElement::RELOADABLE_FILL($data['widget_type'])]);
		$widget_form->submit();

		// Get widget dimensions from the style attribute of the widget grid element and compare with expected values.
		$grid_selector = 'xpath:.//div[contains(@class, "dashboard-grid-widget-head")]/../..';
		$widget_dimensions = $dashboard->getWidget($data['widget_name'])->query($grid_selector)->one()->getAttribute('style');
		$dimension_array = array_map('trim', explode(';', $widget_dimensions));

		foreach ($data['dimensions'] as $dimension) {
			$this->assertContains($dimension, $dimension_array);
		}
	}

	/**
	 * @depends testPageAdministrationGeneralModules_ChangeWidgetModuleStatus
	 */
	public function testPageAdministrationGeneralModules_DisableAllModules() {
		$this->page->login()->open('zabbix.php?action=module.list')->waitUntilReady();

		// Disable all modules.
		$this->query('id:all_modules')->waitUntilPresent()->asCheckbox()->one()->set(true);
		$this->query('button:Disable')->waitUntilCLickable()->one()->click();
		$this->page->acceptAlert();

		// Wait for the Success message to confirm that modules were disabled before heading to the dashboard.
		$this->assertMessage(TEST_GOOD, 'Modules disabled');

		// Open dashboard and check that all widgets are inaccessible.
		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$this->checkAllWidgetsDisabledOnPage();

		// Open template dashboard and check that all widgets are inaccessible.
		$this->page->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$template_dashboardid)->waitUntilReady();
		$this->checkAllWidgetsDisabledOnPage();

		// Open template dashboard on host and check that all widgets are inaccessible.
		$this->page->open('zabbix.php?action=host.dashboard.view&hostid='.self::$hostid.'&dashboardid='.self::$template_dashboardid)
				->waitUntilReady();
		$this->checkAllWidgetsDisabledOnPage();
	}

	/**
	 * Check that all widgets that are displayed on opened dashboard page are inaccessible widgets.
	 */
	private function checkAllWidgetsDisabledOnPage() {
		$dashboard = CDashboardElement::find()->one()->waitUntilPresent();
		$total_count = $dashboard->getWidgets()->count();
		$inaccessible_count = $dashboard->query(self::INACCESSIBLE_XPATH)->waitUntilVisible()->all()->count();
		$this->assertEquals($total_count, $inaccessible_count);
	}

	/**
	 * Check widgets of the enabled/disabled modules are displayed in dashboards, host dashboard and template dashboard views.
	 *
	 * @param array		$module		module related information from data provider.
	 * @param string	$status		status of widget module before execution of this function.
	 */
	private function checkWidgetModuleStatus($module, $status = 'enabled') {
		// Open dashboard or host dashboard and check widget display in this view.
		$url = array_key_exists('template', $module)
			? 'zabbix.php?action=host.dashboard.view&hostid='.self::$hostid.'&dashboardid='.self::$template_dashboardid
			: 'zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid;
		$this->page->open($url)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->waitUntilVisible();
		$this->checkWidgetStatusOnDashboard($dashboard, $module, $status);

		// Open Kiosk mode and check widget display again.
		$this->checkWidgetStatusOnDashboard($dashboard, $module, $status, 'kiosk');
		$this->query('xpath://button[@title="Normal view"]')->one()->click();
		$this->page->waitUntilReady();

		// Open dashboard in edit mode or open dashboard on template and check widget display again.
		if (array_key_exists('template', $module)) {
			$this->page->open('zabbix.php?action=template.dashboard.edit&dashboardid='.self::$template_dashboardid)
					->waitUntilReady();
		}
		else {
			$dashboard->edit();
		}

		$this->checkWidgetStatusOnDashboard($dashboard, $module, $status, 'edit');

		// Check that widget is present among widget types dropdown.
		$widget_dialog = $dashboard->addWidget();
		$widget_type = (array_key_exists('widget_type', $module) ? $module['widget_type'] : $module['module_name']);
		$options = $widget_dialog->asForm()->getField('Type')->asDropdown()->getOptions()->asText();

		// Check that widget type is present in "Type" dropdown only if corresponding module is enabled.
		$this->assertTrue(($status === 'enabled') ? in_array($widget_type, $options) : !in_array($widget_type, $options));

		// Check that module that should be present only on regular dashboards is not present (key used only on template).
		if (array_key_exists('not_available', $module)) {
			$this->assertFalse(in_array($module['not_available'], $options));
		}

		// Go back to the list of modules after the check is complete.
		$widget_dialog->close();
		$this->page->open('zabbix.php?action=module.list');
	}

	/**
	 * Check enabled or disabled widget display and its parameters on a particular dashboard page.
	 * Requirements to the widget are dependent on corresponding module status and dashboard mode (view, kiosk, edit modes).
	 *
	 * @param CDashboardElement		$dashboard	dashboard that contains the corresponding module widget.
	 * @param array					$module		module related information from data provider.
	 * @param string				$status		status of widget module before execution of this function.
	 * @param string				$mode		mode of the dashboard.
	 */
	private function checkWidgetStatusOnDashboard($dashboard, $module, $status, $mode = null) {
		$dashboard->selectPage($module['page']);

		// Switch to kiosk mode if required.
		if ($mode === 'kiosk') {
			$this->query('xpath://button[@title="Kiosk mode"]')->one()->click();
			$this->page->waitUntilReady();
		}

		if ($status === 'enabled') {
			// Check that widget with required name is shown and that is doesn't have the inaccessilbe widget string in it.
			$widget = $dashboard->getWidget($module['widget_name']);
			$this->assertFalse($widget->query("xpath:.//div[text()=".CXPathHelper::escapeQuotes(self::INACCESSIBLE_TEXT).
					"]")->one(false)->isValid()
			);

			// Check refresh interval if such specified in the data provider.
			if (array_key_exists('refresh_rate', $module) && $mode !== 'edit') {
				$this->assertEquals($module['refresh_rate'], $widget->getRefreshInterval());
				CPopupMenuElement::find()->one()->close();
			}

			// Check that dependent widget is there and that it's content is not hidden.
			if (array_key_exists('dependent_widgets', $module)) {
				foreach ($module['dependent_widgets'] as $widget_name) {
					$dependent_widget = $dashboard->getWidget($widget_name);
					$this->assertTrue($dependent_widget->isValid());
					$this->assertNotEquals(self::INACCESSIBLE_TEXT, $dependent_widget->getContent()->getText());
				}
			}
		}
		else {
			// Check that there is only 1 inaccessible widget present on the opened dashboard page.
			$this->assertEquals(1, $dashboard->query(self::INACCESSIBLE_XPATH)->waitUntilVisible()->all()->count());

			// Get the inaccessible widget and check its contents.
			$inaccessible_widget = $dashboard->getWidget('Inaccessible widget');
			$this->assertEquals(self::INACCESSIBLE_TEXT, $inaccessible_widget->getContent()->getText());

			// Check that widget of the disabled module is not present on the dashboard.
			$this->assertFalse($dashboard->getWidget($module['widget_name'], false)->isValid());

			// Check that the dependent widget is still there, but its contents is not displayed.
			if (array_key_exists('dependent_widgets', $module)) {
				foreach ($module['dependent_widgets'] as $widget_name) {
					$dependent_widget = $dashboard->getWidget($widget_name);
					$this->assertTrue($dependent_widget->isValid());
					$this->assertEquals("Referred widget is unavailable\nPlease update configuration",
							$dependent_widget->getContent()->getText()
					);
				}
			}

			/**
			 * Check that edit widget button on disabled module widget is hidden and that it doesn't exist
			 * if the dashboard is opened in Monitoring => Hosts view (where All hosts link is present) or in kiosk mode.
			 */
			$edit_button = $inaccessible_widget->query('xpath:.//button['.CXPathHelper::fromClass('js-widget-edit').']');
			$this->assertFalse(($mode === 'kiosk' || $this->query('link:All hosts')->one(false)->isValid())
					? $edit_button->one(false)->isValid()
					: $edit_button->one()->isDisplayed()
			);

			// It should not be possible only to Delete the widget and only when the dashboard is in edit mode.
			$button = $inaccessible_widget->query('xpath:.//button['.CXPathHelper::fromClass('js-widget-action').']')->one();

			if ($mode === 'edit') {
				$popup_menu = $button->waitUntilPresent()->asPopupButton()->getMenu();
				$menu_items = $popup_menu->getItems();
				$this->assertEquals(['Copy', 'Paste', 'Delete'], $menu_items->asText());

				// Check that inaccessible widgets can only be deleted.
				$this->assertEquals(['Delete'], array_values($menu_items->filter(CElementFilter::CLICKABLE)->asText()));
				$popup_menu->close();
			}
			else {
				$this->assertFalse($button->isVisible());
			}
		}
	}

	/**
	 * Function loads modules in frontend and checks the message depending on whether new modules were loaded.
	 *
	 * @param bool	$first_load		flag that determines whether modules are loaded for the first time.
	 */
	private function loadModules($first_load = true) {
		// Load modules
		$this->query('button:Scan directory')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		// Check message after loading modules.
		if ($first_load) {
			// Each loaded module name is checked separately due to difference in their sorting on Jenkins and locally.
			$this->assertMessage(TEST_GOOD, 'Modules updated', ['Modules added:', '1st Module name',
					'2nd Module name !@#$%^&*()_+', '4th Module', '5th Module', 'Clock2', 'Empty widget', 'шестой модуль'
			]);
		}
		else {
			$this->assertMessage(TEST_GOOD, 'No new modules discovered');
		}
	}

	/**
	 * Function checks if the corresponding menu entry exists, clicks on it and checks the URL and header of the page.
	 * If the module should remove a menu entry, the function makes sure that the corresponding menu entry doesn't exist.
	 *
	 * @param array	$module		module related information from data provider.
	 */
	private function assertModuleEnabled($module) {
		$xpath = 'xpath://ul[@class="menu-main"]//a[text()="';
		// If module removes a menu entry or top level menu entry, check that such entries are not present.
		if (CTestArrayHelper::get($module, 'remove')) {
			$this->assertEquals(0, $this->query($xpath.$module['menu_entry'].'"]')->count());

			if (array_key_exists('top_menu_entry', $module)) {
				$this->assertEquals(0, $this->query($xpath.$module['top_menu_entry'].'"]')->count());
			}

			return;
		}
		// If module adds single or multiple menu entries, open each corresponding view, check view header and URL.
		$top_entry_name = CTestArrayHelper::get($module, 'top_menu_entry', 'Monitoring');

		$top_entry = $this->query('link', $top_entry_name)->one();

		// Click on top level menu only in case if it is not expanded already.
		if (!$top_entry->parents('tag:li')->one()->hasClass('is-expanded')) {
			$top_entry->waitUntilClickable()->click();
		}

		foreach ($module['menu_entries'] as $entry) {
			sleep(1);
			$this->query($xpath.$entry['name'].'"]')->waitUntilClickable()->one()->click();
			$this->page->waitUntilReady();
			$this->assertStringContainsString('zabbix.php?action='.$entry['action'], $this->page->getCurrentURL());

			if (CTestArrayHelper::get($entry, 'form')) {
				$this->query($entry['form'])->asForm()->one()->submit();

				$this->assertMessage(TEST_GOOD, $entry['message']);
			}
			else {
				$this->assertEquals($entry['message'], $this->query('tag:h1')->waitUntilVisible()->one()->getText());
			}
		}
		// Get back to modules list to enable or disable the next module.
		$this->page->open('zabbix.php?action=module.list')->waitUntilReady();
	}

	/**
	 * Function checks if the corresponding menu entry is removed and url is not active after the module is disabled.
	 * If enabling the module removes a menu entry, the function checks that it is back after disabling the module.
	 *
	 * @param array	$module		module related information from data provider.
	 */
	private function assertModuleDisabled($module) {
		$xpath = 'xpath://ul[@class="menu-main"]//li/a[text()="';
		// If module removes a menu entry or top level menu entry, check that entries are back after disabling the module.
		if (array_key_exists('remove', $module)) {
			$this->assertEquals(1, $this->query($xpath.$module['menu_entry'].'"]')->count());

			if (array_key_exists('top_menu_entry', $module)) {
				$this->assertEquals(1, $this->query($xpath.$module['top_menu_entry'].'"]')->count());
			}

			return;
		}
		// If module adds single or multiple menu entries, check that entries don't exist after disabling the module.
		$top_menus = ['Dashboards', 'Monitoring', 'Services', 'Inventory', 'Reports', 'Data collection', 'Alerts',
				'Users', 'Administration'
		];

		foreach ($module['menu_entries'] as $entry) {
			$check_entry = (array_key_exists('top_menu_entry', $module) && !in_array($module['top_menu_entry'], $top_menus))
				? $module['top_menu_entry']
				: $entry['name'];
			$this->assertEquals(0, $this->query($xpath.$check_entry.'"]')->count());

			// In case if module many entry leads to an existing view, don't check that menu entry URL isn't available.
			if (CTestArrayHelper::get($entry, 'check_disabled', true)) {
				$this->page->open('zabbix.php?action='.$entry['action'])->waitUntilReady();
				$message = CMessageElement::find()->one();
				$this->assertStringContainsString('Page not found', $message->getText());
				$this->page->open('zabbix.php?action=module.list');
			}
		}
	}

	/**
	 * Function enables module from the list in modules page or from module details form, depending on input parameters.
	 *
	 * @param array		$data	data array with module details
	 * @param string	$view	view from which the module should be enabled - module list or module details form.
	 */
	private function enableModule($module, $view) {
		$expected = CTestArrayHelper::get($module, 'expected', TEST_GOOD);

		// Change module status from Disabled to Enabled.
		if ($view === 'form') {
			$this->changeModuleStatusFromForm($module['module_name'], true, $expected);
		}
		else {
			$this->changeModuleStatusFromPage($module['module_name'], 'Disabled');
		}
		// In case of negative test check error message and confirm that module wasn't applied.
		if ($expected === TEST_BAD) {
			$title = ($view === 'form') ? 'Cannot update module' : 'Cannot enable module';
			$this->assertMessage($module['expected'], $title, $module['error_details']);

			if ($view === 'form') {
				COverlayDialogElement::find()->one()->close();
			}

			$this->assertModuleDisabled($module);

			return;
		}
		// Check message and confirm that changes, made by the enabled module, took place.
		$message = ($view === 'form') ? 'Module updated' : 'Module enabled';
		$this->assertMessage($expected, $message);
		CMessageElement::find()->one()->close();
	}

	/**
	 * Function disables module from the list in modules page or from module details form, depending on input parameters.
	 *
	 * @param array		$module	data array with module details
	 * @param string	$view	view from which the module should be enabled - module list or module details form.
	 */
	private function disableModule($module, $view) {
		$expected = CTestArrayHelper::get($module, 'expected', TEST_GOOD);

		// In case of negative test do nothing.
		if ($expected === TEST_BAD) {

			return;
		}

		// Change module status from Enabled to Disabled.
		if ($view === 'form') {
			$this->changeModuleStatusFromForm($module['module_name'], false, $expected);
		}
		else {
			$this->changeModuleStatusFromPage($module['module_name'], 'Enabled');
		}
		// Check message and confirm that changes, made by the module, were reversed.
		$message = ($view === 'form') ? 'Module updated' : 'Module disabled';
		$this->assertMessage(TEST_GOOD, $message);
	}

	/**
	 * Function changes module status from the list in modules page.
	 *
	 * @param string	$name				module name
	 * @param string	$current_status		module current status that is going to be changed.
	 */
	private function changeModuleStatusFromPage($name, $current_status) {
		$table = $this->query('class:list-table')->asTable()->one();
		$row = $table->findRow('Name', $name);
		$row->query('link', $current_status)->one()->click();
		$this->page->waitUntilReady();
	}

	/**
	 * Function changes module status from the modules details form.
	 *
	 * @param string	$name			module name
	 * @param bool		$enabled		boolean value to be set in "Enabled" checkbox in module details form.
	 * @param constant	$expected		flag that determines whether the module update should succeed or fail.
	 */
	private function changeModuleStatusFromForm($name, $enabled, $expected) {
		$this->query('link', $name)->waitUntilVisible()->one()->click();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		// Edit module status and press update.
		$dialog->query('id:status')->asCheckbox()->one()->set($enabled);
		$this->query('button:Update')->one()->click();

		if ($expected === TEST_GOOD) {
			$dialog->ensureNotPresent();
		}
	}
}
