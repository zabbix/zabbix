<?php declare(strict_types = 0);
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


namespace Widgets\TopItems\Actions;

use CController,
	CControllerResponseData,
	CNumberParser,
	CParser,
	CWidgetsData;

use Widgets\TopItems\Includes\CWidgetFieldColumnsList;
use Zabbix\Widgets\CWidgetField;

use Zabbix\Widgets\Fields\{
	CWidgetFieldPatternSelectItem,
	CWidgetFieldTags,
	CWidgetFieldTimePeriod
};

class ColumnEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		// Validation is done by CWidgetFieldColumnsList
		$fields = [
			'items' =>					'array',
			'item_tags_evaltype' =>		'int32',
			'item_tags' =>				'array',
			'base_color' =>				'string',
			'display_value_as' =>		'int32',
			'display' =>				'int32',
			'sparkline' =>				'array',
			'min' =>					'string',
			'max' =>					'string',
			'thresholds' =>				'array',
			'highlights' =>				'array',
			'decimal_places' =>			'string',
			'aggregate_function' =>		'int32',
			'time_period' =>			'array',
			'history' =>				'int32',
			'edit' =>					'in 1',
			'update' =>					'in 1',
			'templateid' =>				'string'
		];

		$ret = $this->validateInput($fields) && $this->validateFields();

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

	protected function validateFields(): bool {
		if (!$this->hasInput('update')) {
			return true;
		}

		$input = $this->getInputAll();

		$field = new CWidgetFieldColumnsList('columns', '');

		if (!$this->hasInput('edit') && !$this->hasInput('update')) {
			$input += self::getColumnDefaults();
			$input['sparkline'] = array_replace(CWidgetFieldColumnsList::SPARKLINE_DEFAULT, $input['sparkline']);
		}

		unset($input['edit'], $input['update'], $input['templateid']);

		if (array_key_exists('item_tags', $input)) {
			foreach ($input['item_tags'] as $tag_index => $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					unset($input['item_tags'][$tag_index]);
				}
			}
		}

		$field->setValue([$input]);

		$errors = $field->validate(true);
		array_map('error', $errors);

		return !$errors;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$input = $this->getInputAll();
		unset($input['update']);

		if (!$this->hasInput('update')) {
			$data = [
				'action' => $this->getAction(),
				'color_palette' => CWidgetFieldColumnsList::THRESHOLDS_DEFAULT_COLOR_PALETTE,
				'templateid' => $this->hasInput('templateid') ? $this->getInput('templateid') : null,
				'errors' => hasErrorMessages() ? getMessages() : null,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			] + $input + self::getColumnDefaults();

			$data['sparkline'] = array_replace(CWidgetFieldColumnsList::SPARKLINE_DEFAULT, $data['sparkline']);

			$data['time_period_field'] = (new CWidgetFieldTimePeriod('time_period', _('Time period')))
				->setDefaultPeriod(['from' => 'now-1h', 'to' => 'now'])
				->setInType(CWidgetsData::DATA_TYPE_TIME_PERIOD)
				->acceptDashboard()
				->acceptWidget()
				->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
				->setValue($data['time_period']);

			$data['item_tags_field'] = (new CWidgetFieldTags('item_tags'))->setValue($data['item_tags']);

			$data['item_items_field'] = (new CWidgetFieldPatternSelectItem('items', _('Item patterns')))
				->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
				->setValue($data['items']);

			$this->setResponse(new CControllerResponseData($data));
		}
		else {
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

	private static function getColumnDefaults(): array {
		static $column_defaults;

		if ($column_defaults === null) {
			$column_defaults = [
				'items' => [],
				'item_tags_evaltype' => TAG_EVAL_TYPE_AND_OR,
				'item_tags' => [],
				'base_color' => '',
				'display_value_as' => CWidgetFieldColumnsList::DISPLAY_VALUE_AS_NUMERIC,
				'display' => CWidgetFieldColumnsList::DISPLAY_AS_IS,
				'sparkline' => CWidgetFieldColumnsList::SPARKLINE_DEFAULT,
				'min' => '',
				'max' => '',
				'highlights' => [],
				'thresholds' => [],
				'decimal_places' => CWidgetFieldColumnsList::DEFAULT_DECIMAL_PLACES,
				'aggregate_function' => AGGREGATE_NONE,
				'time_period' => [
					CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
						CWidgetField::REFERENCE_DASHBOARD, CWidgetsData::DATA_TYPE_TIME_PERIOD
					)
				],
				'history' => CWidgetFieldColumnsList::HISTORY_DATA_AUTO
			];
		}

		return $column_defaults;
	}
}
