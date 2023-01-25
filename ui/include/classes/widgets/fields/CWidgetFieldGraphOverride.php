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


namespace Zabbix\Widgets\Fields;

use Zabbix\Widgets\CWidgetField;

/**
 * Class for override widget field used in Graph widget configuration overrides tab.
 */
class CWidgetFieldGraphOverride extends CWidgetField {

	public const DEFAULT_VALUE = [];

	public function __construct(string $name, string $label = null) {
		parent::__construct($name, $label);

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR)
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

	public function setFlags(int $flags): self {
		parent::setFlags($flags);

		if (($flags & self::FLAG_NOT_EMPTY) !== 0) {
			$strict_validation_rules = $this->getValidationRules();
			self::setValidationRuleFlag($strict_validation_rules['fields']['hosts'], API_NOT_EMPTY);
			self::setValidationRuleFlag($strict_validation_rules['fields']['items'], API_NOT_EMPTY);
			self::setValidationRuleFlag($strict_validation_rules['fields']['color'], API_NOT_EMPTY);
			self::setValidationRuleFlag($strict_validation_rules['fields']['timeshift'], API_NOT_EMPTY);
			$this->setStrictValidationRules($strict_validation_rules);
		}
		else {
			$this->setStrictValidationRules();
		}

		return $this;
	}

	public function validate(bool $strict = false): array {
		$errors = parent::validate($strict);
		$value = $this->getValue();
		$label = $this->label ?? $this->name;

		// Validate options.
		if (!$errors && $strict) {
			foreach ($value as $index => $overrides) {
				if (!array_intersect($this->getOverrideOptions(), array_keys($overrides))) {
					$errors[] = _s('Invalid parameter "%1$s": %2$s.', $label.'/'.($index + 1),
						_('at least one override option must be specified')
					);
					break;
				}
			}
		}

		return $errors;
	}

	public function toApi(array &$widget_fields = []): void {
		$value = $this->getValue();

		foreach ($value as $index => $val) {
			// Hosts and items fields are stored as arrays to bypass length limit.
			foreach ($val['hosts'] as $num => $pattern_item) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.hosts.'.$index.'.'.$num,
					'value' => $pattern_item
				];
			}
			foreach ($val['items'] as $num => $pattern_item) {
				$widget_fields[] = [
					'type' => ZBX_WIDGET_FIELD_TYPE_STR,
					'name' => $this->name.'.items.'.$index.'.'.$num,
					'value' => $pattern_item
				];
			}

			foreach ($this->getOverrideOptions() as $opt) {
				if (array_key_exists($opt, $val)) {
					$widget_fields[] = [
						'type' => ($opt === 'color' || $opt === 'timeshift')
							? ZBX_WIDGET_FIELD_TYPE_STR
							: ZBX_WIDGET_FIELD_TYPE_INT32,
						'name' => $this->name.'.'.$opt.'.'.$index,
						'value' => $val[$opt]
					];
				}
			}
		}
	}
}
