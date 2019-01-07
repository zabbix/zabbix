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


/**
 * Class makes datepicker widget field.
 */
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
		$this->setValidationRules(['type' => API_RANGE_TIME, 'length' => 255]);
		$this->setDefault('now');
	}

	/**
	 * Return javascript necessary to initialize field.
	 *
	 * @param string $form_name   Form name in which control is located.
	 * @param string $onselect    Callback script that is executed on date select.
	 *
	 * @return string
	 */
	public function getJavascript($form_name, $onselect = '') {
		return
			'var input = jQuery("[name=\"'.$this->getName().'\"]", jQuery("#'.$form_name.'")).get(0);'.

			'jQuery("#'.$this->getName().'_dp")'.
				'.data("clndr", create_calendar(input, null))'.
				'.data("input", input)'.
				'.click(function() {'.
					'var b = jQuery(this),'.
						'o = b.offset(),'.
						// h - calendar height.
						'h = jQuery(b.data("clndr").clndr.clndr_calendar).outerHeight(),'.
						// d - dialog offset.
						'd = jQuery("#overlay_dialogue").offset(),'.
						// t - calculated calendar top position.
						't = parseInt(o.top + b.outerHeight() - h - d.top, 10),'.
						// l - calculated calendar left position.
						'l = parseInt(o.left - d.left + b.outerWidth(), 10);'.

					'b.data("clndr").clndr.clndrshow(t, l, b.data("input"));'.
					($onselect !== '' ? 'b.data("clndr").clndr.onselect = function() {'.$onselect.'};' : '').

					'return false;'.
				'})';
	}
}
