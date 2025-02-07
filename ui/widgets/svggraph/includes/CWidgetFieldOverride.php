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


namespace Widgets\SvgGraph\Includes;

use Zabbix\Widgets\CWidgetField;

/**
 * Class for override widget field used in Graph widget configuration overrides tab.
 */
class CWidgetFieldOverride extends CWidgetField {

	public const DEFAULT_VIEW = CWidgetFieldOverrideView::class;
	public const DEFAULT_VALUE = [];

	public function __construct(string $name, ?string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setValidationRules(['type' => API_OBJECTS, 'fields' => [
				'hosts'				=> ['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED],
				'items'				=> ['type' => API_STRINGS_UTF8, 'flags' => API_REQUIRED],
				'color'				=> ['type' => API_COLOR],
				'type'				=> ['type' => API_INT32, 'in' => implode(',', [SVG_GRAPH_TYPE_LINE, SVG_GRAPH_TYPE_POINTS, SVG_GRAPH_TYPE_STAIRCASE, SVG_GRAPH_TYPE_BAR])],
				'width'				=> ['type' => API_INT32, 'in' => implode(',', range(0, 10))],
				'pointsize'			=> ['type' => API_INT32, 'in' => implode(',', range(1, 10))],
				'transparency'		=> ['type' => API_INT32, 'in' => implode(',', range(0, 10))],
				'fill'				=> ['type' => API_INT32, 'in' => implode(',', range(0, 10))],
				'missingdatafunc'	=> ['type' => API_INT32, 'in' => implode(',', [SVG_GRAPH_MISSING_DATA_NONE, SVG_GRAPH_MISSING_DATA_CONNECTED, SVG_GRAPH_MISSING_DATA_TREAT_AS_ZERO, SVG_GRAPH_MISSING_DATA_LAST_KNOWN])],
				'axisy'				=> ['type' => API_INT32, 'in' => implode(',', [GRAPH_YAXIS_SIDE_LEFT, GRAPH_YAXIS_SIDE_RIGHT])],
				'timeshift'			=> ['type' => API_TIME_UNIT, 'in' => implode(':', [ZBX_MIN_TIMESHIFT, ZBX_MAX_TIMESHIFT])]
			]]);
	}

	public function getOverrideOptions(): array {
		return ['color', 'width', 'type', 'transparency', 'fill', 'pointsize', 'missingdatafunc', 'axisy', 'timeshift'];
	}

	public function setValue($value): self {
		$overrides = [];

		foreach ((array) $value as $override) {
			$overrides[] = $override + self::getDefaults();
		}

		return parent::setValue($overrides);
	}

	public static function getDefaults(): array {
		return [
			'hosts' => [],
			'items' => []
		];
	}

	public function validate(bool $strict = false): array {
		if (!$strict) {
			return [];
		}

		if ($errors = parent::validate($strict)) {
			return $errors;
		}

		foreach ($this->getValue() as $index => $overrides) {
			if (!array_intersect($this->getOverrideOptions(), array_keys($overrides))) {
				$errors[] = _s('Invalid parameter "%1$s": %2$s.', $this->label ?? $this->name.'/'.($index + 1),
					_('at least one override option must be specified')
				);
				break;
			}
		}

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		foreach ($this->getValue() as $index => $value) {

			foreach ($value['hosts'] as $host_index => $pattern_item) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$index.'.hosts.'.$host_index,
					'value' => $pattern_item
				];
			}

			foreach ($value['items'] as $item_index => $pattern_item) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.'.$index.'.items.'.$item_index,
					'value' => $pattern_item
				];
			}

			foreach ($this->getOverrideOptions() as $option) {
				if (array_key_exists($option, $value)) {
					$widget_fields[] = [
						'type' => ($option === 'color' || $option === 'timeshift')
							? ZBX_WIDGET_FIELD_TYPE_STR
							: ZBX_WIDGET_FIELD_TYPE_INT32,
						'name' => $this->name.'.'.$index.'.'.$option,
						'value' => $value[$option]
					];
				}
			}
		}
	}

	protected function getValidationRules(bool $strict = false): array {
		$validation_rules = parent::getValidationRules($strict);

		if (($this->getFlags() & self::FLAG_NOT_EMPTY) !== 0) {
			if (!$this->isTemplateDashboard()) {
				self::setValidationRuleFlag($validation_rules['fields']['hosts'], API_NOT_EMPTY);
			}

			self::setValidationRuleFlag($validation_rules['fields']['items'], API_NOT_EMPTY);
			self::setValidationRuleFlag($validation_rules['fields']['color'], API_NOT_EMPTY);
			self::setValidationRuleFlag($validation_rules['fields']['timeshift'], API_NOT_EMPTY);
		}

		return $validation_rules;
	}
}
