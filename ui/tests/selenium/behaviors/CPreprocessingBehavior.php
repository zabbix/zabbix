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

require_once __DIR__.'/../../include/CBehavior.php';

/**
 * Behavior for preprocessing related tests.
 */
class CPreprocessingBehavior extends CBehavior {

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
				'selector'	=> 'xpath:.//input[contains(@id, "_params_0")]|.//div[contains(@id, "_params_0")]|'.
						'.//z-select[contains(@name, "[params][0]")]',
				'detect'	=> true,
				'value'		=> ['getValue']
			],
			[
				'name'		=> 'parameter_2',
				'selector'	=> 'xpath:.//input[contains(@id, "_params_1")]|.//z-select[contains(@name, "[params][1]")]|'.
						'.//input[contains(@name, "[params][1]")]',
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
				'selector'	=> 'xpath:.//z-select[contains(@id, "-error-handler")]',
				'detect'	=> true,
				'value'		=> ['getValue']
			],
			[
				'name'		=> 'error_handler_params',
				'selector'	=> 'xpath:.//input[contains(@id, "_error_handler_params")]',
				'value'		=> ['getValue']
			],
			[
				'name' 		=> 'parameter_table_1_1',
				'selector'	=> 'xpath:.//tr[1]/td[1]/*',
				'detect'	=> true,
				'value'		=> ['getValue']
			],
			[
				'name' 		=> 'parameter_table_1_2',
				'selector'	=> 'xpath:.//tr[1]/td[2]/*',
				'detect'	=> true,
				'value'		=> ['getValue']
			],
			[
				'name' 		=> 'parameter_table_1_3',
				'selector'	=> 'xpath:.//tr[1]/td[3]/*',
				'detect'	=> true,
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
	public static function getPreprocessingField($container, $field) {
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
	 * @param array $steps            preprocessing step values
	 * @param boolean $mass_update    true if editing mass update preprocessing form, false if not
	 */
	public function addPreprocessingSteps($steps, $mass_update = false) {
		$rows = $this->test->query('class:preprocessing-list-item')->count() + ($mass_update ? null : 1);
		$add = $this->test->query('id:param_add')->one();
		$fields = self::getPreprocessingFieldDescriptors();

		foreach ($steps as $i => $options) {
			if (!$mass_update || $i !== 0)  {
				$add->click();
			}

			$container = $this->test->query('xpath://li[contains(@class, "preprocessing-list-item") and @data-step="'.$rows - 1 .'"]')
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
	public function getPreprocessingSteps($extended = false) {
		$steps = [];

		$fields = self::getPreprocessingFieldDescriptors();

		foreach ($this->test->query('class:preprocessing-list-item')->all() as $row) {
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
	public function assertPreprocessingSteps($data) {
		$steps = $this->getPreprocessingSteps(true);
		$this->test->assertEquals(count($data), count($steps), 'Preprocessing step count should match step count in data.');

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

				$this->test->assertEquals($options[$field['name']], $value);
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
	public function listPreprocessingSteps() {
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
