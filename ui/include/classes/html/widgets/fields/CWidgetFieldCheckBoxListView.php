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


use Zabbix\Widgets\Fields\CWidgetFieldCheckBoxList;

class CWidgetFieldCheckBoxListView extends CWidgetFieldView {

	protected int $columns = 1;

	public function __construct(CWidgetFieldCheckBoxList $field) {
		$this->field = $field;
	}

	public function getView(): array {
		$options = [];

		foreach ($this->field->getValues() as $key => $label) {
			$options[] = [
				'name' => $this->field->getName().'[]',
				'id' => $this->field->getName().'_'.$key,
				'label' => $label,
				'value' => $key,
				'checked' => in_array($key, $this->field->getValue())
			];
		}

		return [
			(new CVar($this->field->getName(), CWidgetFieldCheckBoxList::EMPTY_VALUE))->removeId(),
			(new CCheckBoxList())
				->setOptions($options)
				->setEnabled(!$this->isDisabled())
				->setColumns($this->columns)
		];
	}

	public function setColumns(int $columns): self {
		$this->columns = $columns;

		return $this;
	}

	public function getJavaScript(): string {
		return '
			document.forms["'.$this->form_name.'"].fields["'.$this->field->getName().'"] =
				new CWidgetFieldCheckboxList('.json_encode($this->field->getName()).');
		';
	}
}
