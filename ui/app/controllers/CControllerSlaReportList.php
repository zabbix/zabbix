<?php declare(strict_types = 1);

use SebastianBergmann\Environment\Console;

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


class CControllerSlaReportList extends CController {

	protected $sla;
	protected $service;
	protected $period_from;
	protected $period_to;

	protected function init(): void {
		$this->disableSIDValidation();
	}

	/**
	 * @throws InvalidArgumentException
	 *
	 * @return bool
	 */
	protected function checkInput(): bool {
		$fields = [
			'filter_slaid' =>		'db sla.slaid',
			'filter_serviceid' =>	'db services.serviceid',
			'filter_period_from' =>	'string',
			'filter_period_to' =>	'string',
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1',
			'page' =>				'ge 1',
			'sort' =>				'in name',
			'sortorder'	=>			'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
		];

		$ret = $this->validateInput($fields) && $this->validateSlaDependency() && $this->validatePeriods();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	/**
	 * @return bool
	 *
	 * @throws InvalidArgumentException
	 */
	protected function validateSlaDependency(): bool {
		if (!$this->hasInput('filter_serviceid')) {
			return true;
		}

		$fields = [
			'filter_slaid' =>	'required'
		];
		$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);
		$ret = !$validator->isErrorFatal() && !$validator->isError();

		if (!$ret) {
			throw new InvalidArgumentException(implode(' ', $validator->getAllErrors()));
		}

		return true;
	}

	/**
	 * @return bool
	 *
	 * @throws InvalidArgumentException
	 */
	protected function validatePeriods(): bool {
		$parser = new CRangeTimeParser();
		$period_from = $this->hasInput('filter_period_from')
			? $this->getInput('filter_period_from', '')
			: CProfile::get('web.slareport.filter.period_from', '');
		$period_to = $this->hasInput('filter_period_to')
			? $this->getInput('filter_period_to', '')
			: CProfile::get('web.slareport.filter.period_to', '');

		if ($period_from !== '') {
			if ($parser->parse($period_from) !== CParser::PARSE_FAIL) {
				$this->period_from = $parser->getDateTime(false)->getTimestamp();
			}
			else {
				CProfile::delete('web.slareport.filter.period_from');

				throw new InvalidArgumentException(
					_s('Incorrect value for field "%1$s": %2$s.', _('From'), _('a time period is expected'))
				);
			}
		}

		if ($period_to !== '') {
			if ($parser->parse($period_to) !== CParser::PARSE_FAIL) {
				$this->period_to = $parser->getDateTime(false)->getTimestamp();
			}
			else {
				CProfile::delete('web.slareport.filter.period_to');

				throw new InvalidArgumentException(
					_s('Incorrect value for field "%1$s": %2$s.', _('To'), _('a time period is expected'))
				);
			}
		}

		return true;
	}

