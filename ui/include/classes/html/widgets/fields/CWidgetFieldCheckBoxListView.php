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
