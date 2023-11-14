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
require_once dirname(__FILE__).'/../behaviors/CPreprocessingBehavior.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup items
 */
class testItemTypeSelection extends CWebTest {

	/**
	 * Attach MessageBehavior and PreprocessingBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CPreprocessingBehavior::class
		];
	}

	const HOSTID = 40001;
	const LLDID = 90001;
	const PROTOTYPE = true;

	public static function getItemData() {
		return [
			[
				[
					'fields' => [
						'Name' => 'Character',
						'Key' => 'agent.hostmetadata[{#KEY}]'
					],
					'type' => 'Character'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Numeric unsigned',
						'Key' => 'agent.ping[{#KEY}]'
					],
					'type' => 'Numeric (unsigned)'
				]
			],
			[
				[

					'fields' => [
						'Name' => 'Numeric float',
						'Key' => 'net.udp.service.perf[service,{#KEY}]'
					],
					'type' => 'Numeric (float)'
				]
			],
			[
				[
					'fields' => [
						'Type' => 'Zabbix agent (active)',
						'Name' => 'Log',
						'Key' => 'eventlog[name,{#KEY}]'
					],
					'type' => 'Log'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Log',
						'Key' => 'eventlog[agent,{#KEY}]'
					],
					'type' => 'Numeric (unsigned)'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Text',
						'Key' => 'net.if.discovery[{#KEY}]'
					],
					'type' => 'Text'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Custom key',
						'Key' => 'custom.key[{#KEY}]'
					],
					'type' => 'Numeric (unsigned)'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Custom key 2',
						'Key' => 'custom.key2[{#KEY}]',
						'Type of information' => 'Text'
					],
					'type' => 'Text',
					'hint' => false
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Test Info Hint',
						'Key' => 'net.if.list[{#KEY}]',
						'Type of information' => 'Log'
					],
					'hint' => true,
					'hint_text' => 'This type of information may not match the key.',
					'type' => 'Log'
				]
			]
		];
	}

	/**
	 * @dataProvider getItemData
	 */
	public function testItemTypeSelection_Item($data) {
		$this->checkItemTypeSelection($data);
	}

	/**
	 * @dataProvider getItemData
	 */
	public function testItemTypeSelection_ItemPrototype($data) {
		$this->checkItemTypeSelection($data, self::PROTOTYPE);
	}

	/**
	 * Function for checking automatic type selection for items and item prototypes.
	 *
	 * @param array      $data         data provider
	 * @param boolean    $prototype    true if it is item prototype, false if item
	 */
	public function checkItemTypeSelection($data, $prototype = false) {
		$link = ($prototype)
			? 'zabbix.php?action=item.prototype.list&parent_discoveryid='.self::LLDID.'&context=host'
			: 'zabbix.php?action=item.list&context=host&filter_set=1&filter_hostids%5B0%5D='.self::HOSTID;

		$this->page->login()->open($link)->waitUntilReady();
		$this->query('button:'.($prototype ? 'Create item prototype' : 'Create item'))->one()->click();
		$form = COverlayDialogElement::find()->one()->waitUntilReady()->asForm();

		// Make names unique for items and prototypes.
		$data['fields']['Name'] = $data['fields']['Name'].microtime();
		$form->fill($data['fields']);

		// Check hintbox text.
		if (CTestArrayHelper::get($data, 'hint')) {
			$icon = $this->query('class:js-hint')->waitUntilClickable()->one();
			$icon->click();
			$this->assertEquals($data['hint_text'],
					$form->query('xpath://div[@class="hintbox-wrap"]')->waitUntilPresent()->one()->getText()
			);
		}
		elseif (CTestArrayHelper::get($data, 'hint') === false) {
			$this->assertFalse($form->query('class:js-hint')->waitUntilPresent()->one()->isVisible());
		}
		else {
			// Check that type changed to automatic.
			$this->assertEquals($data['type'], $form->getField('Type of information')->getValue());
		}

		$form->submit();
		$this->assertMessage(TEST_GOOD, ($prototype) ? 'Item prototype added' : 'Item added');

		// Check saved item form in DB and Frontend.
		$id = CDBHelper::getValue('SELECT itemid FROM items'.
				' WHERE key_ ='.zbx_dbstr($data['fields']['Key']).
					' AND name ='.zbx_dbstr($data['fields']['Name'])
		);

		$this->page->open($link)->waitUntilReady();
		$this->query('link:'.$data['fields']['Name'])->one()->click();
		$form->invalidate();
		$form->checkValue($data['fields']);

		if (CTestArrayHelper::get($data, 'hint')) {
			// Check that info disappears when preprocessing step is added.
			$form->selectTab('Preprocessing');
			$this->addPreprocessingSteps([['type' => 'Regular expression', 'parameter_1' => 'pattern', 'parameter_2' => 'output']]);
			$this->assertEquals($data['fields']['Type of information'], $form->getField('Type of information')->getValue());

			$form->selectTab(($prototype) ? 'Item prototype' : 'Item');
			$this->assertTrue($icon->isStalled());
			$form->submit();
			$this->assertMessage(TEST_GOOD, ($prototype) ? 'Item prototype updated' : 'Item updated');

			// Check that custom type remained in saved form.
			$this->page->open($link)->waitUntilReady();
			$this->query('link', $data['fields']['Name'])->one()->click();
			$form->invalidate();
			$this->assertEquals($data['fields']['Type of information'], $form->getField('Type of information')->getValue());

			// Check that type is the same when preprocessing is cleared.
			$form->selectTab('Preprocessing');
			$form->query('xpath:.//li[@data-step="0"]')->waitUntilPresent()->one()->query('button:Remove')
					->waitUntilClickable()->one()->click();

			$form->selectTab(($prototype) ? 'Item prototype' : 'Item');
			$this->assertEquals($data['type'], $form->getField('Type of information')->getValue());

			// Check saved form.
			$form->submit();
			$this->assertMessage(TEST_GOOD, ($prototype) ? 'Item prototype updated' : 'Item updated');
			$this->page->open($link)->waitUntilReady();
			$this->query('link', $data['fields']['Name'])->one()->click();
			$form->invalidate();
		}

		$this->assertEquals($data['type'], $form->getField('Type of information')->getValue());
	}
}
