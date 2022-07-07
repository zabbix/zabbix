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
 * @var array $data
 */

// indicator of sort field
$sort_div = (new CSpan())->addClass(ZBX_STYLE_ARROW_UP);

$table = (new CTableInfo())
	->setHeader([
		[_x('Host group', 'compact table header'), $sort_div],
		_x('Without problems', 'compact table header'),
		_x('With problems', 'compact table header'),
		_x('Total', 'compact table header')
	])
	->setHeadingColumn(0);

$url_group = $data['allowed_ui_problems']
	?  (new CUrl('zabbix.php'))
		->setArgument('action', 'problem.view')
		->setArgument('filter_name', '')
		->setArgument('show', TRIGGERS_OPTION_RECENT_PROBLEM)
		->setArgument('hostids', $data['filter']['hostids'])
		->setArgument('name', $data['filter']['problem'])
		->setArgument('show_suppressed', ($data['filter']['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)
			? ZBX_PROBLEM_SUPPRESSED_TRUE
			: null
		)
	: null;
$url_host = $data['allowed_ui_problems']
	? (new CUrl('zabbix.php'))
		->setArgument('action', 'problem.view')
		->setArgument('filter_name', '')
		->setArgument('show', TRIGGERS_OPTION_RECENT_PROBLEM)
		->setArgument('name', $data['filter']['problem'])
		->setArgument('show_suppressed', ($data['filter']['show_suppressed'] == ZBX_PROBLEM_SUPPRESSED_TRUE)
			? ZBX_PROBLEM_SUPPRESSED_TRUE
			: null
		)
	: null;

foreach ($data['groups'] as $group) {
	$problematic_count_key = ($data['filter']['ext_ack'] == EXTACK_OPTION_UNACK)
		? 'hosts_problematic_unack_count'
		: 'hosts_problematic_count';

	if ($data['filter']['hide_empty_groups'] && $group[$problematic_count_key] == 0) {
		continue;
	}

	if ($data['allowed_ui_problems']) {
		$url_group->setArgument('groupids', [$group['groupid']]);
		$url_host->setArgument('groupids', [$group['groupid']]);

		$group_row = [new CLink($group['name'], $url_group->getUrl())];
	}
	else {
		$group_row = [$group['name']];
	}

	// Add cell for column 'Without problems'.
	$group_row[] = ($group['hosts_total_count'] != $group[$problematic_count_key])
			? $group['hosts_total_count'] - $group[$problematic_count_key]
			: '';

	/**
	 * Create a CLinkAction element with hint-boxed table that contains hosts of particular host group and number of
	 * unacknowledged problems for each host, grouped per problem severity.
	 */
	if ($data['filter']['ext_ack'] != EXTACK_OPTION_ALL) {
		if ($group['hosts_problematic_unack_list']) {
			$last_unack_count = (new CLinkAction($group['hosts_problematic_unack_count']))->setHint(
				makeProblemHostsHintBox($group['hosts_problematic_unack_list'], $data, $url_host)
			);
		}
		else {
			$last_unack_count = null;
		}
	}

	/**
	 * Create a CLinkAction element with hint-boxed table that contains hosts of particular host group and number of all
	 * problems for each host, grouped per problem severity.
	 */
	if ($data['filter']['ext_ack'] != EXTACK_OPTION_UNACK && $group['hosts_problematic_count'] != 0) {
		$problematic_count = (new CLinkAction($group['hosts_problematic_count']))
			->setHint(makeProblemHostsHintBox($group['hosts_problematic_list'], $data, $url_host))
			->setAttribute('aria-label', _xs('%1$s, Severity, %2$s', 'screen reader',
				$group['hosts_problematic_count'], CSeverityHelper::getName((int) $group['highest_severity'])
			));
	}

	// Add 'With problems' column with ext_ack specific content.
	switch ($data['filter']['ext_ack']) {
		case EXTACK_OPTION_ALL:
			if ($group['hosts_problematic_count'] != 0) {
				$group_row[] = (new CCol($problematic_count))
					->addClass(CSeverityHelper::getStyle((int) $group['highest_severity']));
			}
			else {
				$group_row[] = '';
			}
			break;

		case EXTACK_OPTION_BOTH:
			if ($group['hosts_problematic_count'] != 0) {
				$unack_span = ($last_unack_count !== null) ? [$last_unack_count, ' '._('of').' '] : null;
				$group_row[] = (new CCol([$unack_span, $problematic_count]))
					->addClass(CSeverityHelper::getStyle((int) $group['highest_severity']));
			}
			else {
				$group_row[] = '';
			}
			break;

		case EXTACK_OPTION_UNACK:
			if ($group['hosts_problematic_unack_count'] != 0) {
				$group_row[] = (new CCol($last_unack_count))
					->addClass(CSeverityHelper::getStyle((int) $group['highest_severity']));
			}
			else {
				$group_row[] = '';
			}
			break;
	}

	// Add 'Total' column.
	$group_row[] = $group['hosts_total_count'];

	$table->addRow($group_row);
}

$output = [
	'name' => $data['name'],
	'body' => $table->toString()
];

if ($messages = get_and_clear_messages()) {
	$output['messages'] = array_column($messages, 'message');
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
