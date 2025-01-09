<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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

		$name = (new CLink($template['name'], $url))->addClass(ZBX_STYLE_LINK_ALT);
	}
	else {
		$name = new CSpan($template['name']);
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

			$name = new CLink($template['name'], $url);
		}
		else {
			$name = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
		}

		array_unshift($list, $name, [NBSP(), RARR(), NBSP()]);

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
	$string = strtr($string, ['&' => '&#38;']);

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
	$string = strtr($string, ['&' => '&#38;']);

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
 * @param float  $min    Minimum extreme of the scale.
 * @param float  $max    Maximum extreme of the scale.
 * @param string $units  Scale units.
 * @param int    $power  Scale power (ignored for time units).
 * @param int    $rows   Number of scale rows.
 *
 * @return Generator
 */
function yieldGraphScaleInterval(float $min, float $max, string $units, int $power, int $rows): Generator {
	if ($units === 's') {
		return yield from yieldGraphScaleIntervalForSUnits($min, $max, $power, $rows);
	}

	$is_binary = isBinaryUnits($units);

	$base = getUnitsBase($units, $power);

	// Expression optimized to avoid overflow.
	$interval = truncateFloat($max / $rows - $min / $rows);

	while (true) {
		if ($is_binary && $interval >= $base) {
			$exponent = ceil(log($interval / $base, 2));

			while (true) {
				yield truncateFloat($base * pow(2, $exponent));

				$exponent++;
			}
		}

		$exponent = floor(log10($interval / $base));

		foreach ([1, 2, 5] as $multiplier) {
			$candidate = truncateFloat($base * pow(10, $exponent) * $multiplier);

			if ($candidate >= $interval) {
				yield $candidate;
			}
		}

		$interval = truncateFloat($base * pow(10, $exponent + 1));
	}
}

/**
 * Yield suitable graph scale intervals for time units.
 *
 * @param float $min    Minimum extreme of the scale.
 * @param float $max    Maximum extreme of the scale.
 * @param int   $power  Scale power (ignored for time units).
 * @param int   $rows   Number of scale rows.
 *
 * @return Generator
 */
function yieldGraphScaleIntervalForSUnits(float $min, float $max, int $power, int $rows): Generator {
	static $power_multipliers = [
		0 => [1, 2, 5, 10, 15, 20, 30],
		1 => [1, 2, 5, 10, 15, 20, 30],
		2 => [1, 2, 3, 4, 6, 12],
		3 => [1, 2, 5, 10, 15],
		4 => [1, 2, 3, 4, 6]
	];

	// Expression optimized to avoid overflow.
	$interval = truncateFloat($max / $rows - $min / $rows);

	while (true) {
		$use_power = $power == 5 ? 5 : getUnitsPower('s', $interval);
		$base = getUnitsBase('s', $use_power);

		if (array_key_exists($use_power, $power_multipliers)) {
			foreach ($power_multipliers[$use_power] as $multiplier) {
				$candidate = truncateFloat($base * $multiplier);

				if ($candidate >= $interval) {
					yield $candidate;
				}
			}

			$interval = getUnitsBase('s', $use_power + 1);

			continue;
		}

		$exponent = floor(log10($interval / $base));

		if ($exponent < 0 && $use_power == 5) {
			$exponent = 0;
		}

		foreach ([1, 2, 5] as $multiplier) {
			$candidate = truncateFloat($base * pow(10, $exponent) * $multiplier);

			if ($candidate >= $interval) {
				yield $candidate;
			}
		}

		$interval = truncateFloat($base * pow(10, $exponent + 1));
	}
}

/**
 * Calculate graph scale extremes.
 *
 * @param float  $data_min    Minimum extreme of the graph.
 * @param float  $data_max    Maximum extreme of the graph.
 * @param string $units       Scale units.
 * @param bool   $calc_power  Should scale power be calculated?
 * @param bool   $calc_min    Should scale minimum be calculated?
 * @param bool   $calc_max    Should scale maximum be calculated?
 * @param int    $rows_min    Minimum number of scale rows.
 * @param int    $rows_max    Maximum number of scale rows.
 *
 * @return array|null
 */
