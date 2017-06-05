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

class CWidgetFieldFilterWidgetComboBox extends CWidgetField
{
	public function __construct($name, $label, $default = '') {
		parent::__construct($name, $label, $default, null);
		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
	}

	public function getJavascript() {
		return
			'var widgets, filters_box, dashboard_data;'.
			'widgets = jQuery(".dashbrd-grid-widget-container").dashboardGrid("getWidgetsBy", "type", "navigationtree"),'.
			'dashboard_data = jQuery(".dashbrd-grid-widget-container").data("dashboardGrid"),'.
			'filters_box = jQuery("#'.$this->getName().'");'.
			'if (widgets.length) {'.
				'jQuery("<option>'._('Select filter widget').'</option>").val("").appendTo(filters_box);'.
				'jQuery.each(widgets, function(i, widget) {'.
					'if (typeof widget["type"] !== "undefined") {'.
						'jQuery("<option></option>")'.
								'.text(widget["header"].length'.
										'?widget["header"]:dashboard_data["widget_defaults"]["navigationtree"]["header"])'.
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
