<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CWidgetFormSlaReport extends CWidgetForm
{
	public function __construct($data, $templateid) {
		parent::__construct($data, $templateid, WIDGET_SLA_REPORT);

		// SLA.
		$field_sla = (new CWidgetFieldMsSla('slaids', _('SLA')))
			->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK);

		if (array_key_exists('slaids', $this->data)) {
			$field_sla->setValue($this->data['slaids']);
		}

		$this->fields[$field_sla->getName()] = $field_sla;

		// Services.
		$field_services = new CWidgetFieldMsService('serviceids', _('Services'));

		if (array_key_exists('serviceids', $this->data)) {
			$field_services->setValue($this->data['serviceids']);
		}

		$this->fields[$field_services->getName()] = $field_services;

		// Show periods.
		$field_show_periods = (new CWidgetFieldNumericBox('show_periods', _('Show periods')))
			->setDefault(ZBX_SLA_DEFAULT_REPORTING_PERIODS)
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH);

		if (array_key_exists('serviceids', $this->data) && $this->data['serviceids']) {
			$field_show_periods->setFlags(CWidgetField::FLAG_DISABLED);
		}
		elseif (array_key_exists('show_periods', $this->data)) {
			$field_show_periods->setValue($this->data['show_periods']);
		}

		$this->fields[$field_show_periods->getName()] = $field_show_periods;

		// Date from.
		$field_date_from = new CWidgetFieldDatePicker('date_from', _('From'));

		if (array_key_exists('date_from', $this->data)) {
			$field_date_from->setValue($this->data['date_from']);
		}

		$this->fields[$field_date_from->getName()] = $field_date_from;

		// Date to.
		$field_date_to = new CWidgetFieldDatePicker('date_to', _('To'));

		if (array_key_exists('date_to', $this->data)) {
			$field_date_to->setValue($this->data['date_to']);
		}

		$this->fields[$field_date_to->getName()] = $field_date_to;
	}
}
