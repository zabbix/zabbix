<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


abstract class CControllerProblem extends CController {

	// Filter idx prefix.
	const FILTER_IDX = 'web.monitoring.problem';

	// Filter fields default values.
	const FILTER_FIELDS_DEFAULT = [
		'show' => TRIGGERS_OPTION_RECENT_PROBLEM,
		'groupids' => [],
		'hostids' => [],
		'application' => '',
		'triggerids' => [],
		'name' => '',
		'severities' => [],
		'age_state' => 0,
		'age' => 14,
		'inventory' => [],
		'evaltype' => TAG_EVAL_TYPE_AND_OR,
		'tags' => [],
		'show_tags' => PROBLEMS_SHOW_TAGS_3,
		'show_suppressed' => 0,
		'unacknowledged' => 0,
		'compact_view' => 0,
		'show_timeline' => 1,
		'details' => 0,
		'highlight_row' => 0,
		'show_opdata' => OPERATIONAL_DATA_SHOW_NONE,
		'tag_name_format' => PROBLEMS_TAG_NAME_FULL,
		'tag_priority' => '',
		'page' => null,
		'sort' => 'name',
		'sortorder' => ZBX_SORT_UP
	];

	protected function getCount(array $filter) {
		return 0;
	}

	protected function getData(array $filter): array {
		return [];
	}

	protected function getAdditionalData(array $filter): array {
		$host_groups = [];

		if ($filter['groupids']) {
			$host_groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['groupids'],
				'preservekeys' => true
			]);
			$host_groups = CArrayHelper::renameObjectsKeys($host_groups, ['groupid' => 'id']);
		}

		$data = [
			'groups' => $host_groups,
			'hosts' => [],
			'triggers' => [],
			'inventories' => [],
		];

		return $data;
	}
}
