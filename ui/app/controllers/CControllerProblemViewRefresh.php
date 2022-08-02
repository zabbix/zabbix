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
 * Controller for the "Problems" asynchronous refresh page.
 */
class CControllerProblemViewRefresh extends CControllerProblem {

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'action' =>					'string',
			'sort' =>					'in clock,host,severity,name',
			'sortorder' =>				'in '.ZBX_SORT_DOWN.','.ZBX_SORT_UP,
			'page' =>					'ge 1',
			'filter_show' =>			'in '.TRIGGERS_OPTION_RECENT_PROBLEM.','.TRIGGERS_OPTION_IN_PROBLEM.','.TRIGGERS_OPTION_ALL,
			'filter_groupids' =>		'array_id',
			'filter_hostids' =>			'array_id',
			'filter_application' =>		'string',
			'filter_triggerids' =>		'array_id',
			'filter_name' =>			'string',
			'filter_severities' =>		'array',
			'filter_age_state' =>		'in 1',
			'filter_age' =>				'int32',
			'filter_inventory' =>		'array',
			'filter_evaltype' =>		'in '.TAG_EVAL_TYPE_AND_OR.','.TAG_EVAL_TYPE_OR,
			'filter_tags' =>			'array',
			'filter_show_tags' =>		'in '.PROBLEMS_SHOW_TAGS_NONE.','.PROBLEMS_SHOW_TAGS_1.','.PROBLEMS_SHOW_TAGS_2.','.PROBLEMS_SHOW_TAGS_3,
			'filter_show_suppressed' =>	'in 1',
			'filter_unacknowledged' =>	'in 1',
			'filter_compact_view' =>	'in 1',
			'filter_show_timeline' =>	'in 1',
			'filter_details' =>			'in 1',
			'filter_highlight_row' =>	'in 1',
			'filter_show_opdata' =>		'in '.OPERATIONAL_DATA_SHOW_NONE.','.OPERATIONAL_DATA_SHOW_SEPARATELY.','.OPERATIONAL_DATA_SHOW_WITH_PROBLEM,
			'filter_tag_name_format' =>	'in '.PROBLEMS_TAG_NAME_FULL.','.PROBLEMS_TAG_NAME_SHORTENED.','.PROBLEMS_TAG_NAME_NONE,
			'filter_tag_priority' =>	'string',
			'from' =>					'range_time',
			'to' =>						'range_time'
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
		$data = [
			'page' => $this->getInput('page', 1),
			'action' => $this->getInput('action'),
			'sort' => $this->getInput('sort', 'clock'),
			'sortorder' => $this->getInput('sortorder', ZBX_SORT_DOWN),
			'filter' => [
				'show' => $this->getInput('filter_show', TRIGGERS_OPTION_RECENT_PROBLEM),
				'groupids' => $this->getInput('filter_groupids', []),
				'hostids' => $this->getInput('filter_hostids', []),
				'application' => $this->getInput('filter_application', ''),
				'triggerids' => $this->getInput('filter_triggerids', []),
				'name' => $this->getInput('filter_name', ''),
				'severities' => $this->getInput('filter_severities', []),
				'inventory' => array_filter($this->getInput('filter_inventory', []), function ($filter_inventory) {
					return $filter_inventory['field'] !== '' && $filter_inventory['value'] !== '';
				}),
				'evaltype' => $this->getInput('filter_evaltype', TAG_EVAL_TYPE_AND_OR),
				'tags' => array_filter($this->getInput('filter_tags', []), function ($filter_tag) {
					return $filter_tag['tag'] !== '';
				}),
				'show_tags' => $this->getInput('filter_show_tags', PROBLEMS_SHOW_TAGS_3),
				'tag_name_format' => $this->getInput('filter_tag_name_format', PROBLEMS_TAG_NAME_FULL),
				'tag_priority' => $this->getInput('filter_tag_priority', ''),
				'show_suppressed' => $this->getInput('filter_show_suppressed', ZBX_PROBLEM_SUPPRESSED_FALSE),
				'unacknowledged' => $this->getInput('filter_unacknowledged', 0),
				'compact_view' => $this->getInput('filter_compact_view', 0),
				'show_timeline' => $this->getInput('filter_show_timeline', 0),
				'details' => $this->getInput('filter_details', 0),
				'highlight_row' => $this->getInput('filter_highlight_row', 0),
				'show_opdata' => $this->getInput('filter_show_opdata', OPERATIONAL_DATA_SHOW_NONE),
				'age_state' => $this->getInput('filter_age_state', 0),
				'age' => $this->getInput('filter_age', 14)
			],
			'profileIdx' => 'web.problem.filter',
			'profileIdx2' => 0,
			'from' => $this->hasInput('from') ? $this->getInput('from') : null,
			'to' => $this->hasInput('to') ? $this->getInput('to') : null
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
