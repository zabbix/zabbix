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


use Zabbix\Widgets\Fields\CWidgetFieldIntegerBox;

class CWidgetFieldIntegerBoxView extends CWidgetFieldView {

	public function __construct(CWidgetFieldIntegerBox $field) {
		$this->field = $field;
	}

	public function getView(): CNumericBox {
		return (new CNumericBox($this->field->getName(), $this->field->getValue(), $this->field->getMaxLength(), false,
			!$this->isNotEmpty()
		))
			->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
			->setAriaRequired($this->isRequired());
	}
}
