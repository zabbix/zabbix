<?php declare(strict_types = 1);

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


use Services\FilterCollection;
use Services\DataProviders\HostDataProvider;

class CControllerHostView extends CController {
	// TODO: remove ui/app/controllers/CControllerHost.php
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
			'filter_apply' =>				'in 1',
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
		$config = select_config();
		$view_curl = (new CUrl('zabbix.php'))->setArgument('action', 'host.view.refresh');
		$filter_collection = new FilterCollection(CWebUser::$data['userid'], 'web.monitoring.hosts');
		$filter_collection->setDefaultProvider(HostDataProvider::PROVIDER_TYPE);
		$filter_collection->init();
		$data_provider = $filter_collection->getActiveDataProvider();
		$filter = $this->hasInput('filter_apply') ? $data_provider->getFieldsDefaults() : $data_provider->getFields();
		$filter += [
			'view_curl' => $view_curl,
			'limit' => $config['search_limit'] + 1,
			'sort' => null,
			'sortorder' => null,
			'page' => null
		];
		$this->getInputs($filter, ['sort', 'sortorder', 'page']);

		if (!$this->hasInput('filter_rst')) {
			$input = [];
			$field_mapping = [
				'filter_name' =>				'name',
				'filter_groupids' =>			'groupids',
				'filter_ip' =>					'ip',
				'filter_dns' =>					'dns',
				'filter_port' =>				'port',
				'filter_status' =>				'status',
				'filter_evaltype' =>			'evaltype',
				'filter_tags' =>				'tags',
				'filter_severities' =>			'severities',
				'filter_show_suppressed' =>		'show_suppressed',
				'filter_maintenance_status' =>	'maintenance_status'
			];
			$this->getInputs($input, array_keys($field_mapping));
			$input = CArrayHelper::renameKeys($input, array_intersect_key($field_mapping, $input));
			$data_provider->updateFields($input + $filter);
			$filter = $data_provider->getFields();
		}

		if ($this->hasInput('filter_set')) {
			$filter_collection->updateProfile(CWebUser::$data['userid'], $data_provider);
		}

		$refresh_curl = (new CUrl('zabbix.php'))
			->setArgument('action', 'host.view.refresh')
			->setArgument('filter_name', $filter['name'] ? $filter['name'] : null)
			->setArgument('filter_groupids', $filter['groupids'] ? $filter['groupids'] : null)
			->setArgument('filter_ip', $filter['ip'] ? $filter['ip'] : null)
			->setArgument('filter_dns', $filter['dns'] ? $filter['dns'] : null)
			->setArgument('filter_status', $filter['status'])
			->setArgument('filter_evaltype', $filter['evaltype'])
			->setArgument('filter_tags', $filter['tags'] ? $filter['tags'] : null)
			->setArgument('filter_severities', $filter['severities'] ? $filter['severities'] : null)
			->setArgument('filter_show_suppressed', $filter['show_suppressed'])
			->setArgument('filter_maintenance_status', $filter['maintenance_status'])
			->setArgument('sort', $filter['sort'])
			->setArgument('sortorder', $filter['sortorder'])
			->setArgument('page', $filter['page']);


		$data = [
			'config' => $config,
			'view_curl' => $view_curl,
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 1000,

			'filter_tabs' => $filter_collection->getDataProvidersArray()
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Hosts'));
		$this->setResponse($response);
	}
}
