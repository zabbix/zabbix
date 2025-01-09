<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CControllerAvailabilityReportList extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'mode' =>					'in '.AVAILABILITY_REPORT_BY_HOST.','.AVAILABILITY_REPORT_BY_TEMPLATE,
			'filter_template_groups' =>	'array_db hosts_groups.groupid',
			'filter_templates' =>		'array_db hosts.hostid',
			'filter_triggers' =>		'array_db triggers.triggerid',
			'filter_host_groups' =>		'array_db hosts_groups.groupid',
			'filter_hosts' =>			'array_db hosts.hostid',
			'filter_set'=>				'in 1',
			'filter_rst' =>				'in 1',
			'from' =>					'range_time',
			'to' =>						'range_time',
			'page' => 					'ge 1'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_AVAILABILITY_REPORT);
	}

	protected function doAction(): void {
		$report_mode = $this->getInput('mode',
			CProfile::get('web.availabilityreport.filter.mode', AVAILABILITY_REPORT_BY_HOST)
		);
		$prefix = 'web.availabilityreport.filter.'.$report_mode;

		CProfile::update('web.availabilityreport.filter.mode', $report_mode, PROFILE_TYPE_INT);

		if ($this->hasInput('filter_set')) {
			$this->updateProfiles($report_mode);
		}
		elseif ($this->hasInput('filter_rst')) {
			$this->deleteProfiles($report_mode);
		}

		$timeselector_options = [
			'profileIdx' => 'web.availabilityreport.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];

		updateTimeSelectorPeriod($timeselector_options);

		$data = [
			'timeline' => getTimeSelectorPeriod($timeselector_options),
			'active_tab' => CProfile::get('web.availabilityreport.filter.active', 1),
			'profileIdx' => 'web.availabilityreport.filter',
			'mode' => $report_mode
		];

		$data['filter'] = ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE)
			? [
				'template_groups' => CProfile::getArray($prefix.'.template_groups',
					$this->getInput('filter_template_groups', [])),
				'templates' => CProfile::getArray($prefix.'.templates', $this->getInput('filter_templates', [])),
				'triggers' => CProfile::getArray($prefix.'.triggers', $this->getInput('filter_hosts', [])),
				'host_groups' => CProfile::getArray($prefix.'.host_groups', $this->getInput('filter_host_groups', []))
			]
			: [
				'host_groups' => CProfile::getArray($prefix.'.host_groups', $this->getInput('filter_host_groups', [])),
				'hosts' => CProfile::getArray($prefix.'.hosts', $this->getInput('filter_hosts', []))
			];

		$data['filter'] = $this->prepareDataForMultiselectFields($data['filter']);

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;

		if ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE) {
			$template_groupids = $data['filter']['template_groups']
				? getTemplateSubGroups(array_keys($data['filter']['template_groups']))
				: null;

			$triggers = API::Trigger()->get([
				'output' => ['description'],
				'selectHosts' => ['name'],
				'selectItems' => ['status'],
				'triggerids' => $data['filter']['triggers'] ? array_keys($data['filter']['triggers']) : null,
				'templateids' => $data['filter']['templates'] ? array_keys($data['filter']['templates']) : null,
				'groupids' => $template_groupids,
				'templated' => true,
				'filter' => [
					'status' => TRIGGER_STATUS_ENABLED,
					'flags' => [ZBX_FLAG_DISCOVERY_NORMAL]
				],
				'sortfield' => 'description',
				'preservekeys' => true
			]);

			if ($triggers) {
				foreach ($triggers as $id => $trigger) {
					foreach ($trigger['items'] as $item) {
						if ($item['status'] != ITEM_STATUS_ACTIVE) {
							unset($triggers[$id]);

							break;
						}
					}
				}

				if ($triggers) {
					$host_groupids = $data['filter']['host_groups']
						? getSubGroups(array_keys($data['filter']['host_groups']))
						: null;

					$triggers = API::Trigger()->get([
						'output' => ['triggerid', 'description', 'expression', 'value'],
						'selectHosts' => ['name'],
						'expandDescription' => true,
						'monitored' => true,
						'groupids' => $host_groupids,
						'filter' => ['templateid' => array_keys($triggers)],
						'limit' => $limit
					]);

					if (!$triggers) {
						$triggers = [];
					}
				}
			}
		}
		else {
			$host_groupids = $data['filter']['host_groups']
				? getSubGroups(array_keys($data['filter']['host_groups']))
				: null;

			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description', 'expression', 'value'],
				'selectHosts' => ['name'],
				'groupids' => $host_groupids,
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

	private function updateProfiles($report_mode): void {
		$prefix = 'web.availabilityreport.filter.'.$report_mode;

		if ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE) {
			CProfile::updateArray($prefix.'.template_groups', $this->getInput('filter_template_groups', []),
				PROFILE_TYPE_ID
			);
			CProfile::updateArray($prefix.'.templates', $this->getInput('filter_templates', []), PROFILE_TYPE_ID);
			CProfile::updateArray($prefix.'.triggers', $this->getInput('filter_triggers', []), PROFILE_TYPE_ID);
			CProfile::updateArray($prefix.'.host_groups', $this->getInput('filter_host_groups', []), PROFILE_TYPE_ID);
		}
		else {
			CProfile::updateArray($prefix.'.host_groups', $this->getInput('filter_host_groups', []), PROFILE_TYPE_ID);
			CProfile::updateArray($prefix.'.hosts', $this->getInput('filter_hosts', []), PROFILE_TYPE_ID);
		}
	}

	private function deleteProfiles($report_mode): void {
		$prefix = 'web.availabilityreport.filter.'.$report_mode;

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

	/**
	 * Prepare data for multiselect fields.
	 *
	 * @param array  $filter
	 *
	 * @return array
	 */

	private function prepareDataForMultiselectFields(array $filter): array {
		if (array_key_exists('template_groups', $filter)) {
			$template_groups = CArrayHelper::renameObjectsKeys(API::TemplateGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['template_groups'],
				'preservekeys' => true
			]), ['groupid' => 'id']);

			$prepared_data['template_groups'] = $template_groups ?: [];
		}

		if (array_key_exists('templates', $filter)) {
			$templates = CArrayHelper::renameObjectsKeys(API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter['templates'],
				'preservekeys' => true
			]), ['templateid' => 'id']);

			$prepared_data['templates'] = $templates ?: [];
		}

		if (array_key_exists('triggers', $filter)) {
			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description'],
				'triggerids' => $filter['triggers'],
				'templated' => true,
				'selectHosts' => ['name'],
				'filter' => [
					'status' => TRIGGER_STATUS_ENABLED,
					'flags' => [ZBX_FLAG_DISCOVERY_NORMAL]
				],
				'expandDescription' => true,
				'preservekeys' => true
			]);

			if ($triggers) {
				foreach ($triggers as $id => $trigger) {
					$prepared_data['triggers'][$id] = [
						'id' => $id,
						'name' => $trigger['hosts'][0]['name'].NAME_DELIMITER.$trigger['description']
					];
				}
			}
			else {
				$prepared_data['triggers'] = [];
			}
		}

		if (array_key_exists('host_groups', $filter)) {
			$host_groups = CArrayHelper::renameObjectsKeys(API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['host_groups'],
				'with_monitored_hosts' => true,
				'preservekeys' => true
			]), ['groupid' => 'id']);

			CArrayHelper::sort($host_groups, ['name']);

			$prepared_data['host_groups'] = $host_groups ?: [];
		}

		if (array_key_exists('hosts', $filter)) {
			$hosts = CArrayHelper::renameObjectsKeys(API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter['hosts'],
				'preservekeys' => true
			]), ['hostid' => 'id']);

			CArrayHelper::sort($hosts, ['name']);

			$prepared_data['hosts'] = $hosts ?: [];
		}

		return $prepared_data;
	}
}
