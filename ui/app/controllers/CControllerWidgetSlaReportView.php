<?php
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


class CControllerWidgetSlaReportView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_SLA_REPORT);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json'
		]);
	}

	/**
	 * @throws APIException
	 */
	protected function doAction(): void {
		$fields = $this->getForm()->getFieldsData();

		$db_services = API::Service()->get([
			'output' => ['name'],
			'serviceids' => $fields['serviceid'] ?: null,
			'limit' => CWebUser::$data['rows_per_page'],
			'preservekeys' => true
		]);

		$db_sla = API::Sla()->get([
			'output' => ['name', 'period', 'slo', 'timezone'],
			'slaids' => $fields['slaid']
		]);

		$db_sli = API::Sla()->getSli([
			'slaid' => $fields['slaid'][0],
			'serviceids' => array_keys($db_services),
			'periods' => $fields['serviceid'] && $fields['show_periods'] !== '' ? $fields['show_periods'] : null,
			'period_from' => $fields['date_from'] !== '' ? $fields['date_from'] : null,
			'period_to' => $fields['date_to'] !== '' ? $fields['date_to'] : null
		]);

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultName()),
			'services' => array_intersect_key($db_services, array_flip($db_sli['serviceids'])),
			'sla' => $db_sla[0],
			'sli' => $db_sli['sli'],
			'periods' => $db_sli['periods'],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
