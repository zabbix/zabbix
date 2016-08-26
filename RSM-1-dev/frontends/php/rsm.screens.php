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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Screens');
$page['file'] = 'rsm.screens.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'export' =>			array(T_ZBX_INT, O_OPT,		P_ACT,	null,			null),
	// filter
	'filter_set' =>		array(T_ZBX_STR, O_OPT,		P_ACT,	null,			null),
	'tld' =>			array(T_ZBX_STR, O_OPT,		null,	null,			null),
	'filter_year' =>	array(T_ZBX_STR, O_OPT,		null,	null,			null),
	'filter_month' =>	array(T_ZBX_STR, O_OPT,		null,	null,			null),
	'item_key' =>		array(T_ZBX_STR, O_OPT,		P_SYS,	DB_ID,			null),
	'type' =>			array(T_ZBX_INT, O_OPT,		null,	IN('0,1,2'),	null),
	// ajax
	'favobj' =>			array(T_ZBX_STR, O_OPT,		P_ACT,	null,			null),
	'favref' =>			array(T_ZBX_STR, O_OPT,		P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	'favstate' =>		array(T_ZBX_INT, O_OPT,		P_ACT,  NOT_EMPTY,		'isset({favobj})&&("filter"=={favobj})')
);

check_fields($fields);

