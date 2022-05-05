<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CWidgetFieldTimeZone extends CWidgetField {

	private $values;

	/**
	 * CSelect widget field.
	 *
	 * @param string $name    Field name in form
	 * @param string $label   Label for the field in form
	 * @param array  $values  Key/value pairs of select option values. Key - saved in DB. Value - visible to user.
	 */
	public function __construct($name, $label, $values = null) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);

		if ($values === null) {
			$this->values = $this->generateValues();
		}

		$this->setExValidationRules(['in' => implode(',', array_keys($this->values))]);
	}

	public function setValue($value) {
		return parent::setValue($value);
	}

	public function getValues() {
		return $this->values;
	}

	private function generateValues() {
		return [
			ZBX_DEFAULT_TIMEZONE => CTimezoneHelper::getTitle(CTimezoneHelper::getSystemTimezone(),
				_('System default')
			),
			TIMEZONE_DEFAULT_LOCAL => _('Local default')
		] + CTimezoneHelper::getList();
	}

	public function getJavascript() {
		return '
			var timezone_select =  document.getElementById("'.$this->getName().'");
			var local_time_zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
			var timezone_from_list = timezone_select.getOptionByValue(local_time_zone);
			var local_list_item = timezone_select.getOptionByValue("'.TIMEZONE_DEFAULT_LOCAL.'");

			if (timezone_from_list && local_list_item) {
				const title = local_list_item.label + ": " + timezone_from_list.label;
				local_list_item.label = title;
				local_list_item._node.innerText = title;

				if (timezone_select.selectedIndex === local_list_item._index) {
					timezone_select._preselect(timezone_select.selectedIndex);
				}
			}
		';
	}
}
