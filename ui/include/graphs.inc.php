<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

function graph_item_aggr_fnc2str($calc_fnc) {
	switch ($calc_fnc) {
		case AGGREGATE_NONE:
			return _('none');
		case AGGREGATE_MIN:
			return _('min');
		case AGGREGATE_MAX:
			return _('max');
		case AGGREGATE_AVG:
			return _('avg');
		case AGGREGATE_COUNT:
			return _('count');
		case AGGREGATE_SUM:
			return _('sum');
		case AGGREGATE_FIRST:
			return _('first');
		case AGGREGATE_LAST:
			return _('last');
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

	// Select graph's type and height as well as which Y axes are used by graph items.
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

	$graphDims['graphHeight']++;

	return $graphDims;
}

function getGraphByGraphId($graphId) {
	$dbGraph = DBfetch(DBselect('SELECT g.* FROM graphs g WHERE g.graphid='.zbx_dbstr($graphId)));

	if ($dbGraph) {
		return $dbGraph;
	}

	error(_s('No graph item with graph ID "%1$s".', $graphId));

	return false;
}

/**
 * Get parent templates for each given graph.
 *
 * @param $array $graphs                  An array of graphs.
 * @param string $graphs[]['graphid']     ID of a graph.
 * @param string $graphs[]['templateid']  ID of parent template graph.
 * @param int    $flag                    Origin of the graph (ZBX_FLAG_DISCOVERY_NORMAL or
 *                                        ZBX_FLAG_DISCOVERY_PROTOTYPE).
 *
 * @return array
 */
function getGraphParentTemplates(array $graphs, $flag) {
	$parent_graphids = [];
	$data = [
		'links' => [],
		'templates' => []
	];

	foreach ($graphs as $graph) {
		if ($graph['templateid'] != 0) {
			$parent_graphids[$graph['templateid']] = true;
			$data['links'][$graph['graphid']] = ['graphid' => $graph['templateid']];
		}
	}

	if (!$parent_graphids) {
		return $data;
	}

	$all_parent_graphids = [];
	$hostids = [];
	if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
		$lld_ruleids = [];
	}

	do {
		if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
			$db_graphs = API::GraphPrototype()->get([
				'output' => ['graphid', 'templateid'],
				'selectHosts' => ['hostid'],
				'selectDiscoveryRule' => ['itemid'],
				'graphids' => array_keys($parent_graphids)
			]);
		}
		// ZBX_FLAG_DISCOVERY_NORMAL
		else {
			$db_graphs = API::Graph()->get([
				'output' => ['graphid', 'templateid'],
				'selectHosts' => ['hostid'],
				'graphids' => array_keys($parent_graphids)
			]);
		}

		$all_parent_graphids += $parent_graphids;
		$parent_graphids = [];

		foreach ($db_graphs as $db_graph) {
			$data['templates'][$db_graph['hosts'][0]['hostid']] = [];
			$hostids[$db_graph['graphid']] = $db_graph['hosts'][0]['hostid'];

			if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$lld_ruleids[$db_graph['graphid']] = $db_graph['discoveryRule']['itemid'];
			}

			if ($db_graph['templateid'] != 0) {
				if (!array_key_exists($db_graph['templateid'], $all_parent_graphids)) {
					$parent_graphids[$db_graph['templateid']] = true;
				}

				$data['links'][$db_graph['graphid']] = ['graphid' => $db_graph['templateid']];
			}
		}
	}
	while ($parent_graphids);

	foreach ($data['links'] as &$parent_graph) {
		$parent_graph['hostid'] = array_key_exists($parent_graph['graphid'], $hostids)
			? $hostids[$parent_graph['graphid']]
			: 0;

		if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
			$parent_graph['lld_ruleid'] = array_key_exists($parent_graph['graphid'], $lld_ruleids)
				? $lld_ruleids[$parent_graph['graphid']]
				: 0;
		}
	}
	unset($parent_graph);

	$db_templates = $data['templates']
		? API::Template()->get([
			'output' => ['name'],
			'templateids' => array_keys($data['templates']),
			'preservekeys' => true
		])
		: [];

	$rw_templates = $db_templates
		? API::Template()->get([
			'output' => [],
			'templateids' => array_keys($db_templates),
			'editable' => true,
			'preservekeys' => true
		])
		: [];

	$data['templates'][0] = [];

	foreach ($data['templates'] as $hostid => &$template) {
		$template = array_key_exists($hostid, $db_templates)
			? [
				'hostid' => $hostid,
				'name' => $db_templates[$hostid]['name'],
				'permission' => array_key_exists($hostid, $rw_templates) ? PERM_READ_WRITE : PERM_READ
			]
			: [
				'hostid' => $hostid,
				'name' => _('Inaccessible template'),
				'permission' => PERM_DENY
			];
	}
	unset($template);

	return $data;
}

