<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CControllerAvailabilityReport extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'mode' =>					'in '.AVAILABILITY_REPORT_BY_HOST.','.AVAILABILITY_REPORT_BY_TEMPLATE,
			'filter_template_groups' => 'array_db hosts_groups.groupid',
			'filter_templates' =>		'array_db hosts.hostid',
			'filter_triggers' =>		'array_db triggers.triggerid',
			'filter_host_groups' =>		'array_db hosts_groups.groupid',
			'filter_hosts' =>			'array_db hosts.hostid',
			'filter_set'=>				'in 1',
			'filter_rst' =>				'in 1',
			'from' =>					'range_time',
			'to' =>						'range_time'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT)) {
			return false;
		}

		if ($this->hasInput('filter_host_groups')) {
			$hostgroup = API::HostGroup()->get([
				'countOutput' => true,
				'groupids' => $this->getInput('filter_host_groups')
			]);

			if (!$hostgroup) {
				return false;
			}
		}

		if ($this->hasInput('filter_template_groups')) {
			$templategroup = API::TemplateGroup()->get([
				'countOutput' => true,
				'groupids' => $this->getInput('filter_template_groups')
			]);

			if (!$templategroup) {
				return false;
			}
		}

		if ($this->hasInput('filter_templates')) {
			$template = API::Template()->get([
				'countOutput' => true,
				'templateids' => $this->getInput('filter_templates')
			]);

			if (!$template) {
				return false;
			}
		}

		if ($this->hasInput('filter_triggers')) {
			$trigger = API::Trigger()->get([
				'countOutput' => true,
				'triggerids' => $this->getInput('filter_triggers')
			]);

			if (!$trigger) {
				return false;
			}
		}

		return true;
	}

	protected function doAction(): void {
		$report_mode = $this->getInput('mode', CProfile::get('web.availabilityreport.filter.mode', AVAILABILITY_REPORT_BY_HOST));
		$data['mode'] = $report_mode;
		CProfile::update('web.availabilityreport.filter.mode', $report_mode, PROFILE_TYPE_INT);
		$prefix = 'web.availabilityreport.filter.'.$report_mode;

		if ($this->hasInput('filter_set')) {
			if ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE) {
				CProfile::updateArray($prefix.'.template_groups', $this->getInput('filter_template_groups', []), PROFILE_TYPE_ID);
				CProfile::updateArray($prefix.'.templates', $this->getInput('filter_templates', []), PROFILE_TYPE_ID);
				CProfile::updateArray($prefix.'.triggers', $this->getInput('filter_triggers', []), PROFILE_TYPE_ID);
				CProfile::updateArray($prefix.'.host_groups', $this->getInput('filter_host_groups', []), PROFILE_TYPE_ID);
			}
			else {
				CProfile::updateArray($prefix.'.host_groups', $this->getInput('filter_groups', []), PROFILE_TYPE_ID);
				CProfile::updateArray($prefix.'.hosts', $this->getInput('filter_hostids', []), PROFILE_TYPE_ID);
			}
		}
		elseif ($this->hasInput('filter_rst')) {
			if ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE) {
				CProfile::delete($prefix.'.template_groups');
				CProfile::delete($prefix.'.templates');
				CProfile::delete($prefix.'.triggers');
				CProfile::delete($prefix.'.host_groups');
			}
			else {
				CProfile::deleteIdx($prefix.'.host_groups');
				CProfile::deleteIdx($prefix.'.hosts');
			}
		}

		$timeselector_options = [
			'profileIdx' => 'web.availabilityreport.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];
		updateTimeSelectorPeriod($timeselector_options);

		$data += [
			'timeline' => getTimeSelectorPeriod($timeselector_options),
			'active_tab' => CProfile::get('web.availabilityreport.filter.active', 1),
			'profileIdx' => 'web.availabilityreport.filter'
		];

		$data['filter'] = ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE)
			? [
				'template_groups' => CProfile::getArray($prefix.'.template_groups', $this->getInput('filter_template_groups', [])),
				'templates' => CProfile::getArray($prefix.'.templates', $this->getInput('filter_templates', [])),
				'triggers' => CProfile::getArray($prefix.'.triggers', $this->getInput('filter_hosts', [])),
				'host_groups' => CProfile::getArray($prefix.'.host_groups', $this->getInput('filter_host_groups', []))
			]
			: [
				'host_groups' => CProfile::getArray($prefix.'.host_groups', $this->getInput('filter_host_groups', [])),
				'hosts' => CProfile::getArray($prefix.'.hosts', $this->getInput('filter_hosts', []))
			];

		if ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE) {
			$data['filter']['template_groups'] = $data['filter']['template_groups']
				? $this->prepareDataForMultiselect($data['filter']['template_groups'], 'template_groups')
				: [];

			$data['filter']['templates'] = $data['filter']['templates']
				? $this->prepareDataForMultiselect($data['filter']['templates'], 'templates')
				: [];

			$data['filter']['triggers'] = $data['filter']['triggers']
				? $this->prepareDataForMultiselect($data['filter']['triggers'], 'triggers')
				: [];

			$data['filter']['host_groups'] = $data['filter']['host_groups']
				? $this->prepareDataForMultiselect($data['filter']['host_groups'], 'host_groups')
				: [];

			$groups = [];

			if ($data['filter']['template_groups']) {
				$groups = array_merge($groups, array_keys($data['filter']['template_groups']));
			}

			if ($data['filter']['host_groups']) {
				$groups = array_merge($groups, array_keys($data['filter']['host_groups']));
			}

			$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'expression', 'value'],
				'selectHosts' => ['name'],
				'groupids' => $groups ? $groups : null,
				'templateids' => $data['filter']['templates'] ? array_keys($data['filter']['templates']) : null,
				'triggerids' => $data['filter']['triggers'] ? array_keys($data['filter']['triggers']) : null,
				'expandDescription' => true,
				'limit' => $limit,
				'preservekeys' => true
			]);
		}
		else {
			$data['filter']['host_groups'] = $data['filter']['host_groups']
				? $this->prepareDataForMultiselect($data['filter']['host_groups'], 'host_groups')
				: [];

			$data['filter']['hosts'] = $data['filter']['hosts']
				? $this->prepareDataForMultiselect($data['filter']['hosts'], 'hosts')
				: [];

			// Select monitored host triggers, derived from templates and belonging to the requested groups.
			$host_groups = enrichParentGroups($data['filter']['host_groups']);

			$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'expression', 'value'],
				'selectHosts' => ['name'],
				'groupids' => $host_groups ? array_keys($host_groups) : null,
				'hostids' => $data['filter']['hosts'] ? array_keys($data['filter']['hosts']) : null,
				'expandDescription' => true,
				'monitored' => true,
				'limit' => $limit
			]);
		}

		foreach ($triggers as &$trigger) {
			$trigger['host_name'] = $trigger['hosts'][0]['name'];
		}
		unset($trigger);

		CArrayHelper::sort($triggers, ['host_name', 'description']);

		// pager
		$page = $this->getInput('page', 1);
		$view_url = (new CUrl('zabbix.php'))->setArgument('action', 'availabilityreport.list');
		CPagerHelper::savePage('availabilityreport.list', $page);

		$data += [
			'paging' => CPagerHelper::paginate($page, $triggers, ZBX_SORT_UP, $view_url),
			'can_monitor_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
			'triggers' => $triggers
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Availability report'));
		$this->setResponse($response);
	}

	/**
	 * Prepare data for multiselect fields.
	 *
	 * @param array  $ids
	 * @param string $type  Defines data type ('hosts', 'host_groups', 'templates', 'template_groups',
	 *                      'triggers').
	 *
	 * @return array
	 */

	private function prepareDataForMultiselect(array $ids, string $type): array {
		$prepared_data = [];

		switch ($type) {
			case 'hosts':
				$hosts = API::Host()->get([
					'output' => ['hostid', 'name'],
					'hostids' => $ids,
					'monitored_hosts' => true,
					'with_triggers' => true,
					'preservekeys' => true
				]);

				if ($hosts) {
					$prepared_data = CArrayHelper::renameObjectsKeys($hosts, ['hostid' => 'id']);
				}

				break;
			case 'host_groups':
				$host_groups = API::HostGroup()->get([
					'output' => ['groupid', 'name'],
					'groupids' => $ids,
					'with_monitored_hosts' => true,
					'preservekeys' => true
				]);

				if ($host_groups) {
					$prepared_data = CArrayHelper::renameObjectsKeys($host_groups, ['groupid' => 'id']);
				}

				break;
			case 'templates':
				$templates = API::Template()->get([
					'output' => ['templateid', 'name'],
					'templateids' => $ids,
					'with_triggers' => true,
					'preservekeys' => true
				]);

				if ($templates) {
					$prepared_data = CArrayHelper::renameObjectsKeys($templates, ['templateid' => 'id']);
				}

				break;
			case 'template_groups':
				$template_groups = API::TemplateGroup()->get([
					'output' => ['groupid', 'name'],
					'groupids' => $ids,
					'with_templates' => true,
					'with_triggers' => true,
					'preservekeys' => true
				]);

				if ($template_groups) {
					$prepared_data = CArrayHelper::renameObjectsKeys($template_groups, ['groupid' => 'id']);
				}

				break;

			case 'triggers':
				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'description'],
					'triggerids' => $ids,
					'templated' => true,
					'selectHosts' => ['name'],
					'filter' => [
						'status' => TRIGGER_STATUS_ENABLED,
						'flags' => [ZBX_FLAG_DISCOVERY_NORMAL]
					],
					'preservekeys' => true
				]);

				if ($triggers) {
					foreach ($triggers as $id => $trigger) {
						$prepared_data[$id] = [
							'id' => $id,
							'name' => $trigger['hosts'][0]['name'].NAME_DELIMITER.$trigger['description']
						];
					}
				}

				break;
		}

		CArrayHelper::sort($prepared_data, ['name']);

		return $prepared_data;
	}
}
