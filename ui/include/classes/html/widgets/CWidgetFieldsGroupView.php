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


class CWidgetFieldsGroupView extends CDiv {

	protected array $fields = [];

	protected array $label_class_list = [];

	protected string $label;

	protected ?CTag $field_hint = null;

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
		if ($value === null) {
			return $this;
		}

		if (is_string($value)) {
			$value = $this->encode($value, $this->getEncStrategy());
		}

		$this->fields[] = $value;

		return $this;
	}

	public function getLabel(): CLabel {
		$focusable_element_id = null;

		if ($this->fields && $this->fields[0] instanceof CWidgetFieldView) {
			$label = $this->fields[0]->getLabel();

			if ($label === null) {
				$focusable_element_id = $this->fields[0]->getFocusableElementId();
			}
		}

		return (new CLabel([$this->label, $this->field_hint], $focusable_element_id))
			->addClass(CFormGrid::ZBX_STYLE_FIELDS_GROUP_LABEL)
			->addClass($this->label_class_list ? implode(' ', $this->label_class_list) : null);
	}

	public function setFieldHint(CTag $hint): self {
		$this->field_hint = $hint;

		return $this;
	}

	public function addLabelClass(?string $label_class): self {
		if ($label_class !== null) {
			$this->label_class_list[] = $label_class;
		}

		return $this;
	}

	public function addRowClass(?string $row_class): self {
		$this->addLabelClass($row_class);
		$this->addClass($row_class);

		return $this;
	}

	protected function bodyToString() {
		$collection = [];

		foreach ($this->fields as $field) {
			if ($field instanceof CWidgetFieldView) {
				foreach ($field->getViewCollection() as ['label' => $label, 'view' => $view, 'class' => $class]) {
					$collection[] = [$label, (new CFormField($view))->addClass($class)];
				}
			}
			else {
				$collection[] = $field;
			}
		}

		return unpack_object($collection);
	}
}
