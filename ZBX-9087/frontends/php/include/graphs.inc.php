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


function graphType($type = null) {
	$types = array(
		GRAPH_TYPE_NORMAL => _('Normal'),
		GRAPH_TYPE_STACKED => _('Stacked'),
		GRAPH_TYPE_PIE => _('Pie'),
		GRAPH_TYPE_EXPLODED => _('Exploded')
	);

	if (is_null($type)) {
		return $types;
	}
	elseif (isset($types[$type])) {
		return $types[$type];
	}
	else {
		return _('Unknown');
	}
}

function graph_item_drawtypes() {
	return array(
		GRAPH_ITEM_DRAWTYPE_LINE,
		GRAPH_ITEM_DRAWTYPE_FILLED_REGION,
		GRAPH_ITEM_DRAWTYPE_BOLD_LINE,
		GRAPH_ITEM_DRAWTYPE_DOT,
		GRAPH_ITEM_DRAWTYPE_DASHED_LINE,
		GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE
	);
}

function graph_item_drawtype2str($drawtype) {
	switch ($drawtype) {
		case GRAPH_ITEM_DRAWTYPE_LINE:
			return _('Line');
		case GRAPH_ITEM_DRAWTYPE_FILLED_REGION:
			return _('Filled region');
		case GRAPH_ITEM_DRAWTYPE_BOLD_LINE:
			return _('Bold line');
		case GRAPH_ITEM_DRAWTYPE_DOT:
			return _('Dot');
		case GRAPH_ITEM_DRAWTYPE_DASHED_LINE:
			return _('Dashed line');
		case GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE:
			return _('Gradient line');
		default:
			return _('Unknown');
	}
}

function graph_item_calc_fnc2str($calc_fnc) {
	switch ($calc_fnc) {
		case 0:
			return _('Count');
		case CALC_FNC_ALL:
			return _('all');
		case CALC_FNC_MIN:
			return _('min');
		case CALC_FNC_MAX:
			return _('max');
		case CALC_FNC_LST:
			return _('last');
		case CALC_FNC_AVG:
		default:
			return _('avg');
	}
}

function getGraphDims($graphid = null) {
	$graphDims = array();

	$graphDims['shiftYtop'] = 35;
	if (is_null($graphid)) {
		$graphDims['graphHeight'] = 200;
		$graphDims['graphtype'] = 0;

		if (GRAPH_YAXIS_SIDE_DEFAULT == 0) {
			$graphDims['shiftXleft'] = 85;
			$graphDims['shiftXright'] = 30;
		}
		else {
			$graphDims['shiftXleft'] = 30;
			$graphDims['shiftXright'] = 85;
		}

		return $graphDims;
	}

	// zoom featers
	$dbGraphs = DBselect(
		'SELECT MAX(g.graphtype) AS graphtype,MIN(gi.yaxisside) AS yaxissidel,MAX(gi.yaxisside) AS yaxissider,MAX(g.height) AS height'.
		' FROM graphs g,graphs_items gi'.
		' WHERE g.graphid='.zbx_dbstr($graphid).
			' AND gi.graphid=g.graphid'
	);
	if ($graph = DBfetch($dbGraphs)) {
		$yaxis = $graph['yaxissider'];
		$yaxis = ($graph['yaxissidel'] == $yaxis) ? $yaxis : 2;

		$graphDims['yaxis'] = $yaxis;
		$graphDims['graphtype'] = $graph['graphtype'];
		$graphDims['graphHeight'] = $graph['height'];
	}

	if ($yaxis == 2) {
		$graphDims['shiftXleft'] = 85;
		$graphDims['shiftXright'] = 85;
	}
	elseif ($yaxis == 0) {
		$graphDims['shiftXleft'] = 85;
		$graphDims['shiftXright'] = 30;
	}
	else {
		$graphDims['shiftXleft'] = 30;
		$graphDims['shiftXright'] = 85;
	}

	return $graphDims;
}

function get_realhosts_by_graphid($graphid) {
	$graph = getGraphByGraphId($graphid);
	if (!empty($graph['templateid'])) {
		return get_realhosts_by_graphid($graph['templateid']);
	}
	return get_hosts_by_graphid($graphid);
}

