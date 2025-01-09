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


use Zabbix\Widgets\Fields\CWidgetFieldNumericBox;

class CWidgetFieldNumericBoxView extends CWidgetFieldView {

	private string $placeholder = '';

	private int $width = ZBX_TEXTAREA_NUMERIC_BIG_WIDTH;

	public function __construct(CWidgetFieldNumericBox $field) {
		$this->field = $field;
	}

	public function setPlaceholder(string $placeholder): self {
		$this->placeholder = $placeholder;

		return $this;
	}

	public function setWidth(int $width): self {
		$this->width = $width;

		return $this;
	}

	public function getView(): CTextBox {
		$view = (new CTextBox($this->field->getName(), $this->field->getValue(), false, $this->field->getMaxLength()))
			->setWidth($this->width)
			->setAriaRequired($this->isRequired())
			->setEnabled(!$this->isDisabled());

		if ($this->placeholder !== '') {
			$view->setAttribute('placeholder', $this->placeholder);
		}

		return $view;
	}
}
