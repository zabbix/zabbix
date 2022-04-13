<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerPopupTopHostsColumnEdit extends CController {

	protected $column_defaults = [
		'name'					=> '',
		'data'					=> CWidgetFieldColumnsList::DATA_ITEM_VALUE,
		'item'					=> '',
		'timeshift' 			=> '',
		'aggregate_function'	=> AGGREGATE_NONE,
		'aggregate_interval'	=> '1h',
		'display'				=> CWidgetFieldColumnsList::DISPLAY_AS_IS,
		'history'				=> CWidgetFieldColumnsList::HISTORY_DATA_AUTO,
		'min'					=> '',
		'max'					=> '',
		'base_color'			=> '',
		'text'					=> '',
		'thresholds'			=> []
	];

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		// Validation is done by CWidgetFieldColumnsList
		$fields = [
			'name'					=> 'string',
			'data'					=> 'int32',
			'item'					=> 'string',
			'timeshift'				=> 'string',
			'aggregate_function'	=> 'int32',
			'aggregate_interval'	=> 'string',
			'display'				=> 'int32',
			'history'				=> 'int32',
			'min'					=> 'string',
			'max'					=> 'string',
			'base_color'			=> 'string',
			'thresholds'			=> 'array',
			'text'					=> 'string',
			'edit'					=> 'in 1',
			'update'				=> 'in 1'
		];

		$ret = $this->validateInput($fields) && $this->validateFields($this->getInputAll());

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function validateFields(array $input): bool {
		$field = new CWidgetFieldColumnsList('columns', '');

		if (!$this->hasInput('edit') && !$this->hasInput('update')) {
			$input += $this->column_defaults;
		}

		unset($input['edit'], $input['update']);
		$field->setValue([$input]);
		$errors = $field->validate();
		array_map('error', $errors);

		return !$errors;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$input = $this->getInputAll();
		unset($input['update']);

		if (!$this->hasInput('update')) {
			$this->setResponse(new CControllerResponseData([
				'action'			=> $this->getAction(),
				'thresholds_colors'	=> CWidgetFieldColumnsList::THRESHOLDS_DEFAULT_COLOR_PALETTE,
				'errors' 			=> hasErrorMessages() ? getMessages() : null,
				'user' 				=> [
					'debug_mode' => $this->getDebugMode()
				]
			] + $input + $this->column_defaults));

			return;
		}

		$number_parser = new CNumberParser(['with_size_suffix' => true, 'with_time_suffix' => true]);

		$thresholds = [];

		if (array_key_exists('thresholds', $input)) {
			foreach ($input['thresholds'] as $threshold) {
				$order_threshold = trim($threshold['threshold']);

				if ($order_threshold !== '' && $number_parser->parse($order_threshold) == CParser::PARSE_SUCCESS) {
					$thresholds[] = $threshold + ['order_threshold' => $number_parser->calcValue()];
				}
			}

			unset($input['thresholds']);
		}

		if ($thresholds) {
			CArrayHelper::sort($thresholds, ['order_threshold']);

			$input['thresholds'] = [];

			foreach ($thresholds as $threshold) {
				unset($threshold['order_threshold']);

				$input['thresholds'][] = $threshold;
			}
		}

		$this->setResponse((new CControllerResponseData(['main_block' => json_encode($input)]))->disableView());
	}
}