/**
 * Returns a template prefix for selected graph.
 *
 * @param string $graphid
 * @param array  $parent_templates  The list of the templates, prepared by getGraphParentTemplates() function.
 * @param int    $flag              Origin of the graph (ZBX_FLAG_DISCOVERY_NORMAL or ZBX_FLAG_DISCOVERY_PROTOTYPE).
 * @param bool   $provide_links     If this parameter is false, prefix will not contain links.
 *
 * @return array|null
 */
function makeGraphTemplatePrefix($graphid, array $parent_templates, $flag, bool $provide_links) {
	if (!array_key_exists($graphid, $parent_templates['links'])) {
		return null;
	}

	while (array_key_exists($parent_templates['links'][$graphid]['graphid'], $parent_templates['links'])) {
		$graphid = $parent_templates['links'][$graphid]['graphid'];
	}

	$template = $parent_templates['templates'][$parent_templates['links'][$graphid]['hostid']];

	if ($provide_links && $template['permission'] == PERM_READ_WRITE) {
		$url = (new CUrl('graphs.php'))->setArgument('context', 'template');

		if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
			$url->setArgument('parent_discoveryid', $parent_templates['links'][$graphid]['lld_ruleid']);
		}
		// ZBX_FLAG_DISCOVERY_NORMAL
		else {
			$url
				->setArgument('filter_set', '1')
				->setArgument('filter_hostids', [$template['hostid']]);
		}

		$name = (new CLink(CHtml::encode($template['name']), $url))->addClass(ZBX_STYLE_LINK_ALT);
	}
	else {
		$name = new CSpan(CHtml::encode($template['name']));
	}

	return [$name->addClass(ZBX_STYLE_GREY), NAME_DELIMITER];
}

/**
 * Returns a list of graph templates.
 *
 * @param string $graphid
 * @param array  $parent_templates  The list of the templates, prepared by getGraphParentTemplates() function.
 * @param int    $flag              Origin of the item (ZBX_FLAG_DISCOVERY_NORMAL or ZBX_FLAG_DISCOVERY_PROTOTYPE).
 * @param bool   $provide_links     If this parameter is false, prefix will not contain links.
 *
 * @return array
 */
