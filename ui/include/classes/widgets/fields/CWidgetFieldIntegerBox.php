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


namespace Zabbix\Widgets\Fields;

use Zabbix\Widgets\CWidgetField;

class CWidgetFieldIntegerBox extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldIntegerBoxView::class;

	private int $min;
	private int $max;

	/**
	 * @param int $min  Minimal allowed value.
	 * @param int $max  Maximal allowed value.
	 */
	public function __construct(string $name, ?string $label = null, int $min = 0, int $max = ZBX_MAX_INT32) {
		parent::__construct($name, $label);

		$this->min = $min;
		$this->max = $max;

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_INT32);
	}

	public function setValue($value): self {
		return parent::setValue((int) $value);
	}

	public function getMaxLength(): int {
		return strlen((string) $this->max);
	}

	protected function getValidationRules(bool $strict = false): array {
		return parent::getValidationRules($strict)
			+ ['in' => $this->min.':'.$this->max];
	}
}
