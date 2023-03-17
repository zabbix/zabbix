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


use Zabbix\Widgets\Fields\CWidgetFieldWidgetSelect;

class CWidgetFieldWidgetSelectView extends CWidgetFieldView {

	protected ?CSelect $select = null;

	public function __construct(CWidgetFieldWidgetSelect $field) {
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
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAriaRequired($this->isRequired());
		}

		return $this->select;
	}

	public function getJavaScript(): string {
		return '
			var filter_select = document.getElementById("'.$this->field->getName().'");

			filter_select.addOption('.json_encode(['label' => _('Select widget'), 'value' => '-1']).');
			filter_select.selectedIndex = 0;

			ZABBIX.Dashboard.getSelectedDashboardPage().getWidgets().forEach((widget) => {
				if (widget.getType() === "'.$this->field->getSearchByValue().'") {
					const widget_reference = widget.getFields().reference;
					filter_select.addOption({label: widget.getHeaderName(), value: widget_reference});
					if (widget_reference === "'.$this->field->getValue().'") {
						filter_select.value = "'.$this->field->getValue().'";
					}
				}
			});
		';
	}
}
