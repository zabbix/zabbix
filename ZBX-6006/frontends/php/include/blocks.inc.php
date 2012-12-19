<?php
/*
** Zabbix
** Copyright (C) 2001-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/graphs.inc.php';
require_once dirname(__FILE__).'/screens.inc.php';
require_once dirname(__FILE__).'/maps.inc.php';
require_once dirname(__FILE__).'/users.inc.php';

function make_favorite_graphs() {
	$favList = new CList(null, 'favorites');
	$graphids = array();
	$itemids = array();

	$fav_graphs = get_favorites('web.favorite.graphids');
	foreach ($fav_graphs as $favorite) {
		if ('itemid' == $favorite['source']) {
			$itemids[$favorite['value']] = $favorite['value'];
		}
		else {
			$graphids[$favorite['value']] = $favorite['value'];
		}
	}

	$options = array(
		'graphids' => $graphids,
		'selectHosts' => array('name'),
		'output' => array('name')
	);
	$graphs = API::Graph()->get($options);
	$graphs = zbx_toHash($graphs, 'graphid');

	$options = array(
		'itemids' => $itemids,
		'selectHosts' => array('name'),
		'output' => array('name', 'key_'),
		'webitems' => true
	);
	$items = API::Item()->get($options);
	$items = zbx_toHash($items, 'itemid');

	foreach ($fav_graphs as $favorite) {
		$sourceid = $favorite['value'];

		if ('itemid' == $favorite['source']) {
			if (!isset($items[$sourceid])) {
				continue;
			}
			$item = $items[$sourceid];
			$host = reset($item['hosts']);
			$item['name'] = itemName($item);

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$host['name'].':'.$item['name'], 'history.php?action=showgraph&itemid='.$sourceid);
			$link->setTarget('blank');
		}
		else {
			if (!isset($graphs[$sourceid])) {
				continue;
			}
			$graph = $graphs[$sourceid];
			$ghost = reset($graph['hosts']);

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$ghost['name'].':'.$graph['name'], 'charts.php?graphid='.$sourceid);
			$link->setTarget('blank');
		}
		$favList->addItem($link, 'nowrap');
	}
	return $favList;
}

function make_favorite_screens() {
	$favList = new CList(null, 'favorites');
	$fav_screens = get_favorites('web.favorite.screenids');
	$screenids = array();
	foreach ($fav_screens as $favorite) {
		if ('screenid' == $favorite['source']) {
			$screenids[$favorite['value']] = $favorite['value'];
		}
	}

	$options = array(
		'screenids' => $screenids,
		'output' => array('name', 'screenid')
	);
	$screens = API::Screen()->get($options);
	$screens = zbx_toHash($screens, 'screenid');

	foreach ($fav_screens as $favorite) {
		$source = $favorite['source'];
		$sourceid = $favorite['value'];

		if ('slideshowid' == $source) {
			if (!slideshow_accessible($sourceid, PERM_READ_ONLY)) {
				continue;
			}
			if (!$slide = get_slideshow_by_slideshowid($sourceid)) {
				continue;
			}

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$slide['name'], 'slides.php?elementid='.$sourceid);
			$link->setTarget('blank');
		}
		else {
			if (!isset($screens[$sourceid])) {
				continue;
			}
			$screen = $screens[$sourceid];

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$screen['name'], 'screens.php?elementid='.$sourceid);
			$link->setTarget('blank');
		}
		$favList->addItem($link, 'nowrap');
	}
	return $favList;
}

function make_favorite_maps() {
	$favList = new CList(null, 'favorites');
	$fav_sysmaps = get_favorites('web.favorite.sysmapids');
	$sysmapids = array();

	foreach ($fav_sysmaps as $favorite) {
		$sysmapids[$favorite['value']] = $favorite['value'];
	}

	$sysmaps = API::Map()->get(array(
		'sysmapids' => $sysmapids,
		'output' => array('name')
	));
	foreach ($sysmaps as $sysmap) {
		$sysmapid = $sysmap['sysmapid'];

		$link = new CLink(get_node_name_by_elid($sysmapid, null, ': ').$sysmap['name'], 'maps.php?sysmapid='.$sysmapid);
		$link->setTarget('blank');

		$favList->addItem($link, 'nowrap');
	}
	return $favList;
}

function make_system_status($filter) {
	$ackParams = array();
	if (!empty($filter['screenid'])) {
		$ackParams['screenid'] = $filter['screenid'];
	}

	$table = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? _('Node') : null,
		_('Host group'),
		is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_DISASTER]) ? getSeverityCaption(TRIGGER_SEVERITY_DISASTER) : null,
		is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_HIGH]) ? getSeverityCaption(TRIGGER_SEVERITY_HIGH) : null,
		is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_AVERAGE]) ? getSeverityCaption(TRIGGER_SEVERITY_AVERAGE) : null,
		is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_WARNING]) ? getSeverityCaption(TRIGGER_SEVERITY_WARNING) : null,
		is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_INFORMATION]) ? getSeverityCaption(TRIGGER_SEVERITY_INFORMATION) : null,
		is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_NOT_CLASSIFIED]) ? getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED) : null
	));

	// get host groups
	$options = array(
		'nodeids' => get_current_nodeid(),
		'monitored_hosts' => true,
		'groupids' => $filter['groupids'],
		'output' => array('groupid', 'name'),
		'preservekeys' => true
	);
	$groups = API::HostGroup()->get($options);

	foreach($groups as &$group) {
		$group['nodename'] = get_node_name_by_elid($group['groupid']);
	}
	unset($group);

	// we need natural sort
	$sortFields = array(
		array('field' => 'nodename', 'order' => ZBX_SORT_UP),
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	);
	CArrayHelper::sort($groups, $sortFields);

	$groupids = array();
	foreach ($groups as $group) {
		$groupids[] = $group['groupid'];
		$group['tab_priority'] = array();
		$group['tab_priority'][TRIGGER_SEVERITY_DISASTER] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_HIGH] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_AVERAGE] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_WARNING] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_INFORMATION] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$groups[$group['groupid']] = $group;
	}

	// get triggers
	$options = array(
		'nodeids' => get_current_nodeid(),
		'groupids' => $groupids,
		'monitored' => true,
		'maintenance' => $filter['maintenance'],
		'skipDependent' => true,
		'expandDescription' => true,
		'filter' => array(
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		),
		'sortfield' => 'lastchange',
		'sortorder' => ZBX_SORT_DOWN,
		'output' => array('value', 'lastchange', 'priority', 'value_flags', 'description', 'error'),
		'selectHosts' => array('name'),
		'preservekeys' => true
	);
	if ($filter['extAck'] == EXTACK_OPTION_UNACK) {
		$options['withLastEventUnacknowledged'] = 1;
	}
	$triggers = API::Trigger()->get($options);

	foreach ($triggers as $trigger) {
		$options = array(
			'nodeids' => get_current_nodeid(),
			'object' => EVENT_SOURCE_TRIGGERS,
			'triggerids' => $trigger['triggerid'],
			'filter'=> array(
				'value' => TRIGGER_VALUE_TRUE,
				'value_changed' => TRIGGER_VALUE_CHANGED_YES
			),
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => true,
			'select_acknowledges' => API_OUTPUT_COUNT,
			'sortfield' => 'eventid',
			'sortorder' => ZBX_SORT_DOWN,
			'preservekeys' => true,
			'limit' => 1
		);
		$events = API::Event()->get($options);
		if (empty($events)) {
			$trigger['event'] = array(
				'value_changed' => 0,
				'value' => $trigger['value'],
				'acknowledged' => true,
				'clock' => $trigger['lastchange']
			);
		}
		else {
			$trigger['event'] = reset($events);
		}

		foreach ($trigger['groups'] as $group) {
			if (in_array($filter['extAck'], array(EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH))) {
				$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count']++;

				if ($groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count'] < 30) {
					$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['triggers'][] = $trigger;
				}
			}

			if (in_array($filter['extAck'], array(EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH))
					&& !$trigger['event']['acknowledged']) {
				$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count_unack']++;

				if ($groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count_unack'] < 30) {
					$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['triggers_unack'][] = $trigger;
				}
			}
		}
	}
	unset($triggers);

	foreach ($groups as $group) {
		$group_row = new CRow();
		if (is_show_all_nodes()) {
			$group_row->addItem($group['nodename']);
		}

		$name = new CLink($group['name'], 'tr_status.php?groupid='.$group['groupid'].'&hostid=0&show_triggers='.TRIGGERS_OPTION_ONLYTRUE);
		$group_row->addItem($name);

		foreach ($group['tab_priority'] as $severity => $data) {
			if (!is_null($filter['severity']) && !isset($filter['severity'][$severity])) {
				continue;
			}

			$allTriggersNum = $data['count'];
			if ($allTriggersNum) {
				$allTriggersNum = new CSpan($allTriggersNum, 'pointer');
				$allTriggersNum->setHint(makeTriggersPopup($data['triggers'], $ackParams));
			}

			$unackTriggersNum = $data['count_unack'];
			if ($unackTriggersNum) {
				$unackTriggersNum = new CSpan($unackTriggersNum, 'pointer red bold');
				$unackTriggersNum->setHint(makeTriggersPopup($data['triggers_unack'], $ackParams));
			}

			switch ($filter['extAck']) {
				case EXTACK_OPTION_ALL:
					$group_row->addItem(getSeverityCell($severity, $allTriggersNum, !$allTriggersNum));
					break;

				case EXTACK_OPTION_UNACK:
					$group_row->addItem(getSeverityCell($severity, $unackTriggersNum, !$unackTriggersNum));
					break;

				case EXTACK_OPTION_BOTH:
					if ($unackTriggersNum) {
						$span = new Cspan(SPACE._('of').SPACE);
						$unackTriggersNum = new CSpan($unackTriggersNum);
					}
					else {
						$span = null;
						$unackTriggersNum = null;
					}

					$group_row->addItem(getSeverityCell($severity, array($unackTriggersNum, $span, $allTriggersNum), !$allTriggersNum));
					break;
			}
		}
		$table->addRow($group_row);
	}
	$script = new CJSScript(get_js("jQuery('#hat_syssum_footer').html('"._s('Updated: %s', zbx_date2str(_('H:i:s')))."')"));

	return new CDiv(array($table, $script));
}

function make_hoststat_summary($filter) {
	$table = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? _('Node') : null,
		_('Host group'),
		_('Without problems'),
		_('With problems'),
		_('Total')
	));

	// get host groups
	$options = array(
		'nodeids' => get_current_nodeid(),
		'groupids' => $filter['groupids'],
		'monitored_hosts' => 1,
		'output' => array('groupid', 'name')
	);
	$groups = API::HostGroup()->get($options);
	$groups = zbx_toHash($groups, 'groupid');

	foreach($groups as &$group) {
		$group['nodename'] = get_node_name_by_elid($group['groupid']);
	}
	unset($group);

	// we need natural sort
	$sortFields = array(
		array('field' => 'nodename', 'order' => ZBX_SORT_UP),
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	);
	CArrayHelper::sort($groups, $sortFields);

	// get hosts
	$options = array(
		'nodeids' => get_current_nodeid(),
		'groupids' => zbx_objectValues($groups, 'groupid'),
		'monitored_hosts' => 1,
		'filter' => array('maintenance_status' => $filter['maintenance']),
		'output' => array('hostid', 'name')
	);
	$hosts = API::Host()->get($options);
	$hosts = zbx_toHash($hosts, 'hostid');
	CArrayHelper::sort($hosts, array('name'));

	// get triggers
	$options = array(
		'nodeids' => get_current_nodeid(),
		'monitored' => 1,
		'maintenance' => $filter['maintenance'],
		'expandData' => 1,
		'filter' => array(
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		),
		'output' => array('triggerid', 'priority')
	);
	$triggers = API::Trigger()->get($options);

	if ($filter['extAck']) {
		$options = array(
			'nodeids' => get_current_nodeid(),
			'monitored' => 1,
			'maintenance' => $filter['maintenance'],
			'withLastEventUnacknowledged' => 1,
			'selectHosts' => API_OUTPUT_REFER,
			'filter' => array(
				'priority' => $filter['severity'],
				'value' => TRIGGER_VALUE_TRUE
			),
			'output' => API_OUTPUT_REFER
		);
		$triggers_unack = API::Trigger()->get($options);
		$triggers_unack = zbx_toHash($triggers_unack, 'triggerid');
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

			if ($filter['extAck'] && isset($hosts_with_unack_triggers[$host['hostid']])) {
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
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_DISASTER] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_HIGH] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_AVERAGE] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_WARNING] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_INFORMATION] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = 0;
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
		if (is_show_all_nodes()) {
			$group_row->addItem($group['nodename']);
		}

		$name = new CLink($group['name'], 'tr_status.php?groupid='.$group['groupid'].'&hostid=0&show_triggers='.TRIGGERS_OPTION_ONLYTRUE);
		$group_row->addItem($name);
		$group_row->addItem(new CCol($hosts_data[$group['groupid']]['ok'], 'normal'));

		if ($filter['extAck']) {
			if ($hosts_data[$group['groupid']]['lastUnack']) {
				$table_inf = new CTableInfo();
				$table_inf->setAttribute('style', 'width: 400px;');
				$table_inf->setHeader(array(
					_('Host'),
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_DISASTER]) ? getSeverityCaption(TRIGGER_SEVERITY_DISASTER) : null,
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_HIGH]) ? getSeverityCaption(TRIGGER_SEVERITY_HIGH) : null,
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_AVERAGE]) ? getSeverityCaption(TRIGGER_SEVERITY_AVERAGE) : null,
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_WARNING]) ? getSeverityCaption(TRIGGER_SEVERITY_WARNING) : null,
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_INFORMATION]) ? getSeverityCaption(TRIGGER_SEVERITY_INFORMATION) : null,
					is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_NOT_CLASSIFIED]) ? getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED) : null
				));
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
					$r->addItem(new CLink($host_data['host'], 'tr_status.php?groupid='.$group['groupid'].'&hostid='.$hostid.'&show_triggers='.TRIGGERS_OPTION_ONLYTRUE));

					foreach ($lastUnack_host_list[$host['hostid']]['severities'] as $severity => $trigger_count) {
						if (!is_null($filter['severity']) && !isset($filter['severity'][$severity])) {
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
			$table_inf->setHeader(array(
				_('Host'),
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_DISASTER]) ? getSeverityCaption(TRIGGER_SEVERITY_DISASTER) : null,
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_HIGH]) ? getSeverityCaption(TRIGGER_SEVERITY_HIGH) : null,
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_AVERAGE]) ? getSeverityCaption(TRIGGER_SEVERITY_AVERAGE) : null,
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_WARNING]) ? getSeverityCaption(TRIGGER_SEVERITY_WARNING) : null,
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_INFORMATION]) ? getSeverityCaption(TRIGGER_SEVERITY_INFORMATION) : null,
				is_null($filter['severity']) || isset($filter['severity'][TRIGGER_SEVERITY_NOT_CLASSIFIED]) ? getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED) : null
			));
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
				$r->addItem(new CLink($host_data['host'], 'tr_status.php?groupid='.$group['groupid'].'&hostid='.$hostid.'&show_triggers='.TRIGGERS_OPTION_ONLYTRUE));

				foreach ($problematic_host_list[$host['hostid']]['severities'] as $severity => $trigger_count) {
					if (!is_null($filter['severity'])&&!isset($filter['severity'][$severity])) {
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

		switch ($filter['extAck']) {
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
	$script = new CJSScript(get_js("jQuery('#hat_hoststat_footer').html('"._s('Updated: %s', zbx_date2str(_('H:i:s')))."')"));

	return new CDiv(array($table, $script));
}

function make_status_of_zbx() {
	global $ZBX_SERVER, $ZBX_SERVER_PORT;

	$table = new CTableInfo();
	$table->setHeader(array(
		_('Parameter'),
		_('Value'),
		_('Details')
	));

	show_messages(); // because in function get_status(); function clear_messages() is called when fsockopen() fails.
	$status = get_status();

	$table->addRow(array(
		_('Zabbix server is running'),
		new CSpan($status['zabbix_server'], ($status['zabbix_server'] == _('Yes') ? 'off' : 'on')),
		isset($ZBX_SERVER, $ZBX_SERVER_PORT) ? $ZBX_SERVER.':'.$ZBX_SERVER_PORT : _('Zabbix server IP or port is not set!')
	));
	$title = new CSpan(_('Number of hosts (monitored/not monitored/templates)'));
	$title->setAttribute('title', 'asdad');
	$table->addRow(array(_('Number of hosts (monitored/not monitored/templates)'), $status['hosts_count'],
		array(
			new CSpan($status['hosts_count_monitored'], 'off'), ' / ',
			new CSpan($status['hosts_count_not_monitored'], 'on'), ' / ',
			new CSpan($status['hosts_count_template'], 'unknown')
		)
	));
	$title = new CSpan(_('Number of items (monitored/disabled/not supported)'));
	$title->setAttribute('title', _('Only items assigned to enabled hosts are counted'));
	$table->addRow(array($title, $status['items_count'],
		array(
			new CSpan($status['items_count_monitored'], 'off'), ' / ',
			new CSpan($status['items_count_disabled'], 'on'), ' / ',
			new CSpan($status['items_count_not_supported'], 'unknown')
		)
	));
	$title = new CSpan(_('Number of triggers (enabled/disabled)[problem/unknown/ok]'));
	$title->setAttribute('title', _('Only triggers assigned to enabled hosts and depending on enabled items are counted'));
	$table->addRow(array($title, $status['triggers_count'],
		array(
			$status['triggers_count_enabled'], ' / ',
			$status['triggers_count_disabled'].SPACE.SPACE.'[',
			new CSpan($status['triggers_count_on'], 'on'), ' / ',
			new CSpan($status['triggers_count_unknown'], 'unknown'), ' / ',
			new CSpan($status['triggers_count_off'], 'off'), ']'
		)
	));
	$table->addRow(array(_('Number of users (online)'), $status['users_count'], new CSpan($status['users_online'], 'green')));
	$table->addRow(array(_('Required server performance, new values per second'), $status['qps_total'], ' - '));

	// check requirements
	if (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN) {
		$reqs = FrontendSetup::i()->checkRequirements();
		foreach ($reqs as $req) {
			if ($req['result'] == false) {
				$table->addRow(array(
					new CSpan($req['name'], 'red'),
					new CSpan($req['current'], 'red'),
					new CSpan($req['error'], 'red')
				));
			}
		}
	}
	$script = new CJSScript(get_js("jQuery('#hat_stszbx_footer').html('"._s('Updated: %s', zbx_date2str(_('H:i:s')))."')"));
	return new CDiv(array($table, $script));
}

/**
 * Create and return a DIV with latest problem triggers.
 *
 * @param array $filter
 *
 * @return CDiv
 */
