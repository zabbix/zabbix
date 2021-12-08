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


class CControllerServiceSlaList extends CController {

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
			->setArgument('action', 'services.sla.list');

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

		$data = [
			'can_edit' => CWebUser::checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA),
			'filter' => $filter,
			'active_tab' => CProfile::get('web.sla.filter.active', 1),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'page_number' => $page_num,
			'max_in_table' => CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'reset_curl' => $reset_curl,
			'filter_url' => $paging_curl->getUrl(),
			'mode_switch_url' => (clone $paging_curl)
				->setArgument('page', $page_num)
				->getUrl(),
			'refresh_url' => (clone $paging_curl)
				->setArgument('action', 'services.sla.list.refresh')
				->getUrl(),
			'status_toggle_curl' => (new CUrl('zabbix.php'))->setArgument('action', 'services.sla.update'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$options = [
			'output' => [],
			'filter' => [],
			'service_tags' => $filter['tags'],
			'evaltype' => $filter['evaltype'],
			'sortfield' => [$sort_field],
			'sortorder' => $sort_order,
			'preservekeys' => true,
		];

		if ($filter['name'] !== '') {
			$options['search'] = ['name' => $filter['name']];
		}

		if (in_array($filter['status'], [CSlaHelper::SLA_STATUS_ENABLED, CSlaHelper::SLA_STATUS_DISABLED])) {
			$options['filter']['status'] = $filter['status'];
		}

		$records = API::Sla()->get($options);
		$db_slaids = array_keys($records);

		CPagerHelper::savePage('sla.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $db_slaids, $sort_order, $paging_curl);

		$options = [
			'output' => array_diff(CSlaHelper::OUTPUT_FIELDS, ['description']),
			'slaids' => $db_slaids,
			'limit' => $per_page,
			'offset' => $page_num * $per_page,
			'preservekeys' => true
		];

		$data['records'] = API::Sla()->get($options);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('SLA'));
		$this->setResponse($response);
	}
}
