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

		$linked_triggers = [
			'Trigger that linked' => 'Template that linked to host: ',
			'trigger prototype linked update{#KEY}' => 'Template that linked to host: ',
			'trigger template linked update' => 'Template that linked to template: ',
			'trigger prototype template update{#KEY}' => 'Template that linked to template: '
		];

		$linked = array_key_exists($trigger_name, $linked_triggers) ? $linked_triggers[$trigger_name] : null;

		$this->assertTableHasDataColumn([$linked.$trigger_name."\n".
			'Depends on:'."\n".
			implode("\n", $data['result'])
		]);

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
					$element = $row->getColumn('Name')->query('tag:a')->one(false);
					return $element->isValid() && $element->getText() === $trigger;
				})->select();
			}

			$dialog->getFooter()->query('button:Select')->one()->click();
			$dialog->waitUntilNotVisible();
		}
	}

	/**
	 * Function for checking trigger dependence list.
	 *
	 * @param array  $data        data provider
	 * @param string $name        name of a host or template
	 * @param string $objectid    id of host or template
	 * @param string $lldid       id of low level discovery for trigger prototypes
	 * @param string $context     host or template
	 */
	protected function checkDependencyList($data, $name, $objectid, $lldid, $context) {
		$url = (str_contains($data['dependant_trigger'], 'prototype'))
			? 'zabbix.php?action=trigger.prototype.list&parent_discoveryid='.$lldid.'&context='.$context
			: 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B0%5D='.$objectid.'&context='.$context;

		$this->page->login()->open($url)->waitUntilReady();
		$this->query('link', $data['dependant_trigger'])->waitUntilClickable()->one()->click();
		$form = COverlayDialogElement::find()->asForm()->one()->waitUntilReady();
		$form->selectTab('Dependencies');

		$button_selector = (str_contains($data['dependant_trigger'], 'prototype')) ? 'Add prototype' : 'Add';
		$this->selectTriggersAndCheck($form, $button_selector, $data['master_triggers'], $name);

		if (array_key_exists('host_master_triggers', $data)) {
			$this->selectTriggersAndCheck($form, 'Add host trigger', $data['host_master_triggers'],
					'Host for trigger tags filtering'
			);
		}

		COverlayDialogElement::closeAll();
	}

	/**
	 * Function for opening triggers overlay and checking that selected triggers are disabled after save.
	 *
	 * @param CFormElement  $form        trigger creation form
	 * @param string $button_selector    Add, Add prototype or Add host trigger
	 * @param string $master_triggers    selected triggers
	 * @param string $host_name          host or template where triggers are selected from
	 */
	protected function selectTriggersAndCheck($form, $button_selector, $master_triggers, $host_name) {
		$button = $form->getFieldContainer('Dependencies')->query('button', $button_selector)->waitUntilClickable()->one();
		$button->click();
		$initial_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$table = $initial_dialog->query('class:list-table')->asTable()->one();

		if ($button_selector === 'Add host trigger') {
			$initial_dialog->asForm(['normalized' => true])->fill(['Host' => $host_name]);
			$table->waitUntilReloaded();
		}

		$table->findRows('Name', $master_triggers)->select();
		$initial_dialog->asForm()->submit();
		$initial_dialog->waitUntilNotVisible();
		$button->click();
		$saved_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$checked_table = $saved_dialog->query('class:list-table')->asTable()->one();

		// Check that selected triggers are disabled in the list.
		foreach ($master_triggers as $master_trigger) {
			$row = $checked_table->findRow('Name', $host_name.': '.$master_trigger);
			$this->assertTrue($row->query('tag:input')->one()->isEnabled(false) && $row->isSelected());
		}

		$saved_dialog->close();
	}
}