function makeGraphTemplatesHtml($graphid, array $parent_templates, $flag, bool $provide_links) {
	$list = [];

	while (array_key_exists($graphid, $parent_templates['links'])) {
		$template = $parent_templates['templates'][$parent_templates['links'][$graphid]['hostid']];

		if ($provide_links && $template['permission'] == PERM_READ_WRITE) {
			$url = (new CUrl('graphs.php'))
				->setArgument('form', 'update')
				->setArgument('context', 'template');

			if ($flag == ZBX_FLAG_DISCOVERY_PROTOTYPE) {
				$url->setArgument('parent_discoveryid', $parent_templates['links'][$graphid]['lld_ruleid']);
			}

			$url->setArgument('graphid', $parent_templates['links'][$graphid]['graphid']);

			if ($flag == ZBX_FLAG_DISCOVERY_NORMAL) {
				$url->setArgument('hostid', $template['hostid']);
			}

			$name = new CLink(CHtml::encode($template['name']), $url);
		}
		else {
			$name = (new CSpan(CHtml::encode($template['name'])))->addClass(ZBX_STYLE_GREY);
		}

		array_unshift($list, $name, '&nbsp;&rArr;&nbsp;');

		$graphid = $parent_templates['links'][$graphid]['graphid'];
	}

	if ($list) {
		array_pop($list);
	}

	return $list;
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
			$items = API::Item()->get([
				'output' => ['key_'],
				'itemids' => [$gitem['itemid']],
				'webitems' => true
			]);

			$hosts = API::Host()->get([
				'output' => ['host'],
				'hostids' => [$destinationHostId],
				'templated_hosts' => true
			]);

			error(_s('Missing key "%1$s" for host "%2$s".', $items[0]['key_'], $hosts[0]['host']));

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

/**
 * Draws a text on an image. Supports TrueType fonts.
 *
 * @param resource 	$image
 * @param int		$fontsize
 * @param int 		$angle
 * @param int|float $x
 * @param int|float $y
 * @param int		$color		a numeric color identifier from imagecolorallocate() or imagecolorallocatealpha()
 * @param string	$string
 */
function imageText($image, $fontsize, $angle, $x, $y, $color, $string) {
	$x = (int) $x;
	$y = (int) $y;

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
* Yield suitable graph scale intervals.
*
* @param float $min        Minimum extreme of the scale.
* @param float $max        Maximum extreme of the scale.
* @param bool  $is_binary  Is the scale binary (use 1024 base for units)?
* @param int   $power      Scale power.
* @param int   $rows       Number of scale rows.
*
* @return iterable
*/
function yieldGraphScaleInterval(float $min, float $max, bool $is_binary, int $power, int $rows): iterable {
	$unit_base = $is_binary ? ZBX_KIBIBYTE : 1000;

	$divisor = pow($unit_base, $power);

	// Expression optimized to avoid overflow.
	$interval = truncateFloat($max / $divisor / $rows - $min / $divisor / $rows);

	while (true) {
		if ($is_binary && $interval >= 1) {
			$result = pow(2, ceil(log($interval, 2)));
		}
		else {
			$exponent = floor(log10($interval));

			foreach ([2, 5, 10] as $multiply) {
				$candidate = truncateFloat(pow(10, $exponent) * $multiply);
				if ($candidate >= $interval) {
					$result = $candidate;

					break;
				}
			}
		}

		yield $result * pow($unit_base, $power);

		$interval = $result * 1.5;
	}
}

/**
* Calculate graph scale extremes.
*
* @param float $data_min   Minimum extreme of the graph.
* @param float $data_max   Maximum extreme of the graph.
* @param bool  $is_binary  Is the scale binary (use 1024 base for units)?
* @param bool  $calc_power Should scale power be calculated?
* @param bool  $calc_min   Should scale minimum be calculated?
* @param bool  $calc_max   Should scale maximum be calculated?
* @param int   $rows_min   Minimum number of scale rows.
* @param int   $rows_max   Maximum number of scale rows.
*
* @return array|null
*/
function calculateGraphScaleExtremes(float $data_min, float $data_max, bool $is_binary, bool $calc_power,
		bool $calc_min, bool $calc_max, int $rows_min, int $rows_max): ?array {
	$scale_min = truncateFloat($data_min);
	$scale_max = truncateFloat($data_max);

	if ($scale_min >= $scale_max) {
		if ($scale_max > 0) {
			if ($calc_min) {
				$scale_min = 0;
			}
			elseif ($calc_max) {
				$scale_max = $scale_min < 0 ? 0 : ($scale_min == 0 ? 1 : $scale_min * 1.25);
			}
			else {
				return null;
			}
		}
		else {
			if ($calc_max) {
				$scale_max = $scale_min < 0 ? 0 : ($scale_min == 0 ? 1 : $scale_min * 1.25);
			}
			elseif ($calc_min) {
				$scale_min = $scale_max == 0 ? -1 : $scale_max * 1.25;
			}
			else {
				return null;
			}
		}
	}

	$power = $calc_power
		? (int) min(8, max(0, floor(log(max(abs($scale_min), abs($scale_max)), $is_binary ? ZBX_KIBIBYTE : 1000))))
		: 0;

	$best_result_value = null;
	$best_result = [
		'min' => $scale_min,
		'max' => $scale_max,
		// Expression optimized to avoid overflow.
		'interval' => $scale_max / 2 - $scale_min / 2,
		'rows' => 2,
		'power' => $power
	];

	for ($rows = $rows_min; $rows <= $rows_max; $rows++) {
		$clearance_min = $rows * 0.05;
		$clearance_max = $rows * 0.1;

		foreach (yieldGraphScaleInterval($scale_min, $scale_max, $is_binary, $power, $rows) as $interval) {
			if ($interval == INF) {
				break;
			}

			if ($calc_min) {
				$min = floor($scale_min / $interval) * $interval;
				if ($min != 0 && ($scale_min - $min) / $interval < $clearance_min) {
					$min -= $interval;
				}
				$max = $calc_max ? $min + $interval * $rows : $scale_max;
			}
			elseif ($calc_max) {
				$min = $scale_min;
				$max = ceil($scale_min / $interval + $rows) * $interval;
				if ($max != 0 && ($max - $scale_max) / $interval < $clearance_max) {
					$max += $interval;
				}
			}
			else {
				$min = $scale_min;
				$max = $scale_max;
			}

			$min = truncateFloat($min);
			$max = truncateFloat($max);

			if ($min > $scale_min || $max < $scale_max) {
				continue;
			}

			if ($calc_min && $min != 0 && ($scale_min - $min) / $interval < $clearance_min) {
				continue;
			}

			if ($calc_max && $max != 0 && ($max - $scale_max) / $interval < $clearance_max) {
				continue;
			}

			$result = [
				'min' => $min,
				'max' => $max,
				'interval' => $interval,
				'rows' => $rows,
				'power' => $power
			];

			$result_value = ($scale_min - $min) / $interval + ($max - $scale_max) / $interval;

			if ($best_result_value === null || $result_value < $best_result_value) {
				$best_result_value = $result_value;
				$best_result = $result;
			}

			break;
		}
	}

	return $best_result;
}

/**
* Calculate graph scale intermediate values.
*
* @param float  $min             Minimum extreme of the scale.
* @param float  $max             Maximum extreme of the scale.
* @param bool   $min_calculated  Is minimum extreme of the scale calculated?
* @param bool   $max_calculated  Is maximum extreme of the scale calculated?
* @param float  $interval        Scale interval.
* @param string $units           Scale units.
* @param bool   $is_binary       Is the scale binary (use 1024 base for units)?
* @param int    $power           Scale power.
* @param int    $precision_max   Maximum precision to use for the scale.
*
* @return array
*/
function calculateGraphScaleValues(float $min, float $max, bool $min_calculated, bool $max_calculated, float $interval,
		string $units, bool $is_binary, int $power, int $precision_max): array {
	$unit_base = $is_binary ? ZBX_KIBIBYTE : 1000;

	$units_length = ($units !== '' && $units !== '!')
		? ($power > 0 ? 1 : 0) + mb_strlen($units) + ($units[0] !== '!' ? 1 : 0)
		: ($power > 0 ? 1 : 0);

	$precision = max(3, $units_length == 0 ? $precision_max : ($precision_max - $units_length - ($min < 0 ? 1 : 0)));

	$decimals = min(ZBX_UNITS_ROUNDOFF_SUFFIXED, $precision - 1);
	$decimals_exact = false;

	$power_interval = $interval / pow($unit_base, $power);

	if ($power_interval < 1) {
		$decimals = getNumDecimals($power_interval);
		$decimals_exact = true;

		if ($decimals > $precision - 1) {
			$decimals = $precision - 1;
			$decimals_exact = false;
		}
	}

	$rows = [];

	$clearance = 0.5;
	for ($row_index = 0;; $row_index++) {
		$value = ceil($min / $interval + $row_index + $clearance) * $interval;

		if ($value > $max - $interval * $clearance) {
			break;
		}

		$rows[] = $value;
	}

	$ignore_milliseconds = ($min <= -1 || $max >= 1);

	$options = [
		'units' => $units,
		'unit_base' => $unit_base,
		'convert' => ITEM_CONVERT_NO_UNITS,
		'power' => $power,
		'ignore_milliseconds' => $ignore_milliseconds
	];
	$options_fixed = $options + [
		'precision' => $precision,
		'decimals' => $precision - 1,
		'decimals_exact' => false
	];
	$options_calculated = $options + [
		'precision' => $precision,
		'decimals' => $decimals,
		'decimals_exact' => $decimals_exact
	];

	$scale_values = [];

	$scale_values[] = [
		'relative_pos' => 0,
		'value' => convertUnits([
			'value' => $min
		] + ($min_calculated ? $options_calculated : $options_fixed))
	];

	foreach ($rows as $value) {
		$scale_values[] = [
			'relative_pos' => (abs($max - $min) == INF)
				? ($value / 10 - $min / 10) / ($max / 10 - $min / 10)
				: ($value - $min) / ($max - $min),
			'value' => convertUnits([
				'value' => $value
			] + $options_calculated)
		];
	}

	$scale_values[] = [
		'relative_pos' => 1,
		'value' => convertUnits([
			'value' => $max
		] + ($max_calculated ? $options_calculated : $options_fixed))
	];

	return $scale_values;
}

/**
 * @param string $short_item  Comma separated <short_field_name>:<value> pairs.
 *
 * @return array
 */
function expandShortGraphItem($short_item) {
	$map = [
		'gi' => 'gitemid',
		'it' => 'itemid',
		'so' => 'sortorder',
		'fl' => 'flags',
		'ty' => 'type',
		'dr' => 'drawtype',
		'ya' => 'yaxisside',
		'ca' => 'calc_fnc',
		'co' => 'color'
	];

	$item = [];

	foreach (explode(',', $short_item) as $short_field) {
		list($short_name, $value) = explode(':', $short_field);
		$item[$map[$short_name]] = $value;
	}

	return $item;
}