function make_latest_issues(array $filter = array()) {
	$config = select_config();

	$ackParams = array();
	if (!empty($filter['screenid'])) {
		$ackParams['screenid'] = $filter['screenid'];
	}

	$options = array(
		'groupids' => $filter['groupids'],
		'monitored' => true,
		'maintenance' => $filter['maintenance'],
		'withLastEventUnacknowledged' => (!empty($filter['extAck']) && $filter['extAck'] == EXTACK_OPTION_UNACK) ? true : null,
		'skipDependent' => true,
		'filter' => array(
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		),
		'selectHosts' => array('hostid', 'name'),
		'output' => array('value_flags', 'error', 'url', 'expression', 'description', 'priority', 'type')
	);
	$options['sortfield'] = isset($filter['sortfield']) ? $filter['sortfield'] : 'lastchange';
	$options['sortorder'] = isset($filter['sortorder']) ? $filter['sortorder'] : ZBX_SORT_DOWN;
	$options['limit'] = isset($filter['limit']) ? $filter['limit'] : DEFAULT_LATEST_ISSUES_CNT;

	if (isset($filter['hostids'])) {
		$options['hostids'] = $filter['hostids'];
	}
	$triggers = API::Trigger()->get($options);

	// how many issues are there at all with given parameters
	$options['countOutput'] = true;
	unset($options['limit']);
	$triggersTotalCount = API::Trigger()->get($options);

	foreach($triggers as $tnum => $trigger) {
		// if trigger is lost(broken expression) we skip it
		if (empty($trigger['hosts'])) {
			unset($triggers[$tnum]);
			continue;
		}

		$host = reset($trigger['hosts']);
		$trigger['hostid'] = $host['hostid'];
		$trigger['hostname'] = $host['name'];

		$triggers[$tnum] = $trigger;
	}
	$hostIds = zbx_objectValues($triggers, 'hostid');

	// fetch trigger hosts
	$hosts = API::Host()->get(array(
		'hostids' => $hostIds,
		'output' => array('hostid', 'name', 'maintenance_status', 'maintenance_type', 'maintenanceid'),
		'selectInventory' => array('hostid'),
		'selectScreens' => API_OUTPUT_COUNT,
		'preservekeys' => true
	));

	// fetch trigger scripts
	$scripts_by_hosts = API::Script()->getScriptsByHosts($hostIds);

	// indicator of sort field
	$sortDiv = new CDiv(SPACE, $options['sortorder'] === ZBX_SORT_DOWN ? 'icon_sortdown default_cursor' : 'icon_sortup default_cursor');
	$sortDiv->addStyle('float: left');
	$hostHeaderDiv = new CDiv(array(_('Host'), SPACE));
	$hostHeaderDiv->addStyle('float: left');
	$issueHeaderDiv = new CDiv(array(_('Issue'), SPACE));
	$issueHeaderDiv->addStyle('float: left');
	$lastChangeHeaderDiv = new CDiv(array(_('Last change'), SPACE));
	$lastChangeHeaderDiv->addStyle('float: left');

	$table = new CTableInfo();
	$table->setHeader(
		array(
			is_show_all_nodes() ? _('Node') : null,
			$options['sortfield'] === 'hostname' ? array($hostHeaderDiv, $sortDiv) : _('Host'),
			$options['sortfield'] === 'priority' ? array($issueHeaderDiv, $sortDiv) : _('Issue'),
			$options['sortfield'] === 'lastchange' ? array($lastChangeHeaderDiv, $sortDiv) : _('Last change'),
			_('Age'),
			_('Info'),
			$config['event_ack_enable'] ? _('Ack') : null,
			_('Actions')
		)
	);

	foreach ($triggers as $trigger) {
		// check for dependencies
		$host = $hosts[$trigger['hostid']];

		$hostSpan = new CDiv(null, 'maintenance-abs-cont');

		$hostName = new CSpan($host['name'], 'link_menu menu-host');
		$hostName->setAttribute('data-menu', hostMenuData($host, $scripts_by_hosts[$host['hostid']]));

		// add maintenance icon with hint if host is in maintenance
		if ($host['maintenance_status']) {

			$mntIco = new CDiv(null, 'icon-maintenance-abs');

			// get maintenance
			$maintenances = API::Maintenance()->get(array(
				'maintenanceids' => $host['maintenanceid'],
				'output' => API_OUTPUT_EXTEND,
				'limit' => 1
			));
			if ($maintenance = reset($maintenances)) {
				$hint = $maintenance['name'].' ['.($host['maintenance_type']
					? _('Maintenance without data collection')
					: _('Maintenance with data collection')).']';

				if (isset($maintenance['description'])) {
					// double quotes mandatory
					$hint .= "\n".$maintenance['description'];
				}

				$mntIco->setHint($hint);
				$mntIco->addClass('pointer');
			}

			$hostName->addClass('left-to-icon-maintenance-abs');
			$hostSpan->addItem($mntIco);
		}

		$hostSpan ->addItem($hostName);

		// unknown triggers
		$unknown = SPACE;
		if ($trigger['value_flags'] == TRIGGER_VALUE_FLAG_UNKNOWN) {
			$unknown = new CDiv(SPACE, 'status_icon iconunknown');
			$unknown->setHint($trigger['error'], '', 'on');
		}

		$events = API::Event()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'select_acknowledges' => API_OUTPUT_EXTEND,
			'triggerids' => $trigger['triggerid'],
			'acknowledged' => (!empty($filter['extAck']) && $filter['extAck'] == EXTACK_OPTION_UNACK) ? 0 : null,
			'filter' => array(
				'object' => EVENT_OBJECT_TRIGGER,
				'value' => TRIGGER_VALUE_TRUE,
				'value_changed' => TRIGGER_VALUE_CHANGED_YES
			),
			'sortfield' => array('eventid'),
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 1
		));
		if ($event = reset($events)) {
			$ack = getEventAckState(
				$event,
				!empty($filter['backUrl']) ? $filter['backUrl'] : true,
				true,
				$ackParams
			);

			$description = CEventHelper::expandDescription(zbx_array_merge($trigger, array('clock' => $event['clock'], 'ns' => $event['ns'])));

			// actions
			$actions = get_event_actions_stat_hints($event['eventid']);
			$clock = new CLink(zbx_date2str(_('d M Y H:i:s'), $event['clock']), 'events.php?triggerid='.$trigger['triggerid'].'&source=0&show_unknown=1&nav_time='.$event['clock']);

			if ($trigger['url']) {
				$description = new CLink($description, resolveTriggerUrl($trigger), null, null, true);
			}
			else {
				$description = new CSpan($description, 'pointer');
			}

			$description = new CCol($description, getSeverityStyle($trigger['priority']));
			$description->setHint(make_popup_eventlist($event['eventid'], $trigger['type'], $trigger['triggerid']), '', '', false);

			$table->addRow(array(
				get_node_name_by_elid($trigger['triggerid']),
				$hostSpan,
				$description,
				$clock,
				zbx_date2age($event['clock']),
				$unknown,
				$ack,
				$actions
			));
		}
		unset($trigger, $description, $actions);
	}

	// initialize blinking
	zbx_add_post_js('jqBlink.blink();');
	$script = new CJSScript(get_js("jQuery('#hat_lastiss_footer').html('"._s('Updated: %s', zbx_date2str(_('H:i:s')))."')"));

	$infoDiv = new CDiv(_n('%2$d of %1$d issue is shown', '%2$d of %1$d issues are shown', $triggersTotalCount, count($triggers)));
	$infoDiv->addStyle('text-align: right; padding-right: 3px;');
	$widgetDiv = new CDiv(array($table, $infoDiv, $script));

	return $widgetDiv;
}

