<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


/**
 * Controller for the "Host->Monitoring" asynchronous refresh page.
 */
class CControllerHostViewRefresh extends CControllerHost {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$severities = [];
		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severities[] = $severity;
		}

		$fields = [
			//'action' =>					'string',
			'sort' =>				'in name,status',
			'sortorder' =>				'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'page' =>					'ge 1',
			'filter_set' =>				'in 1',
			'filter_rst' =>				'in 1',
			'filter_name' =>			'string',
			'filter_groupids' =>		'array_id',
			'filter_ip_dns' =>			'string',
			'filter_port' =>			'string',
			'filter_status' =>			'in -1,'.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED,
			'filter_evaltype' =>		'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>			'array',
			'filter_severities' =>		'array',
			'filter_show_suppressed' =>	'in '.ZBX_PROBLEM_SUPPRESSED_TRUE,
			'filter_maintenance' =>		'in '.HOST_MAINTENANCE_STATUS_ON
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->hasInput('filter_tags')) {
			foreach ($this->getInput('filter_tags') as $filter_tag) {
				if (count($filter_tag) != 3
						|| !array_key_exists('tag', $filter_tag) || !is_string($filter_tag['tag'])
						|| !array_key_exists('value', $filter_tag) || !is_string($filter_tag['value'])
						|| !array_key_exists('operator', $filter_tag) || !is_string($filter_tag['operator'])) {
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

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];

		foreach ($this->getInput('filter_tags', []) as $filter_tag) {
			if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
				continue;
			}

			$filter_tags['tags'][] = $filter_tag['tag'];
			$filter_tags['values'][] = $filter_tag['value'];
			$filter_tags['operators'][] = $filter_tag['operator'];
		}

		// filter
		$filter = [
			'name' => $this->getInput('filter_name', ''),
			'groupids' => $this->hasInput('filter_groupids') ? $this->getInput('filter_groupids') : null,
			'ip_dns' => $this->getInput('filter_ip_dns', ''),
			'port' => $this->getInput('filter_ip_dns', ''),
			'status' => $this->getInput('filter_ip_dns', -1),
			'evaltpye' => $this->getInput('evaltpye', TAG_EVAL_TYPE_AND_OR),
			'tags' => $filter_tags,
			'severities' => $this->getInput('filter_severities', []),
			'show_suppressed' => $this->getInput('filter_show_suppressed', ZBX_PROBLEM_SUPPRESSED_FALSE),
			'show_maintenance' => $this->getInput('filter_show_maintenance', HOST_MAINTENANCE_STATUS_OFF)
		];

		$sort = $this->getInput('sort', 'name');
		$sortorder = $this->getInput('sortorder', ZBX_SORT_UP);

		$view_curl = (new CUrl('zabbix.php'))->setArgument('action', 'host.view');

		// data sort and pager
		$prepared_data = $this->prepareData($filter, $sort, $sortorder);

		$paging = CPagerHelper::paginate(getRequest('page', 1), $prepared_data['hosts'], ZBX_SORT_UP, $view_curl);

		// display
		$data = [
			'filter' => $filter,
			'sort' => $sort,
			'sortorder' => $sortorder,
			'view_curl' => $view_curl,
			'paging' => $paging
		] + $prepared_data;

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
