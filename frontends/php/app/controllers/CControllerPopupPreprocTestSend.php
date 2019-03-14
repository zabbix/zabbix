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
 * Controller to perform preprocessing test send action.
 */
class CControllerPopupPreprocTestSend extends CControllerPopupPreprocTest {

	protected function checkInput() {
		$fields = [
			'hostid' => 'db hosts.hostid',
			'value_type' => 'in '.implode(',', [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_LOG, ITEM_VALUE_TYPE_TEXT]),
			'test_type' => 'in '.implode(',', [self::ZBX_TEST_TYPE_ITEM, self::ZBX_TEST_TYPE_ITEM_PROTOTYPE, self::ZBX_TEST_TYPE_LLD]),
			'steps' => 'required|array',
			'macros' => 'array',
			'value' => 'string',
			'prev_value' => 'string',
			'prev_time' => 'string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$this->preproc_item = self::getPreprocessingItemType($this->getInput('test_type'));

			if (($err = $this->preproc_item->validateItemPreprocessingSteps($this->getInput('steps'))) !== true) {
				error($err);
			}
		}

		if (($messages = getMessages()) !== null) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => CJs::encodeJson([
						'messages' => $messages->toString(),
						'steps' => [],
						'user' => [
							'debug_mode' => $this->getDebugMode()
						]
					])
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function doAction() {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$data = [
			'value' => $this->getInput('value'),
			'value_type' => $this->getInput('value_type', ITEM_VALUE_TYPE_STR),
			'steps' => $this->getInput('steps')
		];

		$support_lldmacros = ($this->preproc_item instanceof CItemPrototype);
		$preprocessing_types = zbx_objectValues($data['steps'], 'type');
		$macros_posted = $this->getInput('macros', []);

		$macros_types = $support_lldmacros
			? ['usermacros' => true, 'lldmacros' => true]
			: ['usermacros' => true];

		// Get previous value and time.
		if (count(array_intersect($preprocessing_types, self::$preproc_steps_using_prev_value)) > 0) {
			if (($prev_time = $this->getInput('prev_time', '')) === '') {
				$prev_time = 'now-'.ZBX_ITEM_DELAY_DEFAULT;
			}

			$data += [
				'history' => [
					'value' => $this->getInput('prev_value', ''),
					'timestamp' => $prev_time
				]
			];
		}

		// Resolve macros used in parameter fields.
		foreach ($data['steps'] as &$step) {
			foreach (['params', 'error_handler_params'] as $field) {
				$matched_macros = (new CMacrosResolverGeneral)->getMacroPositions($step[$field], $macros_types);

				foreach (array_reverse($matched_macros, true) as $pos => $macro) {
					$macro_value = array_key_exists($macro, $macros_posted)
						? $macros_posted[$macro]
						: '';

					$step[$field] = substr_replace($step[$field], $macro_value, $pos, strlen($macro));
				}
			}
		}
		unset($step);

		$msg_status = true;
		$output = [
			'steps' => [],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		// Send test details to Zabbix server.
		$server = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT);
		$result = $server->testPreprocessingSteps($data, get_cookie('zbx_sessionid'));

		if ($result === false) {
			error($server->getError());
			$msg_status = false;
		}
		elseif (is_array($result)) {
			$test_failed = false;
			foreach ($data['steps'] as $i => &$step) {
				if ($test_failed) {
					// If test is failed, proceesing steps are skipped from results.
					unset($data['steps'][$i]);
					continue;
				}
				elseif (array_key_exists($i, $result)) {
					$step += $result[$i];

					if (array_key_exists('error', $step)) {
						// If error happened and no value is set, frontend shows label 'No value'.
						if (!array_key_exists('action', $step) || $step['action'] != ZBX_PREPROC_FAIL_SET_VALUE) {
							unset($step['result']);
							$test_failed = true;
						}
					}
				}

				unset($step['type']);
				unset($step['params']);
				unset($step['error_handler']);
				unset($step['error_handler_params']);
			}
			unset($step);
		}

		$output['steps'] = $data['steps'];

		if (($messages = getMessages($msg_status)) !== null) {
			$output['messages'] = $messages->toString();
		}

		$this->setResponse((new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView());
	}
}
