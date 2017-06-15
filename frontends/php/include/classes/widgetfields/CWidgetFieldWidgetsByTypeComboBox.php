<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

class CWidgetFieldWidgetsByTypeComboBox extends CWidgetField
{
	private $javascript_attributes;

	public function __construct($name, $label, $default = '', $field, $value) {
		parent::__construct($name, $label, $default, null);
		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);

		$this->javascript_attributes = [
			'field' => $field,
			'value' => $value
		];
	}

	public function getJavascript() {
		$field = array_key_exists('field', $this->javascript_attributes) ? $this->javascript_attributes['field'] : null;
		$value = array_key_exists('value', $this->javascript_attributes) ? $this->javascript_attributes['value'] : null;

		if (!$field || !$value) {
			return '';
		}

		return
			'var widgets, filters_box, dashboard_data;'.
			'widgets = jQuery(".dashbrd-grid-widget-container")'.
				'.dashboardGrid("getWidgetsBy", "'.$field.'", "'.$value.'"),'.
			'dashboard_data = jQuery(".dashbrd-grid-widget-container").data("dashboardGrid"),'.
			'filters_box = jQuery("#'.$this->getName().'");'.
			'if (widgets.length) {'.
				'jQuery("<option>'._('Select filter widget').'</option>").val("").appendTo(filters_box);'.
				'jQuery.each(widgets, function(i, widget) {'.
					'if (typeof widget["'.$field.'"] !== "undefined") {'.
						'jQuery("<option></option>")'.
							'.attr("selected", (widget["fields"]["reference"] === "'.$this->getValue().'"))'.
							'.text(widget["header"].length ? widget["header"] : '.
								'dashboard_data["widget_defaults"]["'.$value.'"]["header"])'.
							'.val(widget["fields"]["reference"])'.
							'.appendTo(filters_box);'.
					'}'.
				'});'.
			'}';
	}

	public function validate() {
		$errors = [];
		if ($this->required === true && $this->value === '') {
			$errors[] = _s('Field \'%s\' is required', $this->label);
		}

		return $errors;
	}
}
