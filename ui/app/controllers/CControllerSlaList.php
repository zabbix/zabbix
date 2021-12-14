<?php declare(strict_types = 1);

use SebastianBergmann\Environment\Console;

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
			'filter_rst' =>			'in 1',
			'uncheck'=>				'in 1',
			'sort' =>				'in '.implode(',', [
				'name',
				'slo',
				'effective_date',
				'status'
			]),
			'sortorder'	=>			'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
			'page' =>				'ge 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	/**
	 * @throws APIException
	 *
	 * @return bool
	 */
	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SLA);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$sort_field = $this->getInput('sort', CProfile::get('web.sla.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.sla.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.sla.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.sla.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.sla.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.sla.filter_status', $this->getInput('filter_status', CSlaHelper::SLA_STATUS_ANY),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.sla.filter_evaltype', $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
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

			CProfile::updateArray('web.sla.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.sla.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.sla.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);

			CProfile::update('web.sla.filter_tags', json_encode($this->getInput('filter_tags', [])), PROFILE_TYPE_STR);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.sla.filter_name');
			CProfile::delete('web.sla.filter_status');
			CProfile::delete('web.sla.filter_evaltype');

			CProfile::delete('web.sla.tags.tag');
			CProfile::delete('web.sla.tags.value');
			CProfile::delete('web.sla.tags.operator');
		}

		$filter = [
			'name' => CProfile::get('web.sla.filter_name', ''),
			'status' => CProfile::get('web.sla.filter_status', CSlaHelper::SLA_STATUS_ANY),
			'evaltype' => CProfile::get('web.sla.filter_evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => []
		];

		foreach (CProfile::getArray('web.sla.tags.tag', []) as $key => $tag) {
			$filter['tags'][] = [
				'tag' => $tag,
				'value' => CProfile::get('web.sla.tags.value', null, $key),
				'operator' => CProfile::get('web.sla.tags.operator', null, $key)
			];
		}

		CArrayHelper::sort($filter['tags'], ['tag', 'value', 'operator']);

		$paging_curl = (new CUrl('zabbix.php'))->setArgument('action', 'sla.list');
		$reset_curl = (clone $paging_curl)->setArgument('filter_rst', 1);
		$page_argument = $this->hasInput('page') ? $this->getInput('page') : null;

		if (!$this->hasInput('filter_rst')) {
			$paging_curl
				->setArgument('filter_name', $filter['name'])
				->setArgument('filter_status', $filter['status'])
				->setArgument('filter_evaltype', $filter['evaltype'])
				->setArgument('filter_tags', $filter['tags']);
		}

		$data = [
			'can_manage_sla' => CWebUser::checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA),
			'has_report_access' => CWebUser::checkAccess(CRoleHelper::UI_SERVICES_SLA_REPORT),
			'filter' => $filter,
			'active_tab' => CProfile::get('web.sla.filter.active', 1),
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter_url' => $paging_curl->getUrl(),
			'reset_curl' => $reset_curl,
			'list_update_url' => (new CUrl('zabbix.php'))
				->setArgument('action', 'sla.listupdate')
				->getUrl(),
			'list_delete_url' => (new CUrl('zabbix.php'))
				->setArgument('action', 'sla.delete')
				->getUrl(),
			'mode_switch_url' => (clone $paging_curl)
				->setArgument('page', $page_argument)
				->getUrl(),
			'status_toggle_curl' => (new CUrl('zabbix.php'))
				->setArgument('action', 'sla.listupdate')
				->setArgument('backurl', urlencode(
					(clone $paging_curl)
						->setArgument('page', $page_argument)
						->getUrl()
				)),
			'uncheck' => $this->hasInput('uncheck'),
			'custom_schedule' => [],
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
			'limit' => CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1,
			'preservekeys' => true
		];

		if ($filter['name'] !== '') {
			$options['search'] = ['name' => $filter['name']];
		}

		if (in_array($filter['status'], [CSlaHelper::SLA_STATUS_ENABLED, CSlaHelper::SLA_STATUS_DISABLED])) {
			$options['filter']['status'] = $filter['status'];
		}

		$slas = API::Sla()->get($options);

		$page_number = $this->getInput('page', 1);
		CPagerHelper::savePage('sla.list', $page_number);
		$data['paging'] = CPagerHelper::paginate($page_number, $slas, $sort_order, $paging_curl);

		$options = [
			'output' => [
				'name',
				'description',
				'effective_date',
				'status',
				'slo',
				'period',
				'timezone'
			],
			'slaids' => array_keys($slas),
			'sortfield' => [$sort_field],
			'sortorder' => $sort_order,
			'selectSchedule' => ['period_from', 'period_to'],
			'preservekeys' => true
		];

		$slas = API::Sla()->get($options);
		$data['slas'] = $slas;

		foreach($slas as $slaid => $sla) {
			if (!$sla['schedule']) {
				continue;
			}

			$data['custom_schedule'][$slaid] = CSlaHelper::convertScheduleToWeekdayPeriods($sla['schedule']);
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('SLA'));
		$this->setResponse($response);
	}
}
