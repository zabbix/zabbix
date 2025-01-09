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


class CControllerHostView extends CControllerHost {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'name' =>					'string',
			'groupids' =>				'array_db hosts_groups.groupid',
			'ip' =>						'string',
			'dns' =>					'string',
			'port' =>					'string',
			'status' =>					'in -1,'.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED,
			'evaltype' =>				'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'tags' =>					'array',
			'severities' =>				'array',
			'show_suppressed' =>		'in '.ZBX_PROBLEM_SUPPRESSED_FALSE.','.ZBX_PROBLEM_SUPPRESSED_TRUE,
			'maintenance_status' =>		'in '.HOST_MAINTENANCE_STATUS_OFF.','.HOST_MAINTENANCE_STATUS_ON,
			'sort' =>					'in name,status',
			'sortorder' =>				'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
			'page' =>					'ge 1',
			'filter_name' =>			'string',
			'filter_custom_time' =>		'in 1,0',
			'filter_show_counter' =>	'in 1,0',
			'filter_counters' =>		'in 1',
			'filter_reset' =>			'in 1',
			'counter_index' =>			'ge 0'
		];

		$ret = $this->validateInput($fields);

		// Validate tags filter.
		if ($ret && $this->hasInput('tags')) {
			foreach ($this->getInput('tags') as $filter_tag) {
				if (!is_array($filter_tag)
						|| count($filter_tag) != 3
						|| !array_key_exists('tag', $filter_tag) || !is_string($filter_tag['tag'])
						|| !array_key_exists('value', $filter_tag) || !is_string($filter_tag['value'])
						|| !array_key_exists('operator', $filter_tag) || !is_string($filter_tag['operator'])) {
					$ret = false;
					break;
				}
			}
		}

		// Validate severity checkbox filter.
		if ($ret && $this->hasInput('severities')) {
			$ret = !array_diff($this->getInput('severities'),
				range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1)
			);
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_HOSTS);
	}

	protected function doAction(): void {
		$filter_tabs = [];
		$profile = (new CTabFilterProfile(static::FILTER_IDX, static::FILTER_FIELDS_DEFAULT))->read();

		if ($this->hasInput('filter_reset')) {
			$profile->reset();
		}
		else {
			$profile->setInput($this->cleanInput($this->getInputAll()));
		}

		foreach ($profile->getTabsWithDefaults() as $index => $filter_tab) {
			if ($index == $profile->selected) {
				// Initialize multiselect data for filter_scr to allow tabfilter correctly handle unsaved state.
				$filter_tab['filter_src']['filter_view_data'] = $this->getAdditionalData($filter_tab['filter_src']);
			}

			$filter_tabs[] = $filter_tab + ['filter_view_data' => $this->getAdditionalData($filter_tab)];
		}

		$filter = $filter_tabs[$profile->selected];
		$filter = self::sanitizeFilter($filter);

		$refresh_curl = new CUrl('zabbix.php');
		$filter['action'] = 'host.view.refresh';
		array_map([$refresh_curl, 'setArgument'], array_keys($filter), $filter);

		$data = [
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'filter_view' => 'monitoring.host.filter',
			'filter_defaults' => $profile->filter_defaults,
			'filter_groupids' => $this->getInput('groupids', []),
			'filter_tabs' => $filter_tabs,
			'can_create_hosts' => $this->checkAccess(CRoleHelper::UI_CONFIGURATION_HOSTS),
			'tabfilter_options' => [
				'idx' => static::FILTER_IDX,
				'selected' => $profile->selected,
				'support_custom_time' => 0,
				'expanded' => $profile->expanded,
				'page' => $filter['page'],
				'csrf_token' => CCsrfTokenHelper::get('tabfilter')
			]
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Hosts'));
		$this->setResponse($response);
	}
}
