<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


$table = new CTableInfo(_('No host groups found.'));
$table->setHeader(array(
	_('Host group'),
	_('Without problems'),
	_('With problems'),
	_('Total')
));

// get host groups
$groups = API::HostGroup()->get(array(
	'output' => array('groupid', 'name'),
	'groupids' => $data['filter']['groupids'],
	'hostids' => isset($data['filter']['hostids']) ? $data['filter']['hostids'] : null,
	'monitored_hosts' => true,
	'preservekeys' => true
));
CArrayHelper::sort($groups, array('name'));

// get hosts
$hosts = API::Host()->get(array(
	'output' => array('hostid', 'name'),
	'selectGroups' => array('groupid'),
	'groupids' => array_keys($groups),
	'hostids' => isset($data['filter']['hostids']) ? $data['filter']['hostids'] : null,
	'filter' => array('maintenance_status' => $data['filter']['maintenance']),
	'monitored_hosts' => true,
	'preservekeys' => true
));
CArrayHelper::sort($hosts, array('name'));

// get triggers
$triggers = API::Trigger()->get(array(
	'output' => array('triggerid', 'priority'),
	'selectHosts' => array('hostid'),
	'filter' => array(
		'priority' => $data['filter']['severity'],
		'value' => TRIGGER_VALUE_TRUE
	),
	'maintenance' => $data['filter']['maintenance'],
	'monitored' => true
));

if ($data['filter']['extAck']) {
	$triggers_unack = API::Trigger()->get(array(
		'output' => array('triggerid'),
		'selectHosts' => array('hostid'),
		'filter' => array(
			'priority' => $data['filter']['severity'],
			'value' => TRIGGER_VALUE_TRUE
		),
		'withLastEventUnacknowledged' => true,
		'maintenance' => $data['filter']['maintenance'],
		'monitored' => true,
		'preservekeys' => true
	));

	foreach ($triggers_unack as $tunack) {
		foreach ($tunack['hosts'] as $unack_host) {
			$hosts_with_unack_triggers[$unack_host['hostid']] = $unack_host['hostid'];
		}
	}
}

$hosts_data = array();
$problematic_host_list = array();
$lastUnack_host_list = array();
$highest_severity = array();
$highest_severity2 = array();

foreach ($triggers as $trigger) {
	foreach ($trigger['hosts'] as $trigger_host) {
		if (!isset($hosts[$trigger_host['hostid']])) {
			continue;
		}
		else {
			$host = $hosts[$trigger_host['hostid']];
		}

		if ($data['filter']['extAck'] && isset($hosts_with_unack_triggers[$host['hostid']])) {
			if (!isset($lastUnack_host_list[$host['hostid']])) {
				$lastUnack_host_list[$host['hostid']] = array();
				$lastUnack_host_list[$host['hostid']]['host'] = $host['name'];
				$lastUnack_host_list[$host['hostid']]['hostid'] = $host['hostid'];
				$lastUnack_host_list[$host['hostid']]['severities'] = array();
				$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_DISASTER] = 0;
				$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_HIGH] = 0;
				$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_AVERAGE] = 0;
				$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_WARNING] = 0;
				$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_INFORMATION] = 0;
				$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = 0;
			}
			if (isset($triggers_unack[$trigger['triggerid']])) {
				$lastUnack_host_list[$host['hostid']]['severities'][$trigger['priority']]++;
			}

			foreach ($host['groups'] as $gnum => $group) {
				if (!isset($highest_severity2[$group['groupid']])) {
					$highest_severity2[$group['groupid']] = 0;
				}

				if ($trigger['priority'] > $highest_severity2[$group['groupid']]) {
					$highest_severity2[$group['groupid']] = $trigger['priority'];
				}

				if (!isset($hosts_data[$group['groupid']])) {
					$hosts_data[$group['groupid']] = array(
						'problematic' => 0,
						'ok' => 0,
						'lastUnack' => 0,
						'hostids_all' => array(),
						'hostids_unack' => array()
					);
				}

				if (!isset($hosts_data[$group['groupid']]['hostids_unack'][$host['hostid']])) {
					$hosts_data[$group['groupid']]['hostids_unack'][$host['hostid']] = $host['hostid'];
					$hosts_data[$group['groupid']]['lastUnack']++;
				}
			}
		}

		if (!isset($problematic_host_list[$host['hostid']])) {
			$problematic_host_list[$host['hostid']] = array();
			$problematic_host_list[$host['hostid']]['host'] = $host['name'];
			$problematic_host_list[$host['hostid']]['hostid'] = $host['hostid'];

			$problematic_host_list[$host['hostid']]['severities'] = array();

			for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
				$problematic_host_list[$host['hostid']]['severities'][$severity] = 0;
			}

			krsort($problematic_host_list[$host['hostid']]['severities']);
		}
		$problematic_host_list[$host['hostid']]['severities'][$trigger['priority']]++;

		foreach ($host['groups'] as $gnum => $group) {
			if (!isset($highest_severity[$group['groupid']])) {
				$highest_severity[$group['groupid']] = 0;
			}

			if ($trigger['priority'] > $highest_severity[$group['groupid']]) {
				$highest_severity[$group['groupid']] = $trigger['priority'];
			}

			if (!isset($hosts_data[$group['groupid']])) {
				$hosts_data[$group['groupid']] = array(
					'problematic' => 0,
					'ok' => 0,
					'lastUnack' => 0,
					'hostids_all' => array(),
					'hostids_unack' => array()
				);
			}

			if (!isset($hosts_data[$group['groupid']]['hostids_all'][$host['hostid']])) {
				$hosts_data[$group['groupid']]['hostids_all'][$host['hostid']] = $host['hostid'];
				$hosts_data[$group['groupid']]['problematic']++;
			}
		}
	}
}

