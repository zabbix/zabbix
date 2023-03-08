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


use Zabbix\Widgets\Fields\CWidgetFieldSelect;

class CWidgetFieldSelectView extends CWidgetFieldView {

	protected ?CSelect $select = null;

	public function __construct(CWidgetFieldSelect $field) {
		$this->field = $field;
	}

	public function getLabel(): ?CLabel {
		$label = parent::getLabel();

		if ($label !== null) {
			$label->setFor($this->getView()->getFocusableElementId());
		}

		return $label;
	}

	public function getView(): CSelect {
		if ($this->select === null) {
			$this->select = (new CSelect($this->field->getName()))
				->setId($this->field->getName())
				->setFocusableElementId('label-'.$this->field->getName())
				->setValue($this->field->getValue())
				->addOptions(CSelect::createOptionsFromArray($this->field->getValues()))
				->setDisabled($this->isDisabled())
				->setAriaRequired($this->isRequired());
		}

		return $this->select;
	}
}
