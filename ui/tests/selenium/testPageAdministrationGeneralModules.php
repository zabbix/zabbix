<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/traits/TableTrait.php';

/**
 * @backup module
 */

class testPageAdministrationGeneralModules extends CWebTest {

	use TableTrait;

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
			]
		];
		// Open modules page and check header.
		$this->page->login()->open('zabbix.php?action=module.list');
		$this->assertEquals('Modules', $this->query('tag:h1')->one()->getText());

		// Check status of buttons on the modules page.
		foreach (['Scan directory' => true, 'Enable' => false, 'Disable' => false] as $button => $enabled) {
			$this->assertTrue($this->query('button', $button)->one()->isEnabled($enabled));
		}
		// Check that modules are not being loaded until the 'Scan directory' button is pressed.
		$this->assertEquals($this->query('class:nothing-to-show')->one()->getText(), 'No data found.');
		$this->assertEquals('Displaying 0 of 0 found', $this->query('class:table-stats')->one()->getText());
		$this->assertEquals('0 selected', $this->query('id:selected_count')->one()->getText());
		// Check modules table headers.
		$table = $this->query('class:list-table')->asTable()->one();
		$headers = $table->getHeadersText();
		// Remove empty element from headers array.
		array_shift($headers);
		$this->assertSame(['Name', 'Version', 'Author', 'Description', 'Status'], $headers);

		// Load modules.
		$this->loadModules();

		// Check parameters of modules in the modules table.
		$this->assertTableData($modules);

		$count = CDBHelper::getCount('SELECT moduleid FROM module');
		$this->assertEquals('Displaying '.$count.' of '.$count.' found', $this->query('class:table-stats')->one()->getText());

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
					'Directory' => 'module_number_1',
					'Namespace' => 'Example_A',
					'Homepage' => '1st module URL',
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
					'Directory' => 'module_number_2',
					'Namespace' => 'Example_B',
					'Homepage' => '!@#$%^&*()_+',
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
					'Directory' => 'module_number_4',
					'Namespace' => 'Example_A',
					'Homepage' => '-',
					'Enabled' => false
				]
			]
		];
	}

	/**
	 * @dataProvider getModuleDetails
	 * @depends testPageAdministrationGeneralModules_Layout
	 */
	public function testPageAdministrationGeneralModules_Details($data) {
		// Open corresponding module from the modules table.
		$this->page->login()->open('zabbix.php?action=module.list');
		$this->query('link', $data['Name'])->waitUntilVisible()->one()->click();
		$this->page->waitUntilReady();
		$form = $this->query('name:module-form')->asForm()->waitUntilVisible()->one();
		// Check value af every field in Module details form.
		foreach ($data as $key => $value) {
			$this->assertEquals($value, $form->getFieldContainer($key)->getText());
		}
	}

	public function getModuleData() {
		return [
			// Enable only 1st module.
			[
				[
					[
						'name' => '1st Module name',
						'menu_entry' => '1st Module',
						'url' => 'zabbix.php?action=first.module',
						'message' => 'If You see this message - 1st module is working'
					]
				]
			],
			// Enable only 2nd Module.
			[
				[
					[
						'name' => '2nd Module name !@#$%^&*()_+',
						'menu_entry' => '2nd Module',
						'url' => 'zabbix.php?action=second.module',
						'message' => '2nd module is also working'
					]
				]
			],
			// Enable both 1st and 2nd module.
			[
				[
					[
						'name' => '1st Module name',
						'menu_entry' => '1st Module',
						'url' => 'zabbix.php?action=first.module',
						'message' => 'If You see this message - 1st module is working'
					],
					[
						'name' => '2nd Module name !@#$%^&*()_+',
						'menu_entry' => '2nd Module',
						'url' => 'zabbix.php?action=second.module',
						'message' => '2nd module is also working'
					]
				]
			],
			// Attempting to enable two modules that use identical namespace.
			[
				[
					[
						'name' => '1st Module name',
						'menu_entry' => '1st Module',
						'url' => 'zabbix.php?action=first.module',
						'message' => 'If You see this message - 1st module is working'
					],
					[
						'expected' => TEST_BAD,
						'name' =>'4th Module',
						'menu_entry' => '4th Module',
						'error_title' => 'Cannot enable module: 4th Module.',
						'error_details' => 'Identical namespace (Example_A) is used by modules located at '.
								'module_number_1, module_number_4.'
					]
				]
			]
		];
	}

	/**
	 * @backup module
	 * @dataProvider getModuleData
	 * @depends testPageAdministrationGeneralModules_Layout
	 */
	public function testPageAdministrationGeneralModules_EnableDisable($data) {
		$this->page->login()->open('zabbix.php?action=module.list');
		// Enable modules from modules page one by one and check that changes took place.
		$this->enableAndCheckModules($data);
		// Disable the module that was enabled from page and confirm that changes made by the module are reverted.
		$this->disableAndCheckModules($data);
		// Enable modules from module details form one by one and check that changes took place.
		$this->enableAndCheckModules($data, true);
		// Disable the module that was enabled from details form and confirm that changes made by the module are reverted.
		$this->disableAndCheckModules($data, true);
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
						'4th Module'
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
					'expected' => [
						'2nd Module name !@#$%^&*()_+'
					]
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
						'4th Module'
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
	 * @depends testPageAdministrationGeneralModules_Layout
	 */
	public function testPageAdministrationGeneralModules_Filter($data) {
		$this->page->login()->open('zabbix.php?action=module.list');

		// Before checking the filter one of the modules needs to be enabled.
		$table = $this->query('class:list-table')->asTable()->one();
		$row = $table->findRow('Name', '2nd Module name !@#$%^&*()_+');
		if ($row->getColumn('Status')->getText() != 'Enabled') {
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

		// Check module update message.
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals($message->getTitle(), 'Module disabled: 1st Module name.'); // ZBX-17721
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

	/**
	 * Function loads modules in frontend and checks the message depending on whether new modules were loaded.
	 *
	 * @param bool		$first_load		flag that determines whether modules are loaded for the first time.
	 */
	private function loadModules($first_load = true) {
		// Load modules
		$this->query('button:Scan directory')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();
		// Check message after loading modules.
		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());

		if ($first_load) {
			// Each loaded module name is checked separatelly due to difference in their sorting on Jenkinsand locally.
			$this->checkModulesMessage($message, 'Modules updated', ['Modules added:', '1st Module name',
					'2nd Module name !@#$%^&*()_+', '4th Module']);
		}
		else {
			$this->assertEquals($message->getTitle(), 'No new modules discovered');
		}
	}

	/**
	 * Function checks if the corresponding menu option exists, clicks on it and checks the URL and header of the page.
	 * If the module shouldn't be enabled, the function makes sure that the corresponding menu entry doesn't exist.
	 */
	private function checkChangesByModule($module, $presence = true) {
		$xpath = 'xpath://ul[@class="menu-main"]//a[text()="'.$module['menu_entry'].'"]';
		if ($presence) {
			$this->query('link:Monitoring')->one()->click();
			$this->query($xpath)->one()->waitUntilClickable()->click();
			$this->page->waitUntilReady();
			$this->assertContains($module['url'], $this->page->getCurrentURL());
			$this->assertEquals($module['message'], $this->query('tag:h1')->waitUntilVisible()->one()->getText());
			$this->page->open('zabbix.php?action=module.list')->waitUntilReady();
		}
		else {
			$this->assertTrue($this->query($xpath)->count() === 0);
		}
	}

	/**
	 * Function checks the title and details of error messages or of messages displayed after scanning module directory.
	 */
	private function checkModulesMessage($message, $title, $details) {
		$this->assertEquals($title, $message->getTitle());
		if (!is_array($details)) {
			$details = [$details];
		}

		foreach ($details as $detail) {
			$this->assertTrue($message->hasLine($detail));
		}
	}

	/**
	 * Function enables modules from the list in modules page or from module details form, depending on input parameters.
	 * @param array		$data			data array with module details
	 * @param bool		$from_form		flag that determines whether the module is enabled from module details form.
	 */
	private function enableAndCheckModules($data, $from_form = false) {
		foreach ($data as $module) {
			// Change module status from Disabled to Enabled.
			($from_form) ? $this->changeModuleStatusFromForm($module['name'], true) :
					$this->changeModuleStatusFromPage($module['name'], 'Disabled');
			$message = CMessageElement::find()->one();
			// In case of negative test check error message and confirm that module wasn't applied.
			if (CTestArrayHelper::get($module, 'expected', TEST_GOOD) === TEST_BAD) {
				$this->assertTrue($message->isBad());
				$this->checkModulesMessage($message, $module['error_title'], $module['error_details']);
				$this->checkChangesByModule($module, false);
				if ($from_form) {
					$this->query('button:Cancel')->one()->click();
					$this->page->waitUntilReady();
				}
				continue;
			}
			// Check message and confirm that changes, made by the enabled module, took place.
			$this->assertTrue($message->isGood());
			$this->assertEquals($message->getTitle(), 'Module enabled: '.$module['name'].'.');
			$this->checkChangesByModule($module, true);
		}
	}

	/**
	 * Function disables modules from the list in modules page or from module details form, depending on input parameters.
	 * @param array		$data			data array with module details
	 * @param bool		$from_form		flag that determines whether the module is enabled from module details form.
	 */
	private function disableAndCheckModules($data, $from_form = false) {
		foreach ($data as $module) {
			// In case of negative test do nothing.
			if (CTestArrayHelper::get($module, 'expected', TEST_GOOD) === TEST_BAD) {
				continue;
			}
			// Change module status from Enabled to Disabled.
			($from_form) ? $this->changeModuleStatusFromForm($module['name'], false) :
					$this->changeModuleStatusFromPage($module['name'], 'Enabled');
			$message = CMessageElement::find()->one();
			// Check message and confirm that changes, made by the module, were revered.
			$this->assertTrue($message->isGood());
			$this->assertEquals($message->getTitle(), 'Module disabled: '.$module['name'].'.');
			$this->checkChangesByModule($module, false);
		}
	}

	/**
	 * Function changes module status from the list in modules page.
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
	 * @param string	$name			module name
	 * @param bool		$enabled		boolean value to be set in "Enabled" checkbox in module details form.
	 */
	private function changeModuleStatusFromForm($name, $enabled) {
		$this->query('link', $name)->waitUntilVisible()->one()->click();
		$this->page->waitUntilReady();

		// Edit module status and press update.
		$this->query('id:status')->asCheckbox()->one()->set($enabled);
		$this->query('button:Update')->one()->click();
		$this->page->waitUntilReady();
	}
}