foreach ($hosts as $host) {
	foreach ($host['groups'] as $group) {
		if (!isset($groups[$group['groupid']])) {
			continue;
		}

		if (!isset($groups[$group['groupid']]['hosts'])) {
			$groups[$group['groupid']]['hosts'] = array();
		}
		$groups[$group['groupid']]['hosts'][$host['hostid']] = array('hostid' => $host['hostid']);

		if (!isset($highest_severity[$group['groupid']])) {
			$highest_severity[$group['groupid']] = 0;
		}

		if (!isset($hosts_data[$group['groupid']])) {
			$hosts_data[$group['groupid']] = array('problematic' => 0, 'ok' => 0, 'lastUnack' => 0);
		}

		if (!isset($problematic_host_list[$host['hostid']])) {
			$hosts_data[$group['groupid']]['ok']++;
		}
	}
}

foreach ($groups as $group) {
	if (!isset($hosts_data[$group['groupid']])) {
		continue;
	}

	$group_row = new CRow();

	$name = new CLink($group['name'], 'tr_status.php?filter_set=1&groupid='.$group['groupid'].'&hostid=0'.
		'&show_triggers='.TRIGGERS_OPTION_RECENT_PROBLEM
	);
	$group_row->addItem($name);
	$group_row->addItem(new CCol($hosts_data[$group['groupid']]['ok'], 'normal'));

	if ($data['filter']['extAck']) {
		if ($hosts_data[$group['groupid']]['lastUnack']) {
			$table_inf = new CTableInfo();
			$table_inf->setAttribute('style', 'width: 400px;');

			// set trigger severities as table header starting from highest severity
			$header = array();

			for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
				$header[] = ($data['filter']['severity'] === null || isset($data['filter']['severity'][$severity]))
					? getSeverityName($severity, $data['config'])
					: null;
			}

			krsort($header);
			array_unshift($header, _('Host'));

			$table_inf->setHeader($header);

			$popup_rows = 0;

			foreach ($group['hosts'] as $host) {
				$hostid = $host['hostid'];
				if (!isset($lastUnack_host_list[$hostid])) {
					continue;
				}

				if ($popup_rows >= ZBX_WIDGET_ROWS) {
					break;
				}
				$popup_rows++;

				$host_data = $lastUnack_host_list[$hostid];

				$r = new CRow();
				$r->addItem(new CLink($host_data['host'], 'tr_status.php?filter_set=1&groupid='.$group['groupid'].
					'&hostid='.$hostid.'&show_triggers='.TRIGGERS_OPTION_RECENT_PROBLEM
				));

				foreach ($lastUnack_host_list[$host['hostid']]['severities'] as $severity => $trigger_count) {
					if (!is_null($data['filter']['severity']) && !isset($data['filter']['severity'][$severity])) {
						continue;
					}
					$r->addItem(new CCol($trigger_count, getSeverityStyle($severity, $trigger_count)));
				}
				$table_inf->addRow($r);
			}
			$lastUnack_count = new CSpan($hosts_data[$group['groupid']]['lastUnack'], 'pointer red bold');
			$lastUnack_count->setHint($table_inf);
		}
		else {
			$lastUnack_count = 0;
		}
	}

	// if hostgroup contains problematic hosts, hint should be built
	if ($hosts_data[$group['groupid']]['problematic']) {
		$table_inf = new CTableInfo();
		$table_inf->setAttribute('style', 'width: 400px;');

		// set trigger severities as table header starting from highest severity
		$header = array();

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$header[] = ($data['filter']['severity'] === null || isset($data['filter']['severity'][$severity]))
				? getSeverityName($severity, $data['config'])
				: null;
		}

		krsort($header);
		array_unshift($header, _('Host'));

		$table_inf->setHeader($header);

		$popup_rows = 0;

		foreach ($group['hosts'] as $host) {
			$hostid = $host['hostid'];
			if (!isset($problematic_host_list[$hostid])) {
				continue;
			}
			if ($popup_rows >= ZBX_WIDGET_ROWS) {
				break;
			}
			$popup_rows++;

			$host_data = $problematic_host_list[$hostid];

			$r = new CRow();
			$r->addItem(new CLink($host_data['host'], 'tr_status.php?filter_set=1&groupid='.$group['groupid'].
				'&hostid='.$hostid.'&show_triggers='.TRIGGERS_OPTION_RECENT_PROBLEM
			));

			foreach ($problematic_host_list[$host['hostid']]['severities'] as $severity => $trigger_count) {
				if (!is_null($data['filter']['severity']) && !isset($data['filter']['severity'][$severity])) {
					continue;
				}
				$r->addItem(new CCol($trigger_count, getSeverityStyle($severity, $trigger_count)));
			}
			$table_inf->addRow($r);
		}
		$problematic_count = new CSpan($hosts_data[$group['groupid']]['problematic'], 'pointer');
		$problematic_count->setHint($table_inf);
	}
	else {
		$problematic_count = 0;
	}

	switch ($data['filter']['extAck']) {
		case EXTACK_OPTION_ALL:
			$group_row->addItem(new CCol(
				$problematic_count,
				getSeverityStyle($highest_severity[$group['groupid']], $hosts_data[$group['groupid']]['problematic']))
			);
			$group_row->addItem($hosts_data[$group['groupid']]['problematic'] + $hosts_data[$group['groupid']]['ok']);
			break;
		case EXTACK_OPTION_UNACK:
			$group_row->addItem(new CCol(
				$lastUnack_count,
				getSeverityStyle((isset($highest_severity2[$group['groupid']]) ? $highest_severity2[$group['groupid']] : 0),
					$hosts_data[$group['groupid']]['lastUnack']))
			);
			$group_row->addItem($hosts_data[$group['groupid']]['lastUnack'] + $hosts_data[$group['groupid']]['ok']);
			break;
		case EXTACK_OPTION_BOTH:
			$unackspan = $lastUnack_count ? new CSpan(array($lastUnack_count, SPACE._('of').SPACE)) : null;
			$group_row->addItem(new CCol(array(
				$unackspan, $problematic_count),
				getSeverityStyle($highest_severity[$group['groupid']], $hosts_data[$group['groupid']]['problematic']))
			);
			$group_row->addItem($hosts_data[$group['groupid']]['problematic'] + $hosts_data[$group['groupid']]['ok']);
			break;
	}

	$table->addRow($group_row);
}

$script = new CJsScript(get_js(
	'jQuery("#'.WIDGET_HOST_STATUS.'_footer").html("'._s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)).'");'
));

$widget = new CDiv(array($table, $script));
$widget->show();
