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

class CWidgetFieldRadioButtonList extends CWidgetField {

	public const DEFAULT_VIEW = \CWidgetFieldRadioButtonListView::class;

	private array $values;

	/**
	 * Radio button widget field. Can use both, string and integer type keys.
	 *
	 * @param array  $values key/value pairs of radio button values. Key - saved in DB. Value - visible to user.
	 */
	public function __construct(string $name, ?string $label = null, array $values = []) {
		parent::__construct($name, $label);

		$this->values = $values;

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_INT32);
	}

	public function getValues(): array {
		return $this->values;
	}

	public function setValue($value): self {
		return parent::setValue((int) $value);
	}

	protected function getValidationRules(bool $strict = false): array {
		return parent::getValidationRules($strict)
			+ ['in' => implode(',', array_keys($this->values))];
	}
}
