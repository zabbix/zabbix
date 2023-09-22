<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


namespace Widgets\SlaReport\Includes;

use API,
	CTimezoneHelper,
	DateTimeZone;

use Zabbix\Widgets\{
	CWidgetField,
	CWidgetForm
};

use Zabbix\Widgets\Fields\{
	CWidgetFieldIntegerBox,
	CWidgetFieldMultiSelectService,
	CWidgetFieldMultiSelectSla,
	CWidgetFieldTimePeriod
};

/**
 * SLA report widget form.
 */
class WidgetForm extends CWidgetForm {

	public function validate(bool $strict = false): array {
		/** @var CWidgetFieldMultiSelectSla $slaid_field */
		$slaid_field = $this->getFields()['slaid'];

		if ($errors = $slaid_field->validate($strict)) {
			return $errors;
		}

		$slaids = $slaid_field->getValue();

		$slas = $slaids
			? API::Sla()->get([
				'output' => ['timezone'],
				'slaids' => $slaids,
				'filter' => [
					'status' => ZBX_SLA_STATUS_ENABLED
				]
			])
			: [];

		$sla = $slas ? $slas[0] : null;

		$timezone = new DateTimeZone($sla !== null && $sla['timezone'] !== ZBX_DEFAULT_TIMEZONE
			? $sla['timezone']
			: CTimezoneHelper::getSystemTimezone()
		);

		/** @var CWidgetFieldTimePeriod $date_period_field */
		$date_period_field = $this->getFields()['date_period'];

		$date_period_field->setTimeZone($timezone);

		return parent::validate($strict);
	}

	public function addFields(): self {
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
				(new CWidgetFieldTimePeriod('date_period'))
					->setDateOnly()
			);
	}
}
