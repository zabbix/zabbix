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
	private $allowed;

	/**
	 * Validate trigger function like last(0), time(), etc.
	 * Examples:
	 *   array('functionName' => 'last', 'functionParamList' => array(0 => '#15'), 'valueType' => 3)
	 *
	 * @param array $value
	 *
	 * @return bool
	 */
	public function validate($value) {
		$this->setError('');

		if (!isset($this->allowed[$value['functionName']])) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).SPACE.
					_('Unknown function.'));
			return false;
		}

		if (!isset($this->allowed[$value['functionName']]['value_types'][$value['valueType']])) {
			$this->setError(_s('Incorrect item value type provided for trigger function "%1$s".', $value['functionName']));
			return false;
		}

		if (count($this->allowed[$value['functionName']]['args']) < count($value['functionParamList'])) {
			$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).SPACE.
					_s('Function supports "%1$s" parameters.', count($this->allowed[$value['functionName']]['args'])));
			return false;
		}

		foreach ($this->allowed[$value['functionName']]['args'] as $anum => $arg) {
			// mandatory check
			if (isset($arg['mandat']) && $arg['mandat'] && !isset($value['functionParamList'][$anum])) {
				$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).SPACE.
						_('Mandatory parameter is missing.'));
				return false;
			}

			// type check
			if (isset($arg['type']) && isset($value['functionParamList'][$anum])) {
				$userMacro = preg_match('/^'.ZBX_PREG_EXPRESSION_USER_MACROS.'$/', $value['functionParamList'][$anum]);

				if (!$userMacro) {
					switch ($arg['type']) {
						case 'str':
							if (!is_string($value['functionParamList'][$anum])) {
								$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).SPACE.
										_s('Parameter of type string or user macro expected, "%1$s" given.', $value['functionParamList'][$anum]));
								return false;
							}
							break;
						case 'sec':
							if (!$this->validateSec($value['functionParamList'][$anum])) {
								$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).SPACE.
										_s('Parameter sec or user macro expected, "%1$s" given.', $value['functionParamList'][$anum]));
								return false;
							}
							break;
						case 'sec_num':
							if (!$this->validateSecNum($value['functionParamList'][$anum])) {
								$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).SPACE.
										_s('Parameter sec or #num or user macro expected, "%1$s" given.', $value['functionParamList'][$anum]));
								return false;
							}
							break;
						case 'num':
							if (!is_numeric($value['functionParamList'][$anum])) {
								$this->setError(_s('Incorrect trigger function "%1$s" provided in expression.', $value['functionName']).SPACE.
										_s('Parameter num or user macro expected, "%1$s" given.', $value['functionParamList'][$anum]));
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
		return preg_match('/^[ ]*\d+['.ZBX_TIME_SUFFIXES.']{0,1}[ ]*$/', $param, $arr) == 1;
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
		if (preg_match('/^[ ]*#\d+[ ]*$/', $param, $arr)) {
			return true;
		}
		return $this->validateSec($param);
	}

	protected function initOptions() {
		$value_types_all = array(
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_UINT64 => true,
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_TEXT => true,
			ITEM_VALUE_TYPE_LOG => true
		);
		$value_types_num = array(
			ITEM_VALUE_TYPE_FLOAT => true,
			ITEM_VALUE_TYPE_UINT64 => true
		);
		$value_types_char = array(
			ITEM_VALUE_TYPE_STR => true,
			ITEM_VALUE_TYPE_TEXT => true,
			ITEM_VALUE_TYPE_LOG => true
		);
		$value_types_log = array(
			ITEM_VALUE_TYPE_LOG => true
		);

		$args_ignored = array(array('type' => 'str'));

		$this->allowed = array(
			'abschange' => array(
				'args' => $args_ignored,
				'value_types' => $value_types_all
			),
			'avg' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $value_types_num
			),
			'change' => array(
				'args' => $args_ignored,
				'value_types' => $value_types_all
			),
			'count' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'str'),
					array('type' => 'str'),
					array('type' => 'sec')
				),
				'value_types' => $value_types_all
			),
			'date' => array(
				'args' => $args_ignored,
				'value_types' => $value_types_all
			),
			'dayofmonth' => array(
				'args' => $args_ignored,
				'value_types' => $value_types_all
			),
			'dayofweek' => array(
				'args' => $args_ignored,
				'value_types' => $value_types_all
			),
			'delta' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $value_types_num
			),
			'diff' => array(
				'args' => $args_ignored,
				'value_types' => $value_types_all
			),
			'fuzzytime' => array(
				'args' => array(
					array('type' => 'sec', 'mandat' => true)
				),
				'value_types' => $value_types_num
			),
			'iregexp' => array(
				'args' => array(
					array('type' => 'str', 'mandat' => true),
					array('type' => 'sec_num')
				),
				'value_types' => $value_types_char
			),
			'last' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $value_types_all
			),
			'logeventid' => array(
				'args' => array(
					array('type' => 'str', 'mandat' => true)
				),
				'value_types' => $value_types_log
			),
			'logseverity' => array(
				'args' => $args_ignored,
				'value_types' => $value_types_log
			),
			'logsource' => array(
				'args' => array(
					array('type' => 'str', 'mandat' => true)
				),
				'value_types' => $value_types_log
			),
			'max' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $value_types_num
			),
			'min' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $value_types_num
			),
			'nodata'=> array(
				'args' => array(
					array('type' => 'sec', 'mandat' => true)
				),
				'value_types' => $value_types_all
			),
			'now' => array(
				'args' => $args_ignored,
				'value_types' => $value_types_all
			),
			'prev' => array(
				'args' => $args_ignored,
				'value_types' => $value_types_all
			),
			'regexp' => array(
				'args' => array(
					array('type' => 'str', 'mandat' => true),
					array('type' => 'sec_num')
				),
				'value_types' => $value_types_char
			),
			'str' => array(
				'args' => array(
					array('type' => 'str', 'mandat' => true),
					array('type' => 'sec_num')
				),
				'value_types' => $value_types_char
			),
			'strlen' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $value_types_char
			),
			'sum' => array(
				'args' => array(
					array('type' => 'sec_num', 'mandat' => true),
					array('type' => 'sec')
				),
				'value_types' => $value_types_num
			),
			'time' => array(
				'args' => $args_ignored,
				'value_types' => $value_types_all
			)
		);
	}
}
