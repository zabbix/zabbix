<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

class CWidgetFieldDatePicker extends CWidgetField {
	/**
	 * Date picker widget field.
	 *
	 * @param string $name   Field name in form.
	 * @param string $label  Label for the field in form.
	 */
	public function __construct($name, $label) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
		$this->setDefault('');
	}

	public function setValue($value) {
		return parent::setValue($value);
	}

	/**
	 * Return javascript necessary to initialize field.
	 *
	 * @return string
	 */
	public function getJavascript($form_name) {
		return
			'var input = jQuery("[name=\"'.$this->getName().'\"]", jQuery("#'.$form_name.'")).get(0);'.
			'jQuery("#'.$this->getName().'_dp")'.
				'.data("clndr", create_calendar(null, input))'.
				'.data("input", input)'.
				'.click(function() {'.
					'var b = jQuery(this),'.
						'o = b.offset(),'.
						't = parseInt(o.top + b.outerHeight(), 10),'.
						'l = parseInt(o.left, 10);'.
					'b.data("clndr").clndr.clndrshow(t, l, b.data("input"));'.
					'return false;'.
				'})';
	}
}