function get_hosts_by_graphid($graphid) {
	return DBselect(
		'SELECT DISTINCT h.*'.
		' FROM graphs_items gi,items i,hosts h'.
		' WHERE h.hostid=i.hostid'.
			' AND gi.itemid=i.itemid'.
			' AND gi.graphid='.zbx_dbstr($graphid)
	);
}

/**
 * Description:
 *	Return the time of the 1st appearance of items included in graph in trends
 * Comment:
 *	sql is split to many sql's to optimize search on history tables
 */
function get_min_itemclock_by_graphid($graphid) {
	$itemids = array();
	$dbItems = DBselect(
		'SELECT DISTINCT gi.itemid'.
		' FROM graphs_items gi'.
		' WHERE gi.graphid='.zbx_dbstr($graphid)
	);
	while ($item = DBfetch($dbItems)) {
		$itemids[$item['itemid']] = $item['itemid'];
	}

	return get_min_itemclock_by_itemid($itemids);
}

/**
 * Return the time of the 1st appearance of item in trends.
 *
 * @param array $itemIds
 *
 * @return int (unixtime)
 */
function get_min_itemclock_by_itemid($itemIds) {
	zbx_value2array($itemIds);

	$min = null;
	$result = time() - SEC_PER_YEAR;

	$itemTypes = array(
		ITEM_VALUE_TYPE_FLOAT => array(),
		ITEM_VALUE_TYPE_STR => array(),
		ITEM_VALUE_TYPE_LOG => array(),
		ITEM_VALUE_TYPE_UINT64 => array(),
		ITEM_VALUE_TYPE_TEXT => array()
	);

	$dbItems = DBselect(
		'SELECT i.itemid,i.value_type'.
		' FROM items i'.
		' WHERE '.dbConditionInt('i.itemid', $itemIds)
	);

	while ($item = DBfetch($dbItems)) {
		$itemTypes[$item['value_type']][$item['itemid']] = $item['itemid'];
	}

	// data for ITEM_VALUE_TYPE_FLOAT and ITEM_VALUE_TYPE_UINT64 can be stored in trends tables or history table
	// get max trends and history values for such type items to find out in what tables to look for data
	$sqlFrom = 'history';
	$sqlFromNum = '';

	if (!empty($itemTypes[ITEM_VALUE_TYPE_FLOAT]) || !empty($itemTypes[ITEM_VALUE_TYPE_UINT64])) {
		$itemIdsNumeric = zbx_array_merge($itemTypes[ITEM_VALUE_TYPE_FLOAT], $itemTypes[ITEM_VALUE_TYPE_UINT64]);

		$sql = 'SELECT MAX(i.history) AS history,MAX(i.trends) AS trends'.
				' FROM items i'.
				' WHERE '.dbConditionInt('i.itemid', $itemIdsNumeric);
		if ($tableForNumeric = DBfetch(DBselect($sql))) {
			// look for data in one of the tables
			$sqlFromNum = ($tableForNumeric['history'] > $tableForNumeric['trends']) ? 'history' : 'trends';

			$result = time() - (SEC_PER_DAY * max($tableForNumeric['history'], $tableForNumeric['trends']));

			/*
			 * In case history storage exceeds the maximum time difference between current year and minimum 1970
			 * (for example year 2014 - 200 years < year 1970), correct year to 1970 (unix time timestamp 0).
			 */
			if ($result < 0) {
				$result = 0;
			}
		}
	}

	foreach ($itemTypes as $type => $items) {
		if (empty($items)) {
			continue;
		}

		switch ($type) {
			case ITEM_VALUE_TYPE_FLOAT:
				$sqlFrom = $sqlFromNum;
				break;
			case ITEM_VALUE_TYPE_STR:
				$sqlFrom = 'history_str';
				break;
			case ITEM_VALUE_TYPE_LOG:
				$sqlFrom = 'history_log';
				break;
			case ITEM_VALUE_TYPE_UINT64:
				$sqlFrom = $sqlFromNum.'_uint';
				break;
			case ITEM_VALUE_TYPE_TEXT:
				$sqlFrom = 'history_text';
				break;
			default:
				$sqlFrom = 'history';
		}

		foreach ($itemIds as $itemId) {
			$sqlUnions[] = 'SELECT MIN(ht.clock) AS c FROM '.$sqlFrom.' ht WHERE ht.itemid='.zbx_dbstr($itemId);
		}

		$dbMin = DBfetch(DBselect(
			'SELECT MIN(ht.c) AS min_clock'.
			' FROM ('.implode(' UNION ALL ', $sqlUnions).') ht'
		));

		$min = $min ? min($min, $dbMin['min_clock']) : $dbMin['min_clock'];
	}

	// in case DB clock column is corrupted having negative numbers, return min clock from max possible history storage
	return ($min > 0) ? $min : $result;
}

