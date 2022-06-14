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


class CControllerSlaReportList extends CController {

	protected function init(): void {
		$this->disableSIDValidation();
	}

	/**
	 * @throws Exception
	 */
	protected function checkInput(): bool {
		$fields = [
			'filter_slaid' =>		'id',
			'filter_serviceid' =>	'id',
			'filter_date_from' =>	'string',
			'filter_date_to' =>		'string',
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1',
			'page' =>				'ge 1',
			'sort' =>				'in name',
			'sortorder'	=>			'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$fields = [];

			if ($this->getInput('filter_date_from', '') !== '') {
				$fields['filter_date_from'] = 'abs_date';
			}

			if ($this->getInput('filter_date_to', '') !== '') {
				$fields['filter_date_to'] = 'abs_date';
			}

			if ($fields) {
				$validator = new CNewValidator($this->getInputAll(), $fields);

				foreach ($validator->getAllErrors() as $error) {
					info($error);
				}

				if ($validator->isErrorFatal() || $validator->isError()) {
					$ret = false;
				}
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SLA_REPORT);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.slareport.list.filter.slaid', $this->getInput('filter_slaid', 0), PROFILE_TYPE_ID);
			CProfile::update('web.slareport.list.filter.serviceid', $this->getInput('filter_serviceid', 0),
				PROFILE_TYPE_ID
			);
			CProfile::update('web.slareport.list.filter.date_from', $this->getInput('filter_date_from', ''),
				PROFILE_TYPE_STR
			);
			CProfile::update('web.slareport.list.filter.date_to', $this->getInput('filter_date_to', ''),
				PROFILE_TYPE_STR
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.slareport.list.filter.slaid');
			CProfile::delete('web.slareport.list.filter.serviceid');
			CProfile::delete('web.slareport.list.filter.date_from');
			CProfile::delete('web.slareport.list.filter.date_to');
		}

		$sla = null;

		$slaid = CProfile::get('web.slareport.list.filter.slaid');

		if ($slaid != 0) {
			$slas = API::Sla()->get([
				'output' => ['slaid', 'name', 'period', 'slo', 'timezone'],
				'slaids' => $slaid,
				'filter' => [
					'status' => ZBX_SLA_STATUS_ENABLED
				]
			]);

			if ($slas) {
				$sla = $slas[0];
			}
			else {
				CProfile::delete('web.slareport.list.filter.slaid');
			}
		}

		$service = null;

		$serviceid = CProfile::get('web.slareport.list.filter.serviceid');

		if ($serviceid != 0) {
			$services = API::Service()->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => $serviceid
			]);

			if ($services) {
				$service = $services[0];
			}
			else {
				CProfile::delete('web.slareport.list.filter.serviceid');
			}
		}

		$filter = [
			'slaid' => CProfile::get('web.slareport.list.filter.slaid', ''),
			'serviceid' => CProfile::get('web.slareport.list.filter.serviceid', ''),
			'date_from' => CProfile::get('web.slareport.list.filter.date_from', ''),
			'date_to' => CProfile::get('web.slareport.list.filter.date_to', '')
		];

		$sort_field = $this->getInput('sort', CProfile::get('web.slareport.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.slareport.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.slareport.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.slareport.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		$period_from = null;

		if ($filter['date_from'] !== '') {
			$parser = new CAbsoluteTimeParser();
			$parser->parse($filter['date_from']);
			$period_from = $parser
				->getDateTime(true, new DateTimeZone('UTC'))
				->getTimestamp();
		}

		$period_to = null;

		if ($filter['date_to'] !== '') {
			$parser = new CAbsoluteTimeParser();
			$parser->parse($filter['date_to']);
			$period_to = $parser
				->getDateTime(false, new DateTimeZone('UTC'))
				->getTimestamp();
		}

		if ($period_from !== null && $period_to !== null && $period_to <= $period_from) {
			$period_from = null;
			$period_to = null;

			error(_s('"%1$s" date must be less than "%2$s" date.', _('From'), _('To')));
		}

		$has_errors = hasErrorMessages();

		$data = [
			'has_access' => [
				CRoleHelper::ACTIONS_MANAGE_SLA => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA)
			],
			'filter' => $filter,
			'active_tab' => CProfile::get('web.slareport.list.filter.active', 1),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'sla' => $sla,
			'service' => $service,
			'has_errors' => $has_errors
		];

		if ($sla !== null && !$has_errors) {
			$options = [
				'output' => ['name'],
				'serviceids' => $service !== null ? $service['serviceid'] : null,
				'slaids' => $sla['slaid'],
				'sortfield' => $sort_field,
				'sortorder' => $sort_order,
				'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
				'preservekeys' => true
			];

			$data['services'] = API::Service()->get($options);

			$page_num = getRequest('page', 1);
			CPagerHelper::savePage('slareport.list', $page_num);
			$data['paging'] = CPagerHelper::paginate($page_num, $data['services'], $sort_order,
				(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
			);

			$options = [
				'slaid' => $sla['slaid'],
				'serviceids' => array_keys($data['services']),
				'period_from' => $period_from,
				'period_to' => $period_to
			];

			$data['sli'] = API::Sla()->getSli($options);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('SLA Report'));
		$this->setResponse($response);
	}
}
