<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup items
 */
class testInheritanceDiscoveryRule extends CLegacyWebTest {
	private $templateid = 15000;	// 'Inheritance test template'
	private $template  = 'Inheritance test template';

	private $hostid = 15001;		// 'Template inheritance test host'
	private $host = 'Template inheritance test host';

	// Returns list of discovery rules from a template.
	public static function update() {
		return CDBHelper::getDataProvider(
			'SELECT itemid'.
			' FROM items'.
			' WHERE hostid=15000'.	//	$this->templateid.
				' AND flags=1'
		);
	}

	/**
	 * @dataProvider update
	 */
	public function testInheritanceDiscoveryRule_SimpleUpdate($data) {
		$sqlDiscovery = 'SELECT * FROM items ORDER BY itemid';
		$oldHashDiscovery = CDBHelper::getHash($sqlDiscovery);

		$this->zbxTestLogin('host_discovery.php?form=update&itemid='.$data['itemid']);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckTitle('Configuration of discovery rules');
		$this->zbxTestTextPresent('Discovery rule updated');

		$this->assertEquals($oldHashDiscovery, CDBHelper::getHash($sqlDiscovery));

	}

	// Returns create data.
	public static function create() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceDiscoveryRule6',
					'key' => 'discovery-rule-inheritance6'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'testInheritanceDiscoveryRule5',
					'key' => 'discovery-rule-inheritance5',
					'errors' => [
						'Discovery rule "discovery-rule-inheritance5" already exists on "Template inheritance test host", inherited from another template'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'testInheritanceDiscoveryRuleWithLLDMacros',
					'key' => 'discovery-rule-inheritance-with-macros',
					'macros' => [
						['macro' => '{#MACRO1}', 'path'=>'$.path.1'],
						['macro' => '{#MACRO2}', 'path'=>'$.path.1']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testInheritanceDiscoveryRule_SimpleCreate($data) {
		$this->zbxTestLogin('host_discovery.php?form=Create+discovery+rule&hostid='.$this->templateid);

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestInputType('key', $data['key']);

		if (array_key_exists('macros', $data)) {
			$this->zbxTestTabSwitch('LLD macros');
			$last = count($data['macros']) - 1;

			foreach ($data['macros'] as $i => $lld_macro) {
				$this->zbxTestInputType('lld_macro_paths_'.$i.'_lld_macro', $lld_macro['macro'] );
				$this->zbxTestInputType('lld_macro_paths_'.$i.'_path', $lld_macro['path'] );
				if ($i !== $last) {
					$this->zbxTestClick('lld_macro_add');
				}
			}
		}

		$this->zbxTestClickWait('add');
		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of discovery rules');
				$this->zbxTestCheckHeader('Discovery rules');
				$this->zbxTestTextPresent('Discovery rule created');

				$itemId = 0;

				// Template DB check.
				$dbResult = DBselect(
					'SELECT itemid,name,templateid'.
					' FROM items'.
					' WHERE hostid='.$this->templateid.
						' AND key_='.zbx_dbstr($data['key']).
						' AND flags=1'
				);
				if ($dbRow = DBfetch($dbResult)) {
					$itemId = $dbRow['itemid'];
					$this->assertEquals($dbRow['name'], $data['name']);
					$this->assertEquals($dbRow['templateid'], 0);
				}

				$this->assertNotEquals($itemId, 0);

				// Host DB check.
				$dbResult = DBselect(
					'SELECT key_,name'.
					' FROM items'.
					' WHERE hostid='.$this->hostid.
						' AND templateid='.$itemId.
						' AND flags=1'
				);
				if ($dbRow = DBfetch($dbResult)) {
					$this->assertEquals($dbRow['key_'], $data['key']);
					$this->assertEquals($dbRow['name'], $data['name']);
				}

				// Host form check.
				$this->zbxTestLogin('host_discovery.php?hostid='.$this->hostid);
				$this->zbxTestClickLinkText($data['name']);
				$this->zbxTestWaitForPageToLoad();
				$this->zbxTestAssertElementPresentXpath('//input[@id="name"][@value="'.$data['name'].'"][@readonly]');
				$this->zbxTestAssertElementPresentXpath('//input[@id="key"][@value="'.$data['key'].'"][@readonly]');
				if (array_key_exists('macros', $data)) {
					$this->zbxTestTabSwitch('LLD macros');
					foreach ($data['macros'] as $i => $lld_macro) {
						$this->zbxTestAssertElementPresentXpath('//input[@id="lld_macro_paths_'.$i.'_lld_macro"][@value="'.$lld_macro['macro'].'"][@readonly]');
						$this->zbxTestAssertElementPresentXpath('//input[@id="lld_macro_paths_'.$i.'_path"][@value="'.$lld_macro['path'].'"][@readonly]');
					}
				}
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of discovery rules');
				$this->zbxTestCheckHeader('Discovery rules');
				$this->zbxTestTextPresent('Cannot add discovery rule');
				$this->zbxTestTextPresent($data['errors']);
				break;
		}
	}

	public function testInheritanceDiscoveryRule_PreprocessingInheritanceFromTemplate() {
		$lld_name = 'Templated LLD with Preprocessing steps';
		$lld_key = 'templated-lld-with-preprocessing-steps';
		$custom_errors = ['Discard value', 'Set value to', 'Set error to'];
		$custom_error_value_text = 'Custom_text';

		$preprocessing = [
			['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => '\1'],
			['type' => 'JSONPath', 'parameter_1' => '$.data.test'],
			['type' => 'Does not match regular expression', 'parameter_1' => 'Pattern'],
			['type' => 'Check for error in JSON', 'parameter_1' => '$.new.path'],
			['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '30']
		];

		// Create discovery rule on template.
		$this->page->login()->open('host_discovery.php?hostid='.$this->templateid);
		$this->query('button:Create discovery rule')->one()->click();

		$form = $this->query('name:itemForm')->asForm()->one();
		$form->getField('Name')->fill($lld_name);
		$form->getField('Key')->fill($lld_key);

		$form->selectTab('Preprocessing');

		foreach ($preprocessing as $step_count => $options) {
			$this->query('id:param_add')->one()->click();
			$this->query('id:preprocessing_'.$step_count.'_type')->asDropdown()->one()->select($options['type']);

			if (array_key_exists('parameter_1', $options) && array_key_exists('parameter_2', $options)) {
				$this->query('id:preprocessing_'.$step_count.'_params_0')->one()->type($options['parameter_1']);
				$this->query('id:preprocessing_'.$step_count.'_params_1')->one()->type($options['parameter_2']);
			}
			elseif (array_key_exists('parameter_1', $options) && !array_key_exists('parameter_2', $options)) {
				$this->query('id:preprocessing_'.$step_count.'_params_0')->one()->type($options['parameter_1']);
			}

			switch ($options['type']) {
				case 'Regular expression':
					$this->query('id:preprocessing_'.$step_count.'_on_fail')->one()->asCheckbox()->check();
					$this->query('id:preprocessing_'.$step_count.'_error_handler')->asSegmentedRadio()->one()->select($custom_errors[$step_count]);
					break;
				case 'JSONPath':
				case 'Does not match regular expression':
					$this->query('id:preprocessing_'.$step_count.'_on_fail')->one()->asCheckbox()->check();
					$this->query('id:preprocessing_'.$step_count.'_error_handler')->asSegmentedRadio()->one()->select($custom_errors[$step_count]);
					$this->query('id:preprocessing_'.$step_count.'_error_handler_params')->one()->type($custom_error_value_text.$step_count);
					break;
				case 'Check for error in JSON':
				case 'Discard unchanged with heartbeat':
					default;
			}
		}
		$form->submit();
		$this->page->waitUntilReady();

		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Discovery rule created', $message->getTitle());

		// Check discovery rules preprocessing steps on host.
		$this->page->login()->open('host_discovery.php?hostid='.$this->hostid);
		$this->query('link:'.$lld_name)->one()->click();
		$this->page->waitUntilReady();
		$form = $this->query('name:itemForm')->asForm()->one();
		$form->selectTab('Preprocessing');
		foreach ($preprocessing as $step_count => $options) {
			$type_field = $this->query('id:preprocessing_'.$step_count.'_type_name')->one();
			$type=$type_field->getValue();
			$this->assertEquals($options['type'], $type);
			$this->assertNotNull($type_field->getAttribute('readonly'));

			if (array_key_exists('parameter_1', $options) && array_key_exists('parameter_2', $options)) {

				$parameter_1_field = $this->query('id:preprocessing_'.$step_count.'_params_0')->one();
				$parameter_1 = $parameter_1_field->getValue();
				$this->assertEquals($options['parameter_1'], $parameter_1);
				$this->assertNotNull($parameter_1_field->getAttribute('readonly'));

				$parameter_2_field = $this->query('id:preprocessing_'.$step_count.'_params_1')->one();
				$parameter_2 = $parameter_2_field->getValue();
				$this->assertEquals($options['parameter_2'], $parameter_2);
				$this->assertNotNull($parameter_2_field->getAttribute('readonly'));

			}
			elseif (array_key_exists('parameter_1', $options) && !array_key_exists('parameter_2', $options)) {
				$parameter_1_field = $this->query('id:preprocessing_'.$step_count.'_params_0')->one();
				$parameter_1 = $parameter_1_field->getValue();
				$this->assertEquals($options['parameter_1'], $parameter_1);
				$this->assertNotNull($parameter_1_field->getAttribute('readonly'));
			}
			$custom_checkbox = $this->query('id:preprocessing_'.$step_count.'_on_fail')->one()->asCheckbox();
			$this->assertNotNull($custom_checkbox->getAttribute('disabled'));
			switch ($options['type']) {
				case 'Regular expression':
					$this->assertTrue($custom_checkbox->isSelected());
					$custom_radio = $this->query('id:preprocessing_'.$step_count.'_error_handler')->asSegmentedRadio()->one();
					$this->assertFalse($custom_radio->isEnabled());
					$this->assertEquals($custom_errors[$step_count], $custom_radio->getText());
					break;
				case 'JSONPath':
				case 'Does not match regular expression':
					$this->assertTrue($custom_checkbox->isSelected());
					$custom_radio = $this->query('id:preprocessing_'.$step_count.'_error_handler')->asSegmentedRadio()->one();
					$this->assertFalse($custom_radio->isEnabled());
					$this->assertEquals($custom_errors[$step_count], $custom_radio->getText());
					$custom_text = $this->query('id:preprocessing_'.$step_count.'_error_handler_params')->one()->getValue();
					$this->assertEquals($custom_error_value_text.$step_count, $custom_text);
					break;
				case 'Check for error in JSON':
				case 'Discard unchanged with heartbeat':
					$this->assertFalse($custom_checkbox->isSelected());
					break;
			}
		}
	}
}
