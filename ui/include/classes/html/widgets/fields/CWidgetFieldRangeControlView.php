<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


use Zabbix\Widgets\Fields\CWidgetFieldRangeControl;

class CWidgetFieldRangeControlView extends CWidgetFieldView {

	protected ?CRangeControl $range_control = null;

	public function __construct(CWidgetFieldRangeControl $field) {
		$this->field = $field;
	}

	public function getView(): CRangeControl {
		return $this->getRangeControl();
	}

	public function getJavaScript(): string {
		return $this->getRangeControl()->getPostJS();
	}

	private function getRangeControl(): CRangeControl {
		if ($this->range_control === null) {
			$this->range_control = (new CRangeControl($this->field->getName(), (int) $this->field->getValue()))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setStep($this->field->getStep())
				->setMin($this->field->getMin())
				->setMax($this->field->getMax())
				->setEnabled(!$this->isDisabled());
		}

		return $this->range_control;
	}
}
