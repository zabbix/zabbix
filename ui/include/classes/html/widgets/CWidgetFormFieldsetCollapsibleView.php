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


class CWidgetFormFieldsetCollapsibleView extends CFormFieldsetCollapsible {

	protected array $fields = [];

	public function __construct(string $caption, array $fields = []) {
		parent::__construct($caption);

		foreach ($fields as $field) {
			$this->addItem($field);
		}
	}

	public function getFields(): array {
		$fields = [];

		foreach ($this->fields as $field) {
			if ($field instanceof CWidgetFieldsGroupView) {
				foreach ($field->getFields() as $group_field) {
					$fields[] = $group_field;
				}
			}
			else {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	public function addFieldsGroup(?CWidgetFieldsGroupView $fields_group): self {
		return $this->addItem($fields_group);
	}

	public function addField(?CWidgetFieldView $field): self {
		return $this->addItem($field);
	}

	public function addItem($value): self {
		if ($value === null) {
			return $this;
		}

		if (is_string($value)) {
			$value = $this->encode($value, $this->getEncStrategy());
		}

		$this->fields[] = $value;

		return $this;
	}

	protected function bodyToString(): string {
		$collection = [];

		foreach ($this->fields as $field) {
			if ($field instanceof CWidgetFieldsGroupView) {
				$collection[] = [$field->getLabel(), $field];
			}
			elseif ($field instanceof CWidgetFieldView) {
				foreach ($field->getViewCollection() as ['label' => $label, 'view' => $view, 'class' => $class]) {
					$collection[] = [$label, (new CFormField($view))->addClass($class)];
				}
			}
			else {
				$collection[] = $field;
			}
		}

		return $this->makeLegend().unpack_object($collection);
	}
}
