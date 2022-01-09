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

require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * Trait for preprocessing related tests.
 */
trait PreprocessingTrait {

	/**
	 * Get descriptors of preprocessing fields.
	 *
	 * @return array
	 */
	protected static function getPreprocessingFieldDescriptors() {
		return [
			[
				'name'		=> 'type',
				'selector'	=> 'xpath:.//z-select[contains(@id, "_type")]',
				'detect'	=> true,
				'value'		=> ['getValue']
			],
			[
				'name'		=> 'parameter_1',
				'selector'	=> 'xpath:.//input[contains(@id, "_params_0")]|.//div[contains(@id, "_params_0")]',
				'detect'	=> true,
				'value'		=> ['getValue']
			],
			[
				'name'		=> 'parameter_2',
				'selector'	=> 'xpath:.//input[contains(@id, "_params_1")]|.//z-select[contains(@name, "[params][1]")]',
				'detect'	=> true,
				'value'		=> ['getValue']
			],
			[
				'name'		=> 'parameter_3',
				'selector'	=> 'xpath:.//input[contains(@id, "_params_2")]',
				'detect'	=> true,
				'value'		=> ['getValue']
			],
			[
				'name'		=> 'on_fail',
				'selector'	=> 'xpath:.//input[contains(@id, "_on_fail")]',
				'class'		=> 'CCheckboxElement',
				'value'		=> ['isChecked']
			],
			[
				'name'		=> 'error_handler',
				'selector'	=> 'xpath:.//ul[contains(@id, "_error_handler")]',
				'class'		=> 'CSegmentedRadioElement',
				'value'		=> ['getText']
			],
			[
				'name'		=> 'error_handler_params',
				'selector'	=> 'xpath:.//input[contains(@id, "_error_handler_params")]',
				'value'		=> ['getValue']
			]
		];
	}

	/**
	 * Get preprocessing step field from container and field description.
	 *
	 * @param Element $container    container element
	 * @param array   $field        field description
	 *
	 * @return CElement|CNullElement
	 */
	protected static function getPreprocessingField($container, $field) {
		$query = $container->query($field['selector']);

		if (array_key_exists('class', $field)) {
			$query->cast($field['class']);
		}

		$element = $query->one(false);
		if ($element->isValid() && array_key_exists('detect', $field) && $field['detect']) {
			$element = $element->detect();
		}

		return $element;
	}

	/**
	 * Add new preprocessing, select preprocessing type and parameters if exist.
	 *
	 * @param array $steps    preprocessing step values
	 */
	protected function addPreprocessingSteps($steps) {
		$rows = $this->query('class:preprocessing-list-item')->count() + 1;
		$add = $this->query('id:param_add')->one();
		$fields = self::getPreprocessingFieldDescriptors();

		foreach ($steps as $options) {
			$add->click();
			$container = $this->query('xpath://li[contains(@class, "preprocessing-list-item")]['.$rows.']')
					->waitUntilPresent()->one();

			foreach ($fields as $field) {
				if (array_key_exists($field['name'], $options)) {
					self::getPreprocessingField($container, $field)->fill($options[$field['name']]);
				}
			}

			$rows++;
		}
	}

	/**
	 * Get input fields of preprocessing steps.
	 *
	 * @param boolean $extended    get preprocessing steps with field descriptors.
	 *
	 * @return array
	 */
	protected function getPreprocessingSteps($extended = false) {
		$steps = [];

		$fields = self::getPreprocessingFieldDescriptors();

		foreach ($this->query('class:preprocessing-list-item')->all() as $row) {
			$preprocessing = [];

			foreach ($fields as $field) {
				$key = $field['name'];

				if (isset($preprocessing[$key]) && (!$extended || $preprocessing[$key]['element']->isValid())) {
					continue;
				}

				$element = self::getPreprocessingField($row, $field);

				$preprocessing[$key] = $extended ? ['element' => $element, 'field' => $field] : $element;
			}

			$steps[] = $preprocessing;
		}

		return $steps;
	}

	/**
	 * Check if values of preprocessing step inputs match data from data provider.
	 *
	 * @return array
	 */
	protected function assertPreprocessingSteps($data) {
		$steps = $this->getPreprocessingSteps(true);
		$this->assertEquals(count($data), count($steps), 'Preprocessing step count should match step count in data.');

		foreach ($data as $i => $options) {
			foreach ($steps[$i] as $control) {
				$field = $control['field'];

				if (!array_key_exists($field['name'], $options)) {
					continue;
				}

				if (!$control['element']->isValid()) {
					$this->fail('Field "'.$field['name'].'" is not present.');
				}

				$value = call_user_func_array([$control['element'], $field['value'][0]],
						array_key_exists('params', $field['value']) ? $field['value']['params'] : []
				);

				$this->assertEquals($options[$field['name']], $value);
			}
		}

		// Remove field data.
		foreach ($steps as &$step) {
			foreach ($step as &$control) {
				$control = $control['element'];
			}
			unset($control);
		}
		unset($step);

		return $steps;
	}

	/**
	 * Get preprocessing steps and convert them to data.
	 *
	 * @return array
	 */
	protected function listPreprocessingSteps() {
		$data = [];
		foreach ($this->getPreprocessingSteps(true) as $i => $step) {
			$values = [];
			foreach ($step as $control) {
				$field = $control['field'];

				if (!$control['element']->isValid()) {
					continue;
				}

				$value = call_user_func_array([$control['element'], $field['value'][0]],
						array_key_exists('params', $field['value']) ? $field['value']['params'] : []
				);

				$values[$field['name']] = $value;
			}

			$data[] = $values;
		}

		return $data;
	}
}
