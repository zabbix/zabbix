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

	private array $classes = [];

	public function __construct(CWidgetFieldCheckBoxList $field) {
		$this->field = $field;
	}

	public function getView(): CList {
		$checkbox_list = (new CList())->addClass(ZBX_STYLE_LIST_CHECK_RADIO);

		foreach ($this->classes as $class) {
			$checkbox_list->addClass($class);
		}

		foreach ($this->field->getValues() as $key => $label) {
			$checkbox_list->addItem(
				(new CCheckBox($this->field->getName().'[]', $key))
					->setLabel($label)
					->setId($this->field->getName().'_'.$key)
					->setChecked(in_array($key, $this->field->getValue()))
					->setEnabled(!$this->isDisabled())
			);
		}

		return $checkbox_list;
	}

	public function addClass(?string $class): self {
		if ($class !== null) {
			$this->classes[] = $class;
		}

		return $this;
	}
}