function make_webmon_overview($filter) {
	$groups = API::HostGroup()->get(array(
		'groupids' => $filter['groupids'],
		'monitored_hosts' => true,
		'with_monitored_httptests' => true,
		'output' => array('groupid', 'name'),
		'preservekeys' => true
	));

	foreach($groups as &$group) {
		$group['nodename'] = get_node_name_by_elid($group['groupid']);
	}
	unset($group);

	// we need natural sort
	$sortFields = array(
		array('field' => 'nodename', 'order' => ZBX_SORT_UP),
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	);
	CArrayHelper::sort($groups, $sortFields);

	$availableHosts = API::Host()->get(array(
		'groupids' => array_keys($groups),
		'monitored_hosts' => true,
		'filter' => array('maintenance_status' => $filter['maintenance']),
		'output' => API_OUTPUT_SHORTEN,
		'preservekeys' => true
	));
	$availableHostIds = array_keys($availableHosts);

	$table  = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? _('Node') : null,
		_('Host group'),
		_('Ok'),
		_('Failed'),
		_('Unknown')
	));


	foreach ($groups as $group) {
		$showGroup = false;
		$okCount = 0;
		$failedCount = 0;
		$unknownCount = 0;

		$result = DBselect(
			'SELECT DISTINCT ht.httptestid,i.lastclock,i.lastvalue'.
			' FROM items i,httptestitem hti,httptest ht,applications a,hosts_groups hg'.
			' WHERE i.itemid=hti.itemid'.
				' AND hti.httptestid=ht.httptestid'.
				' AND ht.applicationid=a.applicationid'.
				' AND a.hostid=hg.hostid'.
				' AND hti.type='.HTTPSTEP_ITEM_TYPE_LASTSTEP.
				' AND ht.status='.HTTPTEST_STATUS_ACTIVE.
				' AND '.dbConditionInt('hg.hostid', $availableHostIds).
				' AND hg.groupid='.$group['groupid']
		);
		while ($row = DBfetch($result)) {
			$showGroup = true;

			if (!$row['lastclock']) {
				$unknownCount++;
			}
			elseif ($row['lastvalue'] != 0) {
				$failedCount++;
			}
			else {
				$okCount++;
			}
		}

		if ($showGroup) {
			$table->addRow(array(
				is_show_all_nodes() ? $group['nodename'] : null,
				$group['name'],
				new CSpan($okCount, 'off'),
				new CSpan($failedCount, $failedCount ? 'on' : 'off'),
				new CSpan($unknownCount, 'unknown')
			));
		}
	}
	$script = new CJSScript(get_js("jQuery('#hat_webovr_footer').html('"._s('Updated: %s', zbx_date2str(_('H:i:s')))."')"));

	return new CDiv(array($table, $script));
}

