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

require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * Tests for "Configuration -> Maintenance".
 *
 * Forms:
 * - Create maintenance.
 * - Clone maintenance.
 * - Delete maintenance.
 *
 * @onBefore prepareMaintenanceData
 *
 * @backup maintenances
 */
class testFormMaintenance extends CLegacyWebTest {

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

	public $name = 'Test maintenance';
	public $periods_table = 'id:timeperiods';

	public function prepareMaintenanceData() {
		CDataHelper::call('maintenance.create', [
			[
				'name' => 'Maintenance for update (data collection)',
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'active_since' => 1534885200,
				'active_till' => 1534971600,
				'description' => 'Test description update',
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'tags_evaltype' => 2,
				'tags' => [
					['tag' => 'Tag1', 'operator' => MAINTENANCE_TAG_OPERATOR_LIKE, 'value' => 'A'],
					['tag' => 'Tag2', 'operator' => MAINTENANCE_TAG_OPERATOR_EQUAL, 'value' => 'B']
				],
				'timeperiods' => [
					[
						'period' => 90000,
						'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
						'start_date' => 1534950000
					]
				]
			]
		]);
	}

	/**
	 * Create maintenance with periods and host group.
	 */
	public function testFormMaintenance_Create() {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->page->assertTitle('Configuration of maintenance periods');
		$this->page->assertHeader('Maintenance periods');
		$this->query('button:Create maintenance period')->one()->waitUntilClickable()->click();

		// Type maintenance name.
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$form->fill(['Name' => $this->name, 'Host groups' => 'Zabbix servers', 'id:tags_evaltype' => 'Or']);

		$periods = [
			[
				'fields' => '',
				'result' => [['Period type' => 'One time only']]
			],
			[
				'fields' => [
					'Period type' => 'Daily'
				],
				'result' => [['Period type' => 'Daily', 'Schedule' => 'At 00:00 every 1 day']]
			],
			[
				'fields' => [
					'Period type' => 'Weekly',
					'Monday' => true,
					'Sunday' => true
				],
				'result' => [['Period type' => 'Weekly', 'Schedule' => 'At 00:00 Monday, Sunday of every 1 week']]
			],
			[
				'fields' => [
					'Period type' => 'Monthly',
					'January' => true,
					'November' => true
				],
				'result' => [['Period type' => 'Monthly', 'Schedule' => 'At 00:00 on day 1 of every January, November']]
			]
		];
		foreach ($periods as $period) {
			$form->query('button:Add')->one()->click();
			$period_overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last()->asForm();
			$period_overlay->fill($period['fields']);
			$period_overlay->submit();
			$period_overlay->waitUntilNotVisible();
			$this->assertTableHasData($period['result'], $this->periods_table);
		}

		// Add problem tags.
		$value = 'Value';
		$tags = [
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 0,
				'tag' => 'Tag1',
				'value' => $value
			],
			[
				'tag' => 'Tag2',
				'value' => $value
			],
			[
				'tag' => 'Tag3',
				'value' => $value
			]
		];
		$this->query('id:tags')->asMultifieldTable()->one()->fill($tags);

