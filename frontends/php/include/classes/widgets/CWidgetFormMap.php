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
 * Map widget form.
 */
class CWidgetFormMap extends CWidgetForm {

	public function __construct($data) {
		parent::__construct($data, WIDGET_SYSMAP);

		// widget reference field
		$field_reference = (new CWidgetFieldReference());
		if (array_key_exists($field_reference->getName(), $this->data)) {
			$field_reference->setValue($this->data[$field_reference->getName()]);
		}
		$this->fields[] = $field_reference;

		// select source type field
		$field_source_type = (new CWidgetFieldRadioButtonList('source_type', _('Source type'), [
			WIDGET_SYSMAP_SOURCETYPE_MAP => _('Map'),
			WIDGET_SYSMAP_SOURCETYPE_FILTER => _('Map navigation tree'),
		]))
			->setDefault(WIDGET_SYSMAP_SOURCETYPE_MAP)
			->setAction('updateWidgetConfigDialogue()')
			->setModern(true);
		if (array_key_exists('source_type', $this->data)) {
			$field_source_type->setValue($this->data['source_type']);
		}
		$this->fields[] = $field_source_type;

		// select filter widget field
		if ($field_source_type->getValue() === WIDGET_SYSMAP_SOURCETYPE_FILTER) {
			$field_filter_widget = (new CWidgetFieldWidgetListComboBox('filter_widget_reference', _('Filter'),
				'type', 'navtree'
			))
				->setDefault('')
				->setFlags(CWidgetField::FLAG_LABEL_ASTERISK);

			if (array_key_exists('filter_widget_reference', $this->data)) {
				$field_filter_widget->setValue($this->data['filter_widget_reference']);
			}

			$this->fields[] = $field_filter_widget;
		}
		else {
			// select sysmap field
			$field_map = (new CWidgetFieldSelectResource('sysmapid', _('Map'), WIDGET_FIELD_SELECT_RES_SYSMAP))
				->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK);

			if (array_key_exists('sysmapid', $this->data)) {
				$field_map->setValue($this->data['sysmapid']);
			}
			$this->fields[] = $field_map;
		}
	}

	/**
	 * Validate form fields.
	 *
	 * @param bool $strict  Enables more strict validation of the form fields.
	 *                      Must be enabled for validation of input parameters in the widget configuration form.
	 *
	 * @return array
	 */
	public function validate($strict = false) {
		$errors = parent::validate($strict);

		// In strict validation 'filter_widget_reference' key should exist.
		$invalid = $strict
			? (!array_key_exists('filter_widget_reference', $this->data)
				|| $this->data['filter_widget_reference'] === '')
			: (array_key_exists('filter_widget_reference', $this->data)
				&& $this->data['filter_widget_reference'] === '');

		if (!$errors && array_key_exists('source_type', $this->data)
				&& $this->data['source_type'] == WIDGET_SYSMAP_SOURCETYPE_FILTER && $invalid) {
			$errors[] = _s('Invalid parameter "%1$s": %2$s.', _('Filter'), _('cannot be empty'));
		}

		return $errors;
	}
}
