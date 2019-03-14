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
			'delay' => 'string'
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

		$macros_parser_types = $support_lldmacros
			? ['usermacros' => true, 'lldmacros' => true]
			: ['usermacros' => true];

		if ($show_prev && ($delay = $this->getInput('delay')) === '') {
			$delay = ZBX_ITEM_DELAY_DEFAULT;
		}

		// Extract delay macro.
		$delay_macro = [];
		if ($show_prev && $delay[0] === '{') {
			$update_interval_parser = new CUpdateIntervalParser($macros_parser_types);

			if ($update_interval_parser->parse($this->getInput('delay')) == CParser::PARSE_SUCCESS) {
				$delay_macro = [$update_interval_parser->getDelay()];
			}
		}

		// Add step number and name for each preprocessing step.
		$num = 0;
		foreach ($preprocessing_steps as &$step) {
			$step['name'] = $preprocessing_names[$step['type']];
			$step['num'] = ++$num;
		}
		unset($step);

		// Extract macros from parameter property.
		$parameters = [];
		foreach ($preprocessing_steps as $step) {
			if ($step['params'] !== '') {
				$parameters[] = $step['params'];
			}
			if ($step['error_handler_params'] !== '') {
				$parameters[] = $step['error_handler_params'];
			}
		}
		$matched_macros = $parameters ?
			(new CMacrosResolverGeneral)->extractMacros($parameters, $macros_parser_types)
			: ['usermacros' => [], 'lldmacros' => []];

		// Select user macros values from database.
		$macros_to_resolve = array_merge(array_keys($matched_macros['usermacros']), $delay_macro);
		$db_macros = ($macros_to_resolve && $this->getInput('hostid', 0) != 0)
			? API::UserMacro()->get([
				'output' => ['macro', 'value'],
				'hostids' => $this->getInput('hostid'),
				'filter' => [
					'macro' => $macros_to_resolve
				]
			])
			: [];

		$db_macros_map = [];
		foreach ($db_macros as $db_macro) {
			$db_macros_map[$db_macro['macro']] = $db_macro['value'];
		}
		$db_macros = $db_macros_map;
		unset($db_macros_map);

		// Combine selected macros and apply values selected from database.
		$macros = $support_lldmacros
			? $matched_macros['usermacros'] + $matched_macros['lldmacros']
			: $matched_macros['usermacros'];
		ksort($macros);

		foreach ($macros as $macro_name => &$macro_value) {
			$macro_value = array_key_exists($macro_name, $db_macros)
				? $db_macros[$macro_name]
				: '';
		}
		unset($macro_value);

		// Get previous value time.
		if ($show_prev) {
			if ($delay_macro && array_key_exists($delay_macro[0], $db_macros)) {
				$delay = $db_macros[$delay_macro[0]];
			}

			$prev_time = (timeUnitToSeconds($delay) > 0)
				? 'now-'.$delay
				: 'now';
		}
		else {
			$prev_time = '';
		}

		$this->setResponse(new CControllerResponseData([
			'title' => _('Test item preprocessing'),
			'errors' => hasErrorMesssages() ? getMessages() : null,
			'steps' => $preprocessing_steps,
			'macros' => $macros,
			'show_prev' => $show_prev,
			'prev_time' => $prev_time,
			'hostid' => $this->getInput('hostid'),
			'value_type' => $this->getInput('value_type'),
			'test_type' => $this->getInput('test_type'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