		// Create maintenance and check the results in frontend.
		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Maintenance period created');
		$this->assertTableHasData([['Name' => $this->name, 'Type' => 'With data collection']]);

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($this->name)));
		$this->assertEquals(3, CDBHelper::getCount('SELECT NULL FROM maintenance_tag WHERE value='.zbx_dbstr($value)));
	}

	/**
	 * Changes not preserve when close edit form using cancel button.
	 *
	 * @depends testFormMaintenance_Create
	 */
	public function testFromMaintenance_Cancel() {
		$sql_hash = 'SELECT * FROM maintenances ORDER BY maintenanceid';
		$old_hash = CDBHelper::getHash($sql_hash);

		// Open form and change maintenance name.
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', $this->name)->one()->waitUntilClickable()->click();
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$form->fill(['Name' => 'Some random text']);

		// Remove 4th defined period.
		$form->query('xpath:.//td[contains(text(), "Monthly")]/..//button[text()="Remove"]')->one()->click()->waitUntilNotvisible();

		// Close the form.
		$this->query('button:Cancel')->one()->click();
		COverlayDialogElement::ensureNotPresent();

		// Check the result in DB.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));

		// Open form to check changes was not saved.
		$this->query('link', $this->name)->one()->waitUntilClickable()->click();
		$form->invalidate();
		$form->checkValue(['Name' => $this->name]);

		// Check that 4th period exist.
		$this->assertTableHasData([['Period type' => 'Monthly']], $this->periods_table);
		$this->query('button:Cancel')->one()->click();
		COverlayDialogElement::ensureNotPresent();
	}

	/**
	 * Test update by changing maintenance period and type.
	 *
	 * @depends testFormMaintenance_Create
	 */
	public function testFormMaintenance_Update() {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', $this->name)->one()->waitUntilClickable()->click();
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();

		// Change maintenance type.
		$form->fill(['Maintenance type' => 'No data collection']);

		// Remove "One time only".
		$table = $this->query($this->periods_table)->asTable()->one();
		$table->findRow('Period type', 'One time only')->getColumn('Actions')->query('button:Remove')->one()->click()->waitUntilNotvisible();

		$periods = [
			[
				'schedule' => 'Weekly',
				'fields' => [
					'Wednesday' => true,
					'Friday' => true
				],
				'result' => [['Period type' => 'Weekly', 'Schedule' => 'At 00:00 Monday, Wednesday, Friday, Sunday of every 1 week']]
			],
			[
				'schedule' => 'Monthly',
				'fields' => [
					'Date' => 'Day of week',
					'June' => true,
					'September' => true
				],
				'result' => [['Period type' => 'Monthly', 'Schedule' => 'At 00:00 on first Wednesday of every January, June, September, November']]
			]
		];
		foreach ($periods as $period) {
			$table->findRow('Period type', $period['schedule'])->getColumn('Actions')->query('button:Edit')->one()->click();
			$period_overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last()->asForm();
			$period_overlay->fill($period['fields']);

			if ($period['schedule'] === 'Monthly') {
				$this->query('id:monthly_days_4')->waitUntilPresent()->asCheckbox()->one()->check();
			}

			$period_overlay->submit();
			$period_overlay->waitUntilNotVisible();
			$this->assertTableHasData($period['result'], $this->periods_table);
		}

		// Check the results in frontend.
		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Maintenance period updated');
		$this->assertTableHasData([['Name' => $this->name, 'Type' => 'No data collection']]);

		// Check the results in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($this->name)));
	}

	public function testFormMaintenance_UpdateTags() {
		$maintenance = 'Maintenance for update (data collection)';
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', $maintenance)->one()->waitUntilClickable()->click();
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$form->fill(['id:tags_evaltype' => 'And/Or']);

		// Update tags.
		$tag = 'Tag';
		$tags = [
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 0,
				'tag' => 'Tag',
				'value' => 'A1'
			],
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 1,
				'tag' => 'Tag',
				'value' => 'B1'
			]
		];
		$this->query('id:tags')->asMultifieldTable()->one()->fill($tags);
		$this->query('xpath://label[@for="tags_0_operator_1"]')->one()->click();
		$this->query('xpath://label[@for="tags_1_operator_0"]')->one()->click();

		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Maintenance period updated');

		$this->assertEquals(2, CDBHelper::getCount('SELECT NULL FROM maintenance_tag WHERE tag='.zbx_dbstr($tag)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenance_tag WHERE value=\'A1\' AND operator=0'));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenance_tag WHERE value=\'B1\' AND operator=2'));
	}

	/**
	 * Test cloning of maintenance.
	 *
	 * @depends testFormMaintenance_Create
	 */
	public function testFormMaintenance_Clone() {
		$suffix = ' (clone)';
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', $this->name)->one()->waitUntilClickable()->click();
		COverlayDialogElement::find()->waitUntilReady();

		// Clone maintenance, rename the clone and save it.
		$this->query('button:Clone')->one()->click()->waitUntilNotVisible();
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$form->fill(['Name' => $this->name.$suffix]);
		$form->submit();
		COverlayDialogElement::ensureNotPresent();

		// Check the result in frontend.
		$this->assertMessage(TEST_GOOD, 'Maintenance period created');
		$this->assertTableHasData([['Name' => $this->name], ['Name' => $this->name.$suffix]]);

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($this->name)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($this->name.$suffix)));
	}

	/**
	 * Test deleting of maintenance.
	 *
	 * @depends testFormMaintenance_Create
	 */
	public function testFormMaintenance_Delete() {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', $this->name)->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		// Delete a maintenance and check the result in frontend.
		$dialog->getFooter()->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		COverlayDialogElement::ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Maintenance period deleted');

		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($this->name)));
	}
}
