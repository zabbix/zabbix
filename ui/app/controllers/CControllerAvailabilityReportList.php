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


class CControllerAvailabilityReportList extends CController {

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
		CProfile::update('web.availabilityreport.filter.mode', $report_mode, PROFILE_TYPE_INT);
		$prefix = 'web.availabilityreport.filter.'.$report_mode;

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

			$triggers = API::Trigger()->get([
				'output' => ['description'],
				'selectHosts' => ['name'],
				'selectItems' => ['status'],
				'templateids' => $data['filter']['templates'] ? array_keys($data['filter']['templates']) : null,
				'groupids' => $data['filter']['template_groups']
					? array_keys($data['filter']['template_groups'])
					: null,
				'templated' => true,
				'filter' => [
					'status' => TRIGGER_STATUS_ENABLED,
					'flags' => [ZBX_FLAG_DISCOVERY_NORMAL]
				],
				'sortfield' => 'description',
				'preservekeys' => true
			]);

			foreach ($triggers as $id => $trigger) {
				foreach ($trigger['items'] as $item) {
					if ($item['status'] != ITEM_STATUS_ACTIVE) {
						unset($triggers[$id]);

						break;
					}
				}
			}

			$triggerids = [];

			foreach ($triggers as $id => $trigger) {
				$triggerids[$id] = true;
			}

			$templated_triggers_all = $data['filter']['triggers']
				? [array_key_first($data['filter']['triggers']) => true]
				: $triggerids;

			$templated_triggers_new = $templated_triggers_all;

			while ($templated_triggers_new) {
				$templated_triggers_new = API::Trigger()->get([
					'output' => ['triggerid'],
					'templated' => true,
					'filter' => ['templateid' => array_keys($templated_triggers_new)],
					'preservekeys' => true
				]);
				$templated_triggers_new = array_diff_key($templated_triggers_new, $templated_triggers_all);
				$templated_triggers_all += $templated_triggers_new;
			}

			if ($templated_triggers_all) {
				$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'description', 'expression', 'value'],
					'selectHosts' => ['name'],
					'expandDescription' => true,
					'monitored' => true,
					'groupids' => $data['filter']['host_groups'] ? array_keys($data['filter']['host_groups']) : null,
					'filter' => ['templateid' => array_keys($templated_triggers_all)],
					'limit' => $limit
				]);
			}
			else {
				$triggers = [];
			}
		}
		else {
			$data['filter']['host_groups'] = $data['filter']['host_groups']
				? $this->prepareDataForMultiselect($data['filter']['host_groups'], 'host_groups')
				: [];

			$data['filter']['hosts'] = $data['filter']['hosts']
				? $this->prepareDataForMultiselect($data['filter']['hosts'], 'hosts')
				: [];

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

	private function updateProfiles($report_mode): void {
		$prefix = 'web.availabilityreport.filter.'.$report_mode;

		if ($report_mode == AVAILABILITY_REPORT_BY_TEMPLATE) {
			CProfile::updateArray($prefix.'.template_groups',
				$this->getInput('filter_template_groups', []), PROFILE_TYPE_ID
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
	 * @param array  $ids
	 * @param string $type  Defines data type ('hosts', 'host_groups', 'templates', 'template_groups', 'triggers').
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