function getGraphByGraphId($graphId) {
	$dbGraph = DBfetch(DBselect('SELECT g.* FROM graphs g WHERE g.graphid='.zbx_dbstr($graphId)));

	if ($dbGraph) {
		return $dbGraph;
	}

	error(_s('No graph item with graphid "%s".', $graphId));

	return false;
}

/**
 * Search items by same key in destination host.
 *
 * @param array  $gitems
 * @param string $destinationHostId
 * @param bool   $error					if false error won't be thrown when item does not exist
 * @param array  $flags
 *
 * @return array|bool
 */
function getSameGraphItemsForHost($gitems, $destinationHostId, $error = true, array $flags = array()) {
	$result = array();

	$flagsSql = $flags ? ' AND '.dbConditionInt('dest.flags', $flags) : '';

	foreach ($gitems as $gitem) {
		$dbItem = DBfetch(DBselect(
			'SELECT dest.itemid,src.key_'.
			' FROM items dest,items src'.
			' WHERE dest.key_=src.key_'.
				' AND dest.hostid='.zbx_dbstr($destinationHostId).
				' AND src.itemid='.zbx_dbstr($gitem['itemid']).
				$flagsSql
		));

		if ($dbItem) {
			$gitem['itemid'] = $dbItem['itemid'];
			$gitem['key_'] = $dbItem['key_'];
		}
		elseif ($error) {
			$item = get_item_by_itemid($gitem['itemid']);
			$host = get_host_by_hostid($destinationHostId);

			error(_s('Missing key "%1$s" for host "%2$s".', $item['key_'], $host['host']));

			return false;
		}
		else {
			continue;
		}

		$result[] = $gitem;
	}

	return $result;
}

/**
 * Copy specified graph to specified host.
 *
 * @param string $graphId
 * @param string $hostId
 *
 * @return array
 */
function copyGraphToHost($graphId, $hostId) {
	$graphs = API::Graph()->get(array(
		'graphids' => $graphId,
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => array('hostid', 'name'),
		'selectGraphItems' => API_OUTPUT_EXTEND
	));
	$graph = reset($graphs);
	$graphHost = reset($graph['hosts']);

	if ($graphHost['hostid'] == $hostId) {
		error(_s('Graph "%1$s" already exists on "%2$s".', $graph['name'], $graphHost['name']));

		return false;
	}

	$graph['gitems'] = getSameGraphItemsForHost(
		$graph['gitems'],
		$hostId,
		true,
		array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)
	);

	if (!$graph['gitems']) {
		$host = get_host_by_hostid($hostId);

		info(_s('Skipped copying of graph "%1$s" to host "%2$s".', $graph['name'], $host['host']));

		return false;
	}

	// retrieve actual ymax_itemid and ymin_itemid
	if ($graph['ymax_itemid'] && $itemId = get_same_item_for_host($graph['ymax_itemid'], $hostId)) {
		$graph['ymax_itemid'] = $itemId;
	}

	if ($graph['ymin_itemid'] && $itemId = get_same_item_for_host($graph['ymin_itemid'], $hostId)) {
		$graph['ymin_itemid'] = $itemId;
	}

	unset($graph['templateid']);

	return API::Graph()->create($graph);
}

