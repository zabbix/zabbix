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

class CSysmapWidgetForm extends CWidgetForm
{
	public function __construct($data) {
		parent::__construct($data);

		// widget name field
		$widget_name = (new CWidgetFieldTextBox('widget_name', _('Name')))->setRequired(true);
		if (array_key_exists('widget_name', $data)) {
			$widget_name->setValue($data['widget_name']);
		}
		$this->fields[] = $widget_name;

		// select source type field
		$source_type = array_key_exists('source_type', $data) ? (int)$data['source_type'] : WIDGET_SYSMAP_SOURCETYPE_MAP;

		$radio_button_field = (new CWidgetRadioButtonList(
				'source_type',
				_('Source type'),
				WIDGET_SYSMAP_SOURCETYPE_MAP,
				'updateWidgetConfigDialogue()'
		));
		$radio_button_field->addValue(_('Map'), WIDGET_SYSMAP_SOURCETYPE_MAP);
		$radio_button_field->addValue(_('Filter'), WIDGET_SYSMAP_SOURCETYPE_FILTER);
		$radio_button_field->setValue($source_type);
		$radio_button_field->setModern(true);

		$this->fields[] = $radio_button_field;

		// select filter widget field
		if ($source_type == WIDGET_SYSMAP_SOURCETYPE_FILTER) {
			$filter_widget_field = (new CWidgetFieldFilterWidgetComboBox('filter_widget_reference', _('Filter')))
					->setRequired(true);

			if (array_key_exists('filter_widget_reference', $data)) {
				$filter_widget_field->setValue($data['filter_widget_reference']);
			}

			$this->fields[] = $filter_widget_field;
		}

		// select sysmap field
		$sysmap_caption = '';
		$sysmap_id = 0;

		if (array_key_exists('sysmap_id', $data) && $data['sysmap_id']) {
			$maps = API::Map()->get([
				'sysmapids' => $data['sysmap_id'],
				'output' => API_OUTPUT_EXTEND
			]);

			if (($map = reset($maps)) !== false) {
				$sysmap_id = $map['sysmapid'];
				$sysmap_caption = $map['name'];
			}
		}

		$map_field = (new CWidgetFieldSelectResource('sysmap_id', _('Map'), WIDGET_FIELD_SELECT_RES_SYSMAP, $sysmap_id, $sysmap_caption));
		$map_field->setRequired($source_type == WIDGET_SYSMAP_SOURCETYPE_MAP);
		
		$this->fields[] = $map_field;
	}

	public function validate() {
		$errors = [];

		foreach ($this->fields as $field) {
			// Validate each field seperately
			$errors = array_merge($errors, $field->validate());
		}

		return $errors;
	}
}
