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


class CWidgetFieldReference extends CWidgetField {

	// This field name is reserved by Zabbix for this particular use case. See comments below.
	const FIELD_NAME = 'reference';

	/**
	 * Reference widget field. If added to widget, will generate unique value across the dashboard
	 * and will be saved to database. This field should be used to save relations between widgets.
	 */
	public function __construct() {
		/*
		 * All reference fields for all widgets on dashboard should share the same name.
		 * It is needed to make possible search if value is not taken by some other widget in same dashboard.
		 */
		parent::__construct(self::FIELD_NAME, null);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_STR);
	}

	/**
	 * JS script, that will call reference generation, if reference is not yet created.
	 *
	 * @param string $form_selector  jQuery context selector for the configuration form (with # or . character)
	 *
	 * @return string
	 */
	public function getJavascript($form_selector) {
		return
			'var reference_field = jQuery("input[name=\"'.$this->getName().'\"]", "'.$form_selector.'");'.
			'if (!reference_field.val().length) {'.
				'var reference = jQuery(".dashbrd-grid-container").dashboardGrid("makeReference");'.
				'reference_field.val(reference);'.
			'}';
	}

	/**
	 * Set field value.
	 *
	 * @param string $value  Reference value. Only numeric characters allowed.
	 *
	 * @return CWidgetFieldReference
	 */
	public function setValue($value) {
		if ($value === '' || ctype_alnum($value)) {
			$this->value = $value;
		}

		return $this;
	}
}
