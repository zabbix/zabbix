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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/reports.inc.php';
require_once dirname(__FILE__).'/include/graphs.inc.php';

$page['file'] = 'chart_bar.php';
$page['type'] = PAGE_TYPE_IMAGE;

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'config' =>				array(T_ZBX_INT, O_OPT,	P_SYS,			IN('0,1,2,3'),	null),
	'hostids' =>			array(T_ZBX_INT, O_OPT,	P_SYS,			DB_ID,			null),
	'groupids' =>			array(T_ZBX_INT, O_OPT,	P_SYS,			DB_ID,			null),
	'items' =>				array(T_ZBX_STR, O_OPT,	P_SYS,			null,			null),
	'title' =>				array(T_ZBX_STR, O_OPT, null,			null,			null),
	'xlabel' =>				array(T_ZBX_STR, O_OPT, null,			null,			null),
	'ylabel' =>				array(T_ZBX_STR, O_OPT, null,			null,			null),
	'showlegend' =>			array(T_ZBX_STR, O_OPT, null,			null,			null),
	'sorttype' =>			array(T_ZBX_INT, O_OPT,	null,			null,			null),
	'scaletype' =>			array(T_ZBX_INT, O_OPT,	null,			null,			null),
	'avgperiod' =>			array(T_ZBX_INT, O_OPT,	null,			null,			null),
	'periods' =>			array(T_ZBX_STR, O_OPT,	null,			null,			null),
	'report_timesince' =>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,			null),
	'report_timetill' =>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,			null),
	'palette'=>				array(T_ZBX_INT, O_OPT,	null,			null,			null),
	'palettetype'=>			array(T_ZBX_INT, O_OPT,	null,			null,			null),
);

check_fields($fields);

// validate permissions
$items = getRequest('items', array());
$itemIds = zbx_objectValues($_REQUEST['items'], 'itemid');
$itemsCount = API::Item()->get(array(
	'itemids' => $itemIds,
	'webitems' => true,
	'countOutput' => true
));
if (count($itemIds) != $itemsCount) {
	access_deny();
}

$config = getRequest('config', 1);
$title = getRequest('title', _('Report'));
$xlabel = getRequest('xlabel', 'X');
$ylabel = getRequest('ylabel', 'Y');

$showlegend = getRequest('showlegend', 0);
$sorttype = getRequest('sorttype', 0);

