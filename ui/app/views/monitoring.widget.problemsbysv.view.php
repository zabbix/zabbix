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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * @var CView $this
 */

if ($data['filter']['show_type'] == WIDGET_PROBLEMS_BY_SV_SHOW_TOTALS) {
	$table = makeSeverityTotals($data)
		->addClass(ZBX_STYLE_BY_SEVERITY_WIDGET)
		->addClass(ZBX_STYLE_TOTALS_LIST)
		->addClass(($data['filter']['layout'] == STYLE_HORIZONTAL)
			? ZBX_STYLE_TOTALS_LIST_HORIZONTAL
			: ZBX_STYLE_TOTALS_LIST_VERTICAL
		);
}
else {
	$filter_severities = (array_key_exists('severities', $data['filter']) && $data['filter']['severities'])
		? $data['filter']['severities']
		: range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1);

	$header = [[_('Host group'), (new CSpan())->addClass(ZBX_STYLE_ARROW_UP)]];

	for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
		if (in_array($severity, $filter_severities)) {
			$header[] = CSeverityHelper::getName($severity);
		}
	}

	$hide_empty_groups = array_key_exists('hide_empty_groups', $data['filter'])
		? $data['filter']['hide_empty_groups']
		: 0;

	$groupurl = (new CUrl('zabbix.php'))
		->setArgument('action', 'problem.view')
		->setArgument('filter_name', '')
		->setArgument('show', TRIGGERS_OPTION_RECENT_PROBLEM)
		->setArgument('hostids',
			array_key_exists('hostids', $data['filter']) ? $data['filter']['hostids'] : null
		)
		->setArgument('name', array_key_exists('problem', $data['filter']) ? $data['filter']['problem'] : null)
		->setArgument('show_suppressed',
			(array_key_exists('show_suppressed', $data['filter']) && $data['filter']['show_suppressed'] == 1) ? 1 : null
		);

	$table = makeSeverityTable($data, $hide_empty_groups, $groupurl)
		->addClass(ZBX_STYLE_BY_SEVERITY_WIDGET)
		->setHeader($header)
		->setHeadingColumn(0);
}

$output = [
	'name' => $data['name'],
	'body' => $table->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
