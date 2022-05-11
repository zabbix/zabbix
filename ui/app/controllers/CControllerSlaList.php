<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
			'filter_status' =>		'in '.implode(',', [CSlaHelper::SLA_STATUS_ANY, CSlaHelper::SLA_STATUS_ENABLED, CSlaHelper::SLA_STATUS_DISABLED]),
			'filter_evaltype' =>	'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>		'array',
			'filter_set' =>			'in 1',
			'filter_rst' =>			'in 1',
			'sort' =>				'in '.implode(',', ['name', 'slo', 'effective_date', 'status']),
			'sortorder'	=>			'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
			'page' =>				'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SLA);
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			CProfile::update('web.sla.filter.name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.sla.filter.status', $this->getInput('filter_status', CSlaHelper::SLA_STATUS_ANY),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.sla.filter.evaltype', $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
				PROFILE_TYPE_INT
			);

			$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];

			foreach ($this->getInput('filter_tags', []) as $filter_tag) {
				if ($filter_tag['tag'] === '' && $filter_tag['value'] === '') {
					continue;
				}

				$filter_tags['tags'][] = $filter_tag['tag'];
				$filter_tags['values'][] = $filter_tag['value'];
				$filter_tags['operators'][] = $filter_tag['operator'];
			}

			CProfile::updateArray('web.sla.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.sla.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.sla.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.sla.filter.name');
			CProfile::delete('web.sla.filter.status');
			CProfile::delete('web.sla.filter.evaltype');
			CProfile::deleteIdx('web.sla.filter.tags.tag');
			CProfile::deleteIdx('web.sla.filter.tags.value');
			CProfile::deleteIdx('web.sla.filter.tags.operator');
		}

		$filter = [
			'name' => CProfile::get('web.sla.filter.name', ''),
			'status' => CProfile::get('web.sla.filter.status', CSlaHelper::SLA_STATUS_ANY),
			'evaltype' => CProfile::get('web.sla.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => []
		];

		foreach (CProfile::getArray('web.sla.filter.tags.tag', []) as $i => $tag) {
			$filter['tags'][] = [
				'tag' => $tag,
				'value' => CProfile::get('web.sla.filter.tags.value', null, $i),
				'operator' => CProfile::get('web.sla.filter.tags.operator', null, $i)
			];
		}

		$sort_field = $this->getInput('sort', CProfile::get('web.sla.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.sla.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.sla.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.sla.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		$data = [
			'has_access' => [
				CRoleHelper::ACTIONS_MANAGE_SLA => $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA),
				CRoleHelper::UI_SERVICES_SLA_REPORT => $this->checkAccess(CRoleHelper::UI_SERVICES_SLA_REPORT)
			],
			'filter' => $filter,
			'active_tab' => CProfile::get('web.sla.list.filter.active', 1),
			'sort' => $sort_field,
			'sortorder' => $sort_order
		];

		$options = [
			'output' => [],
			'evaltype' => $filter['evaltype'],
			'service_tags' => $filter['tags'],
			'search' => [
				'name' => ($filter['name'] === '') ? null : $filter['name']
			],
			'filter' => [
				'status' => $filter['status'] != CSlaHelper::SLA_STATUS_ANY ? $filter['status'] : null
			],
			'sortfield' => $sort_field,
			'sortorder' => $sort_order,
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
			'preservekeys' => true
		];

		$slas = API::Sla()->get($options);

		$page_num = getRequest('page', 1);
		CPagerHelper::savePage('sla.list', $page_num);
		$data['paging'] = CPagerHelper::paginate($page_num, $slas, $sort_order,
			(new CUrl('zabbix.php'))->setArgument('action', $this->getAction())
		);

		$options = [
			'output' => ['name', 'period', 'slo', 'effective_date', 'timezone', 'status'],
			'slaids' => array_keys($slas),
			'selectSchedule' => ['period_from', 'period_to'],
			'sortfield' => $sort_field,
			'sortorder' => $sort_order,
			'preservekeys' => true
		];

		$data['slas'] = API::Sla()->get($options);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('SLA'));
		$this->setResponse($response);
	}
}
