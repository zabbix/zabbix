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
	if (!array_key_exists($group['groupid'], $data['hosts_data'])) {
		continue;
	}

	if ($data['filter']['hide_empty_groups'] && $data['hosts_data'][$group['groupid']]['problematic'] == 0) {
		continue;
	}

	$group_row = new CRow();

	$url_group->setArgument('filter_groupids', [$group['groupid']]);
	$url_host->setArgument('filter_groupids', [$group['groupid']]);
	$name = new CLink($group['name'], $url_group->getUrl());
	$group_row->addItem($name);
	$group_row->addItem(
		($data['hosts_data'][$group['groupid']]['ok'] != 0)
			? $data['hosts_data'][$group['groupid']]['ok']
			: ''
	);

	if ($data['filter']['ext_ack'] != EXTACK_OPTION_ALL) {
		if ($data['hosts_data'][$group['groupid']]['lastUnack']) {
			// Set trigger severities as table header starting from highest severity.
			$header = [_('Host')];

			foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
				if (in_array($severity, $data['filter']['severities'])) {
					$header[] = getSeverityName($severity, $data['config']);
				}
			}

			$table_inf = (new CTableInfo())->setHeader($header);

			$popup_rows = 0;

			foreach ($group['hosts'] as $host) {
				$hostid = $host['hostid'];

				if (!array_key_exists($hostid, $data['lastUnack_host_list'])) {
					continue;
				}

				$host_data = $data['lastUnack_host_list'][$hostid];

				$url_host->setArgument('filter_hostids', [$host['hostid']]);
				$r = new CRow();
				$r->addItem(
					(new CCol(
						new CLink($host_data['host'], $url_host->getUrl())
					))->addClass(ZBX_STYLE_NOWRAP)
				);

				foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
					if (in_array($severity, $data['filter']['severities'])) {
						$trigger_count = $data['lastUnack_host_list'][$host['hostid']]['severities'][$severity];

						$r->addItem(
							($trigger_count != 0)
								? (new CCol($trigger_count))->addClass(getSeverityStyle($severity))
								: ''
						);
					}
				}

				$table_inf->addRow($r);

				if (++$popup_rows == ZBX_WIDGET_ROWS) {
					break;
				}
			}
			$lastUnack_count = (new CLinkAction($data['hosts_data'][$group['groupid']]['lastUnack']))
				->setHint($table_inf);
		}
		else {
			$lastUnack_count = 0;
		}
	}

	// if hostgroup contains problematic hosts, hint should be built
	if ($data['hosts_data'][$group['groupid']]['problematic']) {
		// Set trigger severities as table header starting from highest severity.
		$header = [_('Host')];

		foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
			if (in_array($severity, $data['filter']['severities'])) {
				$header[] = getSeverityName($severity, $data['config']);
			}
		}

		$table_inf = (new CTableInfo())->setHeader($header);

		$popup_rows = 0;

		foreach ($group['hosts'] as $host) {
			$hostid = $host['hostid'];

			if (!array_key_exists($hostid, $data['problematic_host_list'])) {
				continue;
			}

			$host_data = $data['problematic_host_list'][$hostid];

			$url_host->setArgument('filter_hostids', [$host['hostid']]);
			$r = new CRow();
			$r->addItem(
				(new CCol(
					new CLink($host_data['host'], $url_host->getUrl())
				))->addClass(ZBX_STYLE_NOWRAP)
			);

			foreach (range(TRIGGER_SEVERITY_COUNT - 1, TRIGGER_SEVERITY_NOT_CLASSIFIED) as $severity) {
				if (in_array($severity, $data['filter']['severities'])) {
					$trigger_count = $data['problematic_host_list'][$host['hostid']]['severities'][$severity];

					$r->addItem(
						($trigger_count != 0)
							? (new CCol($trigger_count))->addClass(getSeverityStyle($severity))
							: ''
					);
				}
			}

			$table_inf->addRow($r);

			if (++$popup_rows == ZBX_WIDGET_ROWS) {
				break;
			}
		}
		$problematic_count = (new CLinkAction($data['hosts_data'][$group['groupid']]['problematic']))
			->setHint($table_inf);
	}
	else {
		$problematic_count = 0;
	}

	switch ($data['filter']['ext_ack']) {
		case EXTACK_OPTION_ALL:
			if ($data['hosts_data'][$group['groupid']]['problematic'] != 0) {
				$group_row->addItem((new CCol($problematic_count))
					->addClass(getSeverityStyle($data['highest_severity'][$group['groupid']]))
				);
			}
			else {
				$group_row->addItem('');
			}
			$group_row->addItem(
				$data['hosts_data'][$group['groupid']]['problematic'] + $data['hosts_data'][$group['groupid']]['ok']
			);
			break;

		case EXTACK_OPTION_UNACK:
			if ($data['hosts_data'][$group['groupid']]['lastUnack'] != 0) {
				$group_row->addItem((new CCol($lastUnack_count))
					->addClass(getSeverityStyle(
						array_key_exists($group['groupid'], $data['highest_severity2'])
							? $data['highest_severity2'][$group['groupid']]
							: TRIGGER_SEVERITY_NOT_CLASSIFIED
					))
				);
			}
			else {
				$group_row->addItem('');
			}
			$group_row->addItem(
				$data['hosts_data'][$group['groupid']]['lastUnack'] + $data['hosts_data'][$group['groupid']]['ok']
			);
			break;

		case EXTACK_OPTION_BOTH:
			if ($data['hosts_data'][$group['groupid']]['problematic'] != 0) {
				$unackspan = $lastUnack_count ? [$lastUnack_count, ' '._('of').' '] : null;
				$group_row->addItem((new CCol([$unackspan, $problematic_count]))
					->addClass(getSeverityStyle($data['highest_severity'][$group['groupid']]))
				);
			}
			else {
				$group_row->addItem('');
			}
			$group_row->addItem(
				$data['hosts_data'][$group['groupid']]['problematic'] + $data['hosts_data'][$group['groupid']]['ok']
			);
			break;
	}

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
