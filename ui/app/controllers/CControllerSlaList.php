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


class CControllerSlaList extends CController {

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_name' =>		'string',
			'filter_status' =>		'in '.implode(',', [
				CSlaHelper::SLA_STATUS_ANY,
				CSlaHelper::SLA_STATUS_ENABLED,
				CSlaHelper::SLA_STATUS_DISABLED
			]),
			'filter_evaltype' =>	'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>		'array',
			'filter_set' =>			'in 1',
			'sort' =>				'in '.implode(',', [
				'name',
				'slo',
				'effective_date',
				'status'
			]),
			'sortorder'	=>			'in '.implode(',', [ZBX_SORT_UP, ZBX_SORT_DOWN]),
			'page' =>				'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SLA);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$filter = [
			'name' => $this->getInput('filter_name', ''),
			'status' => $this->getInput('filter_status', CSlaHelper::SLA_STATUS_ANY),
			'evaltype' => $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => [],
			'filter_set' => $this->hasInput('filter_set')
		];

		foreach ($this->getInput('filter_tags', []) as $tag) {
			if (!array_key_exists('tag', $tag) || !array_key_exists('value', $tag)
					|| ($tag['tag'] === '' && $tag['value'] === '')) {
				continue;
			}

			$filter['tags'][] = $tag;
		}

		$paging_curl = (new CUrl('zabbix.php'))
			->setArgument('action', 'sla.list');

		$reset_curl = clone $paging_curl;

		if ($filter['filter_set']) {
			$paging_curl
				->setArgument('filter_name', $filter['name'])
				->setArgument('filter_status', $filter['status'])
				->setArgument('filter_evaltype', $filter['evaltype'])
				->setArgument('filter_tags', $filter['tags'])
				->setArgument('filter_set', 1);
		}

		$page_num = $this->getInput('page', 1);
		$per_page = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT);
		$sort_field = $this->getInput('sort', CProfile::get('sla.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('sla.list.sortorder', ZBX_SORT_UP));

		$edit_mode_curl = (clone $paging_curl)
			->setArgument('action', 'service.list.edit')
			->setArgument('page', $page_num);

		$data = [
			'can_edit' => CWebUser::checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA),
			'filter' => $filter,
			'is_filtered' => $filter['filter_set'],
			'active_tab' => CProfile::get('web.sla.filter.active', 1),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'reset_curl' => $reset_curl,
			'filter_url' => $paging_curl->getUrl(),
			'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'edit_mode_url' => $edit_mode_curl->getUrl(),
			'refresh_url' => (clone $paging_curl)
				->setArgument('action', 'sla.list.refresh')
				->getUrl(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$options = [
			'output' => [],
			'search' => ($filter['name'] === '')
				? null
				: ['name' => $filter['name']],
			'filter' => [
				'status' => $filter['status']
			],
			'tags' => $filter['tags'],
			'evaltype' => $filter['evaltype'],
			'sortfield' => [$sort_field],
			'sortorder' => $sort_order,
			'preservekeys' => true
		];

		if (in_array($filter['status'], [CSlaHelper::SLA_STATUS_ENABLED, CSlaHelper::SLA_STATUS_DISABLED])) {
			$options['filter']['status'] = $filter['status'];
		}

		$x = 120;
		$records = [];

		while ($x-- > 0) {
			$records[$x] = [
				'name' => 'My SLA-'.$x,
				'period' => mt_rand(0, 4),
				'slo' => 99.9999,
				'effective_date' => strtotime('+'.mt_rand(0, 365).' days'),
				'timezone' => array_rand(['UTC' => 1, 'Europe/Riga' => 1, 'Europe/Dublin']),
				'schedule_mode' => mt_rand(0, 1),
				'status' => mt_rand(0, 1),
				'description' => 'Description of SLA '.$x
			];
		}

		//$db_slaids = array_keys(API::Sla()->get($options));
		$db_slaids = array_keys($records);

		CPagerHelper::savePage('sla.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $db_slaids, $sort_order, $paging_curl);

		$options = [
			'output' => [],
			'selectTags' => ['tag', 'value'],
			'slaids' => $db_slaids,
			'limit' => $per_page,
			'offset' => $page_num * $per_page
		];

		//$data['records'] = API::Sla()->get($options);
		$data['records'] = $records;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('SLA'));
		$this->setResponse($response);
	}
}
