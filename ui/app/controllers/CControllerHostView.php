<?php declare(strict_types = 1);
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


class CControllerHostView extends CControllerHost {

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'sort' =>						'in name,status',
			'sortorder' =>					'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
			'page' =>						'ge 1',
			'filter_set' =>					'in 1',
			'filter_rst' =>					'in 1',
			'filter_name' =>				'string',
			'filter_groupids' =>			'array_id',
			'filter_ip' =>					'string',
			'filter_dns' =>					'string',
			'filter_port' =>				'string',
			'filter_status' =>				'in -1,'.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED,
			'filter_evaltype' =>			'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>				'array',
			'filter_severities' =>			'array',
			'filter_show_suppressed' =>		'in '.ZBX_PROBLEM_SUPPRESSED_FALSE.','.ZBX_PROBLEM_SUPPRESSED_TRUE,
			'filter_maintenance_status' =>	'in '.HOST_MAINTENANCE_STATUS_OFF.','.HOST_MAINTENANCE_STATUS_ON
		];

		$ret = $this->validateInput($fields);

		// Validate tags filter.
		if ($ret && $this->hasInput('filter_tags')) {
			foreach ($this->getInput('filter_tags') as $filter_tag) {
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
		if ($ret && $this->hasInput('filter_severities')) {
			foreach ($this->getInput('filter_severities') as $severity) {
				if (!in_array($severity, range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1))) {
					$ret = false;
					break;
				}
			}
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction(): void {
		$sort = $this->getInput('sort', CProfile::get('web.hostsmon.sort', 'name'));
		$sortorder = $this->getInput('sortorder', CProfile::get('web.hostsmon.sortorder', ZBX_SORT_UP));
		$active_tab = CProfile::get('web.hostsmon.filter.active', 1);
		CProfile::update('web.hostsmon.filter.active', $active_tab, PROFILE_TYPE_INT);
		CProfile::update('web.hostsmon.sort', $sort, PROFILE_TYPE_STR);
		CProfile::update('web.hostsmon.sortorder', $sortorder, PROFILE_TYPE_STR);

		// filter
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.hostsmon.filter.name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::updateArray('web.hostsmon.filter.groupids', $this->getInput('filter_groupids', []),
				PROFILE_TYPE_ID
			);
			CProfile::update('web.hostsmon.filter.ip', $this->getInput('filter_ip', ''), PROFILE_TYPE_STR);
			CProfile::update('web.hostsmon.filter.dns', $this->getInput('filter_dns', ''), PROFILE_TYPE_STR);
			CProfile::update('web.hostsmon.filter.port', $this->getInput('filter_port', ''), PROFILE_TYPE_STR);
			$severities = $this->getInput('filter_severities', []);
			CProfile::updateArray('web.hostsmon.filter.severities', $severities, PROFILE_TYPE_INT);
			CProfile::update('web.hostsmon.filter.status', getRequest('filter_status', -1), PROFILE_TYPE_INT);

			$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
			foreach ($this->getInput('filter_tags', []) as $filter_tag) {
				if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
					continue;
				}

				$filter_tags['tags'][] = $filter_tag['tag'];
				$filter_tags['values'][] = $filter_tag['value'];
				$filter_tags['operators'][] = $filter_tag['operator'];
			}

			CProfile::update('web.hostsmon.filter.evaltype', $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
				PROFILE_TYPE_INT
			);
			CProfile::updateArray('web.hostsmon.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.hostsmon.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.hostsmon.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
			CProfile::update('web.hostsmon.filter.show_suppressed',
				$this->getInput('filter_show_suppressed', ZBX_PROBLEM_SUPPRESSED_FALSE), PROFILE_TYPE_INT
			);
			CProfile::update('web.hostsmon.filter.maintenance_status',
				$this->getInput('filter_maintenance_status', HOST_MAINTENANCE_STATUS_ON), PROFILE_TYPE_INT
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.hostsmon.filter.name');
			CProfile::deleteIdx('web.hostsmon.filter.groupids');
			CProfile::delete('web.hostsmon.filter.ip');
			CProfile::delete('web.hostsmon.filter.dns');
			CProfile::delete('web.hostsmon.filter.port');
			CProfile::deleteIdx('web.hostsmon.filter.severities');
			CProfile::delete('web.hostsmon.filter.status');
			CProfile::delete('web.hostsmon.filter.show_suppressed');
			CProfile::delete('web.hostsmon.filter.maintenance_status');
			CProfile::delete('web.hostsmon.filter.evaltype');
			CProfile::deleteIdx('web.hostsmon.filter.tags.tag');
			CProfile::deleteIdx('web.hostsmon.filter.tags.value');
			CProfile::deleteIdx('web.hostsmon.filter.tags.operator');
		}

		$filter_tags = [];
		foreach (CProfile::getArray('web.hostsmon.filter.tags.tag', []) as $i => $tag) {
			$filter_tags[] = [
				'tag' => $tag,
				'value' => CProfile::get('web.hostsmon.filter.tags.value', null, $i),
				'operator' => CProfile::get('web.hostsmon.filter.tags.operator', null, $i)
			];
		}
		CArrayHelper::sort($filter_tags, ['tag', 'value', 'operator']);

		$filter = [
			'name' => CProfile::get('web.hostsmon.filter.name', ''),
			'groupids' => CProfile::getArray('web.hostsmon.filter.groupids', []),
			'ip' => CProfile::get('web.hostsmon.filter.ip', ''),
			'dns' => CProfile::get('web.hostsmon.filter.dns', ''),
			'port' => CProfile::get('web.hostsmon.filter.port', ''),
			'status' => CProfile::get('web.hostsmon.filter.status', -1),
			'evaltype' => CProfile::get('web.hostsmon.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => $filter_tags,
			'severities' => CProfile::getArray('web.hostsmon.filter.severities', []),
			'show_suppressed' => CProfile::get('web.hostsmon.filter.show_suppressed', ZBX_PROBLEM_SUPPRESSED_FALSE),
			'maintenance_status' => CProfile::get('web.hostsmon.filter.maintenance_status', HOST_MAINTENANCE_STATUS_ON),
			'page' => $this->hasInput('page') ? $this->getInput('page') : null
		];

		$refresh_curl = (new CUrl('zabbix.php'))->setArgument('action', 'host.view.refresh');

		$refresh_data = array_filter([
			'filter_name' => $filter['name'],
			'filter_groupids' => $filter['groupids'],
			'filter_ip' => $filter['ip'],
			'filter_dns' => $filter['dns'],
			'filter_port' => $filter['port'],
			'filter_status' => $filter['status'],
			'filter_evaltype' => $filter['evaltype'],
			'filter_tags' => $filter['tags'],
			'filter_severities' => $filter['severities'],
			'filter_show_suppressed' => $filter['show_suppressed'],
			'filter_maintenance_status' => $filter['maintenance_status'],
			'sort' => $sort,
			'sortorder' => $sortorder,
			'page' => $filter['page']
		]);

		$prepared_data = $this->prepareData($filter, $sort, $sortorder);

		$data = [
			'filter' => $filter,
			'sort' => $sort,
			'sortorder' => $sortorder,
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_data' => $refresh_data,
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'active_tab' => $active_tab
		] + $prepared_data;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Hosts'));
		$this->setResponse($response);
	}
}
