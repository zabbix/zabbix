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


class CWidgetFieldsGroupView extends CDiv {

	protected ?CLabel $label;

	protected array $fields = [];

	public function __construct($label, array $fields = []) {
		parent::__construct();

		$this->label = (new CLabel($label))->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL);

		foreach ($fields as $field) {
			$this->addItem($field);
		}

		$this->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP);
	}

	public function getFields(): array {
		return $this->fields;
	}

	public function addField(?CWidgetFieldView $field): self {
		return $this->addItem($field);
	}

	public function addItem($value): self {
		if (is_string($value)) {
			$value = $this->encode($value, $this->getEncStrategy());
		}

		$this->fields[] = $value;

		return $this;
	}

	public function getLabel(): ?CLabel {
		return $this->label;
	}

	public function addLabelClass(?string $label_class): self {
		$this->label->addClass($label_class);

		return $this;
	}

	public function addRowClass(?string $row_class): self {
		$this->addLabelClass($row_class);
		$this->addClass($row_class);

		return $this;
	}

	protected function bodyToString() {
		foreach ($this->fields as &$field) {
			if ($field instanceof CWidgetFieldView) {
				$field = [
					$field->getLabel(),
					(new CFormField($field->getView()))->addClass($field->getClass())
				];
			}
		}
		unset($field);

		return unpack_object($this->fields);
	}
}
