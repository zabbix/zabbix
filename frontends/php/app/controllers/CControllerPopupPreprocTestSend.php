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

	/**
	 * @var bool
	 */
	protected $use_prev_value;

	/**
	 * Time suffixes supported by Zabbix server.
	 *
	 * @var array
	 */
	protected static $supported_time_suffixes = ['w', 'd', 'h', 'm', 's'];

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
			$steps = $this->getInput('steps');
			$prepr_types = zbx_objectValues($steps, 'type');
			$this->preproc_item = self::getPreprocessingItemType($this->getInput('test_type'));
			$this->use_prev_value = (count(array_intersect($prepr_types, self::$preproc_steps_using_prev_value)) > 0);

			// Check preprocessing steps.
			if (($error = $this->preproc_item->validateItemPreprocessingSteps($steps)) !== true) {
				error($error);
			}

			// Check previous time.
			if ($this->use_prev_value) {
				$prev_time = $this->getInput('prev_time', '');

				$relative_time_parser = new CRelativeTimeParser();
				if ($relative_time_parser->parse($prev_time) != CParser::PARSE_SUCCESS) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('Prev. time'),
						_('a relative time is expected')
					));
				}
				else {
					foreach ($relative_time_parser->getTokens() as $token) {
						if ($token['type'] == CRelativeTimeParser::ZBX_TOKEN_PRECISION) {
							error(_s('Incorrect value for field "%1$s": %2$s.', _('Prev. time'),
								_('a relative time is expected')
							));
							break;
						}
						elseif (!in_array($token['suffix'], self::$supported_time_suffixes)) {
							error(_s('Incorrect value for field "%1$s": %2$s.', _('Prev. time'),
								_('unsupported time suffix')
							));
							break;
						}
					}
				}
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
			$ret = false;
		}

		return $ret;
	}

	protected function doAction() {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$data = [
			'value' => $this->getInput('value', ''),
			'value_type' => $this->getInput('value_type', ITEM_VALUE_TYPE_STR),
			'steps' => $this->getInput('steps')
		];

		// Resolve macros used in parameter fields.
		$macros_posted = $this->getInput('macros', []);
		$macros_types = ($this->preproc_item instanceof CItemPrototype)
			? ['usermacros' => true, 'lldmacros' => true]
			: ['usermacros' => true];

		foreach ($data['steps'] as &$step) {
			/**
			 * Values received from html form may be transformed so we must removed redundant "\r" before sending data
			 * to Zabbix server.
			 */
			$step['params'] = str_replace("\r\n", "\n", $step['params']);

			// Resolve macros in parameter fields before send data to Zabbix server.
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

		// Get previous value and time.
		if ($this->use_prev_value) {
			$data += [
				'history' => [
					'value' => $this->getInput('prev_value', ''),
					'timestamp' => $this->getInput('prev_time')
				]
			];
		}

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

			$output['steps'] = $data['steps'];
		}

		if (($messages = getMessages(false)) !== null) {
			$output['messages'] = $messages->toString();
		}

		$this->setResponse((new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView());
	}
}
