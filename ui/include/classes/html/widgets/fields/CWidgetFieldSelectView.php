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


use Zabbix\Widgets\Fields\CWidgetFieldSelect;

class CWidgetFieldSelectView extends CWidgetFieldView {

	protected ?CSelect $select = null;

	public function __construct(CWidgetFieldSelect $field) {
		$this->field = $field;
	}

	public function getFocusableElementId(): string {
		return $this->getSelect()->getFocusableElementId();
	}

	public function getView(): CSelect {
		return $this->getSelect();
	}

	private function getSelect(): CSelect {
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
