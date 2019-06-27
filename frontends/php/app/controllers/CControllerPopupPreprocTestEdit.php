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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Controller to build preprocessing test dialog.
 */
class CControllerPopupPreprocTestEdit extends CControllerPopupPreprocTest {

	protected function checkInput() {
		$fields = [
			'hostid' => 'db hosts.hostid',
			'value_type' => 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT]),
			'test_type' => 'in '.implode(',', [self::ZBX_TEST_TYPE_ITEM, self::ZBX_TEST_TYPE_ITEM_PROTOTYPE, self::ZBX_TEST_TYPE_LLD]),
			'steps' => 'required|array',
			'delay' => 'string',
			'data' => 'array',
			'step_obj' => 'required|int32',
			'show_final_result' => 'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$this->preproc_item = self::getPreprocessingItemType($this->getInput('test_type'));

			if (($error = $this->preproc_item->validateItemPreprocessingSteps($this->getInput('steps'))) !== true) {
				$ret = false;
				error($error);
			}
		}

		if (($messages = getMessages(false, null, false)) !== null) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => CJs::encodeJson(['errors' => $messages->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function doAction() {
		$preprocessing_steps = $this->getInput('steps');
		$preprocessing_types = zbx_objectValues($preprocessing_steps, 'type');
		$preprocessing_names = get_preprocessing_types(null, false, $preprocessing_types);
		$support_lldmacros = ($this->preproc_item instanceof CItemPrototype);
		$show_prev = (count(array_intersect($preprocessing_types, self::$preproc_steps_using_prev_value)) > 0);
		$data = $this->getInput('data', []);

		// Extract macros and get effective values.
		$usermacros = CMacrosResolverHelper::extractMacrosFromPreprocessingSteps([
			'steps' => $preprocessing_steps,
			'hostid' => $this->getInput('hostid', 0),
			'delay' => $show_prev ? $this->getInput('delay', ZBX_ITEM_DELAY_DEFAULT) : ''
		], $support_lldmacros);

		// Set resolved macros to previously specified values.
		if ($usermacros['macros'] && array_key_exists('macros', $data) && is_array($data['macros'])) {
			foreach (array_keys($usermacros['macros']) as $macro_name) {
				if (array_key_exists($macro_name, $data['macros']))
				$usermacros['macros'][$macro_name] = $data['macros'][$macro_name];
			}
		}

		// Get previous value time.
		if ($show_prev && array_key_exists('prev_time', $data)) {
			$prev_time = $data['prev_time'];
		}
		elseif ($show_prev) {
			$delay = timeUnitToSeconds($usermacros['delay']);
			$prev_time = ($delay !== null && $delay > 0)
				? 'now-'.$usermacros['delay']
				: 'now';
		}
		else {
			$prev_time = '';
		}

		// Sort macros.
		ksort($usermacros['macros']);

		// Add step number and name for each preprocessing step.
		$num = 0;
		foreach ($preprocessing_steps as &$step) {
			$step['name'] = $preprocessing_names[$step['type']];
			$step['num'] = ++$num;
		}
		unset($step);

		$this->setResponse(new CControllerResponseData([
			'title' => _('Test item preprocessing'),
			'steps' => $preprocessing_steps,
			'value' => array_key_exists('value', $data) ? $data['value'] : '',
			'eol' => array_key_exists('eol', $data) ? (int) $data['eol'] : ZBX_EOL_LF,
			'prev_value' => ($show_prev && array_key_exists('prev_value', $data)) ? $data['prev_value'] : '',
			'macros' => $usermacros['macros'],
			'show_prev' => $show_prev,
			'prev_time' => $prev_time,
			'hostid' => $this->getInput('hostid'),
			'value_type' => $this->getInput('value_type'),
			'test_type' => $this->getInput('test_type'),
			'step_obj' => $this->getInput('step_obj'),
			'show_final_result' => $this->getInput('show_final_result'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
