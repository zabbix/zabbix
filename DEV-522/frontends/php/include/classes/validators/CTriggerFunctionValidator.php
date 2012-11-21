<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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

class CTriggerFunctionValidator extends CValidator {
	/**
	 * The array containing valid functions and parameres to them
	 *
	 * Structure: array(
	 *   '<function>' => array(
	 *     'args' => array(
	 *       array('type' => '<parameter_type>'[, 'mandat' => bool]),
	 *       ...
	 *     ),
	 *     'value_types' => array(<value_type>, <value_type>, ...)
	 *   )
	 * )
	 *
	 * <parameter_type> can be 'sec', 'sec_num' or 'str'
	 * <value_type> can be one of ITEM_VALUE_TYPE_*
	 *
	 * @var array
	 */
	private $allowed;

	/**
	 * Validate trigger function like last(0), time(), etc.
	 * Examples:
	 *   array('functionName' => 'last', 'functionParamList' => array(0 => '#15'), 'valueType' => 3)
	 *
	 * @param array $value
	 * @param string $value['functionName']
	 * @param array $value['functionParamList']
	 * @param int $value['valueType']
	 *
	 * @return bool
	 */
	public function validate($value) {
		$this->setError('');

		if (!isset($this->allowed[$value['functionName']])) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).' '.
					_('Unknown function.'));
			return false;
		}

		if (!isset($this->allowed[$value['functionName']]['value_types'][$value['valueType']])) {
			$this->setError(_s('Incorrect item value type provided for trigger function "%1$s".', $value['functionName']));
			return false;
		}

		if (count($this->allowed[$value['functionName']]['args']) < count($value['functionParamList'])) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).' '.
					_s('Function supports "%1$s" parameters.', count($this->allowed[$value['functionName']]['args'])));
			return false;
		}

		foreach ($this->allowed[$value['functionName']]['args'] as $aNum => $arg) {
			// mandatory check
			if (isset($arg['mandat']) && $arg['mandat'] && !isset($value['functionParamList'][$aNum])) {
				$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).' '.
						_('Mandatory parameter is missing.'));
				return false;
			}

			// type check
			if (isset($arg['type']) && isset($value['functionParamList'][$aNum])) {
				$userMacro = preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/', $value['functionParamList'][$aNum]);

				if (!$userMacro) {
					switch ($arg['type']) {
						case 'str':
							if (!is_string($value['functionParamList'][$aNum])) {
								$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).' '.
										_s('Parameter of type string or user macro expected, "%1$s" given.', $value['functionParamList'][$aNum]));
								return false;
							}
							break;
						case 'sec':
							if (!$this->validateSec($value['functionParamList'][$aNum])) {
								$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).' '.
										_s('Parameter sec or user macro expected, "%1$s" given.', $value['functionParamList'][$aNum]));
								return false;
							}
							break;
						case 'sec_num':
							if (!$this->validateSecNum($value['functionParamList'][$aNum])) {
								$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).' '.
										_s('Parameter sec or #num or user macro expected, "%1$s" given.', $value['functionParamList'][$aNum]));
								return false;
							}
							break;
						case 'num':
							if (!is_numeric($value['functionParamList'][$aNum])) {
								$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).' '.
										_s('Parameter num or user macro expected, "%1$s" given.', $value['functionParamList'][$aNum]));
								return false;
							}
							break;
					}
				}
			}
		}
		return true;
	}

	/**
	 * Validate trigger function parameter which can contain only seconds
	 * Examples:
	 *   5
	 *   1w
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateSec($param) {
		return preg_match('/^\d+['.ZBX_TIME_SUFFIXES.']{0,1}$/', $param) == 1;
	}

	/**
	 * Validate trigger function parameter which can contain seconds or count
	 * Examples:
	 *   5
	 *   1w
	 *   #5
	 *
	 * @param string $param
	 *
	 * @return bool
	 */
	private function validateSecNum($param) {
		if (preg_match('/^#\d+$/', $param)) {
			return true;
		}
		return $this->validateSec($param);
	}

	protected function initOptions() {
		$valueTypesAll = array(
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_UINT64 => true,
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_TEXT => true,
			ITEM_VALUE_TYPE_LOG => true
		);
		$valueTypesNum = array(
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_UINT64 => true
		);
		$valueTypesChar = array(
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_TEXT => true,
			ITEM_VALUE_TYPE_LOG => true
		);
		$valueTypesLog = array(
			ITEM_VALUE_TYPE_LOG => true
		);

		$argsIgnored = array(array('type' => 'str'));

		$this->allowed = array(
			'abschange' => array(
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			),
			'avg' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $valueTypesNum
			),
			'change' => array(
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			),
			'count' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'str'),
					array('type' => 'str'),
					array('type' => 'sec')
				),
				'value_types' => $valueTypesAll
			),
			'date' => array(
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			),
			'dayofmonth' => array(
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			),
			'dayofweek' => array(
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			),
			'delta' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $valueTypesNum
			),
			'diff' => array(
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			),
			'fuzzytime' => array(
				'args' => array(
					array('type' => 'sec', 'mandat' => true)
				),
				'value_types' => $valueTypesNum
			),
			'iregexp' => array(
				'args' => array(
					array('type' => 'str', 'mandat' => true),
					array('type' => 'sec_num')
				),
				'value_types' => $valueTypesChar
			),
			'last' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $valueTypesAll
			),
			'logeventid' => array(
				'args' => array(
					array('type' => 'str', 'mandat' => true)
				),
				'value_types' => $valueTypesLog
			),
			'logseverity' => array(
				'args' => $argsIgnored,
				'value_types' => $valueTypesLog
			),
			'logsource' => array(
				'args' => array(
					array('type' => 'str', 'mandat' => true)
				),
				'value_types' => $valueTypesLog
			),
			'max' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $valueTypesNum
			),
			'min' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $valueTypesNum
			),
			'nodata'=> array(
				'args' => array(
					array('type' => 'sec', 'mandat' => true)
				),
				'value_types' => $valueTypesAll
			),
			'now' => array(
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			),
			'prev' => array(
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			),
			'regexp' => array(
				'args' => array(
					array('type' => 'str', 'mandat' => true),
					array('type' => 'sec_num')
				),
				'value_types' => $valueTypesChar
			),
			'str' => array(
				'args' => array(
					array('type' => 'str', 'mandat' => true),
					array('type' => 'sec_num')
				),
				'value_types' => $valueTypesChar
			),
			'strlen' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $valueTypesChar
			),
			'sum' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $valueTypesNum
			),
			'time' => array(
				'args' => $argsIgnored,
				'value_types' => $valueTypesAll
			)
		);
	}
}