if (isset($_REQUEST['favobj'])) {
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.rsm.screens.filter.state', get_request('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

/*
 * Filter
 */
if (!array_key_exists('tld', $_REQUEST) || !array_key_exists('filter_year', $_REQUEST)
		|| !array_key_exists('filter_month', $_REQUEST) || !array_key_exists('type', $_REQUEST)
		|| !array_key_exists('item_key', $_REQUEST)) {
	show_error_message(_('Incorrect input parameters.'));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$year = date('Y', time());
$month = date('m', time());

if ($month == 1) {
	$year--;
	$month = 12;
}
else {
	$month--;
}

$data = array(
	'filter_year' => get_request('filter_year'),
	'filter_month' => get_request('filter_month'),
	'type' => get_request('type'),
	'item_key' => get_request('item_key')
);

if ($year < $data['filter_year'] || ($year == $data['filter_year'] && $month < $data['filter_month'])) {
	show_error_message(_('Incorrect report period.'));
}

$tld = API::Host()->get(array(
	'tlds' => true,
	'output' => array('hostid', 'host', 'name'),
	'filter' => array(
		'name' => get_request('tld')
	)
));

if (!$tld) {
	show_error_message(_s('No permissions to referred TLD "%1$s" or it does not exist!', get_request('tld')));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$data['tld'] = reset($tld);

// Item validation.
$item = API::Item()->get(array(
	'output' => array('itemid', 'key_', 'value_type'),
	'hostids' => $data['tld']['hostid'],
	'filter' => array(
		'key_' => get_request('item_key')
	)
));

if (!$item) {
	show_error_message(_s('Item with key "%1$s" not exist on TLD!', get_request('item_key')));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$data['item'] = reset($item);

if (in_array($data['filter_month'], array(1, 3, 5, 7, 8, 10, 12))) {
	$period = 3600 * 24 * 31;
}
if (in_array($data['filter_month'], array(4, 6, 9, 11))) {
	$period = 3600 * 24 * 30;
}
elseif ($data['filter_month'] == 2 && $data['filter_year'] % 4 == 0) {
	$period = 3600 * 24 * 29;
}
else {
	$period = 3600 * 24 * 28;
}

$start_time = mktime(0, 0, 0, $data['filter_month'], 1, $data['filter_year']);
$end_time = mktime(0, 0, 0, $data['filter_month'], 1, $data['filter_year']) + $period - 1;

$stime = date('YmdHis', $start_time);

$curtime = time();

// Get data by item key and type
switch ($data['item']['key_']) {
	case RSM_SLV_DNS_DOWNTIME:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => DNS_SERVICE_AVAILABILITY_GRAPH_1),
				'limit' => 1
			));

			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(new CDiv(new CImg($src), 'center')));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('TLD')
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value']
				));
			}

			$data['screen'] = new CDiv(array($table));
		}
		break;

	case RSM_SLV_DNS_TCP_RTT_PFAILED:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1 || get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_2) {
			if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
				$graph_name = TCP_DNS_RESOLUTION_RTT_TCP_GRAPH_1;
			}
			else {
				$graph_name = TCP_DNS_RESOLUTION_RTT_TCP_GRAPH_2;
			}

			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => $graph_name),
				'limit' => 1
			));

			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(
				new CDiv(new CImg($src), 'center')
			));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('SLV'),
				_('Maximum number of expected tests'),
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value'],
					'-'
				));
			}

			$data['screen'] = $table;
		}
		break;

	case RSM_SLV_DNS_UDP_RTT_PFAILED:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1 || get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_2) {
			if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
				$graph_name = UDP_DNS_RESOLUTION_RTT_UDP_GRAPH_1;
			}
			else {
				$graph_name = UDP_DNS_RESOLUTION_RTT_UDP_GRAPH_2;
			}

			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => $graph_name),
				'limit' => 1
			));

			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(
				new CDiv(new CImg($src), 'center')
			));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('SLV'),
				_('Maximum number of expected tests'),
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value'],
					'-'
				));
			}

			$data['screen'] = $table;
		}
		break;

	case RSM_SLV_DNS_UDP_UPD_PFAILED:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1 || get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_2) {
			if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
				$graph_name = DNS_UPDATE_TIME_GRAPH_1;
			}
			else {
				$graph_name = DNS_UPDATE_TIME_GRAPH_2;
			}

			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => $graph_name),
				'limit' => 1
			));

			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(
				new CDiv(new CImg($src), 'center')
			));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('SLV'),
				_('Maximum number of expected tests'),
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value'],
					'-'
				));
			}

			$data['screen'] = $table;
		}
		break;

	case RSM_SLV_RDDS_DOWNTIME:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1 || get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_2) {
			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => RDDS_AVAILABILITY_GRAPH_1),
				'limit' => 1
			));

			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(
				new CDiv(new CImg($src), 'center')
			));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('SLV'),
				_('Maximum number of expected tests'),
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value'],
					'-'
				));
			}

			$data['screen'] = $table;
		}
		break;

	case 0:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
			if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
				$graph_name = RDDS_QUERY_RTT_GRAPH_1;
			}
			else {
				$graph_name = RDDS_QUERY_RTT_GRAPH_2;
			}

			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => $graph_name),
				'limit' => 1
			));
			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(
				new CDiv(new CImg($src), 'center')
			));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('SLV'),
				_('Maximum number of expected tests'),
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value'],
					'-'
				));
			}

			$data['screen'] = $table;
		}
		break;

	case RSM_SLV_RDDS43_UPD_PFAILED:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1 || get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_2) {
			if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
				$graph_name = RDDS_QUERY_RTT_GRAPH_1;
			}
			elseif (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_2) {
				$graph_name = RDDS_QUERY_RTT_GRAPH_2;
			}
			elseif (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_3) {
				$graph_name = RDDS_80_QUERY_RTT_GRAPH_1;
			}
			elseif (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_5) {
				$graph_name = RDDS_UPDATE_TIME_GRAPH_1;
			}
			else {
				$graph_name = RDDS_UPDATE_TIME_GRAPH_2;
			}

			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => $graph_name),
				'limit' => 1
			));

			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(
				new CDiv(new CImg($src), 'center')
			));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('SLV'),
				_('Maximum number of expected tests'),
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value'],
					'-'
				));
			}

			$data['screen'] = $table;
		}
		break;

	case RSM_SLV_EPP_DOWNTIME:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => EPP_SERVICE_AVAILABILITY_GRAPH_1),
				'limit' => 1
			));

			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(
				new CDiv(new CImg($src), 'center')
			));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('SLV'),
				_('Maximum number of expected tests'),
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value'],
					'-'
				));
			}

			$data['screen'] = $table;
		}
		break;

	case RSM_SLV_EPP_RTT_LOGIN_PFAILED:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1 || get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_2) {
			if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
				$graph_name = EPP_SESSION_COMMAND_RTT_GRAPH_1;
			}
			else {
				$graph_name = EPP_SESSION_COMMAND_RTT_GRAPH_2;
			}

			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => $graph_name),
				'limit' => 1
			));

			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(
				new CDiv(new CImg($src), 'center')
			));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('SLV'),
				_('Maximum number of expected tests'),
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value'],
					'-'
				));
			}

			$data['screen'] = $table;
		}
		break;

	case RSM_SLV_EPP_RTT_INFO_PFAILED:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
			if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
				$graph_name = EPP_TRANSFORM_COMMAND_RTT_GRAPH_1;
			}
			else {
				$graph_name = EPP_TRANSFORM_COMMAND_RTT_GRAPH_2;
			}

			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => $graph_name),
				'limit' => 1
			));

			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(
				new CDiv(new CImg($src), 'center')
			));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('SLV'),
				_('Maximum number of expected tests'),
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value'],
					'-'
				));
			}

			$data['screen'] = $table;
		}
		break;

	case RSM_SLV_EPP_RTT_UPDATE_PFAILED:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1 || get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_2) {
			if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
				$graph_name = EPP_QUERY_COMMAND_RTT_GRAPH_1;
			}
			else {
				$graph_name = EPP_QUERY_COMMAND_RTT_GRAPH_2;
			}

			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => $graph_name),
				'limit' => 1
			));

			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(
				new CDiv(new CImg($src), 'center')
			));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('SLV'),
				_('Maximum number of expected tests'),
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value'],
					'-'
				));
			}

			$data['screen'] = $table;
		}
		break;

	// NS items
	default:
		if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1 || get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_2) {
			if (get_request('type') == RSM_SLA_SCREEN_TYPE_GRAPH_1) {
				$graph_name = DNS_NS_AVAILABILITY_GRAPH_1;
			}
			else {
				$graph_name = DNS_NS_AVAILABILITY_GRAPH_2;
			}

			$graphs = API::Graph()->get(array(
				'output' => array('graphid'),
				'hostids' => $data['tld']['hostid'],
				'filter' => array('name' => $graph_name),
				'limit' => 1
			));

			$graph = reset($graphs);

			$src ='chart2.php?graphid='.$graph['graphid'].'&period='.$period.'&stime='.$stime.'&curtime='.$curtime;

			$data['screen'] = new CDiv(array(
				new CDiv(new CImg($src), 'center')
			));
		}
		else {
			$table = new CTableInfo(_('No date found.'));
			$table->setHeader(array(
				_('Date'),
				_('SLV'),
				_('Maximum number of expected tests'),
			));

			$item_values = API::History()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'itemids' => $data['item']['itemid'],
				'time_from' => $start_time,
				'time_till' => $end_time,
				'history' => $data['item']['value_type']
			));

			foreach ($item_values as $item_value) {
				$table->addRow(array(
					date('d.m.Y H:i', $item_value['clock']),
					$item_value['value'],
					'-'
				));
			}

			$data['screen'] = $table;
		}
}
$rsmView = new CView('rsm.screens.view', $data);
$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
