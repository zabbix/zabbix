<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

require_once dirname(__FILE__).'/../traits/MacrosTrait.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

/**
 * Base class for Macros tests.
 */
abstract class testFormMacros extends CLegacyWebTest {

	use MacrosTrait;

	const SQL_HOSTS = 'SELECT * FROM hosts ORDER BY hostid';

	public $macro_resolve;
	public $macro_resolve_hostid;

	public $vault_object;
	public $vault_error_field;
	public $vault_macro_index;
	public $update_vault_macro;

	public $revert_macro_1;
	public $revert_macro_2;
	public $revert_macro_object;
	/**
	 * Attach Behaviors to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	public static function getHash() {
		return CDBHelper::getHash(self::SQL_HOSTS);
	}

	public static function getCreateMacrosData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'Name' => 'With MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$1234}',
							'value' => '!@#$%^&*()_+/*',
							'description' => '!@#$%^&*()_+/*'
						],
						[
							'macro' => '{$M:regex:^[0-9a-z]}',
							'value' => 'regex',
							'description' => 'context macro with regex'
						],
						[
							'macro' => '{$MACRO1}',
							'value' => 'Value_1',
							'description' => 'Test macro Description 1'
						],
						[
							'macro' => '{$MACRO3}',
							'value' => '',
							'description' => ''
						],
						[
							'macro' => '{$MACRO4}',
							'value' => 'value',
							'description' => ''
						],
						[
							'macro' => '{$MACRO5}',
							'value' => '',
							'description' => 'DESCRIPTION'
						],
						[
							'macro' => '{$MACRO6}',
							'value' => 'Значение',
							'description' => 'Описание'
						],
						[
							'macro' => '{$MACRO:A}',
							'value' => '{$MACRO:A}',
							'description' => '{$MACRO:A}'
						],
						[
							'macro' => '{$MACRO:regex:"^[0-9].*$"}',
							'value' => '',
							'description' => ''
						]
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'Name' => 'With lowercase MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$lowercase}',
							'value' => 'lowercase_value',
							'description' => 'UPPERCASE DESCRIPTION'
						]
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'Without dollar in MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{MACRO}'
						]
					],
					'error' => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "MACRO}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With two dollars in MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$$MACRO}'
						]
					],
					'error' => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "$MACRO}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With wrong symbols in MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MAC%^}'
						]
					],
					'error' => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "%^}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With LLD macro in MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{#LLD_MACRO}'
						]
					],
					'error' => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "#LLD_MACRO}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With empty MACRO',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '',
							'value' => 'Macro_Value',
							'description' => 'Macro Description'
						]
					],
					'error' => 'Invalid parameter "/1/macros/1/macro": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated MACROS',
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
					'error' => 'Invalid parameter "/1/macros/2": value (macro)=({$MACRO}) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated regex in MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO:regex:"^[0-9].*$"}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'macro' => '{$MACRO:regex:"^[0-9].*$"}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error'  => 'Invalid parameter "/1/macros/2": value (macro)=({$MACRO:regex:"^[0-9].*$"}) already exists.'
				]
			]
		];
	}

	public static function getUpdateMacrosData() {
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
							'description' => 'updated description 1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO2}',
							'value' => 'Updated value 2',
							'description' => 'Updated description 2'
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
							'description' => ''
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO2}',
							'value' => 'Updated Value 2',
							'description' => ''
						],
						[
							'macro' => '{$UPDATED_MACRO3}',
							'value' => '',
							'description' => 'Updated Description 3'
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
							'description' => '{$MACRO:C}'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$UPDATED_MACRO_1}',
							'value' => '',
							'description' => 'DESCRIPTION'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'macro' => '{$UPDATED_MACRO_2}',
							'value' => 'Значение',
							'description' => 'Описание'
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
							'macro' => '{$lowercase}',
							'value' => 'lowercase_value',
							'description' => 'UPPERCASE DESCRIPTION'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$MACRO:regex:"^[a-z]"}',
							'value' => 'regex',
							'description' => ''
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 2,
							'macro' => '{$MACRO:regex:^[0-9a-z]}',
							'value' => '',
							'description' => 'DESCRIPTION'
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
							'macro' => '{MACRO}'
						]
					],
					'error' => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "MACRO}".'
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
					'error'  => 'Invalid parameter "/1/macros/1/macro": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With two dollars in MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$$MACRO}'
						]
					],
					'error' => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "$MACRO}'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With wrong symbols in MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MAC%^}'
						]
					],
					'error' => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "%^}".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With LLD macro in MACROS',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{#LLD_MACRO}'
						]
					],
					'error'  => 'Invalid parameter "/1/macros/1/macro": incorrect syntax near "#LLD_MACRO}".'
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
					'error'  => 'Invalid parameter "/1/macros/2": value (macro)=({$MACRO}) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated regex Macros',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$M:regex:"[a-z]"}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$M:regex:"[a-z]"}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error'  => 'Invalid parameter "/1/macros/2": value (macro)=({$M:regex:"[a-z]"}) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'Name' => 'With repeated regex Macros and quotes',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$MACRO:regex:"^[0-9].*$"}',
							'value' => 'Macro_Value_1',
							'description' => 'Macro Description_1'
						],
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 1,
							'macro' => '{$MACRO:regex:^[0-9].*$}',
							'value' => 'Macro_Value_2',
							'description' => 'Macro Description_2'
						]
					],
					'error'  => 'Invalid parameter "/1/macros/2": value (macro)=({$MACRO:regex:^[0-9].*$}) already exists.'
				]
			]
		];
	}

	/**
	 *  Check adding and saving macros in host, host prototype or template form.
	 *
	 * @param array	     $data			given data provider
	 * @param string     $host_type		string defining is it host, template or host prototype
	 * @param string     $name			name of host where changes are made
	 * @param boolean    $update		true if update, false if create
	 * @param boolean    $is_prototype	defines is it prototype or not
	 * @param int        $lld_id	    points to LLD rule id where host prototype belongs
	 */
	public function checkMacros($data, $host_type, $name = null, $update = false, $is_prototype = false, $lld_id = null) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = $this->getHash();
		}

		$form_type = ($host_type === 'host prototype') ? 'hostPrototype' : $host_type.'s';
		if ($update) {
			if ($host_type === 'host') {
				$this->page->login()->open('zabbix.php?action=host.view&filter_selected=0&filter_reset=1')->waitUntilReady();
				$column = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $name)->getColumn('Name');
				$column->query('link', $name)->asPopupButton()->one()->select('Configuration');
				$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
			}
			else {
				$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($name));

				$this->page->login()->open(
					$is_prototype
						? 'host_prototypes.php?form=update&context=host&parent_discoveryid='.$lld_id.'&hostid='.$id
						: $host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0'
				);
				$form = $this->query('name:'.$form_type.'Form')->waitUntilPresent()->asForm()->one();
			}
		}
		else {
			if ($host_type === 'host') {
				$this->page->login()->open('zabbix.php?action=host.view&filter_selected=0&filter_reset=1')->waitUntilReady();
				$this->query('button:Create host')->one()->waitUntilClickable()->click();
				$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
			}
			else {
				$this->page->login()->open(
					$is_prototype
						? 'host_prototypes.php?form=create&context=host&parent_discoveryid='.$lld_id
						: $host_type.'s.php?form=create'
				);
				$form = $this->query('name:'.$form_type.'Form')->waitUntilPresent()->asForm()->one();
			}

			$name = $is_prototype ? $data['Name'].' {#KEY}' : $data['Name'];
			$form->fill([(($host_type === 'template') ? 'Template name' : 'Host name') => $name]);
			$form->fill(['Groups' => 'Zabbix servers']);
		}

		$form->selectTab('Macros');
		$this->fillMacros($data['macros']);
		$form->submit();

		$object = $is_prototype ? 'host prototype' : $host_type;
		switch ($data['expected']) {
			case TEST_GOOD:
				if ($host_type === 'host') {
					COverlayDialogElement::ensureNotPresent();
				}
				$this->assertMessage(TEST_GOOD, $update ? ucfirst($object).' updated' : ucfirst($object).' added');
				$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($name)));
				// Check the results in form.
				$this->checkMacrosFields($name, $is_prototype, $lld_id, $form_type, $host_type, $data);
				break;

			case TEST_BAD:
				$this->assertMessage(TEST_BAD, ($update ? 'Cannot update '.$object : 'Cannot add '.$object), $data['error']);
				// Check that DB hash is not changed.
				$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL_HOSTS));

				if ($host_type === 'host') {
					COverlayDialogElement::find()->one()->close();
					COverlayDialogElement::ensureNotPresent();
				}
				break;
		}
	}

	/**
	 * Test removing Macros from host, host prototype or template.
	 *
	 * @param string $name			name of host where changes are made
	 * @param string $host_type		string defining is it host, template or host prototype
	 * @param boolean $is_prototype	defines is it prototype or not
	 * @param int $lld_id			points to LLD rule id where host prototype belongs
	 */
	protected function checkRemoveAll($name, $host_type, $is_prototype = false, $lld_id = null) {
		$form_type = ($host_type === 'host prototype') ? 'hostPrototype' : $host_type.'s';

		if ($host_type === 'host') {
			$this->page->login()->open('zabbix.php?action=host.view')->waitUntilReady();
			$column = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $name)->getColumn('Name');
			$column->query('link', $name)->asPopupButton()->one()->select('Configuration');
			$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		}
		else {
			$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($name));

			$this->page->login()->open(
				$is_prototype
					? 'host_prototypes.php?form=update&context=host&parent_discoveryid='.$lld_id.'&hostid='.$id
					: $host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0'
			);

			$form = $this->query('name:'.$form_type.'Form')->waitUntilPresent()->asForm()->one();
		}

		$form->selectTab('Macros');
		$this->removeAllMacros();
		$form->submit();
		if ($host_type === 'host') {
			COverlayDialogElement::ensureNotPresent();
		}

		$this->assertMessage(TEST_GOOD, ($is_prototype ? 'Host prototype' : ucfirst($host_type)).' updated');
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($name)));
		// Check the results in form.
		$this->checkMacrosFields($name, $is_prototype, $lld_id, $form_type, $host_type, null);
	}

	public static function getCheckInheritedMacrosData() {
		return [
			[
				[
					'case' => 'Add new macro',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$NEW_CHECK_MACRO1}',
							'value' => 'new check macro 1',
							'description' => 'new check macro description 1'
						],
						[
							'macro' => '{$NEW_CHECK_MACRO2}',
							'value' => 'new check macro 2',
							'description' => 'new check macro description 2'
						]
					]
				]
			],
			[
				[
					'case' => 'Redefine global macro on Host',
					'macros' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'macro' => '{$SNMP_COMMUNITY}',
							'value' => 'new redefined value 1',
							'description' => 'new redifined description 1'
						],
						[
							'macro' => '{$_}',
							'value' => 'new redifined value 2',
							'description' => 'new redifined description 2'
						]
					]
				]
			],
			[
				[
					'case' => 'Redefine global macro in Inherited',
					'macros' => [
						[
							'macro' => '{$DEFAULT_DELAY}',
							'value' => '100500',
							'description' => 'new delay description'
						],
						[
							'macro' => '{$LOCALIP}',
							'value' => '100.200.3.4',
							'description' => 'new local ip description'
						]
					]
				]
			]
		];
	}

	/**
	 * Test changing and resetting global macro on host, prototype or template.
	 *
	 * @param array  $data		    given data provider
	 * @param string $host_type		string defining is it host, template or host prototype
	 * @param boolean $is_prototype	defines is it prototype or not
	 * @param int $lld_id			points to LLD rule id where host prototype belongs
	 */
	protected function checkChangeInheritedMacros($data, $host_type, $is_prototype = false, $lld_id = null) {
		$form_type = ($host_type === 'host prototype') ? 'hostPrototype' : $host_type.'s';

		if ($is_prototype) {
			$this->page->login()->open('host_prototypes.php?form=create&context=host&parent_discoveryid='.$lld_id);
			$form = $this->query('name:'.$form_type.'Form')->waitUntilPresent()->asForm()->one();
			$name = 'Host prototype with edited global {#MACRO} '.time();
			$form->fill(['Host name' => $name]);
			$form->fill(['Groups' => 'Zabbix servers']);
		}
		else {
			if ($host_type === 'host') {
				$this->page->login()->open('zabbix.php?action=host.view')->waitUntilReady();
				$this->query('button:Create host')->one()->waitUntilClickable()->click();
				$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
			}
			else {
				$this->page->login()->open($host_type.'s.php?form=create');
				$form = $this->query('name:'.$form_type.'Form')->waitUntilPresent()->asForm()->one();
			}

			$name = $host_type.' with edited global macro '.time();
			$form->fill([
				($host_type === 'template') ? 'Template name' : 'Host name' => $name,
				'Groups' => 'Zabbix servers'
			]);
		}
		$form->selectTab('Macros');
		$radio_switcher = $this->query('id:show_inherited_macros')->asSegmentedRadio()->waitUntilPresent()->one();

		switch ($data['case']) {
			case 'Add new macro':
				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->page->waitUntilReady();
				$global_macros = $this->getGlobalMacrosAndSwitch($radio_switcher, $host_type);
				$this->fillMacros($data['macros']);

				// Get all object's macros.
				$hostmacros = $this->getMacros();

				// By default macro type is Text, which refers to 0.
				foreach ($hostmacros as &$macro) {
					$macro['type'] = 0;
				}
				unset($macro);

				// Go to global macros.
				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->page->waitUntilReady();

				// Check that host macro is editable.
				foreach ($data['macros'] as $data_macro) {
					$this->assertTrue($this->query('xpath://textarea[text()='.CXPathHelper::escapeQuotes($data_macro['macro']).
							']')->waitUntilPresent()->one()->isEnabled()
					);

					$this->assertTrue($this->getValueField($data_macro['macro'])->isEnabled());

					// Fill macro description by new description using found macro index.
					$this->assertTrue($this->query('id:macros_'.$this->getMacroIndex($data_macro['macro']).
							'_description')->one()->isEnabled()
					);
				}

				// Add newly added macros to global macros array.
				$expected_global_macros = array_merge($global_macros, $hostmacros);

				// Compare new macros table from global and inherited macros page with expected result.
				$this->assertEquals($this->sortMacros($expected_global_macros), $this->getGlobalMacrosFrotendTable());
				break;

			case 'Redefine global macro on Host':
				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->page->waitUntilReady();
				$global_macros = $this->getGlobalMacrosAndSwitch($radio_switcher, $host_type);
				$this->fillMacros($data['macros']);

				// Redefine macro values in expected Global macros.
				foreach ($data['macros'] as $data_macro) {
					foreach ($global_macros as &$global_macro) {
						if ($global_macro['macro'] === $data_macro['macro']) {
							$global_macro['value'] = $data_macro['value'];
							$global_macro['description'] = $data_macro['description'];
						}
					}
					unset($global_macro);
				}

				// Compare new macros table from global and inherited macros page with expected result.
				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->page->waitUntilReady();

				// Check enabled/disabled fields.
				foreach ($data['macros'] as $data_macro) {
					$this->assertFalse($this->query('xpath://textarea[text()='.CXPathHelper::escapeQuotes($data_macro['macro']).']')
							->waitUntilPresent()->one()->isEnabled()
					);

					$this->assertTrue($this->getValueField($data_macro['macro'])->isEnabled());

					// Fill macro description by new description using found macro index.
					$this->assertTrue($this->query('id:macros_'.$this->getMacroIndex($data_macro['macro']).
							'_description')->one()->isEnabled()
					);
					$this->assertTrue($this->query('xpath://textarea[text()='.
							CXPathHelper::escapeQuotes($data_macro['macro']).']/../..//button[text()="Remove"]')->exists()
					);
				}

				$this->assertEquals($global_macros, $this->getGlobalMacrosFrotendTable());
				break;

			case 'Redefine global macro in Inherited':
				// Get all object's macros.
				$hostmacros = $this->getMacros();

				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->page->waitUntilReady();

				foreach ($data['macros'] as $data_macro) {
					// Find necessary row by macro name and click Change button.
					$this->query('xpath://textarea[text()='.CXPathHelper::escapeQuotes($data_macro['macro']).
							']/../..//button[text()="Change"]')->waitUntilPresent()->one()->click();

					// Fill macro value by new value.
					$this->getValueField($data_macro['macro'])->fill($data_macro['value']);

					// Fill macro description by new description using found macro index.
					$this->query('id:macros_'.$this->getMacroIndex($data_macro['macro']).'_description')->one()
							->fill($data_macro['description']
					);
				}

				// Get new Global macro table.
				$new_global_macros = $this->getGlobalMacrosFrotendTable();

				if ($host_type === 'host') {
					CElementQuery::getDriver()->executeScript('arguments[0].scrollTo(0, 0)',
							[COverlayDialogElement::find()->one()->getContent()]
					);
				}
				$radio_switcher->fill(ucfirst($host_type).' macros');
				$this->page->waitUntilReady();
				$expected_hostmacros = ($hostmacros[0]['macro'] !== '')
					? array_merge($data['macros'], $hostmacros)
					: $data['macros'];

				// Compare host macros table with expected result.
				$this->assertEquals($this->sortMacros($expected_hostmacros), $this->getMacros());
				break;
		}

		$form->submit();
		if ($host_type === 'host') {
			COverlayDialogElement::ensureNotPresent();
		}
		$this->assertMessage(TEST_GOOD);
		// Check saved edited macros in host/template form.
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($name));

		if ($host_type === 'host') {
			$this->page->login()->open('zabbix.php?action=host.view')->waitUntilReady();
			$column = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $name)
					->getColumn('Name');
			$column->query('link', $name)->asPopupButton()->one()->select('Configuration');
			$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		}
		else {
			$this->page->open(
				$is_prototype
					? 'host_prototypes.php?form=update&context=host&parent_discoveryid='.$lld_id.'&hostid='.$id
					: $host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0'
			);
			$form->invalidate();
		}

		$form->selectTab('Macros');

		// Check all macros after form saving in frontend and db.
		switch ($data['case']) {
			case 'Add new macro':
				// Compare new macros table from host macros page with expected result.
				$this->assertEquals($hostmacros, $this->getMacros(true));

				// Compare new macros table from global and inherited macros page with expected result.
				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->assertEquals($this->sortMacros($expected_global_macros), $this->getGlobalMacrosFrotendTable());

				// Check macros in FE with macros in DB.
				$this->checkInheritedGlobalMacros($hostmacros);
				break;

			case 'Redefine global macro on Host':
				foreach ($data['macros'] as &$data_macro) {
					unset($data_macro['action']);
					unset($data_macro['index']);
					$data_macro['type'] = 0;
				}
				unset($data_macro);

				// Compare new macros table from host macros page with expected result.
				$this->assertEquals($data['macros'], $this->getMacros(true));

				// Compare new macros table with db.
				$this->assertEquals($this->getMacros(true),
					$this->sortMacros(CDBHelper::getAll('SELECT macro, value, description, type'.
						' FROM hostmacro'.
						' WHERE hostid ='.$id)
					)
				);

				// Compare new macros table from global and inherited macros page with expected result.
				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->assertEquals($global_macros, $this->getGlobalMacrosFrotendTable());
				break;

			case 'Redefine global macro in Inherited':
				// Compare host macros table with expected result.
				$this->assertEquals($this->sortMacros($expected_hostmacros), $this->getMacros());

				// Compare new macros table with db.
				$this->assertEquals($this->getMacros(true),
						$this->sortMacros(CDBHelper::getAll('SELECT macro, value, description, type'.
							' FROM hostmacro'.
							' WHERE hostid ='.$id)
						)
				);
				// Compare new macros table from global and inherited macros page with expected result.
				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->assertEquals($new_global_macros, $this->getGlobalMacrosFrotendTable());
				break;
		}

		if ($host_type === 'host') {
			COverlayDialogElement::find()->one()->close();
			COverlayDialogElement::ensureNotPresent();
		}
	}

	public static function getRemoveInheritedMacrosData() {
		return [
			[
				[
					'case' => 'Remove macro from Host',
					'macros' => [
						[
							'macro' => '{$MACRO_FOR_DELETE_HOST1}'
						],
						[
							'macro' => '{$MACRO_FOR_DELETE_HOST2}'
						]
					]
				]
			],
			[
				[
					'case' => 'Remove macro from Inherited',
					'macros' => [
						[
							'macro' => '{$MACRO_FOR_DELETE_GLOBAL1}'
						],
						[
							'macro' => '{$MACRO_FOR_DELETE_GLOBAL2}'
						]
					]
				]
			],
			[
				[
					'case' => 'Remove redefined macro in Inherited',
					'macros' => [
						[
							'macro' => '{$SNMP_COMMUNITY}',
							'value' => 'public',
							'description' => ''
						]
					]
				]
			]
		];
	}

	/**
	 * Test removing and resetting global macro on host, prototype or template.
	 *
	 * @param array      $data		      given data provider
	 * @param string     $host_type	      string defining is it host, template or host prototype
	 * @param int        $id		      host's, prototype's or template's id
	 * @param boolean    $is_prototype    defines is it prototype or not
	 * @param int        $lld_id		  points to LLD rule id where host prototype belongs
	 * @param string     $name		      name of the host where macros are removed
	 */
	protected function checkRemoveInheritedMacros($data, $host_type, $id, $is_prototype = false,
			$lld_id = null, $name = null) {
		if ($host_type === 'host') {
			$this->page->login()->open('zabbix.php?action=host.view')->waitUntilReady();
			$column = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $name)
					->getColumn('Name');
			$column->query('link', $name)->asPopupButton()->one()->select('Configuration');
			$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		}
		else {
			$link = $is_prototype
				? 'host_prototypes.php?form=update&context=host&parent_discoveryid='.$lld_id.'&hostid='.$id
				: $host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0';

			$this->page->login()->open($link);

			$form_type = ($host_type === 'host prototype') ? 'hostPrototype' : $host_type.'s';
			$form = $this->query('name:'.$form_type.'Form')->waitUntilPresent()->asForm()->one();
		}

		$form->selectTab('Macros');
		$radio_switcher = $this->query('id:show_inherited_macros')->asSegmentedRadio()->waitUntilPresent()->one();

		switch ($data['case']) {
			case 'Remove macro from Host':
				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->page->waitUntilReady();
				$global_macros = $this->getGlobalMacrosAndSwitch($radio_switcher, $host_type);

				$this->removeMacro($data['macros']);
				$hostmacros = $this->getMacros(true);

				$expected_hostmacros = ($hostmacros === [])
					? [[ 'macro' => '', 'value' => '', 'description' => '']]
					: $hostmacros;

				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->page->waitUntilReady();

				foreach ($data['macros'] as $data_macro) {
					foreach ($global_macros as $i => &$global_macro) {
						if ($global_macro['macro'] === $data_macro['macro']) {
							unset($global_macros[$i]);
						}
					}
					unset($global_macro);
				}

				$this->assertEquals($this->sortMacros($global_macros), $this->getGlobalMacrosFrotendTable());
				break;

			case 'Remove macro from Inherited':
				// Get all object's macros.
				$hostmacros = $this->getMacros(true);

				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->page->waitUntilReady();
				$this->removeMacro($data['macros']);
				$global_macros = $this->getGlobalMacrosAndSwitch($radio_switcher, $host_type);

				foreach ($data['macros'] as $data_macro) {
					foreach ($hostmacros as $i => &$hostmacro) {
						if ($hostmacro['macro'] === $data_macro['macro']) {
							unset($hostmacros[$i]);
						}
					}
					unset($hostmacro);

					foreach ($global_macros as $i => &$global_macro) {
						if ($global_macro['macro'] === $data_macro['macro']) {
							unset($global_macros[$i]);
						}
					}
					unset($global_macro);
				}

				$expected_hostmacros = ($hostmacros === [])
					? [[ 'macro' => '', 'value' => '', 'description' => '']]
					: $hostmacros;

				// Compare host macros table with expected result.
				$this->assertEquals($this->sortMacros($expected_hostmacros), $this->getMacros(true));
				break;

			case 'Remove redefined macro in Inherited':
				// Get all object's macros before changes.
				$hostmacros = $this->getMacros(true);

				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->page->waitUntilReady();

				$this->removeMacro($data['macros']);

				if ($host_type === 'host') {
					CElementQuery::getDriver()->executeScript('arguments[0].scrollTo(0, 0)',
							[COverlayDialogElement::find()->one()->getContent()]
					);
				}
				$radio_switcher->fill(ucfirst($host_type).' macros');
				$this->page->waitUntilReady();

				// Delete reset macros from hostmacros array.
				foreach ($data['macros'] as $data_macro) {
					foreach ($hostmacros as $i => &$hostmacro) {
						if ($hostmacro['macro'] === $data_macro['macro']) {
							unset($hostmacros[$i]);
						}
					}
					unset($hostmacro);
				}

				$expected_hostmacros = ($hostmacros === [])
					? [[ 'macro' => '', 'value' => '', 'description' => '']]
					: $hostmacros;

				// Check that reset macros were deleted from hostmacros array.
				$this->assertEquals($this->sortMacros($expected_hostmacros), $this->getMacros(true));

				// Return to Global macros table and check fields and values there.
				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->page->waitUntilReady();

				// Check enabled/disabled fields and values.
				foreach ($data['macros'] as $data_macro) {
					$this->assertTrue($this->query('xpath://textarea[text()='.CXPathHelper::escapeQuotes($data_macro['macro']).
							']/../..//button[text()="Change"]')->exists()
					);

					// Check macro field disabled.
					$this->assertFalse($this->query('xpath://textarea[text()='.CXPathHelper::escapeQuotes($data_macro['macro']).']')
							->waitUntilPresent()->one()->isEnabled()
					);

					// Check macro value and disabled field.
					$this->assertFalse($this->query('xpath://textarea[text()='.CXPathHelper::escapeQuotes($data_macro['macro']).
							']/../..//div[contains(@class, "macro-value")]/textarea')->waitUntilPresent()->one()->isEnabled()
					);
					$this->assertEquals($data_macro['value'], $this->getValueField($data_macro['macro'])->getValue());

					// Check macro description and disabled field.
					$this->assertFalse($this->query('id:macros_'.$this->getMacroIndex($data_macro['macro']).'_description')
							->one()->isEnabled()
					);
					$this->assertEquals($data_macro['description'],	$this->query('id:macros_'.$this
							->getMacroIndex($data_macro['macro']).'_description')->one()->getValue()
					);
				}
				break;
		}

		$form->submit();
		if ($host_type === 'host') {
			COverlayDialogElement::ensureNotPresent();
		}
		$this->assertMessage(TEST_GOOD);

		if ($host_type === 'host') {
			$this->page->open('zabbix.php?action=host.view')->waitUntilReady();
			$column = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $name)
					->getColumn('Name');
			$column->query('link', $name)->asPopupButton()->one()->select('Configuration');
		}
		else {
			$this->page->open($link);
		}

		$form->invalidate();
		$form->selectTab('Macros');

		// Compare host macros table with expected result.
		$this->assertEquals($this->sortMacros($expected_hostmacros), $this->getMacros(true));

		// Compare new host macros table with db.
		$this->assertEquals($this->getMacros(true),
			$this->sortMacros(CDBHelper::getAll('SELECT macro, value, description, type'.
				' FROM hostmacro'.
				' WHERE hostid ='.$id)
			)
		);

		// Check all macros after form save.
		switch ($data['case']) {
			case 'Remove macro from Host':
			case 'Remove macro from Inherited':
				// Compare new macros table from global and inherited macros page with expected result.
				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->assertEquals($this->sortMacros($global_macros), $this->getGlobalMacrosFrotendTable());
				break;

			case 'Remove redefined macro in Inherited':
				// Check global macros.
				$radio_switcher->fill('Inherited and '.$host_type.' macros');
				$this->checkInheritedGlobalMacros($expected_hostmacros);
				break;
		}
		if ($host_type === 'host') {
			COverlayDialogElement::find()->one()->close();
			COverlayDialogElement::ensureNotPresent();
		}
	}

	/**
	 * Checking saved macros in host, host prototype or template form.
	 *
	 * @param string     $name			  name of host where changes are made
	 * @param boolean    $is_prototype    defines is it prototype or not
	 * @param int        $lld_id		  points to LLD rule id where host prototype belongs
	 * @param string     $form_type		  string used in form selector
	 * @param string     $host_type		  string defining is it host, template or host prototype
	 * @param array	     $data			  given data provider
	 */
	private function checkMacrosFields($name, $is_prototype, $lld_id, $form_type, $host_type, $data = null) {
		$id = CDBHelper::getValue('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($name));

		if ($host_type === 'host') {
			$this->page->login()->open('zabbix.php?action=host.view')->waitUntilReady();
			$column = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->findRow('Name', $name)
					->getColumn('Name');
			$column->query('link', $name)->asPopupButton()->one()->select('Configuration');
			$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible();
		}
		else {
			$this->page->open(
				$is_prototype
					? 'host_prototypes.php?form=update&context=host&parent_discoveryid='.$lld_id.'&hostid='.$id
					: $host_type.'s.php?form=update&'.$host_type.'id='.$id.'&groupid=0'
			);
			$form = $this->query('name:'.$form_type.'Form')->waitUntilPresent()->asForm()->one();
		}

		$form->selectTab('Macros');

		if ($data !== null) {
			foreach ($data['macros'] as &$macro) {
				if ($macro['macro'] === '{$lowercase}') {
					$macro['macro'] = '{$LOWERCASE}';
				}
			}
			unset($macro);
		}

		$this->assertMacros(($data !== null) ? $data['macros'] : []);
		$this->query('xpath://label[@for="show_inherited_macros_1"]')->waitUntilPresent()->one()->click();
		// Get all macros defined for this host.
		$hostmacros = CDBHelper::getAll('SELECT macro, value, description, type FROM hostmacro where hostid ='.$id);

		$this->checkInheritedGlobalMacros($hostmacros);

		if ($host_type === 'host') {
			COverlayDialogElement::find()->one()->close();
			COverlayDialogElement::ensureNotPresent();
		}
	}

	/**
	 * Check host/host prototype/template inherited macros in form matching with global macros in DB.
	 *
	 * @param array $hostmacros		all macros defined particularly for this host
	 */
	public function checkInheritedGlobalMacros($hostmacros = []) {
		// Create two macros arrays: from DB and from Frontend form.
		$macros_db = array_merge(
			CDBHelper::getAll('SELECT macro, value, description, type FROM globalmacro'),
			$hostmacros
		);

		// If the macro is expected to have type "Secret text", replace the value from db with the secret macro pattern.
		for ($i = 0; $i < count($macros_db); $i++) {
			if (intval($macros_db[$i]['type']) === ZBX_MACRO_TYPE_SECRET) {
				$macros_db[$i]['value'] = '******';
			}
		}

		// Compare macros from DB with macros from Frontend.
		$this->assertEquals($this->sortMacros($macros_db), $this->getGlobalMacrosFrotendTable());
	}

	/**
	 * Get values from global macros table.
	 *
	 * @return array
	 */
	public function getGlobalMacrosFrotendTable() {
		// Write macros rows from Frontend to array.
		$macros_frontend = [];
		$table = $this->query('id:tbl_macros')->waitUntilVisible()->asTable()->one();
		$count = $table->getRows()->count() - 1;
		for ($i = 0; $i < $count; $i += 2) {
			$macro = [];
			$row = $table->getRow($i);
			$macro['macro'] = $row->query('xpath:./td[1]/textarea')->one()->getValue();
			$macro_value = $this->getValueField($macro['macro']);
			$macro['value'] = $macro_value->getValue();
			$macro['description'] = $table->getRow($i + 1)->query('tag:textarea')->one()->getValue();
			$macro['type'] = ($macro_value->getInputType() === CInputGroupElement::TYPE_SECRET) ?
					ZBX_MACRO_TYPE_SECRET : ZBX_MACRO_TYPE_TEXT;
			$macros_frontend[] = $macro;
		}

		return $this->sortMacros($macros_frontend);
	}

	/**
	 * Check content of macro value InputGroup element for macros.
	 *
	 * @param array		$data		given data provider
	 * @param string	$url		url of configuration form of the corresponding entity
	 * @param string	$source		type of entity that is being checked (hots, hostPrototype, template)
	 */
	public function checkSecretMacrosLayout($data, $url, $source, $name = null) {
		$this->openMacrosTab($url, $source, true, $name);

		// Check that value field is disabled for global macros in "Inherited and host macros" tab.
		if (CTestArrayHelper::get($data, 'global', false)) {
			$this->query('xpath://label[@for="show_inherited_macros_1"]')->waitUntilPresent()->one()->click();
			$value_field = $this->getValueField($data['macro']);
			$change_button = $value_field->getNewValueButton();
			$revert_button = $value_field->getRevertButton();

			if ($data['type'] === CInputGroupElement::TYPE_TEXT) {
				$this->assertTrue($value_field->query('xpath:./textarea')->one()->isAttributePresent('readonly'));
				$this->assertEquals(2048, $value_field->query('xpath:./textarea')->one()->getAttribute('maxlength'));
				$this->assertFalse($change_button->isValid());
				$this->assertFalse($revert_button->isValid());
			}
			else {
				$this->assertFalse($value_field->query('xpath:.//input')->one()->isEnabled());
				$this->assertEquals(2048, $value_field->query('xpath:.//input')->one()->getAttribute('maxlength'));
				$this->assertFalse($change_button->isEnabled());
				$this->assertFalse($revert_button->isClickable());
			}
			$this->assertFalse($value_field->query('xpath:.//button[contains(@class, "btn-dropdown-toggle")]')->one()->isEnabled());
		}
		else {
			$value_field = $this->getValueField($data['macro']);
			$change_button = $value_field->getNewValueButton();
			$revert_button = $value_field->getRevertButton();
			$textarea_xpath = 'xpath:.//textarea[contains(@class, "textarea-flexible")]';

			if ($data['type'] === CInputGroupElement::TYPE_SECRET) {
				$this->assertFalse($value_field->query($textarea_xpath)->exists());
				$this->assertEquals(2048, $value_field->query('xpath:.//input')->one()->getAttribute('maxlength'));

				$this->assertTrue($change_button->isValid());
				$this->assertFalse($revert_button->isClickable());
				// Change value text or type and check that New value button is not displayed and Revert button appeared.
				if (CTestArrayHelper::get($data, 'change_type', false)) {
					$value_field->changeInputType(CInputGroupElement::TYPE_TEXT);
				}
				else {
					$change_button->click();
				}
				$value_field->invalidate();

				$this->assertFalse($change_button->isEnabled());
				$this->assertTrue($revert_button->isClickable());
			}
			else {
				$this->assertTrue($value_field->query($textarea_xpath)->exists());
				$this->assertEquals(2048, $value_field->query('xpath:./textarea')->one()->getAttribute('maxlength'));
				$this->assertFalse($change_button->isValid());
				$this->assertFalse($revert_button->isValid());

				// Change value type to "Secret text" and check that new value and revert buttons were not added.
				$value_field->changeInputType(CInputGroupElement::TYPE_SECRET);
				$value_field->invalidate();

				$this->assertFalse($value_field->getNewValueButton()->isValid());
				$this->assertFalse($value_field->getRevertButton()->isValid());
			}
		}

		if ($source === 'hosts') {
			COverlayDialogElement::find()->one()->close();
			COverlayDialogElement::ensureNotPresent();
		}
	}

	/**
	 * Check adding and saving secret macros for host, host prototype and template entities.
	 *
	 * @param array		$data		given data provider
	 * @param string	$url		url of configuration form of the corresponding entity
	 * @param string	$source		type of entity that is being checked (hots, hostPrototype, template)
	 */
	public function createSecretMacros($data, $url, $source, $name = null) {
		$form = $this->openMacrosTab($url, $source, true, $name);

		// Check that macro values have type plain text by default.
		if (CTestArrayHelper::get($data, 'check_default_type', false)){
			$this->assertEquals(CInputGroupElement::TYPE_TEXT, $this->query('xpath://div[contains(@class, "macro-value")]')
					->one()->asInputGroup()->getInputType());
		}

		$this->fillMacros([$data['macro_fields']]);
		$value_field = $this->query('xpath://div[contains(@class, "macro-value")]')->all()->last()->asInputGroup();

		// Check that macro type is set correctly.
		$this->assertEquals($data['macro_fields']['value']['type'], $value_field->getInputType());

		// Check text from value field.
		$this->assertEquals($data['macro_fields']['value']['text'], $value_field->getValue());

		// Switch to tab with inherited and instance macros and verify that the value is secret but is still accessible.
		$this->checkInheritedTab($data['macro_fields'], true);

		// Check that macro value is hidden but is still accessible after switching back to instance macros list.
		$data_value_field = $this->getValueField($data['macro_fields']['macro']);
		$this->assertEquals(CInputGroupElement::TYPE_SECRET, $data_value_field->getInputType());

		// Change macro type back to text (is needed) before saving the changes.
		if (CTestArrayHelper::get($data, 'back_to_text', false)) {
			$data_value_field->changeInputType(CInputGroupElement::TYPE_TEXT);
		}

		$form->invalidate();
		$form->submit();
		if ($source === 'host') {
			COverlayDialogElement::ensureNotPresent();
		}
		$this->assertMessage(TEST_GOOD);

		// Check value field for guest account.
		$this->openMacrosTab($url, $source, false, $name);
		$guest_value_field = $this->getValueField($data['macro_fields']['macro']);

		if (CTestArrayHelper::get($data, 'back_to_text', false)) {
			$this->assertEquals($data['macro_fields']['value']['text'], $guest_value_field->getValue());
			// Switch to tab with inherited and instance macros and verify that the value is plain text.
			$this->checkInheritedTab($data['macro_fields'], false);
		}
		else {
			$this->assertEquals('******', $guest_value_field->getValue());
			// Switch to tab with inherited and instance macros and verify that the value is secret and is not accessible.
			$this->checkInheritedTab($data['macro_fields'], true, false);
		}

		// Check macro value, type and description in DB.
		$sql = 'SELECT value, description, type FROM hostmacro WHERE macro='.zbx_dbstr($data['macro_fields']['macro']);
		$type = (CTestArrayHelper::get($data, 'back_to_text', false)) ? ZBX_MACRO_TYPE_TEXT : ZBX_MACRO_TYPE_SECRET;
		$this->assertEquals([$data['macro_fields']['value']['text'], $data['macro_fields']['description'], $type],
				array_values(CDBHelper::getRow($sql))
		);

		if ($source === 'hosts') {
			COverlayDialogElement::find()->one()->close();
			COverlayDialogElement::ensureNotPresent();
		}
	}

	/**
	 *  Check update of secret macros for host, host prototype and template entities.
	 *
	 * @param array		$data		given data provider
	 * @param string	$url		url of configuration form of the corresponding entity
	 * @param string	$source		type of entity that is being checked (hots, hostPrototype, template)
	 */
	public function updateSecretMacros($data, $url, $source, $name = null) {
		$form = $this->openMacrosTab($url, $source, true, $name);
		$this->fillMacros([$data]);

		// Check that new values are correct in Inherited and host prototype macros tab before saving the values.
		$secret = (CTestArrayHelper::get($data['value'], 'type', CInputGroupElement::TYPE_SECRET) ===
				CInputGroupElement::TYPE_SECRET) ? true : false;
		$this->checkInheritedTab($data, $secret);

		$form->invalidate();
		$form->submit();
		if ($source === 'host') {
			COverlayDialogElement::ensureNotPresent();
		}
		$this->assertMessage(TEST_GOOD);

		$this->openMacrosTab($url, $source, false, $name);

		$value_field = $this->getValueField($data['macro']);
		if (CTestArrayHelper::get($data['value'], 'type', CInputGroupElement::TYPE_SECRET) === CInputGroupElement::TYPE_SECRET) {
			$this->assertEquals(CInputGroupElement::TYPE_SECRET, $value_field->getInputType());
			$this->assertEquals('******', $value_field->getValue());
			$this->checkInheritedTab($data, true, false);
		}
		else {
			$this->assertEquals(CInputGroupElement::TYPE_TEXT, $value_field->getInputType());
			$this->assertEquals($data['value']['text'], $value_field->getValue());
			$this->checkInheritedTab($data, false);
		}
		// Check in DB that values of the updated macros are correct.
		$sql = 'SELECT value FROM hostmacro WHERE macro='.zbx_dbstr($data['macro']);
		$this->assertEquals($data['value']['text'], CDBHelper::getValue($sql));

		if ($source === 'hosts') {
			COverlayDialogElement::find()->one()->close();
			COverlayDialogElement::ensureNotPresent();
		}
	}

	public function getRevertSecretMacrosData() {
		return [
			[
				[
					'macro_fields' => [
						'macro' => $this->revert_macro_1,
						'value' => 'Secret '.$this->revert_macro_object.' value'
					]
				]
			],
			[
				[
					'macro_fields' => [
						'macro' => $this->revert_macro_2,
						'value' => 'Secret '.$this->revert_macro_object.' value 2'
					],
					'set_to_text' => true
				]
			]
		];
	}

	/**
	 *  Check that it is possible to revert secret macro changes for host, host prototype and template entities.
	 *
	 * @param array		$data		given data provider
	 * @param string	$url		url of configuration form of the corresponding entity
	 * @param string	$source		type of entity that is being checked (hots, hostPrototype, template)
	 */
	public function revertSecretMacroChanges($data, $url, $source, $name = null) {
		$form = $this->openMacrosTab($url, $source, true, $name);

		$sql = 'SELECT * FROM hostmacro WHERE macro='.CDBHelper::escape($data['macro_fields']['macro']);
		$old_values = CDBHelper::getRow($sql);

		$value_field = $this->getValueField($data['macro_fields']['macro']);

		// Check that the existing macro value is hidden.
		$this->assertEquals('******', $value_field->getValue());

		// Change the value of the secret macro
		$value_field->getNewValueButton()->click();
		$this->assertEquals('', $value_field->getValue());
		$value_field->fill('New_macro_value');

		if (CTestArrayHelper::get($data, 'set_to_text', false)) {
			$value_field->changeInputType(CInputGroupElement::TYPE_TEXT);
			$this->assertEquals('New_macro_value', $value_field->getValue());
		}

		// Press revert button and save the changes.
		$value_field->getRevertButton()->click();

		$form->invalidate();
		$form->submit();
		if ($source === 'host') {
			COverlayDialogElement::ensureNotPresent();
		}
		$this->assertMessage(TEST_GOOD);

		// Check that no macro value changes took place.
		$this->openMacrosTab($url, $source, false, $name);
		$this->assertEquals('******', $this->getValueField($data['macro_fields']['macro'])->getValue());
		$this->assertEquals($old_values, CDBHelper::getRow($sql));

		if ($source === 'hosts') {
			COverlayDialogElement::find()->one()->close();
			COverlayDialogElement::ensureNotPresent();
		}
	}

	/**
	 * Function opens Inherited and instance macros tab and checks the value, it the value has type Secret text and if
	 * the value is displayed.
	 *
	 * @param type $data		given data provider
	 * @param type $secret		flag that indicates if the value should have type "Secret text".
	 * @param type $available	flag that indicates if the value should be available.
	 */
	public function checkInheritedTab($data, $secret, $available = true) {
		// Switch to the list of inherited and instance macros.
		$this->query('xpath://label[@for="show_inherited_macros_1"]')->waitUntilPresent()->one()->click();
		$this->query('class:is-loading')->waitUntilNotPresent();
		$value_field = $this->getValueField($data['macro']);

		if ($secret) {
			$this->assertEquals(CInputGroupElement::TYPE_SECRET, $value_field->getInputType());
			$expected_value = ($available) ? $data['value']['text'] : '******';
			$this->assertEquals($expected_value, $value_field->getValue());
		}
		else {
			$this->assertEquals(CInputGroupElement::TYPE_TEXT, $value_field->getInputType());
			$this->assertEquals($data['value']['text'], $value_field->getValue());
		}
		// Switch back to the list of instance macros.
		$this->query('xpath://label[@for="show_inherited_macros_0"]')->waitUntilPresent()->one()->click();
		$this->query('class:is-loading')->waitUntilNotPresent();
	}

	/**
	 * Function opens Macros tab in corresponding instance configuration form.
	 *
	 * @param string $url		URL that leads to the configuration form of corresponding entity
	 * @param string $source    type of entity that is being checked (host, hostPrototype, template)
	 * @param type $login		flag that indicates whether login should occur before opening the configuration form
	 * @param type $name		name of a host where macros are updated
	 */
	public function openMacrosTab($url, $source, $login = false, $name = null) {
		if ($login) {
			$this->page->login();
		}

		$this->page->open($url)->waitUntilReady();

		if ($source === 'hosts') {
			$column = $this->query('xpath://table[@class="list-table"]')->asTable()->one()->waitUntilReady()
					->findRow('Name', $name, true)->getColumn('Name');
			$column->query('link', $name)->asPopupButton()->one()->select('Configuration');
			$form = COverlayDialogElement::find()->asForm()->one()->waitUntilVisible()->selectTab('Macros');
		}
		else {
			$form = $this->query('id:'.$source.'-form')->asForm()->one()->selectTab('Macros');
		}

		return $form;
	}

	/**
	 * Sort all macros array by Macros.
	 *
	 * @param array $macros    macros to be sorted
	 *
	 * @return array
	 */
	private function sortMacros($macros) {
		usort($macros, function ($a, $b) {
			return strcmp($a['macro'], $b['macro']);
		});

		return $macros;
	}

	/**
	 * Get macro index for the provided macro name.
	 *
	 * @param string    $macro    macro name for which index needs to be fetched
	 *
	 * @return int
	 */
	private function getMacroIndex($macro) {
		$index = explode('_', $this->query('xpath://textarea[text()='.CXPathHelper::escapeQuotes($macro).']')
				->one()->getAttribute('id'), 3
		);

		return $index[1];
	}

	/**
	 * Get macro index for the provided macro name.
	 *
	 * @param CElement    $radio_switcher    macro name for which index needs to be fetched
	 * @param string      $host_type         string defining is it host, template or host prototype
	 *
	 * @return array
	 */
	private function getGlobalMacrosAndSwitch($radio_switcher, $host_type) {
		// Get all global macros before changes.
		$global_macros = $this->getGlobalMacrosFrotendTable();

		// Return to object's macros.
		if ($host_type === 'host') {
			CElementQuery::getDriver()->executeScript('arguments[0].scrollTo(0, 0)',
					[COverlayDialogElement::find()->one()->getContent()]
			);
		}
		$radio_switcher->fill(ucfirst($host_type).' macros');
		$this->page->waitUntilReady();

		return $global_macros;
	}

	public function getCreateVaultMacrosData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO}',
						'value' => [
							'text' => 'secret/path:key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description'
					],
					'title' => ucfirst($this->vault_object).' updated'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO2}',
						'value' => [
							'text' => 'one/two/three/four/five/six:key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description7'
					],
					'title' => ucfirst($this->vault_object).' updated'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO3}',
						'value' => [
							'text' => 'secret/path:',
							'type' => 'Vault secret'
						],
						'description' => 'vault description2'
					],
					'title' => 'Cannot update '.$this->vault_object,
					'message' => 'Invalid parameter "'.$this->vault_error_field.'": incorrect syntax near "path:".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO4}',
						'value' => [
							'text' => '/path:key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description3'
					],
					'title' => 'Cannot update '.$this->vault_object,
					'message' => 'Invalid parameter "'.$this->vault_error_field.'": incorrect syntax near "/path:key".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO5}',
						'value' => [
							'text' => 'path:key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description4'
					],
					'title' => 'Cannot update '.$this->vault_object,
					'message' => 'Invalid parameter "'.$this->vault_error_field.'": incorrect syntax near "path:key".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO6}',
						'value' => [
							'text' => ':key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description5'
					],
					'title' => 'Cannot update '.$this->vault_object,
					'message' => 'Invalid parameter "'.$this->vault_error_field.'": incorrect syntax near ":key".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO7}',
						'value' => [
							'text' => 'secret/path',
							'type' => 'Vault secret'
						],
						'description' => 'vault description6'
					],
					'title' => 'Cannot update '.$this->vault_object,
					'message' => 'Invalid parameter "'.$this->vault_error_field.'": incorrect syntax near "path".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO8}',
						'value' => [
							'text' => '/secret/path:key',
							'type' => 'Vault secret'
						],
						'description' => 'vault description8'
					],
					'title' => 'Cannot update '.$this->vault_object,
					'message' => 'Invalid parameter "'.$this->vault_error_field.'": incorrect syntax near "/secret/path:key".'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'macro_fields' => [
						'macro' => '{$VAULT_MACRO9}',
						'value' => [
							'text' => '',
							'type' => 'Vault secret'
						],
						'description' => 'vault description9'
					],
					'title' => 'Cannot update '.$this->vault_object,
					'message' => 'Invalid parameter "'.$this->vault_error_field.'": cannot be empty.'
				]
			]
		];
	}

	public function createVaultMacros($data, $url, $source, $name = null) {
		$form = $this->openMacrosTab($url, $source, true, $name);
		$this->fillMacros([$data['macro_fields']]);
		$form->submit();

		if ($data['expected'] == TEST_BAD) {
			$this->assertMessage($data['expected'], $data['title'], $data['message']);
		}
		else {
			if ($source === 'host') {
				COverlayDialogElement::ensureNotPresent();
			}

			$this->assertMessage($data['expected'], $data['title']);
			$sql = 'SELECT value, description, type FROM hostmacro WHERE macro='.zbx_dbstr($data['macro_fields']['macro']);
			$this->assertEquals([$data['macro_fields']['value']['text'], $data['macro_fields']['description'], ZBX_MACRO_TYPE_VAULT],
					array_values(CDBHelper::getRow($sql)));
			$this->openMacrosTab($url, $source, false, $name);
			$value_field = $this->getValueField($data['macro_fields']['macro']);
			$this->assertEquals($data['macro_fields']['value']['text'], $value_field->getValue());
		}

		if ($source === 'hosts') {
			COverlayDialogElement::find()->one()->close();
			COverlayDialogElement::ensureNotPresent();
		}
	}

	public function getUpdateVaultMacrosData() {
		return [
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => $this->vault_macro_index,
					'macro' => $this->update_vault_macro,
					'value' => [
						'text' => 'secret/path:key'
					],
					'description' => ''
				]
			],
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => $this->vault_macro_index,
					'macro' => $this->update_vault_macro,
					'value' => [
						'text' => 'new/path/to/secret:key'
					],
					'description' => ''
				]
			],
			[
				[
					'action' => USER_ACTION_UPDATE,
					'index' => $this->vault_macro_index,
					'macro' => $this->update_vault_macro,
					'value' => [
						'text' => 'new/path/to/secret:key'
					],
					'description' => 'Changing description'
				]
			]
		];
	}

	public function updateVaultMacros($data, $url, $source, $name = null) {
		$form = $this->openMacrosTab($url, $source, true, $name);
		$this->fillMacros([$data]);
		$form->submit();
		if ($source === 'host') {
			COverlayDialogElement::ensureNotPresent();
		}
		$this->assertMessage(TEST_GOOD);

		$this->openMacrosTab($url, $source, false, $name);
		$result = [];
		foreach (['macro', 'value', 'description'] as $field) {
			$result[] = $this->query('xpath://textarea[@id="macros_'.$data['index'].'_'.$field.'"]')->one()->getText();
		}
		$this->assertEquals([$data['macro'], $data['value']['text'], $data['description']], $result);
		array_push($result, ZBX_MACRO_TYPE_VAULT);
		$sql = 'SELECT macro, value, description, type FROM hostmacro WHERE macro='.zbx_dbstr($data['macro']);
		$this->assertEquals($result, array_values(CDBHelper::getRow($sql)));

		if ($source === 'hosts') {
			COverlayDialogElement::find()->one()->close();
			COverlayDialogElement::ensureNotPresent();
		}
	}

	public function getResolveSecretMacroData() {
		return [
			// Latest data page. Macro is resolved only in key.
			[
				[
					'url' => 'zabbix.php?action=latest.view&hostids%5B%5D='.$this->macro_resolve_hostid.'&show_details=1',
					'name' => 'Macro value: '.$this->macro_resolve,
					'key' => 'trap[Value 2 B resolved]',
					'key_secret' => 'trap[******]'
				]
			],
			// Hosts items page. Macro is not resolved in any field.
			[
				[
					'url' => 'items.php?filter_set=1&filter_hostids%5B0%5D='.$this->macro_resolve_hostid.'&context=host',
					'name' => 'Macro value: '.$this->macro_resolve,
					'key' => 'trap['.$this->macro_resolve.']',
					'key_secret' => 'trap['.$this->macro_resolve.']'
				]
			]
		];
	}

	/**
	 * Function for testing resolving macros on host or global level.
	 *
	 * @param string $data    data provider
	 * @param string $object  macros level: global or host
	 */
	public function resolveSecretMacro($data, $object = 'global') {
		$this->checkItemFields($data['url'], $data['name'], $data['key']);

		if ($object === 'host') {
			// Open host form in popup and change macro type to secret.
			$form = $this->openMacrosTab('zabbix.php?action=host.view', 'hosts', false, 'Available host in maintenance');
			$this->getValueField($this->macro_resolve)->changeInputType(CInputGroupElement::TYPE_SECRET);

			$form->submit();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Host updated');
		}
		else {
			// Change global macro type to secret.
			$this->page->open('zabbix.php?action=macros.edit')->waitUntilReady();
			$this->getValueField($this->macro_resolve)->changeInputType(CInputGroupElement::TYPE_SECRET);
			$this->query('button:Update')->one()->click();
		}

		$this->checkItemFields($data['url'], $data['name'], $data['key_secret']);
	}

	/**
	 * 	Function for checking item field on Latest data or Items page.
	 *
	 * @param string $url    Latest data or Items page URL
	 * @param string $name   item name
	 * @param string $key    item key
	 */
	private function checkItemFields($url, $name, $key) {
		$this->page->login()->open($url)->waitUntilReady();
		$table = $this->query('xpath://form[@name="items"]/table[@class="list-table"] | '.
				'//table[contains(@class, "overflow-ellipsis")]')->asTable()->waitUntilPresent()->one();

		$name_column = $table->findRow('Name', $name, true)->getColumn('Name');
		$this->assertEquals($name, $name_column->query('tag:a')->one()->getText());

		$this->assertEquals($key, (strpos($url, 'latest')
				? $name_column->query('xpath://span[@class="green"]')->one()->getText()
				: $table->findRow('Name', $name)->getColumn('Key')->getText()
		));
	}
}
