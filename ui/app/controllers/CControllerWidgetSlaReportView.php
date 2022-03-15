<?php
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

		$data = [
			'name' => $this->getInput('name', $this->getDefaultName()),
			'has_access' => [
				CRoleHelper::ACTIONS_MANAGE_SLA => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA)
			],
			'has_serviceid' => (bool) $fields['serviceid'],
			'has_permissions_error' => false,
			'rows_per_page' => CWebUser::$data['rows_per_page'],
			'search_limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$db_slas = $fields['slaid']
			? API::Sla()->get([
				'output' => ['slaid', 'name', 'period', 'slo', 'timezone', 'status'],
				'slaids' => $fields['slaid']
			])
			: [];

		if ($db_slas) {
			$data['sla'] = $db_slas[0];

			if ($data['sla']['status'] == ZBX_SLA_STATUS_ENABLED) {
				$data['services'] = API::Service()->get([
					'output' => ['name'],
					'serviceids' => $fields['serviceid'] ?: null,
					'slaids' => $data['sla']['slaid'],
					'sortfield' => 'name',
					'sortorder' => ZBX_SORT_UP,
					'limit' => $data['search_limit'] + 1,
					'preservekeys' => true
				]);

				if ($fields['serviceid'] && !$data['services']) {
					$data['has_permissions_error'] = true;
				}
				else {
					$range_time_parser = new CRangeTimeParser();

					if ($fields['date_from'] !== ''
							&& $range_time_parser->parse($fields['date_from']) == CParser::PARSE_SUCCESS) {
						$period_from = $range_time_parser->getDateTime(true)->getTimestamp();
					}
					else {
						$period_from = null;
					}

					if ($fields['date_to'] !== ''
							&& $range_time_parser->parse($fields['date_to']) == CParser::PARSE_SUCCESS) {
						$period_to = $range_time_parser->getDateTime(false)->getTimestamp();
					}
					else {
						$period_to = null;
					}

					$data['sli'] = API::Sla()->getSli([
						'slaid' => $data['sla']['slaid'],
						'serviceids' => array_slice(array_keys($data['services']), 0, $data['rows_per_page']),
						'periods' => $fields['show_periods'] !== '' ? $fields['show_periods'] : null,
						'period_from' => $period_from,
						'period_to' => $period_to
					]);
				}
			}
		}
		else {
			$data['has_permissions_error'] = true;
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
