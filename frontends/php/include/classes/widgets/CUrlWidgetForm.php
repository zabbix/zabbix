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

class CUrlWidgetForm extends CWidgetForm
{
	public function __construct($data) {
		parent::__construct($data);

		// URL field
		$field_url = (new CWidgetFieldTextBox('url', _('URL')));
		if (array_key_exists('url', $data)) {
			$field_url->setValue($data['url']);
		}
		$this->fields[] = $field_url;

		// Dynamic item
		$field_dynamic = (new CWidgetFieldCheckbox('dynamic', _('Dynamic item')));
		if (array_key_exists('dynamic', $data)) {
			$field_dynamic->setValue($data['dynamic']);
		}
		else {
			$field_dynamic->setValue(false);
		}
		$this->fields[] = $field_dynamic;
	}
}
