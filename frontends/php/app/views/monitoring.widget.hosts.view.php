<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


$table = (new CTableInfo())
	->setHeader([
		_('Host group'),
		_('Without problems'),
		_('With problems'),
		_('Total')
	]);

foreach ($data['groups'] as $group) {
	if (!array_key_exists($group['groupid'], $data['hosts_data'])) {
		continue;
	}

	$group_row = new CRow();

	$name = new CLink($group['name'], 'tr_status.php?filter_set=1&groupid='.$group['groupid'].'&hostid=0'.
		'&show_triggers='.TRIGGERS_OPTION_RECENT_PROBLEM
	);
	$group_row->addItem($name);
	$group_row->addItem((new CCol($data['hosts_data'][$group['groupid']]['ok']))->addClass(ZBX_STYLE_NORMAL_BG));

	if ($data['filter']['extAck']) {
		if ($data['hosts_data'][$group['groupid']]['lastUnack']) {
			$table_inf = new CTableInfo();

			// Set trigger severities as table header starting from highest severity.
			$header = [];

			for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
				$header[] = ($data['filter']['severity'] === null
						|| array_key_exists($severity, $data['filter']['severity']))
					? getSeverityName($severity, $data['config'])
					: null;
			}

			krsort($header);
			array_unshift($header, _('Host'));

			$table_inf->setHeader($header);

			$popup_rows = 0;

			foreach ($group['hosts'] as $host) {
				$hostid = $host['hostid'];

				if (!array_key_exists($hostid, $data['lastUnack_host_list'])) {
					continue;
				}

				$host_data = $data['lastUnack_host_list'][$hostid];

				$r = new CRow();
				$r->addItem(
					(new CCol(
						new CLink($host_data['host'], 'tr_status.php?filter_set=1&groupid='.$group['groupid'].
							'&hostid='.$hostid.'&show_triggers='.TRIGGERS_OPTION_RECENT_PROBLEM)
					))->addClass(ZBX_STYLE_NOWRAP)
				);

				foreach ($data['lastUnack_host_list'][$host['hostid']]['severities'] as $severity => $trigger_count) {
					if (!is_null($data['filter']['severity'])
							&& !array_key_exists($severity, $data['filter']['severity'])) {
						continue;
					}

					$r->addItem((new CCol($trigger_count))->addClass(getSeverityStyle($severity, $trigger_count)));
				}

				$table_inf->addRow($r);

				if (++$popup_rows == ZBX_WIDGET_ROWS) {
					break;
				}
			}
			$lastUnack_count = (new CSpan($data['hosts_data'][$group['groupid']]['lastUnack']))
				->addClass(ZBX_STYLE_LINK_ACTION)
				->setHint($table_inf);
		}
		else {
			$lastUnack_count = 0;
		}
	}

	// if hostgroup contains problematic hosts, hint should be built
	if ($data['hosts_data'][$group['groupid']]['problematic']) {
		$table_inf = new CTableInfo();

		// set trigger severities as table header starting from highest severity
		$header = [];

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$header[] = ($data['filter']['severity'] === null
					|| array_key_exists($severity, $data['filter']['severity']))
				? getSeverityName($severity, $data['config'])
				: null;
		}

		krsort($header);
		array_unshift($header, _('Host'));

		$table_inf->setHeader($header);

		$popup_rows = 0;

		foreach ($group['hosts'] as $host) {
			$hostid = $host['hostid'];

			if (!array_key_exists($hostid, $data['problematic_host_list'])) {
				continue;
			}

			$host_data = $data['problematic_host_list'][$hostid];

			$r = new CRow();
			$r->addItem(new CLink($host_data['host'], 'tr_status.php?filter_set=1&groupid='.$group['groupid'].
				'&hostid='.$hostid.'&show_triggers='.TRIGGERS_OPTION_RECENT_PROBLEM
			));

			foreach ($data['problematic_host_list'][$host['hostid']]['severities'] as $severity => $trigger_count) {
				if (!is_null($data['filter']['severity'])
						&& !array_key_exists($severity, $data['filter']['severity'])) {
					continue;
				}

				$r->addItem((new CCol($trigger_count))->addClass(getSeverityStyle($severity, $trigger_count)));
			}

			$table_inf->addRow($r);

			if (++$popup_rows == ZBX_WIDGET_ROWS) {
				break;
			}
		}
		$problematic_count = (new CSpan($data['hosts_data'][$group['groupid']]['problematic']))
			->addClass(ZBX_STYLE_LINK_ACTION)
			->setHint($table_inf);
	}
	else {
		$problematic_count = 0;
	}

	switch ($data['filter']['extAck']) {
		case EXTACK_OPTION_ALL:
			$group_row->addItem((new CCol($problematic_count))
				->addClass(getSeverityStyle($data['highest_severity'][$group['groupid']],
					$data['hosts_data'][$group['groupid']]['problematic']
				))
			);
			$group_row->addItem(
				$data['hosts_data'][$group['groupid']]['problematic'] + $data['hosts_data'][$group['groupid']]['ok']
			);
			break;

		case EXTACK_OPTION_UNACK:
			$group_row->addItem((new CCol($lastUnack_count))
				->addClass(getSeverityStyle(
					array_key_exists($group['groupid'], $data['highest_severity2'])
						? $data['highest_severity2'][$group['groupid']]
						: 0,
					$data['hosts_data'][$group['groupid']]['lastUnack']
				))
			);
			$group_row->addItem(
				$data['hosts_data'][$group['groupid']]['lastUnack'] + $data['hosts_data'][$group['groupid']]['ok']
			);
			break;

		case EXTACK_OPTION_BOTH:
			$unackspan = $lastUnack_count ? [$lastUnack_count, ' '._('of').' '] : null;
			$group_row->addItem((new CCol([$unackspan, $problematic_count]))
				->addClass(getSeverityStyle(
					$data['highest_severity'][$group['groupid']], $data['hosts_data'][$group['groupid']]['problematic']
				))
			);
			$group_row->addItem(
				$data['hosts_data'][$group['groupid']]['problematic'] + $data['hosts_data'][$group['groupid']]['ok']
			);
			break;
	}

	$table->addRow($group_row);
}

$output = [
	'header' => _('Host status'),
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
