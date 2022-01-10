<?php
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


/**
 * Controller for the "Latest data" page.
 */
class CControllerLatestView extends CControllerLatest {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'page' =>						'ge 1',

			// filter inputs
			'filter_groupids' =>			'array_id',
			'filter_hostids' =>				'array_id',
			'filter_select' =>				'string',
			'filter_show_without_data' =>	'in 0,1',
			'filter_show_details' =>		'in 1',
			'filter_set' =>					'in 1',
			'filter_rst' =>					'in 1',
			'filter_evaltype' =>			'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>				'array',

			// table sorting inputs
			'sort' =>						'in host,name,lastclock',
			'sortorder' =>					'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA);
	}

	protected function doAction() {
		// filter
		if ($this->hasInput('filter_set')) {
			CProfile::updateArray('web.latest.filter.groupids', $this->getInput('filter_groupids', []),
				PROFILE_TYPE_ID
			);
			CProfile::updateArray('web.latest.filter.hostids', $this->getInput('filter_hostids', []), PROFILE_TYPE_ID);
			CProfile::update('web.latest.filter.select', trim($this->getInput('filter_select', '')), PROFILE_TYPE_STR);
			CProfile::update('web.latest.filter.show_without_data', $this->getInput('filter_show_without_data', 1),
				PROFILE_TYPE_INT
			);
			CProfile::update('web.latest.filter.show_details', $this->getInput('filter_show_details', 0),
				PROFILE_TYPE_INT
			);

			// tags
			$evaltype = $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR);
			CProfile::update('web.latest.filter.evaltype', $evaltype, PROFILE_TYPE_INT);

			$filter_tags = ['tags' => [], 'values' => [], 'operators' => []];
			foreach ($this->getInput('filter_tags', []) as $tag) {
				if ($tag['tag'] === '' && $tag['value'] === '') {
					continue;
				}
				$filter_tags['tags'][] = $tag['tag'];
				$filter_tags['values'][] = $tag['value'];
				$filter_tags['operators'][] = $tag['operator'];
			}
			CProfile::updateArray('web.latest.filter.tags.tag', $filter_tags['tags'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.latest.filter.tags.value', $filter_tags['values'], PROFILE_TYPE_STR);
			CProfile::updateArray('web.latest.filter.tags.operator', $filter_tags['operators'], PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::deleteIdx('web.latest.filter.groupids');
			CProfile::deleteIdx('web.latest.filter.hostids');
			CProfile::delete('web.latest.filter.select');
			CProfile::delete('web.latest.filter.show_without_data');
			CProfile::delete('web.latest.filter.show_details');
			CProfile::deleteIdx('web.latest.filter.evaltype');
			CProfile::deleteIdx('web.latest.filter.tags.tag');
			CProfile::deleteIdx('web.latest.filter.tags.value');
			CProfile::deleteIdx('web.latest.filter.tags.operator');
		}

		// Force-check "Show items without data" if there are no hosts selected.
		$filter_hostids = CProfile::getArray('web.latest.filter.hostids');
		$filter_show_without_data = $filter_hostids ? CProfile::get('web.latest.filter.show_without_data', 1) : 1;

		$filter = [
			'groupids' => CProfile::getArray('web.latest.filter.groupids'),
			'hostids' => $filter_hostids,
			'select' => CProfile::get('web.latest.filter.select', ''),
			'show_without_data' => $filter_show_without_data,
			'show_details' => CProfile::get('web.latest.filter.show_details', 0),
			'evaltype' => CProfile::get('web.latest.filter.evaltype', TAG_EVAL_TYPE_AND_OR),
			'tags' => []
		];

		// Tags filters.
		foreach (CProfile::getArray('web.latest.filter.tags.tag', []) as $i => $tag) {
			$filter['tags'][] = [
				'tag' => $tag,
				'value' => CProfile::get('web.latest.filter.tags.value', null, $i),
				'operator' => CProfile::get('web.latest.filter.tags.operator', null, $i)
			];
		}

		$sort_field = $this->getInput('sort', CProfile::get('web.latest.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.latest.sortorder', ZBX_SORT_UP));

		CProfile::update('web.latest.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.latest.sortorder', $sort_order, PROFILE_TYPE_STR);

		$view_curl = (new CUrl('zabbix.php'))->setArgument('action', 'latest.view');

		$refresh_curl = (new CUrl('zabbix.php'))->setArgument('action', 'latest.view.refresh');
		$refresh_data = array_filter([
			'filter_groupids' => $filter['groupids'],
			'filter_hostids' => $filter['hostids'],
			'filter_select' => $filter['select'],
			'filter_show_without_data' => $filter['show_without_data'] ? 1 : null,
			'filter_show_details' => $filter['show_details'] ? 1 : null,
			'filter_evaltype' => $filter['evaltype'],
			'filter_tags' => $filter['tags'],
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'page' => $this->hasInput('page') ? $this->getInput('page') : null
		]);

		// data sort and pager
		$prepared_data = $this->prepareData($filter, $sort_field, $sort_order);

		$paging = CPagerHelper::paginate($this->getInput('page', 1), $prepared_data['items'], ZBX_SORT_UP, $view_curl);

		$this->extendData($prepared_data);

		// display
		$data = [
			'filter' => $filter,
			'sort_field' => $sort_field,
			'sort_order' => $sort_order,
			'view_curl' => $view_curl,
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_data' => $refresh_data,
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'active_tab' => CProfile::get('web.latest.filter.active', 1),
			'paging' => $paging,
			'config' => [
				'hk_trends' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS),
				'hk_trends_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL),
				'hk_history' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY),
				'hk_history_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)
			],
			'tags' => makeTags($prepared_data['items'], true, 'itemid', ZBX_TAG_COUNT_DEFAULT, $filter['tags'])
		] + $prepared_data;

		if (!$data['filter']['tags']) {
			$data['filter']['tags'] = [[
				'tag' => '',
				'operator' => TAG_OPERATOR_LIKE,
				'value' => ''
			]];
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Latest data'));
		$this->setResponse($response);
	}
}
