<?php declare(strict_types = 1);
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

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			// filter fields
			'groupids' =>				'array_db hosts_groups.groupid',
			'hostids' =>				'array_db hosts.hostid',
			'name' =>					'string',
			'show_details' =>			'in 1,0',
			'evaltype' =>				'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'tags' =>					'array',
			'show_tags' =>				'in '.SHOW_TAGS_NONE.','.SHOW_TAGS_1.','.SHOW_TAGS_2.','.SHOW_TAGS_3,
			'tag_name_format' =>		'in '.TAG_NAME_FULL.','.TAG_NAME_SHORTENED.','.TAG_NAME_NONE,
			'tag_priority' =>			'string',

			// table sorting inputs
			'sort' =>					'in host,name',
			'sortorder' =>				'in '.ZBX_SORT_UP.','.ZBX_SORT_DOWN,
			'page' =>					'ge 1',

			// named filter properties
			'filter_name' =>			'string',
			'filter_custom_time' =>		'in 1,0',
			'filter_show_counter' =>	'in 1,0',
			'filter_counters' =>		'in 1',
			'filter_reset' =>			'in 1',
			'counter_index' =>			'ge 0',
			'subfilter_hostids' =>		'array',
			'subfilter_tagnames' =>		'array',
			'subfilter_tags' =>			'array',
			'subfilter_data' =>			'array'
		];

		$ret = $this->validateInput($fields);

		// Validate tags filter.
		if ($ret && $this->hasInput('tags')) {
			foreach ($this->getInput('tags') as $filter_tag) {
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

		// Validate subfilters.
		if ($ret && $this->hasInput('subfilter_hostids')) {
			$hostids = $this->getInput('subfilter_hostids', []);
			$ret = (!$hostids || count($hostids) === count(array_filter($hostids, 'ctype_digit')));
		}

		if ($ret && $this->hasInput('subfilter_tagnames')) {
			$tagnames = $this->getInput('subfilter_tagnames', []);
			$ret = (!$tagnames || count($tagnames) === count(array_filter($tagnames, 'is_string')));
		}

		if ($ret && $this->hasInput('subfilter_tags')) {
			$tags = $this->getInput('subfilter_tags', []);
			foreach ($tags as $tag => $values) {
				if (!is_scalar($tag) || !is_array($values)
						|| count($values) !== count(array_filter($values, 'is_string'))) {
					$ret = false;
					break;
				}
			}
		}

		if ($ret && $this->hasInput('subfilter_data')) {
			$data = $this->getInput('subfilter_data', []);
			$valid = array_filter($data, function ($val) {
				return ($val === '0' || $val === '1');
			});
			$ret = (count($data) === count($valid));
		}

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA);
	}

	protected function doAction(): void {

		$profile = (new CTabFilterProfile(static::FILTER_IDX, static::FILTER_FIELDS_DEFAULT))
			->read()
			->setInput($this->cleanInput($this->getInputAll()));

		$filter_tabs = [];
		foreach ($profile->getTabsWithDefaults() as $index => $filter_tab) {
			if ($index == $profile->selected) {
				// Initialize multiselect data for filter_scr to allow tabfilter correctly handle unsaved state.
				$filter_tab['filter_src']['filter_view_data'] = $this->getAdditionalData($filter_tab['filter_src']);
			}

			$filter_tabs[] = $filter_tab + ['filter_view_data' => $this->getAdditionalData($filter_tab)];
		}

		$filter = $filter_tabs[$profile->selected];

		$refresh_curl = new CUrl('zabbix.php');
		$refresh_curl_params = ['action' => 'latest.view.refresh'] + $filter;
		array_map([$refresh_curl, 'setArgument'], array_keys($refresh_curl_params), $refresh_curl_params);

		// data sort and pager
		$sort_field = $this->getInput('sort', 'name');
		$sort_order = $this->getInput('sortorder', ZBX_SORT_UP);
		$prepared_data = $this->prepareData($filter, $sort_field, $sort_order);

		// Prepare subfilter data.
		$subfilters_fields = self::getSubfilterFields($filter, (count($filter['hostids']) == 1));
		$subfilters = self::getSubfilters($subfilters_fields, $prepared_data);
		$prepared_data['items'] = self::applySubfilters($prepared_data['items']);

		$view_url = (new CUrl('zabbix.php'))->setArgument('action', 'latest.view');
		$paging_arguments = array_filter(array_intersect_key($filter, self::FILTER_FIELDS_DEFAULT));
		array_map([$view_url, 'setArgument'], array_keys($paging_arguments), $paging_arguments);
		$paging = CPagerHelper::paginate($this->getInput('page', 1), $prepared_data['items'], ZBX_SORT_UP, $view_url);

		$this->extendData($prepared_data);

		$refresh_data = array_filter([
			'groupids' => $filter['groupids'],
			'hostids' => $filter['hostids'],
			'name' => $filter['name'],
			'show_details' => $filter['show_details'] ? 1 : 0,
			'evaltype' => $filter['evaltype'],
			'tags' => $filter['tags'],
			'show_tags' => $filter['show_tags'],
			'tag_name_format' => $filter['tag_name_format'],
			'tag_priority' => $filter['tag_priority'],
			'subfilter_hostids' => $filter['subfilter_hostids'],
			'subfilter_tagnames' => $filter['subfilter_tagnames'],
			'subfilter_tags' => $filter['tags'],
			'subfilter_data' => $filter['subfilter_data'],
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'page' => $this->hasInput('page') ? $this->getInput('page') : null
		]);

		// display
		$data = [
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'refresh_data' => $refresh_data,
			'filter_defaults' => $profile->filter_defaults,
			'filter_view' => 'monitoring.latest.filter',
			'filter_tabs' => $filter_tabs,
			'tabfilter_options' => [
				'idx' => static::FILTER_IDX,
				'selected' => $profile->selected,
				'support_custom_time' => 0,
				'expanded' => $profile->expanded,
				'page' => $filter['page']
			],
			'filter' => $filter,
			'subfilters' => $subfilters,
			'sort_field' => $sort_field,
			'sort_order' => $sort_order,
			'view_curl' => $view_url,
			'paging' => $paging,
			'config' => [
				'hk_trends' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS),
				'hk_trends_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL),
				'hk_history' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY),
				'hk_history_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)
			],
			'tags' => makeTags($prepared_data['items'], true, 'itemid', (int) $filter['show_tags'], $filter['tags'],
				array_key_exists('tags', $subfilters_fields) ? $subfilters_fields['tags'] : [],
				(int) $filter['tag_name_format'], $filter['tag_priority']
			)
		] + $prepared_data;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Latest data'));
		$this->setResponse($response);
	}
}
