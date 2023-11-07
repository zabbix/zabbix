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
	 * @param string $success_title		success message title.
	 * @param string $expression		trigger expression used in create scenarios.
	 * @param string $error_title		error message title.
	 * @param string $name_check		trigger name that should be checked in update scenarios.
	 */
	public function triggerCreateUpdate($data, $success_title, $expression = null, $error_title = null, $name_check = null) {
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
			foreach ($form->getField('Dependencies')->asTable()->getRows() as $row) {
				$row->query('button:Remove')->one()->click();
			}
		}

		// Dependencies buttons.
		$trigger_dependencies = [
			'dependencies' => 'Add',
			'host_dependencies' => 'Add host trigger',
			'prototype_dependencies' => 'Add prototype'
		];

		// Add dependencies.
		foreach ($trigger_dependencies as $dependency_type => $button) {
			if (array_key_exists($dependency_type, $data)) {
				$this->addDependence($data[$dependency_type], $button);
			}
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
		$trigger_name = is_null($updated_trigger) ? $data['name'] : $updated_trigger;

		// Check that dependent triggers displayed on triggers list page.
		$table = $this->query('class:list-table')->asTable()->one();

		// TODO: uncomment after ZBX-23623 fix. Maybe better solution can be found after bugfix.
//		$linked_triggers = [
//			'Trigger that linked' => 'Template that linked to host: ',
//			'trigger prototype linked update{#KEY}' => 'Template that linked to host: ',
//			'trigger template linked update' => 'Template that linked to template: ',
//			'trigger prototype template update{#KEY}' => 'Template that linked to template: '
//		];
//
//		$linked = array_key_exists($trigger_name, $linked_triggers) ? $linked_triggers[$trigger_name] : null;
//
//		$this->assertTableHasDataColumn([$linked.$trigger_name."\n".
//			'Depends on:'."\n".
//			implode("\n", $data['result'])
//		]);

		// TODO: remove this foreach after ZBX-23623 fix.
		foreach ($data['result'] as $result) {
			$table->findRow('Name', $trigger_name, true)->getColumn('Name')->query('link', $result)->exists();
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
	 * @param string $button		Add, Add host trigger or Add prototype - button text.
	 */
	protected function addDependence($values, $button) {
		foreach ($values as $host_name => $triggers) {
			$this->query('id:dependenciesTab')->query('button', $button)->one()->click();
			$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();

			if ($button !== 'Add prototype') {
				$dialog->query('id:generic-popup-form')->asMultiselect()->one()->fill(['Host' => $host_name]);
				$dialog->waitUntilReady();
			}
			if (!is_array($triggers)) {
				$triggers = [$triggers];
			}

			// Check-in (add) triggers for dependence and submit
			foreach ($triggers as $trigger) {
				$dialog->asTable()->findRows(function ($row) use ($trigger) {
					return $row->getColumn('Name')->query('tag:a')->one()->getText() === $trigger;
				})->select();
			}

			$dialog->getFooter()->query('button:Select')->one()->click();
			$dialog->waitUntilNotVisible();
		}
	}
}
