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


/**
 * Controller for the "Host->Monitoring" asynchronous refresh page.
 */
class CControllerHostViewRefresh extends CControllerHost {

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
				if (count($filter_tag) != 3
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
		$filter = [
			'name' => $this->getInput('filter_name', ''),
			'groupids' => $this->hasInput('filter_groupids') ? $this->getInput('filter_groupids') : null,
			'ip' => $this->getInput('filter_ip', ''),
			'dns' => $this->getInput('filter_dns', ''),
			'port' => $this->getInput('filter_port', ''),
			'status' => $this->getInput('filter_status', -1),
			'evaltype' => $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => $this->getInput('filter_tags', []),
			'severities' => $this->getInput('filter_severities', []),
			'show_suppressed' => $this->getInput('filter_show_suppressed', ZBX_PROBLEM_SUPPRESSED_FALSE),
			'maintenance_status' => $this->getInput('filter_maintenance_status', HOST_MAINTENANCE_STATUS_ON),
			'page' => $this->hasInput('page') ? $this->getInput('page') : null
		];

		$sort = $this->getInput('sort', 'name');
		$sortorder = $this->getInput('sortorder', ZBX_SORT_UP);

		$view_curl = (new CUrl('zabbix.php'))->setArgument('action', 'host.view');

		$prepared_data = $this->prepareData($filter, $sort, $sortorder);

		$data = [
			'filter' => $filter,
			'sort' => $sort,
			'sortorder' => $sortorder,
			'view_curl' => $view_curl
		] + $prepared_data;

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
