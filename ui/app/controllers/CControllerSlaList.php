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
	 */
	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_SERVICES_SLA);
	}

	/**
	 * @throws Exception
	 */
	protected function doAction(): void {
		$sort_field = $this->getInput('sort', CProfile::get('sla.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('sla.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.sla.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.sla.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update(
				'web.sla.filter_name',
				$this->getInput('filter_name', ''),
				PROFILE_TYPE_STR
			);
			CProfile::update(
				'web.sla.filter_status',
				$this->getInput('filter_status', CSlaHelper::SLA_STATUS_ANY),
				PROFILE_TYPE_INT
			);
			CProfile::update(
				'web.sla.filter_evaltype',
				$this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
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

		foreach (CProfile::getArray('web.sla.tags.tag', []) as $i => $tag) {
			$filter['tags'][] = [
				'tag' => $tag,
				'value' => CProfile::get('web.sla.tags.value', null, $i),
				'operator' => CProfile::get('web.sla.tags.operator', null, $i)
			];
		}

		CArrayHelper::sort($filter['tags'], ['tag', 'value', 'operator']);

		$paging_curl = (new CUrl('zabbix.php'))->setArgument('action', 'sla.list');
		$reset_curl = (clone $paging_curl)->setArgument('filter_rst', 1);

		if ($this->hasInput('filter_set')) {
			$paging_curl
				->setArgument('filter_name', $filter['name'])
				->setArgument('filter_status', $filter['status'])
				->setArgument('filter_evaltype', $filter['evaltype'])
				->setArgument('filter_tags', $filter['tags'])
				->setArgument('filter_set', 1);
		}

		$page_num = $this->getInput('page', 1);
		CPagerHelper::savePage('sla.list', $page_num);

		$options = [
			'output' => [],
			'filter' => [],
			'service_tags' => $filter['tags'],
			'evaltype' => $filter['evaltype'],
			'sortfield' => [$sort_field],
			'sortorder' => $sort_order,
			'preservekeys' => true
		];

		if ($filter['name'] !== '') {
			$options['search'] = ['name' => $filter['name']];
		}

		if (in_array($filter['status'], [CSlaHelper::SLA_STATUS_ENABLED, CSlaHelper::SLA_STATUS_DISABLED])) {
			$options['filter']['status'] = $filter['status'];
		}

		$slas = API::Sla()->get($options);

		$data = [
			'can_edit' => CWebUser::checkAccess(CRoleHelper::ACTIONS_MANAGE_SLA),
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
				->setArgument('page', $page_num)
				->getUrl(),
			'status_toggle_curl' => (new CUrl('zabbix.php'))->setArgument('action', 'sla.listupdate'),
			'uncheck' => $this->hasInput('uncheck'),
			'schedule_hints' => [],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$data['paging'] = CPagerHelper::paginate($page_num, $slas, $sort_order, $paging_curl);

		$options = [
			'output' => array_diff(CSlaHelper::OUTPUT_FIELDS, ['description']),
			'slaids' => array_keys($slas),
			'selectSchedule' => ['period_from', 'period_to'],
			'preservekeys' => true
		];

		$slas = API::Sla()->get($options);
		order_result($slas, $sort_field, $sort_order);
		$data['slas'] = $slas;

		foreach($slas as $slaid => $sla) {
			if (!$sla['schedule']) {
				continue;
			}

			$schedule = CSlaHelper::convertScheduleToWeekdayPeriods($sla['schedule']);
			$schedule_hint = (new CTableInfo())->setHeader([_('Schedule'), _('Time period')]);

			foreach ($schedule as $weekday => $periods) {
				if (!$periods) {
					$periods = ['-'];
				}
				else {
					foreach ($periods as $key => $period) {
						$periods[$key] =
							zbx_date2str(TIME_FORMAT, $period['period_from']).
							' - '.
							zbx_date2str(TIME_FORMAT, $period['period_to']);
					}
				}

				$schedule_hint->addRow([
					getDayOfWeekCaption($weekday),
					implode(', ', $periods)
				]);
			}

			$data['schedule_hints'][$slaid] = $schedule_hint;
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('SLA'));
		$this->setResponse($response);
	}
}
