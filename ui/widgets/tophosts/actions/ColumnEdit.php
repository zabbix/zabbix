<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Widgets\TopHosts\Actions;

use CController,
	CControllerResponseData,
	CNumberParser,
	CParser;

use Zabbix\Widgets\Fields\CWidgetFieldColumnsList;

class ColumnEdit extends CController {

	protected array $column_defaults = [
		'name' => '',
		'data' => CWidgetFieldColumnsList::DATA_ITEM_VALUE,
		'item' => '',
		'timeshift' => '',
		'aggregate_function' => AGGREGATE_NONE,
		'aggregate_interval' => '1h',
		'display' => CWidgetFieldColumnsList::DISPLAY_AS_IS,
		'history' => CWidgetFieldColumnsList::HISTORY_DATA_AUTO,
		'min' => '',
		'max' => '',
		'decimal_places' => CWidgetFieldColumnsList::DEFAULT_DECIMAL_PLACES,
		'base_color' => '',
		'text' => '',
		'thresholds' => []
	];

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		// Validation is done by CWidgetFieldColumnsList
		$fields = [
			'name' => 'string',
			'data' => 'int32',
			'item' => 'string',
			'timeshift' => 'string',
			'aggregate_function' => 'int32',
			'aggregate_interval' => 'string',
			'display' => 'int32',
			'history' => 'int32',
			'min' => 'string',
			'max' => 'string',
			'decimal_places' => 'string',
			'base_color' => 'string',
			'thresholds' => 'array',
			'text' => 'string',
			'edit' => 'in 1',
			'update' => 'in 1',
			'templateid' => 'string'
		];

		$ret = $this->validateInput($fields) && $this->validateFields($this->getInputAll());

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				], JSON_THROW_ON_ERROR)]))->disableView()
			);
		}

		return $ret;
	}

	protected function validateFields(array $input): bool {
		$field = new CWidgetFieldColumnsList('columns', '');

		if (!$this->hasInput('edit') && !$this->hasInput('update')) {
			$input += $this->column_defaults;
		}

		unset($input['edit'], $input['update'], $input['templateid']);
		$field->setValue([$input]);

		if (!$this->hasInput('update')) {
			return true;
		}

		$errors = $field->validate();
		array_map('error', $errors);

		return !$errors;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction(): void {
		$input = $this->getInputAll();
		unset($input['update']);

		if (!$this->hasInput('update')) {
			$this->setResponse(new CControllerResponseData([
				'action' => $this->getAction(),
				'thresholds_colors' => CWidgetFieldColumnsList::THRESHOLDS_DEFAULT_COLOR_PALETTE,
				'templateid' => $this->hasInput('templateid') ? $this->getInput('templateid') : null,
				'errors' => hasErrorMessages() ? getMessages() : null,
				'user' => [
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
			uasort($thresholds,
				static function (array $threshold_1, array $threshold_2): int {
					return $threshold_1['order_threshold'] <=> $threshold_2['order_threshold'];
				}
			);

			$input['thresholds'] = [];

			foreach ($thresholds as $threshold) {
				unset($threshold['order_threshold']);

				$input['thresholds'][] = $threshold;
			}
		}

		$this->setResponse(
			(new CControllerResponseData(['main_block' => json_encode($input, JSON_THROW_ON_ERROR)]))->disableView()
		);
	}
}
