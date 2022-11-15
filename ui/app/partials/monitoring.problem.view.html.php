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
 * @var CPartial $this
 * @var array    $data
 */

$options = [
	'resourcetype' => SCREEN_RESOURCE_PROBLEM,
	'mode' => SCREEN_MODE_JS,
	'dataId' => 'problem',
	'page' => $data['page'],
	'data' => [
		'action' => $data['action'],
		'sort' => $data['sort'],
		'sortorder' => $data['sortorder'],
		'filter' => [
			'show' => $data['filter']['show'],
			'groupids' => $data['filter']['groupids'],
			'hostids' => $data['filter']['hostids'],
			'application' => $data['filter']['application'],
			'triggerids' => $data['filter']['triggerids'],
			'name' => $data['filter']['name'],
			'severities' => $data['filter']['severities'],
			'inventory' => $data['filter']['inventory'],
			'evaltype' => $data['filter']['evaltype'],
			'tags' => $data['filter']['tags'],
			'show_tags' => $data['filter']['show_tags'],
			'tag_name_format' => $data['filter']['tag_name_format'],
			'tag_priority' => $data['filter']['tag_priority'],
			'show_suppressed' => $data['filter']['show_suppressed'],
			'unacknowledged' => $data['filter']['unacknowledged'],
			'compact_view' => $data['filter']['compact_view'],
			'show_timeline' => $data['filter']['show_timeline'],
			'details' => $data['filter']['details'],
			'highlight_row' => $data['filter']['highlight_row'],
			'show_opdata' => $data['filter']['show_opdata']
		]
	]
];

switch ($data['filter']['show']) {
	case TRIGGERS_OPTION_RECENT_PROBLEM:
	case TRIGGERS_OPTION_IN_PROBLEM:
		$options['data']['filter']['age_state'] = $data['filter']['age_state'];
		$options['data']['filter']['age'] = $data['filter']['age'];
		break;

	case TRIGGERS_OPTION_ALL:
		$options['profileIdx'] = $data['profileIdx'];
		$options['profileIdx2'] = $data['profileIdx2'];
		$options['from'] = $data['from'];
		$options['to'] = $data['to'];
		break;
}

echo CScreenBuilder::getScreen($options)->get();