function make_discovery_status() {
	$options = array(
		'filter' => array('status' => DHOST_STATUS_ACTIVE),
		'selectDHosts' => array('status', 'dhostid'),
		'output' => API_OUTPUT_EXTEND
	);
	$drules = API::DRule()->get($options);

	foreach($drules as &$drule) {
		$drule['nodename'] = get_node_name_by_elid($drule['druleid']);
	}
	unset($drule);

	// we need natural sort
	$sortFields = array(
		array('field' => 'nodename', 'order' => ZBX_SORT_UP),
		array('field' => 'name', 'order' => ZBX_SORT_UP)
	);
	CArrayHelper::sort($drules, $sortFields);


	foreach ($drules as $drnum => $drule) {
		$drules[$drnum]['up'] = 0;
		$drules[$drnum]['down'] = 0;

		foreach ($drule['dhosts'] as $dhost){
			if (DRULE_STATUS_DISABLED == $dhost['status']) {
				$drules[$drnum]['down']++;
			}
			else {
				$drules[$drnum]['up']++;
			}
		}
	}

	$header = array(
		is_show_all_nodes() ? new CCol(_('Node'), 'center') : null,
		new CCol(_('Discovery rule'), 'center'),
		new CCol(_x('Up', 'discovery results in dashboard')),
		new CCol(_x('Down', 'discovery results in dashboard'))
	);

	$table  = new CTableInfo();
	$table->setHeader($header,'header');

	foreach ($drules as $drule) {
		$table->addRow(array(
			$drule['nodename'],
			new CLink($drule['nodename'].($drule['nodename'] ? ': ' : '').$drule['name'], 'discovery.php?druleid='.$drule['druleid']),
			new CSpan($drule['up'], 'green'),
			new CSpan($drule['down'], ($drule['down'] > 0) ? 'red' : 'green')
		));
	}
	$script = new CJSScript(get_js("jQuery('#hat_dscvry_footer').html('"._s('Updated: %s', zbx_date2str(_('H:i:s')))."')"));
	return new CDiv(array($table, $script));
}

