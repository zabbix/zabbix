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


/**
 * Controller for the "Latest data" asynchronous refresh page.
 */
class CControllerLatestViewRefresh extends CControllerLatestView {

	protected function doAction(): void {

		if ($this->getInput('filter_counters', 0)) {
			$profile = (new CTabFilterProfile(static::FILTER_IDX, static::FILTER_FIELDS_DEFAULT))->read();
			$filters = $this->hasInput('counter_index')
				? [$profile->getTabFilter($this->getInput('counter_index'))]
				: $profile->getTabsWithDefaults();

			$filter_counters = [];
			foreach ($filters as $index => $tabfilter) {
				$filter_counters[$index] = $tabfilter['filter_show_counter'] ? $this->getCount($tabfilter) : 0;
			}

			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['filter_counters' => $filter_counters])
				]))->disableView()
			);
		}
		else {
			$filter = static::FILTER_FIELDS_DEFAULT;
			$this->getInputs($filter, array_keys($filter));
			$filter = $this->cleanInput($filter);

			// make data
			$prepared_data = $this->prepareData($filter, $filter['sort'], $filter['sortorder']);

			$page = $this->getInput('page', 1);
			$view_url = (new CUrl('zabbix.php'))->setArgument('action', 'latest.view');
			$paging = CPagerHelper::paginate($page, $prepared_data['items'], ZBX_SORT_UP, $view_url);

			$this->extendData($prepared_data);

			// make response
			$data = [
				'filter' => $filter,
				'view_curl' => $view_url,
				'sort_field' => $filter['sort'],
				'sort_order' => $filter['sortorder'],
				'paging' => $paging,
				'config' => [
					'hk_trends' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS),
					'hk_trends_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_TRENDS_GLOBAL),
					'hk_history' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY),
					'hk_history_global' => CHousekeepingHelper::get(CHousekeepingHelper::HK_HISTORY_GLOBAL)
				],
				'tags' => makeTags($prepared_data['items'], true, 'itemid', ZBX_TAG_COUNT_DEFAULT, $filter['tags'])
			] + $prepared_data;

			$response = new CControllerResponseData($data);
			$this->setResponse($response);
		}
	}
}
