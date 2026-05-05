<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


use PHPUnit\Framework\TestCase;

class CTriggerConditionFunctionDataTest extends TestCase {

	/**
	 * Checks if all functions with their types are defined in all arrays.
	 */
	public function test_FunctionsMatch(): void {
		$desc_functions = array_keys(CTriggerConditionFunctionData::getDescriptions());
		$functions = CTriggerConditionFunctionData::getParameters();
		$value_types = CTriggerConditionFunctionData::getValueTypes();
		$rules = CTriggerConditionFunctionData::getValidationRules(false);

		$this->assertSame([], array_diff($desc_functions, array_keys($functions)),
			'Mismatched descriptions and functions'
		);
		$this->assertSame([], array_diff(array_keys($functions), $desc_functions),
			'Mismatched functions and descriptions'
		);
		$this->assertSame([], array_diff($desc_functions, array_keys($value_types)),
			'Mismatched descriptions and value types'
		);
		$this->assertSame([], array_diff(array_keys($value_types), $desc_functions),
			'Mismatched value types and descriptions'
		);
		$this->assertSame([], array_diff($desc_functions, array_keys($rules)), 'Mismatched descriptions and rules');
		$this->assertSame([], array_diff(array_keys($rules), $desc_functions), 'Mismatched rules and descriptions');

		foreach ($functions as $function => $types) {
			$this->assertSame(array_keys($value_types[$function]), array_keys($types),
				'Mismatched value types: ' . $function
			);
			$this->assertSame(array_keys($rules[$function]), array_keys($types), 'Mismatched rules: ' . $function);
		}
	}

	/**
	 * Checks if custom parameters are all defined.
	 */
	public function test_ParametersMatchesFields(): void {
		$fields = CTriggerConditionFunctionData::getParamsFields();
		$used_fields = [];
		$functions = CTriggerConditionFunctionData::getParameters();

		foreach ($functions as $function => $types) {
			foreach ($types as $type_config) {
				if (!array_key_exists('params', $type_config)) {
					continue;
				}

				foreach ($type_config['params'] as $key => $config) {
					if (in_array($key, ['last'])) {
						continue;
					}

					$used_fields[] = $key;
					$field_type = array_key_exists('options', $config)
						? CSelect::class
						: CTextBox::class;

					$this->assertTrue(array_key_exists($key, $fields), 'Missing param field: '. $key);
					$this->assertTrue(array_key_exists('type', $fields[$key]),
						'Missing param field attribute type: '. $key
					);
					$this->assertSame($fields[$key]['type'], $field_type,
						'Mismatch field type: ' . $function . ', ' . $key
					);

					if ($field_type === CSelect::class) {
						$this->assertSame([], array_diff($config['options'], array_keys($fields[$key]['options'])),
							'Missing options: ' . $function . ', ' . $key
						);
					}
				}
			}
		}

		$this->assertSame([], array_diff(array_keys($fields), array_unique($used_fields)), 'Unused fields');
	}

	/**
	 * Checks if parameter configuration doesn't go out of scope, that is accepted by validation rules.
	 */
	public function test_ParametersMatchValidationRules(): void {
		$functions = CTriggerConditionFunctionData::getParameters();
		$validation_rules = CTriggerConditionFunctionData::getValidationRules(false);

		foreach ($functions as $function => $types) {
			foreach ($types as $type => $type_config) {
				$this->assertTrue(array_key_exists($function, $validation_rules),
					'Validation rules for function not found: ' . $function
				);
				$this->assertTrue(array_key_exists($type, $validation_rules[$function]));
				$this->assertTrue(array_key_exists($function, $validation_rules),
					'Validation rules for function type not found: ' . $function . ',' . $type
				);
				$rules = $validation_rules[$function][$type];
				$this->testParameterRulesRecursive($function, $type_config, $rules);
			}
		}
	}

	private function testParameterRulesRecursive(string $function, array $parameters, array $rules): void {
		foreach ($parameters as $param => $param_config) {
			if ($param === 'params') {
				$this->testParameterRulesRecursive($function, $param_config, $rules[$param]['fields']);
			}
			else {
				$param_rules = is_array($rules[$param][0]) ? $rules[$param]: [$rules[$param]];
				$options = array_key_exists('options', $param_config) ? $param_config['options'] : null;
				$required = array_key_exists('required', $param_config) && $param_config['required'];

				foreach ($param_rules as $rule) {
					if ($required) {
						$this->assertTrue(in_array('required', $rule),
							'Missing required flag: ' . $function . ', ' . $param
						);
					}

					if ($options !== null) {
						$this->assertEqualsCanonicalizing($options, $rule['in'],
							'Mismatch IN rule to options: ' . $function . ', ' . $param
						);
					}
				}
			}
		}
	}

	/**
	 * Checks if there is new function that should be supported.
	 */
	public function test_HasForgottenFunctions (): void {
		$data_class = new CHistFunctionData();
		$function_names = array_keys($data_class->getParameters());

		$data_class = new CMathFunctionData();
		$function_names = array_values(array_merge($function_names, array_keys($data_class->getParameters())));

		$trigger_functions = array_keys(CTriggerConditionFunctionData::getDescriptions());

		$this->assertSame([], array_diff($function_names, $trigger_functions),
			'Missing functions for trigger conditions'
		);
		$this->assertSame([], array_diff($trigger_functions, $function_names),
			'Trigger condition has unsupported functions'
		);
	}
}