function make_graph_menu(&$menu, &$submenu) {
	$menu['menu_graphs'][] = array(
		_('Favourite graphs'),
		null,
		null,
		array('outer' => array('pum_oheader'), 'inner' => array('pum_iheader'))
	);

	$menu['menu_graphs'][] = array(
		_('Add').' '._('Graph'),
		'javascript: PopUp(\'popup.php?srctbl=graphs&srcfld1=graphid&reference=graphid&monitored_hosts=1&multiselect=1\',800,450); void(0);',
		null,
		array('outer' => 'pum_o_submenu', 'inner' => array('pum_i_submenu'))
	);
	$menu['menu_graphs'][] = array(
		_('Add').' '._('Simple graph'),
		'javascript: PopUp(\'popup.php?srctbl=simple_graph&srcfld1=itemid&monitored_hosts=1&reference=itemid&multiselect=1\',800,450); void(0);',
		null,
		array('outer' => 'pum_o_submenu', 'inner' => array('pum_i_submenu'))
	);
	$menu['menu_graphs'][] = array(
		_('Remove'),
		null,
		null,
		array('outer' => 'pum_o_submenu', 'inner' => array('pum_i_submenu'))
	);
	$submenu['menu_graphs'] = make_graph_submenu();
}

function make_graph_submenu() {
	$graphids = array();
	$itemids = array();

	$fav_graphs = get_favorites('web.favorite.graphids');
	foreach ($fav_graphs as $key => $favorite) {
		if ('itemid' == $favorite['source']) {
			$itemids[$favorite['value']] = $favorite['value'];
		}
		else {
			$graphids[$favorite['value']] = $favorite['value'];
		}
	}

	$options = array(
		'graphids' => $graphids,
		'selectHosts' => array('hostid', 'host'),
		'output' => array('name')
	);
	$graphs = API::Graph()->get($options);
	$graphs = zbx_toHash($graphs, 'graphid');

	$options = array(
		'itemids' => $itemids,
		'selectHosts' => array('hostid', 'host'),
		'output' => array('name', 'key_'),
		'webitems' => 1
	);
	$items = API::Item()->get($options);
	$items = zbx_toHash($items, 'itemid');

	$favGraphs = array();
	foreach ($fav_graphs as $favorite) {
		$source = $favorite['source'];
		$sourceid = $favorite['value'];

		if ('itemid' == $source) {
			if (!isset($items[$sourceid])) {
				continue;
			}
			$item_added = true;
			$item = $items[$sourceid];
			$host = reset($item['hosts']);
			$item['name'] = itemName($item);
			$favGraphs[] = array(
				'name' => $host['host'].':'.$item['name'],
				'favobj' => 'itemid',
				'favid' => $sourceid,
				'favaction' => 'remove'
			);
		}
		else {
			if (!isset($graphs[$sourceid])) {
				continue;
			}
			$graph_added = true;
			$graph = $graphs[$sourceid];
			$ghost = reset($graph['hosts']);
			$favGraphs[] = array(
				'name' => $ghost['host'].':'.$graph['name'],
				'favobj' => 'graphid',
				'favid' => $sourceid,
				'favaction' => 'remove'
			);
		}
	}

	if (isset($graph_added)) {
		$favGraphs[] = array(
			'name' => _('Remove').' '._('All').' '._('Graphs'),
			'favobj' => 'graphid',
			'favid' => 0,
			'favaction' => 'remove'
		);
	}

	if (isset($item_added)) {
		$favGraphs[] = array(
			'name' => _('Remove').' '._('All').' '._('Simple graphs'),
			'favobj' => 'itemid',
			'favid' => 0,
			'favaction' => 'remove'
		);
	}
	return $favGraphs;
}

