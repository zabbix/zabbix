<?php declare(strict_types = 0);
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


class CWidgetFormSlaReport extends CWidgetForm {

	public function __construct(array $values, ?string $templateid) {
		parent::__construct(WIDGET_SLA_REPORT, $values, $templateid);
	}

	public function validate(bool $strict = false): array {
		if ($errors = parent::validate($strict)) {
			return $errors;
		}

		$absolute_time_parser = new CAbsoluteTimeParser();

		if (!$absolute_time_parser->parse($this->getFieldValue('date_from')) == CParser::PARSE_SUCCESS) {
			return [];
		}

		$date_from_timestamp = $absolute_time_parser->getDateTime(false);

		if (!$absolute_time_parser->parse($this->getFieldValue('date_to')) == CParser::PARSE_SUCCESS) {
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

	protected function addFields(): self {
		parent::addFields();

		return $this
			->addField(
				(new CWidgetFieldMultiSelectSla('slaid', _('SLA')))
					->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
					->setMultiple(false)
			)
			->addField(
				(new CWidgetFieldMultiSelectService('serviceid', _('Service')))->setMultiple(false)
			)
			->addField(
				(new CWidgetFieldIntegerBox('show_periods', _('Show periods'), 1, ZBX_SLA_MAX_REPORTING_PERIODS))
					->setDefault(ZBX_SLA_DEFAULT_REPORTING_PERIODS)
			)
			->addField(
				new CWidgetFieldDatePicker('date_from', _('From'), true)
			)
			->addField(
				new CWidgetFieldDatePicker('date_to', _('To'), true)
			);
	}
}
