<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/hosts.inc.php';
require_once dirname(__FILE__).'/include/events.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/discovery.inc.php';
require_once dirname(__FILE__).'/include/html.inc.php';

if (isset($_REQUEST['csv_export'])) {
	$CSV_EXPORT = true;
	$csvRows = array();

	$page['type'] = detect_page_type(PAGE_TYPE_CSV);
	$page['file'] = 'zbx_events_export.csv';

	require_once dirname(__FILE__).'/include/func.inc.php';
}
else {
	$CSV_EXPORT = false;

	$page['title'] = _('Latest events');
	$page['file'] = 'events.php';
	$page['hist_arg'] = array('groupid', 'hostid');
	$page['scripts'] = array('class.calendar.js', 'gtlc.js');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	if (PAGE_TYPE_HTML == $page['type']) {
		define('ZBX_PAGE_DO_REFRESH', 1);
	}
}

require_once dirname(__FILE__).'/include/page_header.php';


$allow_discovery = check_right_on_discovery(PERM_READ_ONLY);

$allowed_sources[] = EVENT_SOURCE_TRIGGERS;
if ($allow_discovery) {
	$allowed_sources[] = EVENT_SOURCE_DISCOVERY;
}

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'source'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	IN($allowed_sources),	null),
	'groupid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	null),
	'hostid'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	null),
	'triggerid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	null),

	'period'=>			array(T_ZBX_INT, O_OPT,	 null,	null, null),
	'dec'=>				array(T_ZBX_INT, O_OPT,	 null,	null, null),
	'inc'=>				array(T_ZBX_INT, O_OPT,	 null,	null, null),
	'left'=>			array(T_ZBX_INT, O_OPT,	 null,	null, null),
	'right'=>			array(T_ZBX_INT, O_OPT,	 null,	null, null),
	'stime'=>			array(T_ZBX_STR, O_OPT,	 null,	null, null),

	'load'=>			array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			null),
	'fullscreen'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		null),
// Export
	'csv_export'=>		array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
// filter
	'filter_rst'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	null),
	'filter_set'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	null),

	'showUnknown'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	null),
//ajax
	'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	null,			null),
	'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})&&("filter"=={favobj})'),
	'favstate'=>	array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})&&("filter"=={favobj})'),
	'favid'=>		array(T_ZBX_INT, O_OPT, P_ACT,  null,			null),
);

check_fields($fields);