function navigation_bar_calc($idx = null, $idx2 = 0, $update = false) {
	if (!empty($idx)) {
		if ($update) {
			if (!empty($_REQUEST['period']) && $_REQUEST['period'] >= ZBX_MIN_PERIOD) {
				CProfile::update($idx.'.period', $_REQUEST['period'], PROFILE_TYPE_INT, $idx2);
			}
			if (!empty($_REQUEST['stime'])) {
				CProfile::update($idx.'.stime', $_REQUEST['stime'], PROFILE_TYPE_STR, $idx2);
			}
		}
		$_REQUEST['period'] = getRequest('period', CProfile::get($idx.'.period', ZBX_PERIOD_DEFAULT, $idx2));
		$_REQUEST['stime'] = getRequest('stime', CProfile::get($idx.'.stime', null, $idx2));
	}

	$_REQUEST['period'] = getRequest('period', ZBX_PERIOD_DEFAULT);
	$_REQUEST['stime'] = getRequest('stime');

	if ($_REQUEST['period'] < ZBX_MIN_PERIOD) {
		show_message(_n('Minimum time period to display is %1$s hour.',
			'Minimum time period to display is %1$s hours.',
			(int) ZBX_MIN_PERIOD / SEC_PER_HOUR
		));
		$_REQUEST['period'] = ZBX_MIN_PERIOD;
	}
	elseif ($_REQUEST['period'] > ZBX_MAX_PERIOD) {
		show_message(_n('Maximum time period to display is %1$s day.',
			'Maximum time period to display is %1$s days.',
			(int) ZBX_MAX_PERIOD / SEC_PER_DAY
		));
		$_REQUEST['period'] = ZBX_MAX_PERIOD;
	}

	if (!empty($_REQUEST['stime'])) {
		$time = zbxDateToTime($_REQUEST['stime']);
		if (($time + $_REQUEST['period']) > time()) {
			$_REQUEST['stime'] = date(TIMESTAMP_FORMAT, time() - $_REQUEST['period']);
		}
	}
	else {
		$_REQUEST['stime'] = date(TIMESTAMP_FORMAT, time() - $_REQUEST['period']);
	}

	return $_REQUEST['period'];
}

function get_next_color($palettetype = 0) {
	static $prev_color = array('dark' => true, 'color' => 0, 'grad' => 0);

	switch ($palettetype) {
		case 1:
			$grad = array(200, 150, 255, 100, 50, 0);
			break;
		case 2:
			$grad = array(100, 50, 200, 150, 250, 0);
			break;
		case 0:
		default:
			$grad = array(255, 200, 150, 100, 50, 0);
			break;
	}

	$set_grad = $grad[$prev_color['grad']];

	$r = $g = $b = (100 < $set_grad) ? 0 : 255;

	switch ($prev_color['color']) {
		case 0:
			$g = $set_grad;
			break;
		case 1:
			$r = $set_grad;
			break;
		case 2:
			$b = $set_grad;
			break;
		case 3:
			$r = $b = $set_grad;
			break;
		case 4:
			$g = $b = $set_grad;
			break;
		case 5:
			$r = $g = $set_grad;
			break;
		case 6:
			$r = $g = $b = $set_grad;
			break;
	}

	$prev_color['dark'] = !$prev_color['dark'];
	if ($prev_color['color'] == 6) {
		$prev_color['grad'] = ($prev_color['grad'] + 1) % 6;
	}
	$prev_color['color'] = ($prev_color['color'] + 1) % 7;

	return array($r, $g, $b);
}

function get_next_palette($palette = 0, $palettetype = 0) {
	static $prev_color = array(0, 0, 0, 0);

	switch ($palette) {
		case 0:
			$palettes = array(
				array(150, 0, 0), array(0, 100, 150), array(170, 180, 180), array(152, 100, 0), array(130, 0, 150),
				array(0, 0, 150), array(200, 100, 50), array(250, 40, 40), array(50, 150, 150), array(100, 150, 0)
			);
			break;
		case 1:
			$palettes = array(
				array(0, 100, 150), array(153, 0, 30), array(100, 150, 0), array(130, 0, 150), array(0, 0, 100),
				array(200, 100, 50), array(152, 100, 0), array(0, 100, 0), array(170, 180, 180), array(50, 150, 150)
			);
			break;
		case 2:
			$palettes = array(
				array(170, 180, 180), array(152, 100, 0), array(50, 200, 200), array(153, 0, 30), array(0, 0, 100),
				array(100, 150, 0), array(130, 0, 150), array(0, 100, 150), array(200, 100, 50), array(0, 100, 0)
			);
			break;
		case 3:
		default:
			return get_next_color($palettetype);
	}

	if (isset($palettes[$prev_color[$palette]])) {
		$result = $palettes[$prev_color[$palette]];
	}
	else {
		return get_next_color($palettetype);
	}

	switch ($palettetype) {
		case 0:
			$diff = 0;
			break;
		case 1:
			$diff = -50;
			break;
		case 2:
			$diff = 50;
			break;
	}

	foreach ($result as $n => $color) {
		if (($color + $diff) < 0) {
			$result[$n] = 0;
		}
		elseif (($color + $diff) > 255) {
			$result[$n] = 255;
		}
		else {
			$result[$n] += $diff;
		}
	}
	$prev_color[$palette]++;

	return $result;
}

