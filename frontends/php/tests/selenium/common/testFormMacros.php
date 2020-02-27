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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../traits/MacrosTrait.php';

/**
 * Base class for Macros tests.
 */
abstract class testFormMacros extends CWebTest {

	use MacrosTrait;

	const SQL_HOSTS = 'SELECT * FROM hosts ORDER BY hostid';

	public static function getHash() {
		return CDBHelper::getHash(self::SQL_HOSTS);
	}

	public static function getCreateCommonMacrosData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'Name' => 'With Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$1234}',
							'value' => '!@#$%^&*()_+/*',
							'description' => '!@#$%^&*()_+/*',
						],
						[
							'macro' => '{$MACRO1}',
							'value' => 'Value_1',
							'description' => 'Test macro Description 1'
						],
						[
							'macro' => '{$MACRO3}',
							'value' => '',
							'description' => '',
						],
						[
							'macro' => '{$MACRO4}',
							'value' => 'value',
							'description' => '',
						],
						[
							'macro' => '{$MACRO5}',
							'value' => '',
							'description' => 'DESCRIPTION',
						],
						[
							'macro' => '{$MACRO6}',
							'value' => 'Значение',
							'description' => 'Описание',
						],
						[
							'macro' => '{$MACRO:A}',
							'value' => '{$MACRO:A}',
							'description' => '{$MACRO:A}',
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Without dollar in Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{MACRO}',
						]
					],
					'error_details' => 'Invalid macro "{MACRO}": incorrect syntax near "MACRO}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With empty Macro',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '',
							'value' => 'Macro_Value',
							'description' => 'Macro Description'
						]
					],
					'error_details' => 'Invalid macro "": macro is empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'macro' => '{$MACRO}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error_details' => 'Macro "{$MACRO}" is not unique.'
				]
			]
		];
	}

	/**
	 * Test creating of host or template with Macros.
	 */
	protected function checkCreate($data, $host_type = 'host') {
		$this->page->login()->open($host_type.'s.php?form=create');
		$form = $this->query('name:'.$host_type.'sForm')->waitUntilPresent()->asForm()->one();

		$form->fill([
			ucfirst($host_type).' name' => $data['Name'],
			'Groups' => 'Zabbix servers'
		]);

		$this->checkMacros(' added', $data['Name'], $host_type, $data, 'Cannot add ');
	}

	public static function getUpdateCommonMacrosData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$UPDATED_MACRO1}',
							'value' => 'updated value1',
							'description' => 'updated description 1',
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO2}',
							'value' => 'Updated value 2',
							'description' => 'Updated description 2',
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$UPDATED_MACRO1}',
							'value' => '',
							'description' => '',
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO2}',
							'value' => 'Updated Value 2',
							'description' => '',
						],
						[
							'macro' => '{$UPDATED_MACRO3}',
							'value' => '',
							'description' => 'Updated Description 3',
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO:A}',
							'value' => '{$MACRO:B}',
							'description' => '{$MACRO:C}',
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO_1}',
							'value' => '',
							'description' => 'DESCRIPTION',
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'macro' => '{$UPDATED_MACRO_2}',
							'value' => 'Значение',
							'description' => 'Описание',
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Without dollar in Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{MACRO}',
						]
					],
					'error_details' => 'Invalid macro "{MACRO}": incorrect syntax near "MACRO}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With empty Macro',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '',
							'value' => 'Macro_Value',
							'description' => 'Macro Description'
						]
					],
					'error_details' => 'Invalid macro "": macro is empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$MACRO}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error_details' => 'Macro "{$MACRO}" is not unique.'
				]
			]
		];
	}

	/**
	 * Test updating of host or template with Macros.
	 */
	protected function checkUpdate($data, $hostname, $host_type = 'host') {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($hostname));

		$this->page->login()->open($host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0');
		$this->checkMacros(' updated', $hostname, $host_type, $data, 'Cannot update ');
	}

	/**
	 * Test removing Macros from host or template.
	 */
	protected function checkRemove($hostname, $host_type = 'host') {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($hostname));

		$this->page->login()->open($host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0');
		$form = $this->query('name:'.$host_type.'sForm')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Macros');
		$this->removeMacros();
		$form->submit();

		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals(ucfirst($host_type).' updated', $message->getTitle());
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($hostname)));
		// Check the results in form.
		$this->checkMacrosFields($host_type, null, $hostname);
	}

	/**
	 * Test changing and resetting global macro on host or template.
	 */
	protected function checkChangeRemoveInheritedMacro($host_type = 'host') {
		$host = [
			ucfirst($host_type).' name' => 'With edited global macro',
			'Groups' => 'Zabbix servers'
		];

		$this->page->login()->open($host_type.'s.php?form=create');
		$form = $this->query('name:'.$host_type.'sForm')->waitUntilPresent()->asForm()->one();
		$form->fill($host);

		$form->selectTab('Macros');

		// Check inherited macros before editing.
		$this->query('id:show_inherited_macros')->waitUntilPresent()
			->asSegmentedRadio()->one()->fill('Inherited and '.$host_type.' macros');
		$this->checkInheritedGlobalMacros();

		$edited_macros = [
			[
				'macro' => '{$1}',
				'value' => 'New updated Numeric macro 1',
				'description' => 'New updated Test description 2'
			]
		];

		$count = count($edited_macros);
		// Change macro.
		for ($i = 0; $i < $count; $i += 1) {
			$this->query('id:macros_'.$i.'_change')->one()->click();
			$this->query('id:macros_'.$i.'_value')->one()->fill($edited_macros[$i]['value']);
			$this->query('id:macros_'.$i.'_description')->one()->fill($edited_macros[$i]['description']);
		}

		$form->submit();

		// Check saved edited macros in host/template form.
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($host[ucfirst($host_type).' name']));
		$this->page->open($host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0');
		$form->selectTab('Macros');
		$this->assertMacros($edited_macros);

		// Remove edited macro and reset to global.
		$this->query('id:show_inherited_macros')->waitUntilPresent()
			->asSegmentedRadio()->one()->fill('Inherited and '.$host_type.' macros');

		for ($i = 0; $i < $count; $i += 1) {
			$this->query('id:macros_'.$i.'_change')->waitUntilVisible()->one()->click();
		}

		$form->submit();

		$this->page->open($host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0');
		$form->selectTab('Macros');

		$this->assertMacros();

		// Check inherited macros again after remove.
		$this->query('id:show_inherited_macros')->waitUntilPresent()
			->asSegmentedRadio()->one()->fill('Inherited and '.$host_type.' macros');
		$this->checkInheritedGlobalMacros();
	}

	/**
	 * Check adding and saving macros in host or template form.
	 */
	private function checkMacros($action, $name, $host_type, $data, $error_message) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = $this->getHash();
		}

		$form = $this->query('name:'.$host_type.'sForm')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Macros');
		$this->fillMacros($data['macros']);
		$form->submit();

		$message = CMessageElement::find()->one();
		switch ($data['expected']) {
			case TEST_GOOD:
				$this->assertTrue($message->isGood());
				$this->assertEquals(ucfirst($host_type).$action, $message->getTitle());
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($name)));
				// Check the results in form.
				$this->checkMacrosFields($host_type, $data, $name);
				break;
			case TEST_BAD:
				$this->assertTrue($message->isBad());
				$this->assertEquals($error_message.$host_type, $message->getTitle());
				$this->assertTrue($message->hasLine($data['error_details']));
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL_HOSTS));
				break;
		}
	}

	/**
	 * Checking saved macros in host or template form.
	 */
	private function checkMacrosFields($host_type, $data = null, $name) {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($name));

		$this->page->open($host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0');
		$form = $this->query('id:'.$host_type.'sForm')->waitUntilPresent()->asForm()->one();
		$form->selectTab('Macros');

		$this->assertMacros(($data !== null) ? $data['macros'] : []);
	}

	/**
	 * Check host/template inherited macros in form matching with global macros in DB,
	 * if there is no any host/template defined macros.
	 */
	public function checkInheritedGlobalMacros() {
		// Create two macros arrays: from DB and from Frontend form.
		$macros = [
			'database' => CDBHelper::getAll('SELECT macro, value, description FROM globalmacro'),
			'frontend' => []
		];

		// Write macros rows from Frontend to array.
		$table = $this->query('id:tbl_macros')->waitUntilVisible()->asTable()->one();
		$count = $table->getRows()->count() - 1;
		for ($i = 0; $i < $count; $i += 2) {
			$macro = [];
			$row = $table->getRow($i);
			$macro['macro'] = $row->query('xpath:./td[1]/textarea')->one()->getValue();
			$macro['value'] = $row->query('xpath:./td[3]/textarea')->one()->getValue();
			$macro['description'] = $table->getRow($i + 1)->query('tag:textarea')->one()->getValue();

			$macros['frontend'][] = $macro;
		}

		// Sort arrays by Macros.
		foreach ($macros as &$array) {
			usort($array, function ($a, $b) {
				return strcmp($a['macro'], $b['macro']);
			});
		}
		unset($array);

		// Compare macros from DB with macros from Frontend.
		$this->assertEquals($macros['database'], $macros['frontend']);
	}
}
