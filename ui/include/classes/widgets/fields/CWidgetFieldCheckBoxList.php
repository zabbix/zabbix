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

class CWidgetFieldCheckBoxList extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldCheckBoxListView::class;
	public const DEFAULT_VALUE = [];

	public const EMPTY_VALUE = '';

	private array $values;

	public function __construct(string $name, ?string $label = null, array $values = []) {
		parent::__construct($name, $label);

		$this->values = $values;

		$this
			->setDefault(self::DEFAULT_VALUE)
			->setSaveType(ZBX_WIDGET_FIELD_TYPE_INT32)
			->setValidationRules(['type' => API_INTS32, 'in' => implode(',', array_keys($this->values)), 'uniq' => true]);
	}

	public function getValues(): array {
		return $this->values;
	}

	public function setValue($value): self {
		$this->value = $value === self::EMPTY_VALUE ? [] : (array) $value;

		sort($this->value);

		return $this;
	}

	public function setDefault($value): self {
		$this->default = (array) $value;

		sort($this->default);

		return $this;
	}
}
