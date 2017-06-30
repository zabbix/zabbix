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

class CWidgetForm {

	protected $fields;

	public function __construct($data) {
		$this->fields = [];
	}

	/**
	 * Return fields for this form
	 *
	 * @return array  an array of CWidgetField
	 */
	public function getFields() {
		return $this->fields;
	}

	public function validate() {
		$errors = [];

		foreach ($this->fields as $field) {
			$errors = array_merge($errors, $field->validate());
		}

		return $errors;
	}

	/**
	 * Prepares array, ready to be passed to CDashboard API functions
	 *
	 * @return array  Array of widget fields ready for saving in API
	 */
	public function fieldsToApi() {
		$api_fields = [];

		foreach ($this->fields as $field) {
			$api_fields = array_merge($api_fields, $field->toApi());
		}

		return $api_fields;
	}
}
