<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
		$field_sla = (new CWidgetFieldMsSla('slaid', _('SLA')))
			->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
			->setMultiple(false);

		if (array_key_exists('slaid', $this->data)) {
			$field_sla->setValue($this->data['slaid']);
		}

		$this->fields[$field_sla->getName()] = $field_sla;

		// Service.
		$field_service = (new CWidgetFieldMsService('serviceid', _('Service')))
			->setMultiple(false);

		if (array_key_exists('serviceid', $this->data)) {
			$field_service->setValue($this->data['serviceid']);
		}

		$this->fields[$field_service->getName()] = $field_service;

		// Show periods.
		$field_show_periods = (new CWidgetFieldIntegerBox('show_periods', _('Show periods'), 1,
			ZBX_SLA_MAX_REPORTING_PERIODS
		))->setDefault(ZBX_SLA_DEFAULT_REPORTING_PERIODS);

		if (array_key_exists('show_periods', $this->data)) {
			$field_show_periods->setValue($this->data['show_periods']);
		}

		$this->fields[$field_show_periods->getName()] = $field_show_periods;

		// Date from.
		$field_date_from = new CWidgetFieldDatePicker('date_from', _('From'), true);

		if (array_key_exists('date_from', $this->data)) {
			$field_date_from->setValue($this->data['date_from']);
		}

		$this->fields[$field_date_from->getName()] = $field_date_from;

		// Date to.
		$field_date_to = new CWidgetFieldDatePicker('date_to', _('To'), true);

		if (array_key_exists('date_to', $this->data)) {
			$field_date_to->setValue($this->data['date_to']);
		}

		$this->fields[$field_date_to->getName()] = $field_date_to;
	}

	/**
	 * @param bool $strict
	 *
	 * @return array
	 */
	public function validate($strict = false): array {
		if ($errors = parent::validate($strict)) {
			return $errors;
		}

		$absolute_time_parser = new CAbsoluteTimeParser();

		if (!$absolute_time_parser->parse($this->fields['date_from']->getValue()) == CParser::PARSE_SUCCESS) {
			return [];
		}

		$date_from_timestamp = $absolute_time_parser->getDateTime(false);

		if (!$absolute_time_parser->parse($this->fields['date_to']->getValue()) == CParser::PARSE_SUCCESS) {
			return [];
		}

		$date_to_timestamp = $absolute_time_parser->getDateTime(false);

		if ($date_to_timestamp < $date_from_timestamp) {
			return [
				_s('"%1$s" date must be less than "%2$s" date.', _('From'), _('To'))
			];
		}

		return [];
	}
}
