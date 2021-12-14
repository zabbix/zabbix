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

		$ret = $this->validateInput($fields);

		if ($ret && !$this->hasInput('filter_rst') && $this->hasInput('filter_serviceid')) {
			$fields = [
				'filter_slaid' =>	'required'
			];
			$validator = new CNewValidator(array_intersect_key($this->getInputAll(), $fields), $fields);
			$ret = !$validator->isErrorFatal() && !$validator->isError();

			if (!$ret) {
				throw new InvalidArgumentException(implode(' ', $validator->getAllErrors()));
			}
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
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

		$filter = [
			'slaid' => $this->hasInput('filter_slaid'),
			'serviceid' => $this->hasInput('filter_serviceid'),
			'period_from' => $this->hasInput('filter_period_from'),
			'period_to' => $this->hasInput('filter_period_to')
		];
		$filter_submitted = array_filter($filter);

		$slaid = $filter_submitted
			? ($this->hasInput('filter_slaid') ? $this->getInput('filter_slaid') : null)
			: CProfile::get('web.slareport.filter_slaid');

		if ($slaid !== null) {
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

		$serviceid = $filter_submitted
			? ($this->hasInput('filter_serviceid') ? $this->getInput('filter_serviceid') : null)
			: CProfile::get('web.slareport.filter_serviceid');

		if ($serviceid !== null) {
			if ($this->sla === null) {
				return false;
			}

			$this->service = API::Service()->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => $serviceid
			]);

			if (!$this->service) {
				return false;
			}

			$this->service = reset($this->service);
		}

		return true;
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$sort_field = $this->getInput('sort', CProfile::get('web.slareport.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.slareport.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.slareport.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.slareport.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.slareport.filter_slaid', $this->getInput('filter_slaid', ''), PROFILE_TYPE_ID);
			CProfile::update('web.slareport.filter_serviceid', $this->getInput('filter_serviceid', ''),
				PROFILE_TYPE_ID
			);
			CProfile::update('web.slareport.filter_period_from', $this->getInput('filter_period_from', ''),
				PROFILE_TYPE_STR
			);
			CProfile::update('web.slareport.filter_period_to', $this->getInput('filter_period_to', ''),
				PROFILE_TYPE_STR
			);

			if ($this->getInput('filter_slaid', '') === '') {
				CProfile::delete('web.slareport.filter_serviceid');
			}
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.slareport.filter_slaid');
			CProfile::delete('web.slareport.filter_serviceid');
			CProfile::delete('web.slareport.filter_period_from');
			CProfile::delete('web.slareport.filter_period_to');

			CProfile::delete('web.slareport.list.sort');
			CProfile::delete('web.slareport.list.sortorder');
		}

		$filter = [
			'slaid' => $this->getInput('filter_slaid', ''),
			'serviceid' => $this->getInput('filter_serviceid', ''),
			'period_from' => $this->getInput('filter_period_from', ''),
			'period_to' => $this->getInput('filter_period_to', '')
		];
		$filter_submitted = [];
		$paging_curl = (new CUrl('zabbix.php'))->setArgument('action', 'slareport.list');
		$reset_curl = (clone $paging_curl)->setArgument('filter_rst', 1);

		if (!$this->hasInput('filter_rst')) {
			$filter_submitted = array_filter($filter);

			if (!$filter_submitted) {
				$filter = [
					'slaid' => CProfile::get('web.slareport.filter_slaid', ''),
					'serviceid' => CProfile::get('web.slareport.filter_serviceid', ''),
					'period_from' => CProfile::get('web.slareport.filter_period_from', ''),
					'period_to' => CProfile::get('web.slareport.filter_period_to', '')
				];

				$filter_submitted = array_filter($filter);
			}

			foreach ($filter_submitted as $key => $value) {
				$paging_curl->setArgument('filter_'.$key, $value);
			}
		}

		$data = [
			'can_manage_sla' => CWebUser::checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA),
			'filter' => $filter,
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'active_tab' => CProfile::get('web.slareport.filter.active', 1),
			'filter_url' => $paging_curl->getUrl(),
			'reset_curl' => $reset_curl,
			'ms_sla' => $this->sla !== null
				? [CArrayHelper::renameKeys($this->sla, ['slaid' => 'id'])]
				: null,
			'ms_service' => $this->service !== null
				? [CArrayHelper::renameKeys($this->service, ['serviceid' => 'id'])]
				: null,
			'periods' => null,
			'paging' => '',
			'sla' => $this->sla,
			'user' => ['debug_mode' => $this->getDebugMode()],
		];
		$services = [];

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

			$page_number = $this->getInput('page', 1);
			CPagerHelper::savePage('slareport.list', $page_number);
			$data['paging'] = CPagerHelper::paginate($page_number, $sla_services, $sort_order, $paging_curl);

			$parser = new CRangeTimeParser();
			$options = [
				'slaid' => $this->sla['slaid'],
				'serviceids' => array_keys($sla_services)
			];

			if ($filter['period_from'] !== '' && $parser->parse($filter['period_from']) !== CParser::PARSE_FAIL) {
				$options['period_from'] = $parser->getDateTime(true)->getTimestamp();
			}

			if ($filter['period_to'] !== '' && $parser->parse($filter['period_to']) !== CParser::PARSE_FAIL) {
				$options['period_to'] = $parser->getDateTime(false)->getTimestamp();
			}

			$sli = API::Sla()->getSli($options);

			$page_services = [
				'output' => ['name'],
				'slaids' => $this->sla['slaid'],
				'sortfield' => [$sort_field],
				'sortorder' => $sort_order,
				'serviceids' => $sli['serviceids'],
				'preservekeys' => true
			];

			$services = API::Service()->get($page_services);
			$data['periods'] = $sli['periods'];

			foreach($services as &$service) {
				$service['sli'] = [];
			}
			unset($service);

			foreach (array_keys($sli['periods']) as $period_key) {
				foreach ($sli['serviceids'] as $service_key => $serviceid) {
					$services[$serviceid]['sli'][] = $sli['sli'][$period_key][$service_key];
				}
			}
		}

		$data['services'] = $services;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('SLA Report'));
		$this->setResponse($response);
	}
}