if ($config == 1) {
	$scaletype = getRequest('scaletype', TIMEPERIOD_TYPE_WEEKLY);

	$timesince = getRequest('report_timesince', time() - SEC_PER_DAY);
	$timetill = getRequest('report_timetill', time());

	$str_since['hour'] = date('H', $timesince);
	$str_since['day'] = date('d', $timesince);
	$str_since['weekday'] = date('w', $timesince);
	if ($str_since['weekday'] == 0) {
		$str_since['weekday'] = 7;
	}

	$str_since['mon'] = date('m', $timesince);
	$str_since['year'] = date('Y', $timesince);

	$str_till['hour'] = date('H', $timetill);
	$str_till['day'] = date('d', $timetill);
	$str_till['weekday'] = date('w', $timetill);
	if ($str_till['weekday'] == 0) {
		$str_till['weekday'] = 7;
	}

	$str_till['mon'] = date('m', $timetill);
	$str_till['year'] = date('Y', $timetill);

	switch ($scaletype) {
		case TIMEPERIOD_TYPE_HOURLY:
			$scaleperiod = SEC_PER_HOUR;
			$str = $str_since['year'].'-'.$str_since['mon'].'-'.$str_since['day'].' '.$str_since['hour'].':00:00';
			$timesince = strtotime($str);

			$str = $str_till['year'].'-'.$str_till['mon'].'-'.$str_till['day'].' '.$str_till['hour'].':00:00';
			$timetill = strtotime($str) + $scaleperiod;
			break;
		case TIMEPERIOD_TYPE_DAILY:
			$scaleperiod = SEC_PER_DAY;
			$str = $str_since['year'].'-'.$str_since['mon'].'-'.$str_since['day'].' 00:00:00';
			$timesince = strtotime($str);

			$str = $str_till['year'].'-'.$str_till['mon'].'-'.$str_till['day'].' 00:00:00';
			$timetill = strtotime($str) + $scaleperiod;
			break;
		case TIMEPERIOD_TYPE_WEEKLY:
			$scaleperiod = SEC_PER_WEEK;
			$str = $str_since['year'].'-'.$str_since['mon'].'-'.$str_since['day'].' 00:00:00';
			$timesince = strtotime($str);
			$timesince -= ($str_since['weekday'] - 1) * SEC_PER_DAY;

			$str = $str_till['year'].'-'.$str_till['mon'].'-'.$str_till['day'].' 00:00:00';
			$timetill = strtotime($str);
			$timetill -= ($str_till['weekday'] - 1) * SEC_PER_DAY;

			$timetill += $scaleperiod;
			break;
		case TIMEPERIOD_TYPE_MONTHLY:
			$scaleperiod = SEC_PER_MONTH;
			$str = $str_since['year'].'-'.$str_since['mon'].'-01 00:00:00';
			$timesince = strtotime($str);

			$str = $str_till['year'].'-'.$str_till['mon'].'-01 00:00:00';
			$timetill = strtotime($str);
			$timetill = strtotime('+1 month', $timetill);
			break;
		case TIMEPERIOD_TYPE_YEARLY:
			$scaleperiod = SEC_PER_YEAR;
			$str = $str_since['year'].'-01-01 00:00:00';
			$timesince = strtotime($str);

			$str = $str_till['year'].'-01-01 00:00:00';
			$timetill = strtotime($str);
			$timetill = strtotime('+1 year', $timetill);
			break;
	}

	$p = $timetill - $timesince;				// graph size in time
	$z = $p - ($timesince % $p);				// graphsize - mod(from_time,p) for Oracle...
	$x = round($p / $scaleperiod);				// graph size in px
	$calc_field = 'floor('.$x.'*'.zbx_sql_mod(zbx_dbcast_2bigint('clock').'+'.$z, $p).'/('.$p.'))';	// required for 'group by' support of Oracle

	$period_step = $scaleperiod;

	$graph = new CBarGraphDraw(GRAPH_TYPE_COLUMN);
	$graph->setHeader($title);

	$graph_data['colors'] = array();
	$graph_data['legend'] = array();
	$db_values = array();

	foreach ($items as $item) {
		$itemid = $item['itemid'];
		$item_data = &$db_values[$itemid];

		$graph_data['legend'][] = $item['caption'];

		$sql_arr = array();
		array_push($sql_arr,
			'SELECT itemid,'.$calc_field.' as i,'.
				' sum(num) as count,avg(value_avg) as avg,min(value_min) as min,'.
				' max(value_max) as max,max(clock) as clock'.
			' FROM trends '.
			' WHERE itemid='.zbx_dbstr($itemid).
				' AND clock>='.zbx_dbstr($timesince).
				' AND clock<='.zbx_dbstr($timetill).
			' GROUP BY itemid,'.$calc_field.
			' ORDER BY clock ASC'
			,

			'SELECT itemid,'.$calc_field.' as i,'.
				' sum(num) as count,avg(value_avg) as avg,min(value_min) as min,'.
				' max(value_max) as max,max(clock) as clock'.
			' FROM trends_uint '.
			' WHERE itemid='.zbx_dbstr($itemid).
				' AND clock>='.zbx_dbstr($timesince).
				' AND clock<='.zbx_dbstr($timetill).
			' GROUP BY itemid,'.$calc_field.
			' ORDER BY clock ASC'
			);

		foreach($sql_arr as $id => $sql){
			$result = DBselect($sql);

			$i = 0;
			$start = 0;
			$end = $timesince;
			while ($end < $timetill) {
				switch ($scaletype) {
					case TIMEPERIOD_TYPE_HOURLY:
					case TIMEPERIOD_TYPE_DAILY:
					case TIMEPERIOD_TYPE_WEEKLY:
						$start = $end;
						$end = $start + $scaleperiod;
						break;
					case TIMEPERIOD_TYPE_MONTHLY:
						$start = $end;
						$str_start['mon'] = date('m', $start);
						$str_start['year'] = date('Y', $start);
						$str = $str_start['year'].'-'.$str_start['mon'].'-01 00:00:00';
						$end = strtotime($str);
						$end = strtotime('+1 month', $end);
						break;
					case TIMEPERIOD_TYPE_YEARLY:
						$start = $end;
						$str_start['year'] = date('Y', $start);
						$str = $str_start['year'].'-01-01 00:00:00';
						$end = strtotime($str);
						$end = strtotime('+1 year', $end);
						break;
				}

				if (!isset($row) || ($row['clock']<$start)) {
					$row = DBfetch($result);
				}

				if (isset($row) && $row && ($row['clock'] >= $start) && ($row['clock'] < $end)) {
					$item_data['count'][$i]	= $row['count'];
					$item_data['min'][$i] = $row['min'];
					$item_data['avg'][$i] = $row['avg'];
					$item_data['max'][$i] = $row['max'];
					$item_data['clock'][$i] = $start;
					$item_data['type'][$i] = true;
				}
				else {
					if (isset($item_data['type'][$i]) && $item_data['type'][$i]) {
						continue;
					}

					$item_data['count'][$i]	= 0;
					$item_data['min'][$i] = 0;
					$item_data['avg'][$i] = 0;
					$item_data['max'][$i] = 0;
					$item_data['clock'][$i]	= $start;
					$item_data['type'][$i]	= false;
				}
				$i++;
			}
			unset($row);
		}

		switch ($item['calc_fnc']) {
			case 0:
				$tmp_value = $item_data['count'];
				break;
			case CALC_FNC_MIN:
				$tmp_value = $item_data['min'];
				break;
			case CALC_FNC_AVG:
				$tmp_value = $item_data['avg'];
				break;
			case CALC_FNC_MAX:
				$tmp_value = $item_data['max'];
				break;
		}

		$graph->addSeries($tmp_value, $item['axisside']);

		$graph_data['colors'][] = $item['color'];

		if ($db_item = get_item_by_itemid($item['itemid'])) {
			$graph->setUnits($db_item['units'], $item['axisside']);
			$graph->setSideValueType($db_item['value_type'], $item['axisside']);
		}

		if (!isset($graph_data['captions'])) {
			$date_caption = ($scaletype == TIMEPERIOD_TYPE_HOURLY) ? DATE_TIME_FORMAT : DATE_FORMAT;

			$graph_data['captions'] = array();
			foreach ($item_data['clock'] as $id => $clock) {
				$graph_data['captions'][$id] = zbx_date2str($date_caption, $clock);
			}
		}
	}
}
elseif ($config == 2) {
	$periods = getRequest('periods', array());

	$graph = new CBarGraphDraw(GRAPH_TYPE_COLUMN);
	$graph->setHeader('REPORT 1');

	$graph_data = array();

	$graph_data['colors'] = array();
	$graph_data['captions'] = array();
	$graph_data['values'] = array();
	$graph_data['legend'] = array();

	foreach ($periods as $pid => $period) {
		$graph_data['colors'][] = $period['color'];
		$graph_data['legend'][] = $period['caption'];

		$db_values[$pid] = array();
		foreach ($items as $item) {
			$itemid = $item['itemid'];
			$item_data = &$db_values[$pid][$itemid];

			$sql = 'SELECT itemid, sum(num) as count,avg(value_avg) as avg,min(value_min) as min,'.
					' max(value_max) as max,max(clock) as clock'.
				' FROM trends '.
				' WHERE itemid='.zbx_dbstr($itemid).
					' AND clock>='.zbx_dbstr($period['report_timesince']).
					' AND clock<='.zbx_dbstr($period['report_timetill']).
				' GROUP BY itemid';
			$result = DBselect($sql);
			if ($row = DBfetch($result)) {
				$item_data['count'] = $row['count'];
				$item_data['min'] = $row['min'];
				$item_data['avg'] = $row['avg'];
				$item_data['max'] = $row['max'];
				$item_data['clock'] = $row['clock'];
			}

			$sql = 'SELECT itemid, sum(num) as count,avg(value_avg) as avg,min(value_min) as min,'.
					' max(value_max) as max,max(clock) as clock'.
				' FROM trends_uint '.
				' WHERE itemid='.zbx_dbstr($itemid).
					' AND clock>='.zbx_dbstr($period['report_timesince']).
					' AND clock<='.zbx_dbstr($period['report_timetill']).
				' GROUP BY itemid';
			$result = DBselect($sql);
			if ($row = DBfetch($result)) {
				if (!empty($item_data)) {
					$item_data['count']	+= $row['count'];
					$item_data['min'] = min($item_data['count'], $row['min']);
					$item_data['avg'] = ($item_data['count'] + $row['avg']) / 2;
					$item_data['max'] = max($item_data['count'], $row['max']);
					$item_data['clock'] = max($item_data['count'], $row['clock']);
				}
				else{
					$item_data['count'] = $row['count'];
					$item_data['min'] = $row['min'];
					$item_data['avg'] = $row['avg'];
					$item_data['max'] = $row['max'];
					$item_data['clock']	= $row['clock'];
				}
			}

// fixes bug #21788, due to Zend casting the array key as a numeric and then they are reassigned
			$itemid = "0$itemid";

			switch ($item['calc_fnc']) {
				case 0:
					$graph_data['values'][$itemid] = $item_data['count'];
					break;
				case CALC_FNC_MIN:
					$graph_data['values'][$itemid] = $item_data['min'];
					break;
				case CALC_FNC_AVG:
					$graph_data['values'][$itemid] = $item_data['avg'];
					break;
				case CALC_FNC_MAX:
					$graph_data['values'][$itemid] = $item_data['max'];
					break;
			}

			$graph_data['captions'][$itemid] = $item['caption'];

			if ($db_item = get_item_by_itemid($item['itemid'])) {
				$graph->setUnits($db_item['units'], $item['axisside']);
				$graph->setSideValueType($db_item['value_type'], $item['axisside']);
			}
		}

		if ($sorttype == 0 || count($periods) < 2) {
			array_multisort($graph_data['captions'], $graph_data['values']);
		}
		else {
			array_multisort($graph_data['values'], SORT_DESC, $graph_data['captions']);
		}

		$graph->addSeries($graph_data['values']);
	}
}
elseif ($config == 3) {
	$hostids = getRequest('hostids', array());
	$groupids = getRequest('groupids', array());

	// validate permissions
	if (!API::Host()->isReadable($hostids) || !API::HostGroup()->isReadable($groupids)) {
		access_deny();
	}

	$title = getRequest('title','Report 2');
	$xlabel = getRequest('xlabel','');
	$ylabel = getRequest('ylabel','');

	$palette = getRequest('palette',0);
	$palettetype = getRequest('palettetype',0);

	$scaletype = getRequest('scaletype', TIMEPERIOD_TYPE_WEEKLY);
	$avgperiod = getRequest('avgperiod', TIMEPERIOD_TYPE_DAILY);

	if (!empty($groupids)) {
		$sql = 'SELECT DISTINCT hg.hostid'.
			' FROM hosts_groups hg,hosts h'.
			' WHERE h.hostid=hg.hostid'.
				' AND '.dbConditionInt('h.status', array(HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED)).
				' AND '.dbConditionInt('hg.groupid', $groupids);
		$res = DBselect($sql);
		while ($db_host = DBfetch($res)) {
			$hostids[$db_host['hostid']] = $db_host['hostid'];
		}
	}

	$itemids = array();
	foreach ($items as $item){
		if ($item['itemid'] > 0) {
			$itemids = get_same_item_for_host($item['itemid'], $hostids);
			break;
		}
	}

	$graph = new CBarGraphDraw(GRAPH_TYPE_COLUMN);
	$graph->setHeader('REPORT 3');

	$graph_data = array();

	$graph_data['colors'] = array();
	$graph_data['captions'] = array();
	$graph_data['values'] = array();
	$graph_data['legend'] = array();


	$timesince = getRequest('report_timesince', time() - SEC_PER_DAY);
	$timetill = getRequest('report_timetill', time());

	$str_since['hour'] = date('H', $timesince);
	$str_since['day'] = date('d', $timesince);
	$str_since['weekday'] = date('w', $timesince);
	if ($str_since['weekday'] == 0) {
		$str_since['weekday'] = 7;
	}

	$str_since['mon'] = date('m', $timesince);
	$str_since['year'] = date('Y', $timesince);

	$str_till['hour'] = date('H', $timetill);
	$str_till['day'] = date('d', $timetill);
	$str_till['weekday'] = date('w', $timetill);
	if ($str_till['weekday'] == 0) {
		$str_till['weekday'] = 7;
	}

	$str_till['mon'] = date('m', $timetill);
	$str_till['year'] = date('Y', $timetill);

	switch ($scaletype) {
		case TIMEPERIOD_TYPE_HOURLY:
			$scaleperiod = SEC_PER_HOUR;
			$str = $str_since['year'].'-'.$str_since['mon'].'-'.$str_since['day'].' '.$str_since['hour'].':00:00';
			$timesince = strtotime($str);

			$str = $str_till['year'].'-'.$str_till['mon'].'-'.$str_till['day'].' '.$str_till['hour'].':00:00';
			$timetill = strtotime($str) + $scaleperiod;
			break;
		case TIMEPERIOD_TYPE_DAILY:
			$scaleperiod = SEC_PER_DAY;
			$str = $str_since['year'].'-'.$str_since['mon'].'-'.$str_since['day'].' 00:00:00';
			$timesince = strtotime($str);

			$str = $str_till['year'].'-'.$str_till['mon'].'-'.$str_till['day'].' 00:00:00';
			$timetill = strtotime($str) + $scaleperiod;
			break;
		case TIMEPERIOD_TYPE_WEEKLY:
			$scaleperiod = SEC_PER_WEEK;
			$str = $str_since['year'].'-'.$str_since['mon'].'-'.$str_since['day'].' 00:00:00';
			$timesince = strtotime($str);
			$timesince -= ($str_since['weekday'] - 1) * SEC_PER_DAY;

			$str = $str_till['year'].'-'.$str_till['mon'].'-'.$str_till['day'].' 00:00:00';
			$timetill = strtotime($str);
			$timetill -= ($str_till['weekday'] - 1) * SEC_PER_DAY;

			$timetill+= $scaleperiod;
			break;
		case TIMEPERIOD_TYPE_MONTHLY:
			$scaleperiod = SEC_PER_MONTH;
			$str = $str_since['year'].'-'.$str_since['mon'].'-01 00:00:00';
			$timesince = strtotime($str);

			$str = $str_till['year'].'-'.$str_till['mon'].'-01 00:00:00';
			$timetill = strtotime($str);
			$timetill = strtotime('+1 month',$timetill);
			break;
		case TIMEPERIOD_TYPE_YEARLY:
			$scaleperiod = SEC_PER_YEAR;
			$str = $str_since['year'].'-01-01 00:00:00';
			$timesince = strtotime($str);

			$str = $str_till['year'].'-01-01 00:00:00';
			$timetill = strtotime($str);
			$timetill = strtotime('+1 year',$timetill);
			break;
	}

	// updating
	switch ($avgperiod) {
		case TIMEPERIOD_TYPE_HOURLY:
			$period = SEC_PER_HOUR;
			break;
		case TIMEPERIOD_TYPE_DAILY:
			$period = SEC_PER_DAY;
			break;
		case TIMEPERIOD_TYPE_WEEKLY:
			$period = SEC_PER_WEEK;
			break;
		case TIMEPERIOD_TYPE_MONTHLY:
			$period = SEC_PER_MONTH;
			break;
		case TIMEPERIOD_TYPE_YEARLY:
			$period = SEC_PER_YEAR;
			break;
	}

	$hosts = get_host_by_itemid($itemids);

	$db_values = array();
	foreach ($itemids as $itemid) {
		$count = 0;
		if (!isset($db_values[$count])) {
			$db_values[$count] = array();
		}
		$graph_data['captions'][$itemid] = $hosts[$itemid]['host'];

		$start = 0;
		$end = $timesince;
		while ($end < $timetill) {
			switch ($scaletype) {
				case TIMEPERIOD_TYPE_HOURLY:
				case TIMEPERIOD_TYPE_DAILY:
				case TIMEPERIOD_TYPE_WEEKLY:
					$start = $end;
					$end = $start + $scaleperiod;
					break;
				case TIMEPERIOD_TYPE_MONTHLY:
					$start = $end;

					$str_start['mon'] = date('m',$start);
					$str_start['year'] = date('Y',$start);

					$str = $str_start['year'].'-'.$str_start['mon'].'-01 00:00:00';
					$end = strtotime($str);
					$end = strtotime('+1 month',$end);
					break;
				case TIMEPERIOD_TYPE_YEARLY:
					$start = $end;

					$str_start['year'] = date('Y',$start);

					$str = $str_start['year'].'-01-01 00:00:00';
					$end = strtotime($str);
					$end = strtotime('+1 year',$end);
					break;
			}

			$p = $end - $start;						// graph size in time
			$z = $p - ($start % $p);				// graphsize - mod(from_time,p) for Oracle...
			$x = floor($scaleperiod / $period);		// graph size in px
			$calc_field = 'round('.$x.'*'.zbx_sql_mod(zbx_dbcast_2bigint('clock').'+'.$z, $p).'/('.$p.'),0)';	// required for 'group by' support of Oracle

			$item_data = null;

			$sql_arr = array();

			array_push($sql_arr,
				'SELECT itemid,'.$calc_field.' as i,sum(num) as count,avg(value_avg) as avg '.
				' FROM trends '.
				' WHERE itemid='.zbx_dbstr($itemid).
					' AND clock>='.zbx_dbstr($start).
					' AND clock<='.zbx_dbstr($end).
				' GROUP BY itemid,'.$calc_field
				,

				'SELECT itemid,'.$calc_field.' as i,sum(num) as count,avg(value_avg) as avg '.
				' FROM trends_uint '.
				' WHERE itemid='.zbx_dbstr($itemid).
					' AND clock>='.zbx_dbstr($start).
					' AND clock<='.zbx_dbstr($end).
				' GROUP BY itemid,'.$calc_field
				);

			foreach ($sql_arr as $sql) {
				$result = DBselect($sql);
				while ($row = DBfetch($result)) {
					if ($row['i'] == $x) {
						continue;
					}
					if (!is_null($item_data)) {
						$item_data = ($item_data + $row['avg']) / 2;
					}
					else {
						$item_data = $row['avg'];
					}
				}

			}

			$db_values[$count][$itemid] = is_null($item_data) ? 0 : $item_data;

			$tmp_color = get_next_palette($palette,$palettetype);

			if (!isset($graph_data['colors'][$count])) {
				$graph_data['colors'][$count] = rgb2hex($tmp_color);
			}

			$date_caption = ($scaletype == TIMEPERIOD_TYPE_HOURLY) ? DATE_TIME_FORMAT : DATE_FORMAT;
			$graph_data['legend'][$count] = zbx_date2str($date_caption, $start);

			$count++;
		}
	}

	foreach ($db_values as $item_data) {
		$graph->addSeries($item_data);
	}

	if (isset($itemid) && ($db_item = get_item_by_itemid($itemid))) {
		$graph->setUnits($db_item['units']);
		$graph->setSideValueType($db_item['value_type']);
	}
}

if (!isset($graph_data['captions'])) {
	$graph_data['captions'] = array();
}
if (!isset($graph_data['legend'])) {
	$graph_data['legend'] = '';
}

$graph->setSeriesLegend($graph_data['legend']);
$graph->setPeriodCaption($graph_data['captions']);

$graph->setHeader($title);
$graph->setPeriod($scaleperiod);
$graph->setXLabel($xlabel);
$graph->setYLabel($ylabel);

$graph->setSeriesColor($graph_data['colors']);

$graph->showLegend($showlegend);

$graph->setWidth(1024);
$graph->setMinChartHeight(250);

$graph->draw();

require_once dirname(__FILE__).'/include/page_footer.php';