function make_sysmap_menu(&$menu, &$submenu) {
	$menu['menu_sysmaps'][] = array(_('Favourite maps'), null, null, array('outer' => array('pum_oheader'), 'inner' => array('pum_iheader')));
	$menu['menu_sysmaps'][] = array(
		_('Add').' '._('Map'),
		'javascript: PopUp(\'popup.php?srctbl=sysmaps&srcfld1=sysmapid&reference=sysmapid&multiselect=1\',800,450); void(0);',
		null,
		array('outer' => 'pum_o_submenu', 'inner' => array('pum_i_submenu')
	));
	$menu['menu_sysmaps'][] = array(_('Remove'), null, null, array('outer' => 'pum_o_submenu', 'inner' => array('pum_i_submenu')));
	$submenu['menu_sysmaps'] = make_sysmap_submenu();
}

function make_sysmap_submenu() {
	$fav_sysmaps = get_favorites('web.favorite.sysmapids');
	$favMaps = array();
	$sysmapids = array();
	foreach ($fav_sysmaps as $favorite) {
		$sysmapids[$favorite['value']] = $favorite['value'];
	}

	$options = array(
		'sysmapids' => $sysmapids,
		'output' => array('name')
	);
	$sysmaps = API::Map()->get($options);
	foreach ($sysmaps as $sysmap) {
		$favMaps[] = array(
			'name' => $sysmap['name'],
			'favobj' => 'sysmapid',
			'favid' => $sysmap['sysmapid'],
			'favaction' => 'remove'
		);
	}

	if (!empty($favMaps)) {
		$favMaps[] = array(
			'name' => _('Remove').' '._('All').' '._('Maps'),
			'favobj' => 'sysmapid',
			'favid' => 0,
			'favaction' => 'remove'
		);
	}
	return $favMaps;
}