/**
 * Draw trigger recent change markers.
 *
 * @param resource $im
 * @param int      $x
 * @param int      $y
 * @param int      $offset
 * @param string   $color
 * @param string   $marks	"t" - top, "r" - right, "b" - bottom, "l" - left
 */
function imageVerticalMarks($im, $x, $y, $offset, $color, $marks) {
	global $colors;

	$polygons = 5;
	$gims = array(
		't' => array(0, 0, -6, -6, -3, -9, 3, -9, 6, -6),
		'r' => array(0, 0, 6, -6, 9, -3, 9, 3, 6, 6),
		'b' => array(0, 0, 6, 6, 3, 9, -3, 9, -6, 6),
		'l' => array(0, 0, -6, 6, -9, 3, -9, -3, -6, -6)
	);

	foreach ($gims['t'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['t'][$num] = $px + $x;
		}
		else {
			$gims['t'][$num] = $px + $y - $offset;
		}
	}

	foreach ($gims['r'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['r'][$num] = $px + $x + $offset;
		}
		else {
			$gims['r'][$num] = $px + $y;
		}
	}

	foreach ($gims['b'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['b'][$num] = $px + $x;
		}
		else {
			$gims['b'][$num] = $px + $y + $offset;
		}
	}

	foreach ($gims['l'] as $num => $px) {
		if (($num % 2) == 0) {
			$gims['l'][$num] = $px + $x - $offset;
		}
		else {
			$gims['l'][$num] = $px + $y;
		}
	}

	if (strpos($marks, 't') !== false) {
		imagefilledpolygon($im, $gims['t'], $polygons, $color);
		imagepolygon($im, $gims['t'], $polygons, $colors['Dark Red']);
	}
	if (strpos($marks, 'r') !== false) {
		imagefilledpolygon($im, $gims['r'], $polygons, $color);
		imagepolygon($im, $gims['r'], $polygons, $colors['Dark Red']);
	}
	if (strpos($marks, 'b') !== false) {
		imagefilledpolygon($im, $gims['b'], $polygons, $color);
		imagepolygon($im, $gims['b'], $polygons, $colors['Dark Red']);
	}
	if (strpos($marks, 'l') !== false) {
		imagefilledpolygon($im, $gims['l'], $polygons, $color);
		imagepolygon($im, $gims['l'], $polygons, $colors['Dark Red']);
	}
}

/**
 * Draws a text on an image. Supports TrueType fonts.
 *
 * @param resource 	$image
 * @param int		$fontsize
 * @param int 		$angle
 * @param int		$x
 * @param int 		$y
 * @param int		$color		a numeric color identifier from imagecolorallocate() or imagecolorallocatealpha()
 * @param string	$string
 */
function imageText($image, $fontsize, $angle, $x, $y, $color, $string) {
	if ((preg_match(ZBX_PREG_DEF_FONT_STRING, $string) && $angle != 0) || ZBX_FONT_NAME == ZBX_GRAPH_FONT_NAME) {
		$ttf = ZBX_FONTPATH.'/'.ZBX_FONT_NAME.'.ttf';
		imagettftext($image, $fontsize, $angle, $x, $y, $color, $ttf, $string);
	}
	elseif ($angle == 0) {
		$ttf = ZBX_FONTPATH.'/'.ZBX_GRAPH_FONT_NAME.'.ttf';
		imagettftext($image, $fontsize, $angle, $x, $y, $color, $ttf, $string);
	}
	else {
		$ttf = ZBX_FONTPATH.'/'.ZBX_GRAPH_FONT_NAME.'.ttf';
		$size = imageTextSize($fontsize, 0, $string);

		$imgg = imagecreatetruecolor($size['width'] + 1, $size['height']);
		$transparentColor = imagecolorallocatealpha($imgg, 200, 200, 200, 127);
		imagefill($imgg, 0, 0, $transparentColor);
		imagettftext($imgg, $fontsize, 0, 0, $size['height'], $color, $ttf, $string);

		$imgg = imagerotate($imgg, $angle, $transparentColor);
		imagealphablending($imgg, false);
		imagesavealpha($imgg, true);
		imagecopy($image, $imgg, $x - $size['height'], $y - $size['width'], 0, 0, $size['height'], $size['width'] + 1);
		imagedestroy($imgg);
	}
}

