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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


function graphType($type = null) {
	$types = [
		GRAPH_TYPE_NORMAL => _('Normal'),
		GRAPH_TYPE_STACKED => _('Stacked'),
		GRAPH_TYPE_PIE => _('Pie'),
		GRAPH_TYPE_EXPLODED => _('Exploded')
	];

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
	return [
		GRAPH_ITEM_DRAWTYPE_LINE,
		GRAPH_ITEM_DRAWTYPE_FILLED_REGION,
		GRAPH_ITEM_DRAWTYPE_BOLD_LINE,
		GRAPH_ITEM_DRAWTYPE_DOT,
		GRAPH_ITEM_DRAWTYPE_DASHED_LINE,
		GRAPH_ITEM_DRAWTYPE_GRADIENT_LINE
	];
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
	$graphDims = [];

	$graphDims['shiftYtop'] = CGraphDraw::DEFAULT_HEADER_PADDING_TOP;
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
		$graphDims['graphHeight'] = (int) $graph['height'];
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

	++$graphDims['graphHeight'];

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
function getSameGraphItemsForHost($gitems, $destinationHostId, $error = true, array $flags = []) {
	$result = [];

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
 * @param string $graphid
 * @param string $hostid
 *
 * @return array
 */
function copyGraphToHost($graphid, $hostid) {
	$graphs = API::Graph()->get([
		'output' => ['graphid', 'name', 'width', 'height', 'yaxismin', 'yaxismax', 'show_work_period', 'show_triggers',
			'graphtype', 'show_legend', 'show_3d', 'percent_left', 'percent_right', 'ymin_type', 'ymax_type',
			'ymin_itemid', 'ymax_itemid'
		],
		'selectGraphItems' => ['itemid', 'drawtype', 'sortorder', 'color', 'yaxisside', 'calc_fnc', 'type'],
		'selectHosts' => ['hostid', 'name'],
		'graphids' => $graphid
	]);
	$graph = reset($graphs);
	$host = reset($graph['hosts']);

	if ($host['hostid'] == $hostid) {
		error(_s('Graph "%1$s" already exists on "%2$s".', $graph['name'], $host['name']));

		return false;
	}

	$graph['gitems'] = getSameGraphItemsForHost(
		$graph['gitems'],
		$hostid,
		true,
		[ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]
	);

	if (!$graph['gitems']) {
		$host = get_host_by_hostid($hostid);

		info(_s('Skipped copying of graph "%1$s" to host "%2$s".', $graph['name'], $host['host']));

		return false;
	}

	// retrieve actual ymax_itemid and ymin_itemid
	if ($graph['ymax_itemid'] && $itemid = get_same_item_for_host($graph['ymax_itemid'], $hostid)) {
		$graph['ymax_itemid'] = $itemid;
	}

	if ($graph['ymin_itemid'] && $itemid = get_same_item_for_host($graph['ymin_itemid'], $hostid)) {
		$graph['ymin_itemid'] = $itemid;
	}

	return API::Graph()->create($graph);
}

function get_next_color($palettetype = 0) {
	static $prev_color = ['dark' => true, 'color' => 0, 'grad' => 0];

	switch ($palettetype) {
		case 1:
			$grad = [200, 150, 255, 100, 50, 0];
			break;
		case 2:
			$grad = [100, 50, 200, 150, 250, 0];
			break;
		case 0:
		default:
			$grad = [255, 200, 150, 100, 50, 0];
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

	return [$r, $g, $b];
}

function get_next_palette($palette = 0, $palettetype = 0) {
	static $prev_color = [0, 0, 0, 0];

	switch ($palette) {
		case 0:
			$palettes = [
				[150, 0, 0], [0, 100, 150], [170, 180, 180], [152, 100, 0], [130, 0, 150],
				[0, 0, 150], [200, 100, 50], [250, 40, 40], [50, 150, 150], [100, 150, 0]
			];
			break;
		case 1:
			$palettes = [
				[0, 100, 150], [153, 0, 30], [100, 150, 0], [130, 0, 150], [0, 0, 100],
				[200, 100, 50], [152, 100, 0], [0, 100, 0], [170, 180, 180], [50, 150, 150]
			];
			break;
		case 2:
			$palettes = [
				[170, 180, 180], [152, 100, 0], [50, 200, 200], [153, 0, 30], [0, 0, 100],
				[100, 150, 0], [130, 0, 150], [0, 100, 150], [200, 100, 50], [0, 100, 0]
			];
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
	$polygons = 5;
	$gims = [
		't' => [0, 0, -6, -6, -3, -9, 3, -9, 6, -6],
		'r' => [0, 0, 6, -6, 9, -3, 9, 3, 6, 6],
		'b' => [0, 0, 6, 6, 3, 9, -3, 9, -6, 6],
		'l' => [0, 0, -6, 6, -9, 3, -9, -3, -6, -6]
	];

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

	$color = get_color($im, $color);
	$polygon_color = get_color($im, '960000');

	if (strpos($marks, 't') !== false) {
		imagefilledpolygon($im, $gims['t'], $polygons, $color);
		imagepolygon($im, $gims['t'], $polygons, $polygon_color);
	}
	if (strpos($marks, 'r') !== false) {
		imagefilledpolygon($im, $gims['r'], $polygons, $color);
		imagepolygon($im, $gims['r'], $polygons, $polygon_color);
	}
	if (strpos($marks, 'b') !== false) {
		imagefilledpolygon($im, $gims['b'], $polygons, $color);
		imagepolygon($im, $gims['b'], $polygons, $polygon_color);
	}
	if (strpos($marks, 'l') !== false) {
		imagefilledpolygon($im, $gims['l'], $polygons, $color);
		imagepolygon($im, $gims['l'], $polygons, $polygon_color);
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

	return [
		'height' => abs($ar[1] - $ar[5]),
		'width' => abs($ar[0] - $ar[4]),
		'baseline' => $ar[1]
	];
}

function dashedLine($image, $x1, $y1, $x2, $y2, $color) {
	// style for dashed lines
	if (!is_array($color)) {
		$style = [$color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT];
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