function make_screen_menu(&$menu, &$submenu) {
	$menu['menu_screens'][] = array(_('Favourite screens'), null, null, array('outer' => array('pum_oheader'), 'inner' => array('pum_iheader')));
	$menu['menu_screens'][] = array(
		_('Add').' '._('Screen'),
		'javascript: PopUp(\'popup.php?srctbl=screens&srcfld1=screenid&reference=screenid&multiselect=1\', 800, 450); void(0);',
		null,
		array('outer' => 'pum_o_submenu', 'inner' => array('pum_i_submenu')
	));
	$menu['menu_screens'][] = array(
		_('Add').' '._('Slide show'),
		'javascript: PopUp(\'popup.php?srctbl=slides&srcfld1=slideshowid&reference=slideshowid&multiselect=1\', 800, 450); void(0);',
		null,
		array('outer' => 'pum_o_submenu', 'inner' => array('pum_i_submenu')
	));
	$menu['menu_screens'][] = array(_('Remove'), null, null, array('outer' => 'pum_o_submenu', 'inner' => array('pum_i_submenu')));
	$submenu['menu_screens'] = make_screen_submenu();
}

function make_screen_submenu() {
	$fav_screens = get_favorites('web.favorite.screenids');
	$screenids = array();
	foreach ($fav_screens as $favorite) {
		if ('screenid' == $favorite['source']) {
			$screenids[$favorite['value']] = $favorite['value'];
		}
	}

	$options = array(
		'screenids' => $screenids,
		'output' => array('name', 'screenid')
	);
	$screens = API::Screen()->get($options);
	$screens = zbx_toHash($screens, 'screenid');
	$favScreens = array();
	foreach ($fav_screens as $favorite) {
		$source = $favorite['source'];
		$sourceid = $favorite['value'];
		if ('slideshowid' == $source) {
			if (!slideshow_accessible($sourceid, PERM_READ_ONLY)) {
				continue;
			}
			if (!$slide = get_slideshow_by_slideshowid($sourceid)) {
				continue;
			}
			$slide_added = true;
			$favScreens[] = array(
				'name' => $slide['name'],
				'favobj' => 'slideshowid',
				'favid' => $slide['slideshowid'],
				'favaction' => 'remove'
			);
		}
		else {
			if (!isset($screens[$sourceid])) {
				continue;
			}
			$screen = $screens[$sourceid];
			$screen_added = true;
			$favScreens[] = array(
				'name' => $screen['name'],
				'favobj' => 'screenid',
				'favid' => $screen['screenid'],
				'favaction' => 'remove'
			);
		}
	}

	if (isset($screen_added)) {
		$favScreens[] = array(
			'name' => _('Remove').' '._('All').' '._('Screens'),
			'favobj' => 'screenid',
			'favid' => 0,
			'favaction' => 'remove'
		);
	}

	if (isset($slide_added)) {
		$favScreens[] = array(
			'name' => _('Remove').' '._('All').' '._('Slides'),
			'favobj' => 'slideshowid',
			'favid' => 0,
			'favaction' => 'remove'
		);
	}
	return $favScreens;
}