	/**
	 * @throws APIException
	 *
	 * @return bool
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA_REPORT)) {
			return false;
		}

		if ($this->hasInput('filter_rst')) {
			return true;
		}

		$slaid = $this->hasInput('filter_slaid')
			? $this->getInput('filter_slaid', '')
			: CProfile::get('web.slareport.filter.slaid', '');

		if ($slaid !== '') {
			$this->sla = API::Sla()->get([
				'output' => ['slaid', 'name', 'slo', 'period', 'timezone'],
				'slaids' => $slaid
			]);

			if (!$this->sla) {
				return false;
			}

			$this->sla = reset($this->sla);

			$this->sla['slo'] = (float) $this->sla['slo'];
			$this->sla['period'] = (int) $this->sla['period'];
		}

		$serviceid = $this->hasInput('filter_serviceid')
			? $this->getInput('filter_serviceid', '')
			: CProfile::get('web.slareport.filter.serviceid', '');

		if ($serviceid !== '') {
			if ($this->sla === null) {
				return false;
			}

			$this->service = API::Service()->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => $serviceid
			]);

			if (!$this->service) {
				CProfile::delete('web.slareport.filter.serviceid');

				return false;
			}

			$this->service = reset($this->service);
		}

		return true;
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.slareport.filter.slaid', $this->getInput('filter_slaid', ''), PROFILE_TYPE_ID);

			if ($this->hasInput('filter_serviceid')) {
				CProfile::update('web.slareport.filter.serviceid', $this->getInput('filter_serviceid', ''),
					PROFILE_TYPE_ID
				);
			}
			else {
				$this->service = null;
				CProfile::delete('web.slareport.filter.serviceid');
			}

			CProfile::update('web.slareport.filter.period_from', $this->getInput('filter_period_from', ''),
				PROFILE_TYPE_STR
			);
			CProfile::update('web.slareport.filter.period_to', $this->getInput('filter_period_to', ''),
				PROFILE_TYPE_STR
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.slareport.filter.slaid');
			CProfile::delete('web.slareport.filter.serviceid');
			CProfile::delete('web.slareport.filter.period_from');
			CProfile::delete('web.slareport.filter.period_to');
		}

		$filter = [
			'slaid' => CProfile::get('web.slareport.filter.slaid', ''),
			'serviceid' => CProfile::get('web.slareport.filter.serviceid', ''),
			'period_from' => CProfile::get('web.slareport.filter.period_from', ''),
			'period_to' => CProfile::get('web.slareport.filter.period_to', '')
		];

		$sort_field = $this->getInput('sort', CProfile::get('web.slareport.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.slareport.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.slareport.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.slareport.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		$data = [
			'has_access' => [
				CRoleHelper::ACTIONS_MANAGE_SLA => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA)
			],
			'filter' => $filter,
			'active_tab' => CProfile::get('web.slareport.filter.active', 1),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'ms_sla' => $this->sla !== null
				? [CArrayHelper::renameKeys($this->sla, ['slaid' => 'id'])]
				: null,
			'ms_service' => $this->service !== null
				? [CArrayHelper::renameKeys($this->service, ['serviceid' => 'id'])]
				: null,
			'periods' => null,
			'paging' => null,
			'sla' => $this->sla,
			'services' => [],
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		if ($this->sla != null) {
			$data['service_curl'] = (new CUrl('zabbix.php'))
				->setArgument('action', 'slareport.list')
				->setArgument('filter_slaid', $this->sla['slaid']);

			$options = [
				'output' => [],
				'slaids' => $this->sla['slaid'],
				'sortfield' => [$sort_field],
				'sortorder' => $sort_order,
				'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
				'preservekeys' => true
			];

			if ($this->service !== null) {
				$options['serviceids'] = $this->service['serviceid'];
			}

			$sla_services = API::Service()->get($options);

			$page_num = $this->getInput('page', 1);
			CPagerHelper::savePage('slareport.list', $page_num);
			$data['paging'] = CPagerHelper::paginate($page_num, $sla_services, $sort_order,
				(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
			);

			$options = [
				'slaid' => $this->sla['slaid'],
				'serviceids' => array_keys($sla_services),
				'period_from' => $this->period_from,
				'period_to' => $this->period_to
			];

			$sli = API::Sla()->getSli($options);
			$data['periods'] = $sli['periods'];

			$page_services = [
				'output' => ['name'],
				'slaids' => $this->sla['slaid'],
				'sortfield' => [$sort_field],
				'sortorder' => $sort_order,
				'serviceids' => $sli['serviceids'],
				'preservekeys' => true
			];

			$services = API::Service()->get($page_services);

			foreach($services as &$service) {
				$service['sli'] = [];
			}
			unset($service);

			if (count($services) < 2) {
				$data['paging'] = null;
			}

			foreach (array_keys($sli['periods']) as $period_key) {
				foreach ($sli['serviceids'] as $service_key => $serviceid) {
					$services[$serviceid]['sli'][] = $sli['sli'][$period_key][$service_key];
				}
			}

			$data['services'] = $services;
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('SLA Report'));
		$this->setResponse($response);
	}
}
