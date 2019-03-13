<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CWidgetFieldWidgetListComboBox extends CWidgetField {

	private $search_by_key;
	private $search_by_value;

	/**
	 * Field that creates ComboBox with widgets of current dashboard, filtered by given key of widget array.
	 *
	 * @param string $name             Name of field in config form and widget['fields'] array.
	 * @param string $label            Field label in config form.
	 * @param string $search_by_key    Key of widget array, by which widgets will be filtered.
	 * @param mixed  $search_by_value  Value that will be searched in widget[$search_by_key].
	 */
	public function __construct($name, $label, $search_by_key, $search_by_value) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->search_by_key = $search_by_key;
		$this->search_by_value = $search_by_value;
	}

	/**
	 * JS code, that should be executed, to fill ComboBox with values and select current one.
	 *
	 * @return string
	 */
	public function getJavascript() {
		return
			'var dashboard = jQuery(".dashbrd-grid-container"),'.
				'dashboard_data = dashboard.data("dashboardGrid"),'.
				'filters_box = jQuery("#'.$this->getName().'");'.
			'jQuery("<option />").text("'._('Select widget').'").val("").appendTo(filters_box);'.
			'jQuery.each('.
				'dashboard.dashboardGrid("getWidgetsBy", "'.$this->search_by_key.'", "'.$this->search_by_value.'"),'.
				'function(i, widget) {'.
					'if (widget !== dashboard_data["dialogue"]["widget"]) {'. // Widget currently edited or null for new widgets.
						'jQuery("<option />")'.
							'.text(widget["header"].length'.
								'? widget["header"]'.
								': dashboard_data["widget_defaults"][widget["type"]]["header"]'.
							')'.
							'.val(widget["fields"]["reference"])'.
							'.attr("selected", (widget["fields"]["reference"] === "'.$this->getValue().'"))'.
							'.appendTo(filters_box);'.
					'}'.
			'});';
	}

	public function setValue($value) {
		if ($value === '' || ctype_alnum($value)) {
			$this->value = $value;
		}

		return $this;
	}
}