/**
 * Generate table for dashboard triggers popup.
 *
 * @see make_system_status
 *
 * @param array $triggers
 * @param array $ackParams
 *
 * @return CTableInfo
 */
function makeTriggersPopup(array $triggers, array $ackParams) {
	$config = select_config();

	$popupTable = new CTableInfo();
	$popupTable->setAttribute('style', 'width: 400px;');
	$popupTable->setHeader(array(
		is_show_all_nodes() ? _('Node') : null,
		_('Host'),
		_('Issue'),
		_('Age'),
		_('Info'),
		$config['event_ack_enable'] ? _('Ack') : null,
		_('Actions')
	));

	foreach ($triggers as $tnum => $trigger) {
		$triggers[$tnum]['clock'] = $trigger['event']['clock'];
	}
	CArrayHelper::sort($triggers, array(array('field' => 'clock', 'order' => ZBX_SORT_DOWN)));

	foreach ($triggers as $trigger) {
		$event = $trigger['event'];
		$ack = getEventAckState($event, true, true, $ackParams);

		if (isset($event['eventid'])) {
			$actions = get_event_actions_status($event['eventid']);
		}
		else {
			$actions = _('no data');
		}

		// unknown triggers
		$unknown = SPACE;
		if ($trigger['value_flags'] == TRIGGER_VALUE_FLAG_UNKNOWN) {
			$unknown = new CDiv(SPACE, 'status_icon iconunknown');
			$unknown->setHint($trigger['error'], '', 'on');
		}

		$trigger['hostname'] = $trigger['hosts'][0]['name'];
		$popupTable->addRow(array(
			get_node_name_by_elid($trigger['triggerid']),
			$trigger['hostname'],
			getSeverityCell($trigger['priority'], $trigger['description']),
			zbx_date2age($trigger['clock']),
			$unknown,
			$config['event_ack_enable'] ? $ack : null,
			$actions
		));
	}

	return $popupTable;
}
