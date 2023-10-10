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


use Zabbix\Widgets\Fields\CWidgetFieldDatePicker;

class CWidgetFieldDatePickerView extends CWidgetFieldView {

	private string $date_format = '';

	private string $placeholder = '';

	public function __construct(CWidgetFieldDatePicker $field) {
		$this->field = $field;
	}

	public function getView(): CDateSelector {
		$view = (new CDateSelector($this->field->getName(), $this->field->getValue()))
			->setMaxLength($this->field->getMaxLength())
			->setAriaRequired($this->isRequired())
			->setEnabled(!$this->isDisabled());

		if ($this->date_format !== '') {
			$view->setDateFormat($this->date_format);
		}

		if ($this->placeholder !== '') {
			$view->setPlaceholder($this->placeholder);
		}

		return $view;
	}

	public function setDateFormat(string $date_format): self {
		$this->date_format = $date_format;

		return $this;
	}

	public function setPlaceholder(string $placeholder): self {
		$this->placeholder = $placeholder;

		return $this;
	}
}
