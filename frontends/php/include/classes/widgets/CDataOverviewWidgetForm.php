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


/**
 * Data overview widget form.
 */
class CDataOverviewWidgetForm extends CWidgetForm {

	public function __construct($data) {
		parent::__construct($data, WIDGET_DATA_OVERVIEW);

		$field_groups = new CWidgetFieldGroup('groupids', _('Host groups'));

		if (array_key_exists('groupids', $this->data)) {
			$field_groups->setValue($this->data['groupids']);
		}
		$this->fields[] = $field_groups;

		$field_application = new CWidgetFieldTextBox('application', _('Application'));

		if (array_key_exists('application', $this->data)) {
			$field_application->setValue($this->data['application']);
		}
		$this->fields[] = $field_application;

		$field_style = (new CWidgetFieldRadioButtonList('style', _('Hosts location'), [
			STYLE_LEFT => _('Left'),
			STYLE_TOP => _('Top')
		]))
			->setDefault(STYLE_LEFT)
			->setModern(true);

		if (array_key_exists('style', $this->data)) {
			$field_style->setValue($this->data['style']);
		}
		$this->fields[] = $field_style;
	}
}