/* AJAX */
if (isset($_REQUEST['favobj'])) {
	if ('filter' == $_REQUEST['favobj']) {
		CProfile::update('web.events.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
	}
	// saving fixed/dynamic setting to profile
	if ('timelinefixedperiod' == $_REQUEST['favobj']) {
		if (isset($_REQUEST['favid'])) {
			CProfile::update('web.events.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
		}
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}
//--------

// FILTER
if (isset($_REQUEST['filter_rst'])) {
	$_REQUEST['triggerid'] = 0;
	$_REQUEST['showUnknown'] = 0;
}

$source = get_request('triggerid') > 0 ? EVENT_SOURCE_TRIGGERS
		: get_request('source', CProfile::get('web.events.source', EVENT_SOURCE_TRIGGERS));

$_REQUEST['triggerid'] = get_request('triggerid', CProfile::get('web.events.filter.triggerid', 0));
$_REQUEST['showUnknown'] = get_request('showUnknown', CProfile::get('web.events.filter.showUnknown', 0));

// Change triggerId filter if change hostId
if (($_REQUEST['triggerid'] > 0) && isset($_REQUEST['hostid'])) {
	$hostid = get_request('hostid');
	$oldTriggers = API::Trigger()->get(array(
		'output' => array(
			'triggerid',
			'description',
			'expression'
		),
		'selectHosts' => array(
			'hostid',
			'host'
		),
		'selectItems' => API_OUTPUT_EXTEND,
		'selectFunctions' => API_OUTPUT_EXTEND,
		'triggerids' => $_REQUEST['triggerid']
	));

	foreach ($oldTriggers as $oldTrigger) {
		$_REQUEST['triggerid'] = 0;
		$oldTrigger['hosts'] = zbx_toHash($oldTrigger['hosts'], 'hostid');
		$oldTrigger['items'] = zbx_toHash($oldTrigger['items'], 'itemid');
		$oldTrigger['functions'] = zbx_toHash($oldTrigger['functions'], 'functionid');
		$oldExpression = triggerExpression($oldTrigger);

		if (isset($oldTrigger['hosts'][$hostid])) {
			break;
		}

		$newTriggers = API::Trigger()->get(array(
			'output' => array(
				'triggerid',
				'description',
				'expression'
			),
			'selectHosts' => array(
				'hostid',
				'host'
			),
			'selectItems' => API_OUTPUT_EXTEND,
			'selectFunctions' => API_OUTPUT_EXTEND,
			'filter' => array('description' => $oldTrigger['description']),
			'hostids' => $hostid
		));

		foreach ($newTriggers as $newTrigger) {
			if (count($oldTrigger['items']) != count($newTrigger['items'])) {
				continue;
			}
			$newTrigger['items'] = zbx_toHash($newTrigger['items'], 'itemid');
			$newTrigger['hosts'] = zbx_toHash($newTrigger['hosts'], 'hostid');
			$newTrigger['functions'] = zbx_toHash($newTrigger['functions'], 'functionid');

			$found = false;
			foreach ($newTrigger['functions'] as $fnum => $function) {
				foreach ($oldTrigger['functions'] as $ofnum => $oldFunction) {
					;
					// compare functions
					if (($function['function'] != $oldFunction['function']) || ($function['parameter'] != $oldFunction['parameter'])) {
						continue;
					}
					// compare that functions uses same item keys
					if ($newTrigger['items'][$function['itemid']]['key_'] != $oldTrigger['items'][$oldFunction['itemid']]['key_']) {
						continue;
					}
					// rewrite itemid so we could compare expressions
					// of two triggers form different hosts
					$newTrigger['functions'][$fnum]['itemid'] = $oldFunction['itemid'];
					$found = true;

					unset($oldTrigger['functions'][$ofnum]);
					break;
				}
				if (!$found) {
					break;
				}
			}
			if (!$found) {
				continue;
			}

			// if we found same trigger we overwriting it's hosts and items for expression compare
			$newTrigger['hosts'] = $oldTrigger['hosts'];
			$newTrigger['items'] = $oldTrigger['items'];

			$newExpression = triggerExpression($newTrigger);

			if (strcmp($oldExpression, $newExpression) == 0) {
				$_REQUEST['triggerid'] = $newTrigger['triggerid'];
				$_REQUEST['filter_set'] = 1;
				break;
			}
		}
	}
}
// --------

if (isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])) {
	CProfile::update('web.events.filter.triggerid', $_REQUEST['triggerid'], PROFILE_TYPE_ID);
	CProfile::update('web.events.filter.showUnknown', $_REQUEST['showUnknown'], PROFILE_TYPE_INT);
}
// --------------

CProfile::update('web.events.source', $source, PROFILE_TYPE_INT);

// page filter
if ($source == EVENT_SOURCE_TRIGGERS) {
	$options = array(
		'groups' => array(
			'monitored_hosts' => 1,
			'with_items' => 1
		),
		'hosts' => array(
			'monitored_hosts' => 1,
			'with_items' => 1
		),
		'triggers' => array(),
		'hostid' => get_request('hostid', null),
		'groupid' => get_request('groupid', null),
		'triggerid' => get_request('triggerid', null)
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = $pageFilter->hostid;
	if ($pageFilter->triggerid > 0) {
		$_REQUEST['triggerid'] = $pageFilter->triggerid;
	}
}

$events_wdgt = new CWidget();

// header
// allow CSV export button
$frmForm = new CForm();
if (isset($_REQUEST['source'])) {
	$frmForm->addVar('source', $_REQUEST['source'], 'source_csv');
}
if (isset($_REQUEST['stime'])) {
	$frmForm->addVar('stime', $_REQUEST['stime'], 'stime_csv');
}
if (isset($_REQUEST['period'])) {
	$frmForm->addVar('period', $_REQUEST['period'], 'period_csv');
}
$frmForm->addVar('page', getPageNumber(), 'page_csv');
if ($source == EVENT_SOURCE_TRIGGERS) {
	if ($_REQUEST['triggerid']) {
		$frmForm->addVar('triggerid', $_REQUEST['triggerid'], 'triggerid_csv');
	}
	else {
		$frmForm->addVar('groupid', $_REQUEST['groupid'], 'groupid_csv');
		$frmForm->addVar('hostid', $_REQUEST['hostid'], 'hostid_csv');
	}
}
$frmForm->addItem(new CSubmit('csv_export', _('Export to CSV')));

$events_wdgt->addPageHeader(
	_('HISTORY OF EVENTS').SPACE.'['.zbx_date2str(_('d M Y H:i:s')).']',
	array(
		$frmForm,
		SPACE,
		get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']))
	)
);

$r_form = new CForm('get');
$r_form->addVar('fullscreen', $_REQUEST['fullscreen']);
$r_form->addVar('stime', get_request('stime'));
$r_form->addVar('period', get_request('period'));

// add host and group filters to the form
if ($source == EVENT_SOURCE_TRIGGERS) {
	$r_form->addItem(array(
		_('Group').SPACE,
		$pageFilter->getGroupsCB(true)
	));
	$r_form->addItem(array(
		SPACE._('Host').SPACE,
		$pageFilter->getHostsCB(true)
	));
}

if ($allow_discovery) {
	$cmbSource = new CComboBox('source', $source, 'submit()');
	$cmbSource->addItem(EVENT_SOURCE_TRIGGERS, _('Trigger'));
	$cmbSource->addItem(EVENT_SOURCE_DISCOVERY, _('Discovery'));
	$r_form->addItem(array(
		SPACE._('Source').SPACE,
		$cmbSource
	));
}

$events_wdgt->addHeader(_('Events'), $r_form);
$events_wdgt->addHeaderRowNumber();

// FILTER {{{
$filterForm = null;

if (EVENT_SOURCE_TRIGGERS == $source) {
	$filterForm = new CFormTable(null, null, 'get'); //,'events.php?filter_set=1','POST',null,'sform');
	$filterForm->setAttribute('name', 'zbx_filter');
	$filterForm->setAttribute('id', 'zbx_filter');

	$filterForm->addVar('triggerid', get_request('triggerid'));
	$filterForm->addVar('stime', get_request('stime'));
	$filterForm->addVar('period', get_request('period'));

	if (isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid'] > 0)) {
		$dbTrigger = API::Trigger()->get(array(
			'triggerids' => $_REQUEST['triggerid'],
			'output' => array('description', 'expression'),
			'selectHosts' => array('name'),
			'preservekeys' => true,
			'expandDescription' => true
		));
		// check if trigger is accessible
		if ($dbTrigger) {
			$dbTrigger = reset($dbTrigger);
			$host = reset($dbTrigger['hosts']);
			$trigger = $host['name'].':'.$dbTrigger['description'];
		}
		else {
			$_REQUEST['triggerid'] = 0;
		}
	}
	if (!isset($trigger)) {
		$trigger = '';
	}

	$row = new CRow(array(
		new CCol(_('Trigger'), 'form_row_l'),
		new CCol(array(
			new CTextBox('trigger', $trigger, 96, 'yes'),
			new CButton("btn1", _('Select'), "return PopUp('popup.php?"."dstfrm=".$filterForm->GetName()."&dstfld1=triggerid&dstfld2=trigger"."&srctbl=triggers&srcfld1=triggerid&srcfld2=description&real_hosts=1');", 'T')
		), 'form_row_r')
	));
	$filterForm->addRow($row);

	$filterForm->addVar('showUnknown', $_REQUEST['showUnknown']);
	$unkcbx = new CCheckBox('hide_unk',
		$_REQUEST['showUnknown'],
			'javascript: create_var("'.$filterForm->GetName().'", "showUnknown", (this.checked?1:0), 0); ',
		'1');

	$filterForm->addRow(_('Show unknown events'), $unkcbx);

	$reset = new CButton('filter_rst', _('Reset'), 'javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

	$filterForm->addItemToBottomRow(new CSubmit('filter_set', _('Filter')));
	$filterForm->addItemToBottomRow($reset);
}

$events_wdgt->addFlicker($filterForm, CProfile::get('web.events.filter.state', 0));


$scroll_div = new CDiv();
$scroll_div->setAttribute('id', 'scrollbar_cntr');
$events_wdgt->addFlicker($scroll_div, CProfile::get('web.events.filter.state', 0));
// }}} FILTER


$table = new CTableInfo(_('No events defined.'));

// CHECK IF EVENTS EXISTS {{{
$options = array(
	'output' => API_OUTPUT_EXTEND,
	'sortfield' => 'eventid',
	'sortorder' => ZBX_SORT_UP,
	'nopermissions' => 1,
	'limit' => 1
);

if ($source == EVENT_SOURCE_DISCOVERY) {
	$options['source'] = EVENT_SOURCE_DISCOVERY;
}
else {
	if (isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid'] > 0)) {
		$options['triggerids'] = $_REQUEST['triggerid'];
	}
	$options['object'] = EVENT_OBJECT_TRIGGER;
	$options['filter'] = array('value_changed' => ($_REQUEST['showUnknown'] ? null : TRIGGER_VALUE_CHANGED_YES));
	$options['nodeids'] = get_current_nodeid();
}

$firstEvent = API::Event()->get($options);
// }}} CHECK IF EVENTS EXISTS

$_REQUEST['period'] = get_request('period', SEC_PER_WEEK);
$effectiveperiod = navigation_bar_calc();

$from = zbxDateToTime($_REQUEST['stime']);
$till = $from + $effectiveperiod;

$csv_disabled = true;

if (empty($firstEvent)) {
	$starttime = null;
	$events = array();
	$paging = getPagingLine($events);
}
else {
	$config = select_config();
	$firstEvent = reset($firstEvent);
	$starttime = $firstEvent['clock'];

	if ($source == EVENT_SOURCE_DISCOVERY) {
		$options = array(
			'source' => EVENT_SOURCE_DISCOVERY,
			'time_from' => $from,
			'time_till' => $till,
			'output' => API_OUTPUT_SHORTEN,
			'sortfield' => 'eventid',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => ($config['search_limit'] + 1)
		);
		$dsc_events = API::Event()->get($options);

		$paging = getPagingLine($dsc_events);

		$options = array(
			'source' => EVENT_SOURCE_DISCOVERY,
			'eventids' => zbx_objectValues($dsc_events, 'eventid'),
			'output' => API_OUTPUT_EXTEND
		);
		$dsc_events = API::Event()->get($options);
		order_result($dsc_events, 'eventid', ZBX_SORT_DOWN);

		// do we need to make CVS export button enabled?
		$csv_disabled = zbx_empty($dsc_events);

		$objectids = array();
		foreach ($dsc_events as $enum => $event_data) {
			$objectids[$event_data['objectid']] = $event_data['objectid'];
		}

// OBJECT DHOST
		$dhosts = array();
		$sql = 'SELECT s.dserviceid,s.dhostid,s.ip,s.dns '.
				' FROM dservices s '.
				' WHERE '.dbConditionInt('s.dhostid', $objectids);
		$res = DBselect($sql);
		while ($dservices = DBfetch($res)) {
			$dhosts[$dservices['dhostid']] = $dservices;
		}

// OBJECT DSERVICE
		$dservices = array();
		$sql = 'SELECT s.dserviceid,s.ip,s.dns,s.type,s.port '.
				' FROM dservices s '.
				' WHERE '.dbConditionInt('s.dserviceid', $objectids);
		$res = DBselect($sql);
		while ($dservice = DBfetch($res)) {
			$dservices[$dservice['dserviceid']] = $dservice;
		}

// TABLE
		$table->setHeader(array(
			_('Time'),
			_('IP'),
			_('DNS'),
			_('Description'),
			_('Status')
		));

		if ($CSV_EXPORT) {
			$csvRows[] = array(
				_('Time'),
				_('IP'),
				_('DNS'),
				_('Description'),
				_('Status')
			);
		}

		foreach ($dsc_events as $event_data) {
			switch ($event_data['object']) {
				case EVENT_OBJECT_DHOST:
					if (isset($dhosts[$event_data['objectid']])) {
						$event_data['object_data'] = $dhosts[$event_data['objectid']];
					}
					else {
						$event_data['object_data']['ip'] = _('Unknown');
						$event_data['object_data']['dns'] = _('Unknown');
					}
					$event_data['description'] = _('Host');
					break;
				case EVENT_OBJECT_DSERVICE:
					if (isset($dservices[$event_data['objectid']])) {
						$event_data['object_data'] = $dservices[$event_data['objectid']];
					}
					else {
						$event_data['object_data']['ip'] = _('Unknown');
						$event_data['object_data']['dns'] = _('Unknown');
						$event_data['object_data']['type'] = _('Unknown');
						$event_data['object_data']['port'] = _('Unknown');
					}

					$event_data['description'] = _('Service').': '.
							discovery_check_type2str($event_data['object_data']['type']).
							discovery_port2str($event_data['object_data']['type'], $event_data['object_data']['port']);

					break;
				default:
					continue;
			}

			if (!isset($event_data['object_data'])) {
				continue;
			}
			$table->addRow(array(
				zbx_date2str(EVENTS_DISCOVERY_TIME_FORMAT, $event_data['clock']),
				$event_data['object_data']['ip'],
				zbx_empty($event_data['object_data']['dns']) ? SPACE : $event_data['object_data']['dns'],
				$event_data['description'],
				new CCol(discovery_value($event_data['value']), discovery_value_style($event_data['value']))
			));

			if ($CSV_EXPORT) {
				$csvRows[] = array(
					zbx_date2str(EVENTS_DISCOVERY_TIME_FORMAT, $event_data['clock']),
					$event_data['object_data']['ip'],
					$event_data['object_data']['dns'],
					$event_data['description'],
					discovery_value($event_data['value'])
				);
			}


		}
	}
	// source not discovery i.e. Trigger
	else {
		$table->setHeader(array(
			_('Time'),
			is_show_all_nodes() ? _('Node') : null,
			($_REQUEST['hostid'] == 0) ? _('Host') : null,
			_('Description'),
			_('Status'),
			_('Severity'),
			_('Duration'),
			($config['event_ack_enable']) ? _('Ack') : null,
			_('Actions')
		));

		if ($CSV_EXPORT) {
			$csvRows[] = array(
				_('Time'),
				is_show_all_nodes() ? _('Node') : null,
				($_REQUEST['hostid'] == 0) ? _('Host') : null,
				_('Description'),
				_('Status'),
				_('Severity'),
				_('Duration'),
				($config['event_ack_enable']) ? _('Ack') : null,
				_('Actions')
			);
		}

		if ($pageFilter->hostsSelected) {
			$options = array(
				'nodeids' => get_current_nodeid(),
				'filter' => array(
					'value_changed' => TRIGGER_VALUE_CHANGED_YES,
					'object' => EVENT_OBJECT_TRIGGER,
				),
				'time_from' => $from,
				'time_till' => $till,
				'output' => API_OUTPUT_SHORTEN,
				'sortfield' => 'eventid',
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => ($config['search_limit'] + 1)
			);

			if ($_REQUEST['showUnknown']) {
				$options['filter']['value_changed'] = null;
			}

			// trigger options
			$trigOpt = array(
				'nodeids' => get_current_nodeid(),
				'output' => API_OUTPUT_SHORTEN
			);

			if (isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid'] > 0)) {
				$trigOpt['triggerids'] = $_REQUEST['triggerid'];
			}
			else if ($pageFilter->hostid > 0) {
				$trigOpt['hostids'] = $pageFilter->hostid;
			}
			else if ($pageFilter->groupid > 0) {
				$trigOpt['groupids'] = $pageFilter->groupid;
			}

			$trigOpt['monitored'] = true;

			$triggers = API::Trigger()->get($trigOpt);
			$options['triggerids'] = zbx_objectValues($triggers, 'triggerid');

			// query event with short data
			$events = API::Event()->get($options);

			// get pagging
			$paging = getPagingLine($events);

			// query event with extend data
			$options = array(
				'nodeids' => get_current_nodeid(),
				'eventids' => zbx_objectValues($events, 'eventid'),
				'output' => API_OUTPUT_EXTEND,
				'select_acknowledges' => API_OUTPUT_COUNT,
				'sortfield' => 'eventid',
				'sortorder' => ZBX_SORT_DOWN,
				'nopermissions' => 1
			);
			$events = API::Event()->get($options);

			$csv_disabled = zbx_empty($events);

			$triggersOptions = array(
				'triggerids' => zbx_objectValues($events, 'objectid'),
				'selectHosts' => array('hostid'),
				'selectItems' => array('name', 'value_type', 'key_'),
				'output' => array('description', 'expression', 'priority')
			);
			$triggers = API::Trigger()->get($triggersOptions);
			$triggers = zbx_toHash($triggers, 'triggerid');

			// fetch hosts
			$hosts = array();
			foreach ($triggers as $trigger) {
				$hosts[] = reset($trigger['hosts']);
			}
			$hostids = zbx_objectValues($hosts, 'hostid');
			$hosts = API::Host()->get(array(
				'output' => array(
					'name',
					'hostid'
				),
				'hostids' => $hostids,
				'selectScreens' => API_OUTPUT_COUNT,
				'selectInventory' => array('hostid'),
				'preservekeys' => true
			));

			// fetch scripts for the host JS menu
			if ($_REQUEST['hostid'] == 0) {
				$hostScripts = API::Script()->getScriptsByHosts($hostids);
			}

			// actions
			$actions = getEventActionsStatus(zbx_objectValues($events, 'eventid'));

			// events
			foreach ($events as $enum => $event) {
				$trigger = $triggers[$event['objectid']];
				$host = reset($trigger['hosts']);
				$host = $hosts[$host['hostid']];

				$items = array();
				foreach ($trigger['items'] as $item) {
					$i = array();
					$i['itemid'] = $item['itemid'];
					$i['value_type'] = $item['value_type']; // ZBX-3059: So it would be possible to show different caption for history for chars and numbers (KB)
					$i['action'] = str_in_array($item['value_type'], array(
						ITEM_VALUE_TYPE_FLOAT,
						ITEM_VALUE_TYPE_UINT64
					)) ? 'showgraph' : 'showvalues';
					$i['name'] = itemName($item);
					$items[] = $i;
				}

				$ack = getEventAckState($event, true);

				$description = CEventHelper::expandDescription(zbx_array_merge($trigger, array(
					'clock' => $event['clock'],
					'ns' => $event['ns']
				)));

				$tr_desc = new CSpan($description, 'pointer');
				$tr_desc->addAction('onclick', "create_mon_trigger_menu(event, ".
						" [{'triggerid': '".$trigger['triggerid']."', 'lastchange': '".$event['clock']."'}],".
						zbx_jsvalue($items, true).");");

				// duration
				if ($nextEvent = get_next_event($event, $events, $_REQUEST['showUnknown'])) {
					$event['duration'] = zbx_date2age($event['clock'], $nextEvent['clock']);
				}
				else {
					$event['duration'] = zbx_date2age($event['clock']);
				}

				$statusSpan = new CSpan(trigger_value2str($event['value']));
				// add colors and blinking to span depending on configuration and trigger parameters
				addTriggerValueStyle(
					$statusSpan,
					$event['value'],
					$event['clock'],
					$event['acknowledged']
				);

				// host JS menu link
				$hostSpan = null;
				if ($_REQUEST['hostid'] == 0) {
					$hostSpan = new CSpan($host['name'], 'link_menu menu-host');
					$scripts = $hostScripts[$host['hostid']];
					$hostSpan->setAttribute('data-menu', hostMenuData($host, $scripts));
				}

				// action
				$action = isset($actions[$event['eventid']]) ? $actions[$event['eventid']] : ' - ';

				$table->addRow(array(
					new CLink(zbx_date2str(EVENTS_ACTION_TIME_FORMAT, $event['clock']),
							'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid'],
						'action'
					),
					is_show_all_nodes() ? get_node_name_by_elid($event['objectid']) : null,
					$hostSpan,
					new CSpan($tr_desc, 'link_menu'),
					$statusSpan,
					getSeverityCell($trigger['priority'], null, !$event['value']),
					$event['duration'],
					($config['event_ack_enable']) ? $ack : null,
					$action
				));

				if ($CSV_EXPORT) {
					$csvRows[] = array(
						zbx_date2str(EVENTS_ACTION_TIME_FORMAT, $event['clock']),
						is_show_all_nodes() ? get_node_name_by_elid($event['objectid']) : null,
						$_REQUEST['hostid'] == 0 ? $host['name'] : null,
						$description,
						trigger_value2str($event['value']),
						getSeverityCaption($trigger['priority']),
						$event['duration'],
						($config['event_ack_enable']) ? ($event['acknowledges'] ? _('Yes') : _('No')) : null,
						// ($config['event_ack_enable'])? $ack :NULL,
						strip_tags((string) $action)
					);
				}
			}
		}
		else {
			$events = array();
			$paging = getPagingLine($events);
		}
	}

	if ($CSV_EXPORT) {
		print(zbx_toCSV($csvRows));
		exit();
	}

	$table = array(
		$paging,
		$table,
		$paging
	);
}

$events_wdgt->addItem($table);

// NAV BAR
$timeline = array(
	'period' => $effectiveperiod,
	'starttime' => date('YmdHis', $starttime),
	'usertime' => date('YmdHis', $till)
);

$dom_graph_id = 'scroll_events_id';
$objData = array(
	'id' => 'timeline_1',
	'loadSBox' => 0,
	'loadImage' => 0,
	'loadScroll' => 1,
	'dynamic' => 0,
	'mainObject' => 1,
	'periodFixed' => CProfile::get('web.events.timelinefixed', 1),
	'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
);

zbx_add_post_js('jqBlink.blink();');
zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
zbx_add_post_js('timeControl.processObjects();');

// js templates
require_once dirname(__FILE__).'/include/views/js/general.script.confirm.js.php';

$events_wdgt->show();
if ($csv_disabled) {
	zbx_add_post_js('document.getElementById(\'csv_export\').disabled=true;');
}


require_once dirname(__FILE__).'/include/page_footer.php';