/**
 * Calculates the size of the given string.
 *
 * Returns the following data:
 * - height 	- height of the text;
 * - width		- width of the text;
 * - baseline	- baseline Y coordinate (can only be used for horizontal text, can be negative).
 *
 * @param int 		$fontsize
 * @param int 		$angle
 * @param string 	$string
 *
 * @return array
 */
function imageTextSize($fontsize, $angle, $string) {
	if (preg_match(ZBX_PREG_DEF_FONT_STRING, $string) && $angle != 0) {
		$ttf = ZBX_FONTPATH.'/'.ZBX_FONT_NAME.'.ttf';
	}
	else {
		$ttf = ZBX_FONTPATH.'/'.ZBX_GRAPH_FONT_NAME.'.ttf';
	}

	$ar = imagettfbbox($fontsize, $angle, $ttf, $string);

	return array(
		'height' => abs($ar[1] - $ar[5]),
		'width' => abs($ar[0] - $ar[4]),
		'baseline' => $ar[1]
	);
}

function dashedLine($image, $x1, $y1, $x2, $y2, $color) {
	// style for dashed lines
	if (!is_array($color)) {
		$style = array($color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
	}
	else {
		$style = $color;
	}

	imagesetstyle($image, $style);
	zbx_imageline($image, $x1, $y1, $x2, $y2, IMG_COLOR_STYLED);
}

function dashedRectangle($image, $x1, $y1, $x2, $y2, $color) {
	dashedLine($image, $x1, $y1, $x1, $y2, $color);
	dashedLine($image, $x1, $y2, $x2, $y2, $color);
	dashedLine($image, $x2, $y2, $x2, $y1, $color);
	dashedLine($image, $x2, $y1, $x1, $y1, $color);
}

function find_period_start($periods, $time) {
	$date = getdate($time);
	$wday = $date['wday'] == 0 ? 7 : $date['wday'];
	$curr = $date['hours'] * 100 + $date['minutes'];

	if (isset($periods[$wday])) {
		$next_h = -1;
		$next_m = -1;
		foreach ($periods[$wday] as $period) {
			$per_start = $period['start_h'] * 100 + $period['start_m'];
			if ($per_start > $curr) {
				if (($next_h == -1 && $next_m == -1) || ($per_start < ($next_h * 100 + $next_m))) {
					$next_h = $period['start_h'];
					$next_m = $period['start_m'];
				}
				continue;
			}

			$per_end = $period['end_h'] * 100 + $period['end_m'];
			if ($per_end <= $curr) {
				continue;
			}
			return $time;
		}

		if ($next_h >= 0 && $next_m >= 0) {
			return mktime($next_h, $next_m, 0, $date['mon'], $date['mday'], $date['year']);
		}
	}

	for ($days = 1; $days < 7 ; ++$days) {
		$new_wday = ($wday + $days - 1) % 7 + 1;
		if (isset($periods[$new_wday ])) {
			$next_h = -1;
			$next_m = -1;

			foreach ($periods[$new_wday] as $period) {
				$per_start = $period['start_h'] * 100 + $period['start_m'];

				if (($next_h == -1 && $next_m == -1) || ($per_start < ($next_h * 100 + $next_m))) {
					$next_h = $period['start_h'];
					$next_m = $period['start_m'];
				}
			}

			if ($next_h >= 0 && $next_m >= 0) {
				return mktime($next_h, $next_m, 0, $date['mon'], $date['mday'] + $days, $date['year']);
			}
		}
	}
	return -1;
}

function find_period_end($periods, $time, $max_time) {
	$date = getdate($time);
	$wday = $date['wday'] == 0 ? 7 : $date['wday'];
	$curr = $date['hours'] * 100 + $date['minutes'];

	if (isset($periods[$wday])) {
		$next_h = -1;
		$next_m = -1;

		foreach ($periods[$wday] as $period) {
			$per_start = $period['start_h'] * 100 + $period['start_m'];
			$per_end = $period['end_h'] * 100 + $period['end_m'];
			if ($per_start > $curr) {
				continue;
			}
			if ($per_end < $curr) {
				continue;
			}

			if (($next_h == -1 && $next_m == -1) || ($per_end > ($next_h * 100 + $next_m))) {
				$next_h = $period['end_h'];
				$next_m = $period['end_m'];
			}
		}

		if ($next_h >= 0 && $next_m >= 0) {
			$new_time = mktime($next_h, $next_m, 0, $date['mon'], $date['mday'], $date['year']);

			if ($new_time == $time) {
				return $time;
			}
			if ($new_time > $max_time) {
				return $max_time;
			}

			$next_time = find_period_end($periods, $new_time, $max_time);
			if ($next_time < 0) {
				return $new_time;
			}
			else {
				return $next_time;
			}
		}
	}

	return -1;
}

/**
 * Converts Base1000 values to Base1024 and calculate pow
 * Example:
 * 	204800 (200 KBytes) with '1024' step convert to 209715,2 (0.2MB (204.8 KBytes))
 *
 * @param string   $value
 * @param bool|int $step
 *
 * @return array
 */
function convertToBase1024($value, $step = false) {
	if (!$step) {
		$step = 1000;
	}

	if ($value < 0) {
		$abs = bcmul($value, '-1');
	}
	else {
		$abs = $value;
	}

	// set default values
	$valData['pow'] = 0;
	$valData['value'] = 0;

	// supported pows ('-2' - '8')
	for ($i = -2; $i < 9; $i++) {
		$val = bcpow($step, $i);
		if (bccomp($abs, $val) > -1) {
			$valData['pow'] = $i;
			$valData['value'] = $val;
		}
		else {
			break;
		}
	}

	if ($valData['pow'] >= 0) {
		if ($valData['value'] != 0) {
			$valData['value'] = bcdiv(sprintf('%.10f',$value), sprintf('%.10f', $valData['value']),
				ZBX_PRECISION_10);

			$valData['value'] = sprintf('%.10f', round(bcmul($valData['value'], bcpow(1024, $valData['pow'])),
				ZBX_PRECISION_10));
		}
	}
	else {
		$valData['pow'] = 0;
		if (round($valData['value'], 6) > 0) {
			$valData['value'] = $value;
		}
		else {
			$valData['value'] = 0;
		}
	}

	return $valData;
}

/**
 * Calculate interval for base 1024 values.
 * Example:
 * 	Convert 1000 to 1024
 *
 * @param $interval
 * @param $minY
 * @param $maxY
 *
 * @return float|int
 */
function getBase1024Interval($interval, $minY, $maxY) {
	$intervalData = convertToBase1024($interval);
	$interval = $intervalData['value'];

	if ($maxY > 0) {
		$absMaxY = $maxY;
	}
	else {
		$absMaxY = bcmul($maxY, '-1');
	}

	if ($minY > 0) {
		$absMinY = $minY;
	}
	else {
		$absMinY = bcmul($minY, '-1');
	}

	if ($absMaxY > $absMinY) {
		$sideMaxData = convertToBase1024($maxY);
	}
	else {
		$sideMaxData = convertToBase1024($minY);
	}

	if ($sideMaxData['pow'] != $intervalData['pow']) {
		// interval correction, if Max Y have other unit, then interval unit = Max Y unit
		if ($intervalData['pow'] < 0) {
			$interval = sprintf('%.10f', bcmul($interval, 1.024, 10));
		}
		else {
			$interval = sprintf('%.6f', round(bcmul($interval, 1.024), ZBX_UNITS_ROUNDOFF_UPPER_LIMIT));
		}
	}

	return $interval;
}

/**
 * Returns digit count for the item with most digit after point in given array.
 * Example:
 *	Input: array(0, 0.1, 0.25, 0.005)
 *	Return 3
 *
 * @param array $calcValues
 *
 * @return int
 */
function calcMaxLengthAfterDot($calcValues) {
	$maxLength = 0;

	foreach ($calcValues as $calcValue) {
		preg_match('/^-?[0-9].?([0-9]*)\s?/', $calcValue, $matches);
		if ($matches['1'] != 0 && strlen($matches['1']) > $maxLength) {
			$maxLength = strlen($matches['1']);
		}
	}

	return $maxLength;
}
