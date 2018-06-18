<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


// indicator of sort field
$sort_div = (new CSpan())->addClass(ZBX_STYLE_ARROW_UP);

$table = (new CTableInfo())
	->setHeader([
		[_('Host group'), $sort_div],
		_('Without problems'),
		_('With problems'),
		_('Total')
	]);

$url_group = (new CUrl('zabbix.php'))
	->setArgument('action', 'problem.view')
	->setArgument('filter_set', 1)
	->setArgument('filter_show', TRIGGERS_OPTION_RECENT_PROBLEM)
	->setArgument('filter_groupids', null)
	->setArgument('filter_hostids', $data['filter']['hostids'])
	->setArgument('filter_name', $data['filter']['problem'])
	->setArgument('filter_maintenance', ($data['filter']['maintenance'] == 1) ? 1 : null)
	->setArgument('fullscreen', $data['fullscreen'] ? '1' : null);
$url_host = (new CUrl('zabbix.php'))
	->setArgument('action', 'problem.view')
	->setArgument('filter_set', 1)
	->setArgument('filter_show', TRIGGERS_OPTION_RECENT_PROBLEM)
	->setArgument('filter_groupids', null)
	->setArgument('filter_hostids', null)
	->setArgument('filter_name', $data['filter']['problem'])
	->setArgument('filter_maintenance', ($data['filter']['maintenance'] == 1) ? 1 : null)
	->setArgument('fullscreen', $data['fullscreen'] ? '1' : null);

foreach ($data['groups'] as $group) {
	$problematic_count_key = ($data['filter']['ext_ack'] == EXTACK_OPTION_UNACK)
		? 'hosts_problematic_unack_count'
		: 'hosts_problematic_count';

	if ($data['filter']['hide_empty_groups'] && $group[$problematic_count_key] == 0) {
		continue;
	}

	$group_row = new CRow();

	$url_group->setArgument('filter_groupids', [$group['groupid']]);
	$url_host->setArgument('filter_groupids', [$group['groupid']]);
	$group_name = new CLink($group['name'], $url_group->getUrl());
	$group_row->addItem($group_name);

	// Add cell for column 'Without problems'.
	$group_row->addItem(
		($group['hosts_total_count'] != $group[$problematic_count_key])
			? $group['hosts_total_count'] - $group[$problematic_count_key]
			: ''
	);

	/**
	 * Create a CLinkAction element with hint-boxed table that contains hosts of particular host group and number of
	 * unacknowledged problems for each host, grouped per problem severity.
	 */
	if ($data['filter']['ext_ack'] != EXTACK_OPTION_ALL) {
		if ($group['hosts_problematic_unack_list']) {
			// Set trigger severities as table header, ordered starting from highest severity.
			$header = [_('Host')];

			foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
				if (in_array($severity, $data['filter']['severities'])) {
					$header[] = getSeverityName($severity, $data['config']);
				}
			}

			$table_inf = (new CTableInfo())->setHeader($header);

			$popup_rows = 0;

			foreach ($group['hosts_problematic_unack_list'] as $hostid => $host_name) {
				$host = $data['hosts_data'][$hostid];

				$url_host->setArgument('filter_hostids', [$hostid]);
				$row = new CRow();
				$row->addItem(
					(new CCol(
						new CLink($host['host'], $url_host->getUrl())
					))->addClass(ZBX_STYLE_NOWRAP)
				);

				foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
					if (in_array($severity, $data['filter']['severities'])) {
						$row->addItem(
							($host['severities'][$severity] != 0)
								? (new CCol($host['severities'][$severity]))->addClass(getSeverityStyle($severity))
								: ''
						);
					}
				}

				$table_inf->addRow($row);

				if (++$popup_rows == ZBX_WIDGET_ROWS) {
					break;
				}
			}

			$last_unack_count = (new CLinkAction($group['hosts_problematic_unack_count']))->setHint($table_inf);
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
		// Set trigger severities as table header, ordered starting from highest severity.
		$header = [_('Host')];

		foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
			if (in_array($severity, $data['filter']['severities'])) {
				$header[] = getSeverityName($severity, $data['config']);
			}
		}

		$table_inf = (new CTableInfo())->setHeader($header);

		$popup_rows = 0;

		foreach ($group['hosts_problematic_list'] as $hostid => $host_name) {
			$host_data = $data['hosts_data'][$hostid];

			$url_host->setArgument('filter_hostids', [$hostid]);
			$table_inf_row = new CRow();
			$table_inf_row->addItem(
				(new CCol(
					new CLink($host_data['host'], $url_host->getUrl())
				))->addClass(ZBX_STYLE_NOWRAP)
			);

			foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
				if (in_array($severity, $data['filter']['severities'])) {
					$table_inf_row->addItem(
						($host_data['severities'][$severity] != 0)
							? (new CCol($host_data['severities'][$severity]))->addClass(getSeverityStyle($severity))
							: ''
					);
				}
			}

			$table_inf->addRow($table_inf_row);

			if (++$popup_rows == ZBX_WIDGET_ROWS) {
				break;
			}
		}

		$problematic_count = (new CLinkAction($group['hosts_problematic_count']))->setHint($table_inf);
	}

	// Add 'With problems' column with ext_ack specific content.
	switch ($data['filter']['ext_ack']) {
		case EXTACK_OPTION_ALL:
			if ($group['hosts_problematic_count'] != 0) {
				$group_row->addItem((new CCol($problematic_count))
					->addClass(getSeverityStyle($group['highest_severity']))
				);
			}
			else {
				$group_row->addItem('');
			}
			break;

		case EXTACK_OPTION_BOTH:
			if ($group['hosts_problematic_count'] != 0) {
				$unack_span = $last_unack_count ? [$last_unack_count, ' '._('of').' '] : null;
				$group_row->addItem((new CCol([$unack_span, $problematic_count]))
					->addClass(getSeverityStyle($group['highest_severity']))
				);
			}
			else {
				$group_row->addItem('');
			}
			break;

		case EXTACK_OPTION_UNACK:
			if ($group['hosts_problematic_unack_count'] != 0) {
				$group_row->addItem((new CCol($last_unack_count))
					->addClass(getSeverityStyle($group['highest_severity']))
				);
			}
			else {
				$group_row->addItem('');
			}
			break;
	}

	// Add 'Total' column.
	$group_row->addItem($group['hosts_total_count']);

	$table->addRow($group_row);
}

$output = [
	'header' => $data['name'],
	'body' => $table->toString(),
	'footer' => (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