function calculateGraphScaleExtremes(float $data_min, float $data_max, string $units, bool $calc_power, bool $calc_min,
		bool $calc_max, int $rows_min, int $rows_max): ?array {
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

	$power = $calc_power ? getUnitsPower($units, max(abs($scale_min), abs($scale_max))) : 0;

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
		$clearance_min = min(0.5, $rows * 0.05);
		$clearance_max = min(1, $rows * 0.1);

		foreach (yieldGraphScaleInterval($scale_min, $scale_max, $units, $power, $rows) as $interval) {
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

			if (is_infinite($min) || is_infinite($max)) {
				break;
			}

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

			// Expression optimized to avoid overflow.
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
 * Calculate logarithmic graph scale extremes.
 *
 * @param float      $min       Minimum extreme of the graph.
 * @param float      $max       Maximum extreme of the graph.
 * @param float|null $min_pos   Minimum positive extreme of the graph.
 * @param float|null $max_neg   Maximum negative extreme of the graph.
 * @param bool       $calc_min  Scale minimum is calculated
 * @param bool       $calc_max  Scale maximum is calculated
 * @param int        $rows_min  Minimum number of scale rows.
 * @param int        $rows_max  Maximum number of scale rows.
 *
 * @return array
 */
function calculateLogarithmicGraphScaleExtremes(float $min, float $max, ?float $min_pos, ?float $max_neg,
		bool $calc_min, bool $calc_max, int $rows_min, int $rows_max): array {
	$pow_min_pos = null;
	$pow_max_pos = null;
	$pow_min_neg = null;
	$pow_max_neg = null;

	if ($max < $min) {
		if ($calc_max) {
			$max = $min;
		}
		else {
			$min = $max;
		}
	}

	if ($max == 0 && $min == 0) {
		if ($calc_max) {
			$max = 1;
		}
		else {
			$min = -1;
		}
	}

	if ($max > 0) {
		$pow_max_pos = ceil(log10($max));

		if (!$calc_min && $min > 0) {
			$pow_min_pos = floor(log10($min));
		}
		elseif ($min_pos !== null) {
			$pow_min_pos = floor(log10($min_pos));
		}
		else {
			$pow_min_pos = 0;
		}

		if ($min == 0) {
			$pow_min_pos = min(-1, $pow_min_pos);
		}

		if ($pow_max_pos <= $pow_min_pos) {
			if ($calc_min || $min <= 0) {
				$pow_min_pos = $pow_max_pos - 1;
			}
			else {
				$pow_max_pos = $pow_min_pos + 1;
			}
		}
	}

	if ($min < 0) {
		$pow_max_neg = ceil(log10(-$min));

		if (!$calc_max && $max < 0) {
			$pow_min_neg = floor(log10(-$max));
		}
		elseif ($max_neg !== null) {
			$pow_min_neg = floor(log10(-$max_neg));
		}
		else {
			$pow_min_neg = 0;
		}

		if ($max == 0) {
			$pow_min_neg = min(-1, $pow_min_neg);
		}

		if ($pow_max_neg <= $pow_min_neg) {
			if ($calc_max || $max >= 0) {
				$pow_min_neg = $pow_max_neg - 1;
			}
			else {
				$pow_max_neg = $pow_min_neg + 1;
			}
		}
	}

	if ($max > 0 && $min < 0) {
		$pow_zero = min(-1, $pow_min_pos, $pow_min_neg);
		$pow_min_pos = $pow_zero;
		$pow_min_neg = $pow_zero;
	}

	$variants = [];

	if ($max > 0 && $min < 0) {
		$pow_diff_pos = $pow_max_pos - $pow_min_pos;
		$pow_diff_neg = $pow_max_neg - $pow_min_neg;

		for ($rows = 2; $rows <= $rows_max; $rows++) {
			$rows_pos = min($rows - 1, max(1, round($pow_diff_pos / ($pow_diff_pos + $pow_diff_neg) * $rows)));
			$rows_neg = $rows - $rows_pos;

			$row_pow = max(ceil($pow_diff_pos / $rows_pos), ceil($pow_diff_neg / $rows_neg));

			$add_pos = $rows_pos * $row_pow - $pow_diff_pos;
			$add_neg = $rows_neg * $row_pow - $pow_diff_neg;

			$zoom_zero = min($add_pos, $add_neg);

			while ($zoom_zero > 0 && 10 ** ($pow_min_pos - $zoom_zero) == 0) {
				$zoom_zero--;
			}

			$add_pos -= $zoom_zero;
			$add_neg -= $zoom_zero;

			if (($add_pos > 0 && !$calc_max) || ($add_neg > 0 && !$calc_min)) {
				continue;
			}

			if (10 ** ($pow_max_pos + $add_pos) == INF || -10 ** ($pow_max_neg + $add_neg) == -INF) {
				continue;
			}

			$variants[] = [
				'rows' => $rows,
				'add_pos' => $add_pos,
				'add_neg' => $add_neg,
				'zoom_zero' => $zoom_zero
			];
		}
	}
	else {
		for ($rows = 1; $rows <= $rows_max; $rows++) {
			$add_pos = 0;
			$add_neg = 0;
			$zoom_zero = 0;

			if ($max > 0) {
				$add_pos = $rows * ceil(($pow_max_pos - $pow_min_pos) / $rows) - $pow_max_pos + $pow_min_pos;

				if ($calc_min) {
					$zoom_zero = $add_pos;

					while ($zoom_zero > 0 && 10 ** ($pow_min_pos - $zoom_zero) == 0) {
						$zoom_zero--;
					}

					$add_pos -= $zoom_zero;
				}

				if ($add_pos > 0 && (!$calc_max || 10 ** ($pow_max_pos + $add_pos) == INF)) {
					continue;
				}
			}
			else {
				$add_neg = $rows * ceil(($pow_max_neg - $pow_min_neg) / $rows) - $pow_max_neg + $pow_min_neg;

				if ($calc_max) {
					$zoom_zero = $add_neg;

					while ($zoom_zero > 0 && -10 ** ($pow_min_neg - $zoom_zero) == 0) {
						$zoom_zero--;
					}

					$add_neg -= $zoom_zero;
				}

				if ($add_neg > 0 && (!$calc_min || -10 ** ($pow_max_neg + $add_neg) == -INF)) {
					continue;
				}
			}

			$variants[] = [
				'rows' => $rows,
				'add_pos' => $add_pos,
				'add_neg' => $add_neg,
				'zoom_zero' => $zoom_zero
			];
		}
	}

	if ($variants) {
		$row_avg = ($rows_min + $rows_max) / 2;

		usort($variants, static function (array $a, array $b) use ($row_avg) {
			$a_expand = $a['add_pos'] + $a['add_neg'] + $a['zoom_zero'];
			$b_expand = $b['add_pos'] + $b['add_neg'] + $b['zoom_zero'];

			if ($a_expand != $b_expand) {
				return $a_expand <=> $b_expand;
			}

			return abs($row_avg - $a['rows']) <=> abs($row_avg - $b['rows']);
		});

		$best_variant = $variants[0];

		if ($max > 0) {
			$pow_min_pos -= $best_variant['zoom_zero'];
			$pow_max_pos += $best_variant['add_pos'];
		}

		if ($min < 0) {
			$pow_min_neg -= $best_variant['zoom_zero'];
			$pow_max_neg += $best_variant['add_neg'];
		}

		$rows = $best_variant['rows'];
		$interval = (($max > 0 ? $pow_max_pos - $pow_min_pos : 0) + ($min < 0 ? $pow_max_neg - $pow_min_neg : 0))
			/ $rows;
	}
	else {
		$rows = 0;
		$interval = 0;
	}

	$pow_shift_above = 0;
	$pow_shift_below = 0;

	if ($calc_max) {
		if ($max != 0) {
			$max = $max > 0 ? 10 ** $pow_max_pos : -10 ** $pow_min_neg;
		}
	}
	else {
		if ($max > 0) {
			$pow_shift_above = $pow_max_pos - log10($max);
			$pow_max_pos = log10($max);
		}
		elseif ($max < 0) {
			$pow_shift_above = log10(-$max) - $pow_min_neg;
			$pow_min_neg = log10(-$max);
		}
	}

	if ($calc_min) {
		if ($min != 0) {
			$min = $min < 0 ? -10 ** $pow_max_neg : 10 ** $pow_min_pos;
		}
	}
	else {
		if ($min < 0) {
			$pow_shift_below = $pow_max_neg - log10(-$min);
			$pow_max_neg = log10(-$min);
		}
		elseif ($min > 0) {
			$pow_shift_below = log10($min) - $pow_min_pos;
			$pow_min_pos = log10($min);
		}
	}

	return [
		'min' => $min,
		'max' => $max,
		'max_positive_power' => $pow_max_pos,
		'min_positive_power' => $pow_min_pos,
		'min_negative_power' => $pow_min_neg,
		'max_negative_power' => $pow_max_neg,
		'lower_power_shift' => $pow_shift_below,
		'upper_power_shift' => $pow_shift_above,
		'rows' => $rows,
		'interval' => $interval
	];
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
 * @param int    $power           Scale power.
 * @param int    $precision_max   Maximum precision to use for the scale.
 *
 * @return array
 */
function calculateGraphScaleValues(float $min, float $max, bool $min_calculated, bool $max_calculated, float $interval,
		string $units, int $power, int $precision_max): array {
	$rows = [];

	$clearance = 0.5;
	for ($row_index = 0;; $row_index++) {
		$value = ceil($min / $interval + $row_index + $clearance) * $interval;

		if ($value > $max - $interval * $clearance) {
			break;
		}

		$rows[] = $value;
	}

	$options = [
		'value' => 0,
		'units' => $units,
		'convert' => ITEM_CONVERT_NO_UNITS,
		'power' => $power,
		'ignore_milliseconds' => $min <= -1 || $max >= 1
	];
	$options_fixed = $options;
	$options_calculated = $options;

	$pre_conversion = convertUnitsRaw($options);

	if ($pre_conversion['is_numeric'] || ($units === 's' && $power == -1)) {
		$precision = max(3,
			$pre_conversion['units'] !== ''
				? $precision_max - 1 - mb_strlen($pre_conversion['units'])
				: $precision_max
		);

		$decimals = min(ZBX_UNITS_ROUNDOFF_SUFFIXED, $precision - 1);
		$decimals_exact = false;

		$power_interval = $interval / getUnitsBase($units, $power);

		if ($power_interval < 1) {
			$decimals = getNumDecimals($power_interval);
			$decimals_exact = true;

			if ($decimals > $precision - 1) {
				$decimals = $precision - 1;
				$decimals_exact = false;
			}
		}

		$options_fixed += [
			'precision' => $precision,
			'decimals' => $precision - 1,
			'decimals_exact' => false
		];

		$options_calculated += [
			'precision' => $precision,
			'decimals' => $decimals,
			'decimals_exact' => $decimals_exact
		];
	}

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
 * Calculate graph scale intermediate values.
 *
 * @param float|null $min_negative_power Minimum negative power extreme of the scale.
 * @param float|null $max_negative_power Maximum negative power extreme of the scale.
 * @param float|null $min_positive_power Minimum positive power extreme of the scale.
 * @param float|null $max_positive_power Maximum positive power extreme of the scale.
 * @param bool       $has_zero           Does scale have zero?
 * @param bool       $min_calculated     Is minimum extreme of the scale calculated?
 * @param bool       $max_calculated     Is maximum extreme of the scale calculated?
 * @param int        $interval           Scale interval.
 * @param string     $units              Scale units.
 * @param bool       $is_binary          Is the scale binary (use 1024 base for units)?
 * @param int        $precision_max      Maximum precision to use for the scale.
 * @param float|null $lower_power_shift  Scale bottom positive power shift extreme.
 * @param float|null $upper_power_shift  Scale top positive power shift extreme.
 *
 * @return array
 */
function calculateLogarithmicGraphScaleValues(?float $min_negative_power, ?float $max_negative_power,
		?float $min_positive_power, ?float $max_positive_power, bool $has_zero, bool $min_calculated,
		bool $max_calculated, int $interval, string $units, int $precision_max, ?float $lower_power_shift,
		?float $upper_power_shift): array {
	$rows = [];

	$start_index = 0;
	$scale_overall = 0;
	$min_position = 0;
	$clearance = 0.5;

	// Negative scale calculation
	if ($min_negative_power !== null) {
		$scale_overall += $max_negative_power - $min_negative_power;
		$start_power = $max_negative_power;
		$min_position = -1 * ($max_negative_power - $min_negative_power);

		if ($lower_power_shift > 0) {
			$rows[] = [
				'log_value' => -1 * ($max_negative_power - $min_negative_power),
				'value' => -10 ** $max_negative_power
			];

			$start_index++;
			$start_power += $lower_power_shift;

			if ($lower_power_shift > $clearance * $interval) {
				$start_index++;
			}
		}

		if ($interval > 0) {
			for ($row_index = $start_index;; $row_index++) {
				$log_value = $start_power - $row_index * $interval;

				if (!$has_zero && $upper_power_shift > 0 && $start_index > 0
						&& $log_value - $clearance * $interval < $min_negative_power) {
					break;
				}

				if ($log_value < $min_negative_power) {
					break;
				}

				if ($has_zero && $log_value <= $min_negative_power) {
					break;
				}

				$rows[] = [
					'log_value' => -1 * ($log_value - $min_negative_power),
					'value' => -10 ** $log_value
				];
			}
		}

		if ($upper_power_shift > 0 && !$has_zero) {
			$rows[] = [
				'log_value' => 1,
				'value' => -10 ** $min_negative_power
			];
		}
	}

	$start_index = 0;

	// 0 scale calculation
	if ($has_zero) {
		$rows[] = [
			'log_value' => 0,
			'value' => 0
		];

		if ($min_negative_power === null) {
			$min_position = 0;
		}

		$start_index++;
	}


	// Positive scale calculation
	if ($min_positive_power !== null) {
		$start_power = $min_positive_power;
		$scale_overall += $max_positive_power - $min_positive_power;

		if (!$has_zero) {
			$min_position = 0;
		}

		if ($lower_power_shift > 0 && !$has_zero) {
			$rows[] = [
				'log_value' => 0,
				'value' => 10 ** $min_positive_power
			];

			$start_index++;
			$start_power -= $lower_power_shift;

			if ($lower_power_shift > $clearance * $interval
					&& $max_positive_power - $min_positive_power > $clearance * $interval) {
				$start_index++;
			}
		}

		if ($interval > 0) {
			for ($row_index = $start_index;; $row_index++) {
				$log_value = $start_power + $row_index * $interval;

				if ($upper_power_shift > 0 && $start_index > 0
						&& $log_value > $max_positive_power - $clearance * $interval) {
					break;
				}

				if ($log_value > $max_positive_power) {
					break;
				}

				$rows[] = [
					'log_value' => $log_value - $min_positive_power,
					'value' => 10 ** $log_value
				];
			}
		}

		if ($upper_power_shift > 0) {
			$rows[] = [
				'log_value' => $max_positive_power - $min_positive_power,
				'value' => 10 ** $max_positive_power
			];
		}
	}

	$ignore_milliseconds = (($min_negative_power !== null && $min_negative_power >= 0)
			|| ($min_positive_power !== null && $min_positive_power >= 0));

	$options = [
		'units' => $units,
		'convert' => ITEM_CONVERT_NO_UNITS,
		'ignore_milliseconds' => $ignore_milliseconds
	];
	$options_fixed = $options;
	$options_calculated = $options;

	$pre_conversion = convertUnitsRaw($options);

	if ($pre_conversion['is_numeric']) {
		$precision = max(3,
			$pre_conversion['units'] !== ''
				? $precision_max - 1 - mb_strlen($pre_conversion['units'])
				: $precision_max
		);

		$decimals = min(ZBX_UNITS_ROUNDOFF_SUFFIXED, $precision - 1);
		$decimals_exact = false;

		$options_fixed += [
			'precision' => $precision,
			'decimals' => $precision - 1,
			'decimals_exact' => false
		];

		$options_calculated += [
			'precision' => $precision,
			'decimals' => $decimals,
			'decimals_exact' => $decimals_exact
		];
	}

	$scale_values = [];

	$scale_values[] = [
		'relative_pos' => 0,
		'value' => convertUnits([
			'value' => $rows[0]['value']
		] + ($min_calculated ? $options_calculated : $options_fixed))
	];

	foreach (array_slice($rows, 1, count($rows) - 2) as $row) {
		$scale_values[] = [
			'relative_pos' => ($row['log_value'] - $min_position) / $scale_overall,
			'value' => convertUnits([
				'value' => $row['value']
			] + $options_calculated)
		];
	}

	$scale_values[] = [
		'relative_pos' => 1,
		'value' => convertUnits([
			'value' => $rows[count($rows) - 1]['value']
		] + ($max_calculated ? $options_calculated : $options_fixed))
	];

	return $scale_values;
}

/**
 * Calculate value relative position on logarithmic scale.
 *
 * @param float|null $max_negative_power  Negative scale part maximum power.
 * @param float|null $min_negative_power  Negative scale part minimum power.
 * @param float|null $min_positive_power  Positive scale part minimum power.
 * @param float|null $max_positive_power  Positive scale part maximum power.
 * @param float      $value               Value to which the relative position should be calculated.
 *
 * @return float
 */
function calculateLogarithmicRelativePosition(?float $max_negative_power, ?float $min_negative_power,
		?float $min_positive_power, ?float $max_positive_power, float $value): float {
	$converted_value = $value != 0 ? log10(abs($value)) : null;
	$sign = $value >= 0 ? 1 : -1;

	$scale_overall = 0;
	$negative_scale_difference = 0;
	$positive_scale_difference = 0;
	$top = 0;

	if ($min_negative_power !== null && $max_negative_power !== null) {
		$scale_overall += $max_negative_power - $min_negative_power;
		$negative_scale_difference = $min_negative_power;
	}

	if ($min_positive_power !== null && $max_positive_power !== null) {
		$scale_overall += $max_positive_power - $min_positive_power;
		$positive_scale_difference = $min_positive_power;
		$top = $max_positive_power - $min_positive_power;
	}

	if ($converted_value === null) {
		return $top / $scale_overall;
	}

	$scale_difference = $sign === 1 ? $positive_scale_difference : $negative_scale_difference;

	return ($top - ($sign * ($converted_value - $scale_difference))) / $scale_overall;
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
