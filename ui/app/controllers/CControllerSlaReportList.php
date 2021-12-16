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


class CControllerSlaReportList extends CController {

	private $sla;
	private $service;
	private $period_from;
	private $period_to;

	protected function init(): void {
		$this->disableSIDValidation();
	}

	/**
	 * @throws InvalidArgumentException
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

		if ($ret && $this->hasInput('filter_set')) {
			$filter_date_from = $this->getInput('filter_date_from', '');

			if ($filter_date_from !== '') {
				$date_from = DateTime::createFromFormat('!'.DATE_FORMAT, $filter_date_from, new DateTimeZone('UTC'));
				$last_errors = DateTime::getLastErrors();

				if ($date_from === false || $last_errors['warning_count'] > 0 || $last_errors['error_count'] > 0) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('From'), _('a date is expected')));
				}
				else {
					$this->period_from = $date_from->getTimestamp();

					if ($this->period_from < 0 || $this->period_from > ZBX_MAX_DATE) {
						$this->period_from = null;

						error(_s('Incorrect value for field "%1$s": %2$s.', _('From'),
							_s('a time not later than %1$s is expected', zbx_date2str(DATE_TIME_FORMAT, ZBX_MAX_DATE))
						));
					}
				}
			}

			$filter_date_to = $this->getInput('filter_date_to', '');

			if ($filter_date_to !== '') {
				$date_to = DateTime::createFromFormat('!'.DATE_FORMAT, $filter_date_to, new DateTimeZone('UTC'));
				$last_errors = DateTime::getLastErrors();

				if ($date_to === false || $last_errors['warning_count'] > 0 || $last_errors['error_count'] > 0) {
					error(_s('Incorrect value for field "%1$s": %2$s.', _('To'), _('a date is expected')));
				}
				else {
					$this->period_to = $date_to->getTimestamp();

					if ($this->period_to < 0 || $this->period_to > ZBX_MAX_DATE) {
						$this->period_to = null;

						error(_s('Incorrect value for field "%1$s": %2$s.', _('To'),
							_s('a time not later than %1$s is expected', zbx_date2str(DATE_TIME_FORMAT, ZBX_MAX_DATE))
						));
					}
				}
			}

			if ($this->period_from !== null && $this->period_to !== null && $this->period_to <= $this->period_from) {
				$this->period_from = null;
				$this->period_to = null;

				error(_s('"%1$s" date must be less than "%2$s" date.', _('From'), _('To')));
			}

			if ($this->period_from >= time()) {
				$this->period_from = null;

				error(_s('Incorrect value for field "%1$s": %2$s.', _('From'),
					_s('a date not later than %1$s is expected', zbx_date2str(DATE_FORMAT, time(), 'UTC'))
				));
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	/**
	 * @throws APIException
	 *
	 * @return bool
	 */
	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SLA_REPORT);
	}

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

		$slaid = CProfile::get('web.slareport.list.filter.slaid');

		if ($slaid != 0) {
			$sla = API::Sla()->get([
				'output' => ['slaid', 'name', 'slo', 'period', 'timezone'],
				'slaids' => $slaid
			]);

			if ($sla) {
				$this->sla = $sla[0];
			}
			else {
				CProfile::delete('web.slareport.list.filter.slaid');
			}
		}

		$serviceid = CProfile::get('web.slareport.list.filter.serviceid');

		if ($serviceid != 0) {
			$service = API::Service()->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => $serviceid
			]);

			if ($service) {
				$this->service = $service[0];
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

		$page_num = getRequest('page', 1);
		CPagerHelper::savePage('slareport.list', $page_num);

		$data = [
			'has_access' => [
				CRoleHelper::ACTIONS_MANAGE_SLA => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA)
			],
			'filter' => $filter,
			'active_tab' => CProfile::get('web.slareport.list.filter.active', 1),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'ms_sla' => $this->sla !== null
				? [CArrayHelper::renameKeys($this->sla, ['slaid' => 'id'])]
				: [],
			'ms_service' => $this->service !== null
				? [CArrayHelper::renameKeys($this->service, ['serviceid' => 'id'])]
				: [],
			'sla' => $this->sla,
			'service' => $this->service
		];

		if ($this->sla !== null) {
			$options = [
				'output' => ['name'],
				'serviceids' => $this->service !== null ? $this->service['serviceid'] : null,
				'slaids' => $this->sla['slaid'],
				'sortfield' => $sort_field,
				'sortorder' => $sort_order,
				'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
				'preservekeys' => true
			];

			$data['services'] = API::Service()->get($options);

			$data['paging'] = CPagerHelper::paginate($page_num, $data['services'], $sort_order,
				(new CUrl('zabbix.php'))
					->setArgument('action', $this->getAction())
					->setArgument('filter_slaid', $this->sla['slaid'])
					->setArgument('filter_serviceid', 0)
					->setArgument('filter_set', 1)
			);

			$options = [
				'slaid' => $this->sla['slaid'],
				'serviceids' => array_keys($data['services']),
				'period_from' => $this->period_from,
				'period_to' => $this->period_to
			];

			$data['sli'] = API::Sla()->getSli($options);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('SLA Report'));
		$this->setResponse($response);
	}
}
