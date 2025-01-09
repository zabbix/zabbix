<?php
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
 * Controller for the "Problems" asynchronous refresh page.
 */
class CControllerProblemViewRefresh extends CControllerProblemView {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'action' =>					'string',
			'sort' =>					'in clock,host,severity,name',
			'sortorder' =>				'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'page' =>					'ge 1',
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
			'filter_counters' =>		'in 1'
		];

		$ret = $this->validateInput($fields) && $this->validateTimeSelectorPeriod() && $this->validateInventory()
			&& $this->validateTags();

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$data = [];

		if ($this->getInput('filter_counters', 0)) {
			$profile = (new CTabFilterProfile(static::FILTER_IDX, static::FILTER_FIELDS_DEFAULT))->read();
			$filters = $this->hasInput('counter_index')
				? [$profile->getTabFilter($this->getInput('counter_index'))]
				: $profile->getTabsWithDefaults();
			$data['filter_counters'] = [];

			foreach ($filters as $index => $tabfilter) {
				$tabfilter = self::sanitizeFilter($tabfilter);

				if (!$tabfilter['filter_custom_time']) {
					$tabfilter = [
						'from' => $profile->from,
						'to' => $profile->to
					] + $tabfilter;
				}
				else {
					$tabfilter['show'] = TRIGGERS_OPTION_ALL;
				}

				$data['filter_counters'][$index] = $tabfilter['filter_show_counter'] ? $this->getCount($tabfilter) : 0;
			}

			if (($messages = getMessages()) !== null) {
				$data['messages'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($data)]))->disableView()
			);
		}
		else {
			$filter = [
				'show' => $this->getInput('show', TRIGGERS_OPTION_RECENT_PROBLEM),
				'groupids' => $this->getInput('groupids', []),
				'hostids' => $this->getInput('hostids', []),
				'triggerids' => $this->getInput('triggerids', []),
				'name' => $this->getInput('name', ''),
				'severities' => $this->getInput('severities', []),
				'inventory' => array_filter($this->getInput('inventory', []), function ($filter_inventory) {
					return $filter_inventory['field'] !== '' && $filter_inventory['value'] !== '';
				}),
				'evaltype' => $this->getInput('evaltype', TAG_EVAL_TYPE_AND_OR),
				'tags' => array_filter($this->getInput('tags', []), function ($filter_tag) {
					return $filter_tag['tag'] !== '';
				}),
				'show_tags' => $this->getInput('show_tags', SHOW_TAGS_3),
				'tag_name_format' => $this->getInput('tag_name_format', TAG_NAME_FULL),
				'tag_priority' => $this->getInput('tag_priority', ''),
				'show_suppressed' => $this->getInput('show_suppressed', ZBX_PROBLEM_SUPPRESSED_FALSE),
				'show_symptoms' => $this->getInput('show_symptoms', 0),
				'acknowledgement_status' => $this->getInput('acknowledgement_status', ZBX_ACK_STATUS_ALL),
				'acknowledged_by_me' =>
					$this->getInput('acknowledgement_status', ZBX_ACK_STATUS_ALL) == ZBX_ACK_STATUS_ACK
						? $this->getInput('acknowledged_by_me', 0)
						: 0,
				'compact_view' => $this->getInput('compact_view', 0),
				'show_timeline' => $this->getInput('show_timeline', ZBX_TIMELINE_OFF),
				'details' => $this->getInput('details', 0),
				'highlight_row' => $this->getInput('highlight_row', 0),
				'show_opdata' => $this->getInput('show_opdata', OPERATIONAL_DATA_SHOW_NONE),
				'age_state' => $this->getInput('age_state', 0),
				'age' => $this->getInput('age_state', 0) ? $this->getInput('age', 14) : null,
				'from' => $this->hasInput('from') ? $this->getInput('from') : null,
				'to' => $this->hasInput('to') ? $this->getInput('to') : null
			];

			$filter = self::sanitizeFilter($filter);

			$data = [
				'page' => $this->getInput('page', 1),
				'action' => $this->getInput('action'),
				'sort' => $this->getInput('sort', 'clock'),
				'sortorder' => $this->getInput('sortorder', ZBX_SORT_DOWN),
				'filter' => $filter,
				'tabfilter_idx' => 'web.problem.filter'
			];

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
