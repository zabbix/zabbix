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

	protected array $label_class = [];

	protected string $label;
	protected $help_hint;

	protected array $fields = [];

	public function __construct(string $label, array $fields = []) {
		parent::__construct();

		$this->label = $label;

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

	public function setHelpHint($help_hint): self {
		$this->help_hint = $help_hint;

		return $this;
	}

	public function getLabel(): CLabel {
		return (new CLabel([$this->label, $this->help_hint !== null ? makeHelpIcon($this->help_hint) : null]))
			->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL)
			->addClass($this->label_class ? implode(' ', $this->label_class) : null);
	}

	public function addLabelClass(?string $label_class): self {
		if ($label_class !== null) {
			$this->label_class[] = $label_class;
		}

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
