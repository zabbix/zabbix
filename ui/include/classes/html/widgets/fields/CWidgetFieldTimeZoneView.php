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


use Zabbix\Widgets\Fields\CWidgetFieldTimeZone;

class CWidgetFieldTimeZoneView extends CWidgetFieldSelectView {

	public function __construct(CWidgetFieldTimeZone $field) {
		parent::__construct($field);
	}

	public function getJavaScript(): string {
		return '
			var timezone_select = document.getElementById("'.$this->field->getName().'");
			var timezone_from_list = timezone_select.getOptionByValue(Intl.DateTimeFormat().resolvedOptions().timeZone);
			var local_list_item = timezone_select.getOptionByValue("'.TIMEZONE_DEFAULT_LOCAL.'");

			if (timezone_from_list && local_list_item) {
				const title = `${local_list_item.label}: ${timezone_from_list.label}`;
				local_list_item.label = title;
				local_list_item._node.innerText = title;

				if (timezone_select.selectedIndex === local_list_item._index) {
					timezone_select._preselect(timezone_select.selectedIndex);
				}
			}
		';
	}
}
