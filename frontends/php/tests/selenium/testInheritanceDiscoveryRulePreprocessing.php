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

require_once dirname(__FILE__).'/../include/CWebTest.php';
require_once dirname(__FILE__).'/traits/PreprocessingTrait.php';

/**
 * Test the creation of inheritance of new objects on a previously linked template.
 *
 * @backup items
 */
class testInheritanceDiscoveryRulePreprocessing extends CWebTest {
	private $templateid	= 15000;		// 'Inheritance test template'
	private $hostid		= 15001;		// 'Template inheritance test host'

	use PreprocessingTrait;

	/**
	 * Data provider for preprocessing test.
	 *
	 * @return array
	 */
	public function getPreprocessingData() {
		return [
			[
				[
					[
						'type' => 'Regular expression',
						'parameter_1' => 'expression',
						'parameter_2' => '\1',
						'on_fail' => true,
						'error_handler' => 'Discard value'
					],
					[
						'type' => 'JSONPath',
						'parameter_1' => '$.data.test',
						'on_fail' => true,
						'error_handler' => 'Set value to',
						'error_handler_params' => 'Custom_text'
					],
					[
						'type' => 'Does not match regular expression',
						'parameter_1' => 'Pattern',
						'on_fail' => true,
						'error_handler' => 'Set error to',
						'error_handler_params' => 'Custom_text'
					],
					[
						'type' => 'Check for error in JSON',
						'parameter_1' => '$.new.path'
					],
					[
						'type' => 'Discard unchanged with heartbeat',
						'parameter_1' => '30'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getPreprocessingData
	 */
	public function testInheritanceDiscoveryRulePreprocessing_PreprocessingInheritanceFromTemplate($preprocessing) {
		$fields = [
			'Name' => 'Templated LLD with Preprocessing steps',
			'Key' => 'templated-lld-with-preprocessing-steps'
		];

		// Create discovery rule on template.
		$this->page->login()->open('host_discovery.php?hostid='.$this->templateid);
		$this->query('button:Create discovery rule')->waitUntilPresent()->one()->click();

		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$form->fill($fields);

		$form->selectTab('Preprocessing');
		$this->addPreprocessingSteps($preprocessing);
		$form->submit();
		$this->page->waitUntilReady();

		$message = CMessageElement::find()->one();
		$this->assertTrue($message->isGood());
		$this->assertEquals('Discovery rule created', $message->getTitle());

		// Check discovery rules preprocessing steps on host.
		$this->page->open('host_discovery.php?hostid='.$this->hostid);
		$this->query('link:'.$fields['Name'])->one()->click();
		$this->page->waitUntilReady();

		$form->selectTab('Preprocessing');
		$steps = $this->assertPreprocessingSteps($preprocessing);

		foreach ($preprocessing as $i => $options) {
			$step = $steps[$i];
			$this->assertNotNull($step['type']->getAttribute('readonly'));

			foreach (['parameter_1', 'parameter_2'] as $param) {
				if (array_key_exists($param, $options)) {
					$this->assertNotNull($step[$param]->getAttribute('readonly'));
				}
			}

			$this->assertNotNull($step['on_fail']->getAttribute('disabled'));

			switch ($options['type']) {
				case 'Regular expression':
				case 'JSONPath':
				case 'Does not match regular expression':
					$this->assertTrue($step['on_fail']->isSelected());
					$this->assertFalse($step['error_handler']->isEnabled());
					break;

				case 'Check for error in JSON':
				case 'Discard unchanged with heartbeat':
					$this->assertFalse($step['on_fail']->isSelected());
					break;
			}
		}
	}
}
