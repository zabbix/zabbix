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

class CWidgetFieldReference extends CWidgetField {
	public function __construct() {
		/*
		 * All reference fields for all widgets on dashboard should share the same name.
		 * It is needed to make possible search if value is not taken by some other widget in same dashboard.
		 */
		parent::__construct('reference', '', null, null);
		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
	}

	public function getJavascript($form_selector) {
		return ''.
			'var reference_field = jQuery("input[name=\"'.$this->getName().'\"]", "'.$form_selector.'");'.
			'if (!reference_field.val().length) {'.
				'var reference = jQuery(".dashbrd-grid-widget-container").dashboardGrid("makeReference");'.
				'reference_field.val(reference);'.
			'}';
	}
}
