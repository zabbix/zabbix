<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

class testTriggerDependencies extends CWebTest {

	/**
	 * SQL to check trigger dependencies hash.
	 */
	const SQL = 'SELECT * FROM trigger_depends ORDER by triggerdepid';

	/**
	 * Attach Behaviors to the test.
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
	 * Create or update trigger with dependencies.
	 *
	 * @param array  $data				data provider.
	 * @param string $expression		trigger expression used in create scenarios.
	 * @param string $success_title		success message title.
	 * @param string $error_title		error message title.
	 * @param string $name_check		trigger name that should be checked in update scenarios.
	 */
	public function triggerCreateUpdate($data, $expression = null, $success_title, $error_title = null, $name_check = null) {
		// If scenarios is TEST_BAD, hash should be checked.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();

		if ($expression) {
			$form->fill(['Name' => $data['name'], 'Expression' => $expression]);
		}

		// Add trigger/host trigger dependency.
		$form->selectTab('Dependencies');

		// If expressions doesn't exist, then it is update scenario.
		if (is_null($expression) && CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			foreach ($this->query('id:dependency-table')->asTable()->one()->getRows() as $row) {
				$row->query('button:Remove')->one()->click();
			}
		}

		$trigger_dependencies = [
			'dependencies' => 'id:add-dep-template-trigger',
			'host_dependencies' => 'id:add-dep-host-trigger',
			'dependencies_for_host' => 'id:add-dep-trigger'
		];

		// Add dependencies.
		foreach ($trigger_dependencies as $dependency_type => $selector) {
			if (array_key_exists($dependency_type, $data)) {
				$this->addDependence($data[$dependency_type], $selector);
			}
		}

		// Adding trigger prototype dependencies allowed only from same host.
		if (array_key_exists('prototype_dependencies', $data)) {
			$form->query('id:add-dep-trigger-prototype')->one()->click();
			$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();

			foreach ($data['prototype_dependencies'] as $trigger) {
				// TODO: should be fixed after git-hook improvements in DEV-2396
				$dialog->query("xpath:.//a[text()=".CXPathHelper::escapeQuotes($trigger)."]/../preceding-sibling::td/input")
						->asCheckbox()->one()->check();
			}

			$dialog->query('button:Select')->one()->click();
			$dialog->waitUntilNotVisible();
		}

		$form->submit();
		$this->page->waitUntilReady();

		// Check error message and added dependencies.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, $error_title, $data['error_message']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
		}
		else {
			$this->assertMessage(TEST_GOOD, $success_title);
			$this->checkTrigger($data, $name_check);
		}
		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Check that dependencies added/updated on trigger.
	 *
	 * @param array  $data				data provider.
	 * @param string $updated_trigger	updated trigger name.
	 */
	protected function checkTrigger($data, $updated_trigger = null) {
		// Trigger name where to check dependencies.
		$trigger_name = (is_null($updated_trigger)) ? $data['name'] : $updated_trigger;

		// Check that dependent triggers displayed on triggers list page.
		$table = $this->query('class:list-table')->asTable()->one();

		foreach ($data['result'] as $result) {
			$table->findRow('Name', $trigger_name, true)->getColumn('Name')->query('link:'.$result)->exists();
		}

		// Open just created/updated trigger and navigate to dependencies tab.
		$table->query('link', $trigger_name)->one()->click();
		$this->page->waitUntilReady();
		$this->query('name:trigger_edit_form')->asForm()->one()->selectTab('Dependencies');

		// Take all hosts->triggers from Name column and check that created/updated hosts->trigger exists.
		$this->assertEquals($data['result'], $this->getTableColumnData('Name', 'id:dependency-table'));
	}

	/**
	 * Add trigger dependence - host trigger, simple trigger
	 *
	 * @param array $values			host/template name and trigger name.
	 * @param string $selector		Add or Add host trigger - button selector.
	 */
	protected function addDependence($values, $selector) {
		foreach ($values as $host_name => $triggers) {
			$this->query($selector)->one()->click();
			$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
			$dialog->query('id:generic-popup-form')->asMultiselect()->one()->fill(['Host' => $host_name]);
			$dialog->waitUntilReady();

			foreach ($triggers as $trigger) {
				// TODO: should be fixed after git-hook improvements in DEV-2396, remove double quotes.
				$dialog->query("xpath:.//a[text()=".CXPathHelper::escapeQuotes($trigger)."]/../preceding-sibling::td/input")
						->asCheckbox()->one()->check();
			}

			$dialog->getFooter()->query('button:Select')->one()->click();
			$dialog->waitUntilNotVisible();
		}
	}
}
