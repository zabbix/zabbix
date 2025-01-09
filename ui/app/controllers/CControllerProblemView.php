<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * Controller for the "Problems" page and Problems CSV export.
 */
class CControllerProblemView extends CControllerProblem {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'show' =>					'in '.TRIGGERS_OPTION_RECENT_PROBLEM.','.TRIGGERS_OPTION_IN_PROBLEM.','.TRIGGERS_OPTION_ALL,
			'groupids' =>				'array_id',
			'hostids' =>				'array_id',
			'triggerids' =>				'array_id',
			'name' =>					'string',
			'severities' =>				'array',
			'age_state' =>				'in 0,1',
			'age' =>					'int32',
			'inventory' =>				'array',
			'evaltype' =>				'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'tags' =>					'array',
			'show_tags' =>				'in '.SHOW_TAGS_NONE.','.SHOW_TAGS_1.','.SHOW_TAGS_2.','.SHOW_TAGS_3,
			'show_symptoms' =>			'in 0,1',
			'show_suppressed' =>		'in 0,1',
			'acknowledgement_status' =>	'in '.ZBX_ACK_STATUS_ALL.','.ZBX_ACK_STATUS_UNACK.','.ZBX_ACK_STATUS_ACK,
			'acknowledged_by_me' =>		'in 0,1',
			'compact_view' =>			'in 0,1',
			'show_timeline' =>			'in '.ZBX_TIMELINE_OFF.','.ZBX_TIMELINE_ON,
			'details' =>				'in 0,1',
			'highlight_row' =>			'in 0,1',
			'show_opdata' =>			'in '.OPERATIONAL_DATA_SHOW_NONE.','.OPERATIONAL_DATA_SHOW_SEPARATELY.','.OPERATIONAL_DATA_SHOW_WITH_PROBLEM,
			'tag_name_format' =>		'in '.TAG_NAME_FULL.','.TAG_NAME_SHORTENED.','.TAG_NAME_NONE,
			'tag_priority' =>			'string',
			'from' =>					'range_time',
			'to' =>						'range_time',
			'sort' =>					'in clock,host,severity,name',
			'sortorder' =>				'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'page' =>					'ge 1',
			'uncheck' =>				'in 1',
			'filter_name' =>			'string',
			'filter_custom_time' =>		'in 1,0',
			'filter_show_counter' =>	'in 1,0',
			'filter_counters' =>		'in 1',
			'filter_set' =>				'in 1',
			'filter_reset' =>			'in 1',
			'counter_index' =>			'ge 0'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod() && $this->validateInventory()
			&& $this->validateTags();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);
	}

	protected function doAction(): void {
		$filter_tabs = [];
		$profile = (new CTabFilterProfile(static::FILTER_IDX, static::FILTER_FIELDS_DEFAULT))->read();

		if ($this->hasInput('filter_reset')) {
			$profile->reset();
		}
		elseif ($this->hasInput('filter_set')) {
			$profile->setTabFilter(0, ['filter_name' => ''] + $this->cleanInput($this->getInputAll()));
			$profile->update();
		}
		else {
			$profile->setInput($this->cleanInput($this->getInputAll()));
		}

		foreach ($profile->getTabsWithDefaults() as $index => $filter_tab) {
			if ($filter_tab['filter_custom_time']) {
				$filter_tab['show'] = TRIGGERS_OPTION_ALL;
				$filter_tab['filter_src']['show'] = TRIGGERS_OPTION_ALL;
			}

			if ($index == $profile->selected) {
				// Initialize multiselect data for filter_scr to allow tabfilter correctly handle unsaved state.
				$filter_tab['filter_src']['filter_view_data'] = $this->getAdditionalData($filter_tab['filter_src']);
			}

			$filter_tabs[] = $filter_tab + ['filter_view_data' => $this->getAdditionalData($filter_tab)];
		}

		$filter = $filter_tabs[$profile->selected];
		$filter = self::sanitizeFilter($filter);

		$refresh_curl = new CUrl('zabbix.php');
		$filter['action'] = 'problem.view.refresh';
		array_map([$refresh_curl, 'setArgument'], array_keys($filter), $filter);

		if (!$this->hasInput('page')) {
			$refresh_curl->removeArgument('page');
		}

		$timeselector_from = $filter['filter_custom_time'] == 1 ? $filter['from'] : $profile->from;
		$timeselector_to = $filter['filter_custom_time'] == 1 ? $filter['to'] : $profile->to;

		$data = [
			'action' => $this->getAction(),
			'tabfilter_idx' => static::FILTER_IDX,
			'filter' => $filter,
			'filter_view' => 'monitoring.problem.filter',
			'filter_defaults' => $profile->filter_defaults,
			'tabfilter_options' => [
				'idx' => static::FILTER_IDX,
				'selected' => $profile->selected,
				'support_custom_time' => 1,
				'expanded' => $profile->expanded,
				'expanded_timeselector' => $profile->expanded_timeselector,
				'page' => $filter['page'],
				'csrf_token' => CCsrfTokenHelper::get('tabfilter'),
				'timeselector' => [
					'from' => $timeselector_from,
					'to' => $timeselector_to,
					'disabled' => ($filter['show'] != TRIGGERS_OPTION_ALL || $filter['filter_custom_time'])
				] + getTimeselectorActions($timeselector_from, $timeselector_to)
			],
			'filter_tabs' => $filter_tabs,
			'refresh_url' => $refresh_curl->getUrl(),
			'refresh_interval' => CWebUser::getRefresh() * 1000,
			'inventories' => array_column(getHostInventories(), 'title', 'db_field'),
			'sort' => $filter['sort'],
			'sortorder' => $filter['sortorder'],
			'uncheck' => $this->hasInput('filter_reset'),
			'page' => $this->getInput('page', 1)
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Problems'));

		if ($data['action'] === 'problem.view.csv') {
			$response->setFileName('zbx_problems_export.csv');
		}

		$this->setResponse($response);
	}
}
