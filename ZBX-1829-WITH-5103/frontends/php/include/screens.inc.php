<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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


require_once dirname(__FILE__).'/events.inc.php';
require_once dirname(__FILE__).'/actions.inc.php';
require_once dirname(__FILE__).'/js.inc.php';

function screen_resources($resource = null) {
	$resources = array(
		SCREEN_RESOURCE_CLOCK => _('Clock'),
		SCREEN_RESOURCE_DATA_OVERVIEW => _('Data overview'),
		SCREEN_RESOURCE_GRAPH => _('Graph'),
		SCREEN_RESOURCE_ACTIONS => _('History of actions'),
		SCREEN_RESOURCE_EVENTS => _('History of events'),
		SCREEN_RESOURCE_HOSTS_INFO => _('Hosts info'),
		SCREEN_RESOURCE_MAP => _('Map'),
		SCREEN_RESOURCE_PLAIN_TEXT => _('Plain text'),
		SCREEN_RESOURCE_SCREEN => _('Screen'),
		SCREEN_RESOURCE_SERVER_INFO => _('Server info'),
		SCREEN_RESOURCE_SIMPLE_GRAPH => _('Simple graph'),
		SCREEN_RESOURCE_HOSTGROUP_TRIGGERS => _('Status of hostgroup triggers'),
		SCREEN_RESOURCE_HOST_TRIGGERS => _('Status of host triggers'),
		SCREEN_RESOURCE_SYSTEM_STATUS => _('System status'),
		SCREEN_RESOURCE_TRIGGERS_INFO => _('Triggers info'),
		SCREEN_RESOURCE_TRIGGERS_OVERVIEW => _('Triggers overview'),
		SCREEN_RESOURCE_URL => _('Url')
	);

	if (is_null($resource)) {
		natsort($resources);
		return $resources;
	}
	elseif (isset($resources[$resource])) {
		return $resources[$resource];
	}
	else {
		return _('Unknown');
	}
}

function get_screen_by_screenid($screenid) {
	$dbScreen = DBfetch(DBselect('SELECT s.* FROM screens s WHERE s.screenid='.$screenid));
	return !empty($dbScreen) ? $dbScreen : false;
}

function check_screen_recursion($mother_screenid, $child_screenid) {
	if (bccomp($mother_screenid , $child_screenid) == 0) {
		return true;
	}

	$db_scr_items = DBselect(
		'SELECT si.resourceid'.
		' FROM screens_items si'.
		' WHERE si.screenid='.$child_screenid.
		' AND si.resourcetype='.SCREEN_RESOURCE_SCREEN
	);
	while ($scr_item = DBfetch($db_scr_items)) {
		if (check_screen_recursion($mother_screenid, $scr_item['resourceid'])) {
			return true;
		}
	}
	return false;
}

function get_slideshow($slideshowid, $step) {
	$db_slides = DBfetch(DBselect(
		'SELECT MIN(s.step) AS min_step,MAX(s.step) AS max_step'.
		' FROM slides s'.
		' WHERE s.slideshowid='.$slideshowid
	));
	if (!$db_slides || is_null($db_slides['min_step'])) {
		return false;
	}

	$step = $step % ($db_slides['max_step'] + 1);
	if (!isset($step) || $step < $db_slides['min_step'] || $step > $db_slides['max_step']) {
		$curr_step = $db_slides['min_step'];
	}
	else {
		$curr_step = $step;
	}

	return DBfetch(DBselect(
		'SELECT sl.*'.
		' FROM slides sl,slideshows ss'.
		' WHERE ss.slideshowid='.$slideshowid.
			' AND sl.slideshowid=ss.slideshowid'.
			' AND sl.step='.$curr_step
	));
}

function slideshow_accessible($slideshowid, $perm) {
	$result = false;

	$sql = 'SELECT s.slideshowid'.
			' FROM slideshows s'.
			' WHERE s.slideshowid='.$slideshowid.
				' AND '.DBin_node('s.slideshowid', get_current_nodeid(null, $perm)
	);
	if (DBselect($sql)) {
		$result = true;

		$screenids = array();
		$db_screens = DBselect(
			'SELECT DISTINCT s.screenid'.
			' FROM slides s'.
			' WHERE s.slideshowid='.$slideshowid
		);
		while ($slide_data = DBfetch($db_screens)) {
			$screenids[$slide_data['screenid']] = $slide_data['screenid'];
		}

		$options = array(
			'screenids' => $screenids
		);
		if ($perm == PERM_READ_WRITE) {
			$options['editable'] = true;
		}
		$screens = API::Screen()->get($options);
		$screens = zbx_toHash($screens, 'screenid');

		foreach ($screenids as $screenid) {
			if (!isset($screens[$screenid])) {
				return false;
			}
		}
	}
	return $result;
}

function get_slideshow_by_slideshowid($slideshowid) {
	return DBfetch(DBselect('SELECT s.* FROM slideshows s WHERE s.slideshowid='.$slideshowid));
}

function add_slideshow($name, $delay, $slides) {
	// validate slides
	if (empty($slides)) {
		error(_('Slide show must contain slides.'));
		return false;
	}

	// validate screens
	$screenids = zbx_objectValues($slides, 'screenid');
	$screens = API::Screen()->get(array(
		'screenids' => $screenids,
		'output' => API_OUTPUT_SHORTEN
	));
	$screens = ZBX_toHash($screens, 'screenid');
	foreach ($screenids as $screenid) {
		if (!isset($screens[$screenid])) {
			error(_('Incorrect screen provided for slide show.'));
			return false;
		}
	}

	// validate slide name
	$sql = 'SELECT s.slideshowid FROM slideshows s WHERE s.name='.zbx_dbstr($name);
	$db_slideshow = DBfetch(DBselect($sql, 1));
	if (!empty($db_slideshow)) {
		error(_s('Slide show "%s" already exists.', $name));
		return false;
	}

	$slideshowid = get_dbid('slideshows', 'slideshowid');
	$result = DBexecute(
		'INSERT INTO slideshows (slideshowid,name,delay)'.
		' VALUES ('.$slideshowid.','.zbx_dbstr($name).','.$delay.')'
	);

	// create slides
	$i = 0;
	foreach ($slides as $slide) {
		$slideid = get_dbid('slides', 'slideid');

		// set default delay
		if (empty($slide['delay'])) {
			$slide['delay'] = 0;
		}

		$result = DBexecute(
			'INSERT INTO slides (slideid,slideshowid,screenid,step,delay)'.
			' VALUES ('.$slideid.','.$slideshowid.','.$slide['screenid'].','.($i++).','.$slide['delay'].')'
		);
		if (!$result) {
			return false;
		}
	}
	return $slideshowid;
}

function update_slideshow($slideshowid, $name, $delay, $slides) {
	// validate slides
	if (empty($slides)) {
		error(_('Slide show must contain slides.'));
		return false;
	}

	// validate screens
	$screenids = zbx_objectValues($slides, 'screenid');
	$screens = API::Screen()->get(array(
		'screenids' => $screenids,
		'output' => API_OUTPUT_SHORTEN
	));
	$screens = ZBX_toHash($screens, 'screenid');
	foreach ($screenids as $screenid) {
		if (!isset($screens[$screenid])) {
			error(_('Incorrect screen provided for slide show.'));
			return false;
		}
	}

	// validate slide name
	$sql = 'SELECT s.slideshowid FROM slideshows s WHERE s.name='.zbx_dbstr($name).' AND s.slideshowid<>'.$slideshowid;
	$db_slideshow = DBfetch(DBselect($sql, 1));
	if (!empty($db_slideshow)) {
		error(_s('Slide show "%s" already exists.', $name));
		return false;
	}

	$db_slideshow = DBfetchArray(DBselect('SELECT * FROM slideshows WHERE slideshowid='.$slideshowid));
	$db_slideshow = $db_slideshow[0];
	$changed = false;
	$slideshow = array('name' => $name, 'delay' => $delay);
	foreach ($slideshow as $key => $val) {
		if ($db_slideshow[$key] != $val) {
			$changed = true;
			break;
		}
	}
	if ($changed) {
		if (!$result = DBexecute('UPDATE slideshows SET name='.zbx_dbstr($name).',delay='.$delay.' WHERE slideshowid='.$slideshowid)) {
			return false;
		}
	}

	// get slides
	$db_slides = DBfetchArrayAssoc(DBselect('SELECT s.* FROM slides s WHERE s.slideshowid='.$slideshowid), 'slideid');

	$slidesToDel = zbx_objectValues($db_slides, 'slideid');
	$slidesToDel = zbx_toHash($slidesToDel);
	$step = 0;
	foreach ($slides as $slide) {
		$slide['delay'] = $slide['delay'] ? $slide['delay'] : 0;
		if (isset($db_slides[$slide['slideid']])) {
			// update slide
			if ($db_slides[$slide['slideid']]['delay'] != $slide['delay'] || $db_slides[$slide['slideid']]['step'] != $step) {
				$result = DBexecute('UPDATE slides SET  step='.$step.', delay='.$slide['delay'].' WHERE slideid='.$slide['slideid']);
			}
			// do nothing with slide
			else {
				$result = true;
			}
			unset($slidesToDel[$slide['slideid']]);
		}
		// insert slide
		else {
			$slideid = get_dbid('slides', 'slideid');
			$result = DBexecute(
				'INSERT INTO slides (slideid,slideshowid,screenid,step,delay)'.
				' VALUES ('.$slideid.','.$slideshowid.','.$slide['screenid'].','.$step.','.$slide['delay'].')'
			);
		}
		$step ++;
		if (!$result) {
			return false;
		}
	}

	// delete unnecessary slides
	if (!empty($slidesToDel)) {
		DBexecute('DELETE FROM slides WHERE slideid IN('.implode(',', $slidesToDel).')');
	}

	return true;
}

function delete_slideshow($slideshowid) {
	$result = DBexecute('DELETE FROM slideshows where slideshowid='.$slideshowid);
	$result &= DBexecute('DELETE FROM slides where slideshowid='.$slideshowid);
	$result &= DBexecute('DELETE FROM profiles WHERE idx=\'web.favorite.screenids\' AND source=\'slideshowid\' AND value_id='.$slideshowid);
	return $result;
}

// show screen cell containing plain text values
function get_screen_plaintext($itemid, $elements, $style = 0) {
	if ($itemid == 0) {
		$table = new CTableInfo(_('Item does not exist.'));
		$table->setHeader(array(_('Timestamp'), _('Item')));
		return $table;
	}

	$item = get_item_by_itemid($itemid);
	switch ($item['value_type']) {
		case ITEM_VALUE_TYPE_TEXT:
		case ITEM_VALUE_TYPE_LOG:
			$order_field = 'id';
			break;
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
		default:
			$order_field = array('itemid', 'clock');
	}

	$host = get_host_by_itemid($itemid);

	$table = new CTableInfo();
	$table->setHeader(array(_('Timestamp'), $host['name'].': '.itemName($item)));

	$hData = API::History()->get(array(
		'history' => $item['value_type'],
		'itemids' => $itemid,
		'output' => API_OUTPUT_EXTEND,
		'sortorder' => ZBX_SORT_DOWN,
		'sortfield' => $order_field,
		'limit' => $elements
	));
	foreach ($hData as $data) {
		switch ($item['value_type']) {
			case ITEM_VALUE_TYPE_TEXT:
				// do not use break
			case ITEM_VALUE_TYPE_STR:
				if ($style) {
					$value = new CJSscript($data['value']);
				}
				else {
					$value = $data['value'];
				}
				break;
			case ITEM_VALUE_TYPE_LOG:
				if ($style) {
					$value = new CJSscript($data['value']);
				}
				else {
					$value = $data['value'];
				}
				break;
			default:
				$value = $data['value'];
				break;
		}

		if ($item['valuemapid'] > 0) {
			$value = applyValueMap($value, $item['valuemapid']);
		}

		$table->addRow(
			array(
				zbx_date2str(_('d M Y H:i:s'), $data['clock']),
				new CCol($value, 'pre')
			)
		);
	}
	return $table;
}

// check whether there are dynamic items in the screen, if so return TRUE, else FALSE
function check_dynamic_items($elid, $config = 0) {
	if ($config == 0) {
		$sql = 'SELECT si.screenitemid'.
				' FROM screens_items si'.
				' WHERE si.screenid='.$elid.
					' AND si.dynamic='.SCREEN_DYNAMIC_ITEM;
	}
	else {
		$sql = 'SELECT si.screenitemid'.
				' FROM slides s,screens_items si'.
				' WHERE s.slideshowid='.$elid.
					' AND si.screenid=s.screenid'.
					' AND si.dynamic='.SCREEN_DYNAMIC_ITEM;
	}
	if (DBfetch(DBselect($sql, 1))) {
		return true;
	}
	return false;
}

// editmode: 0 - view with actions, 1 - edit mode, 2 - view without any actions
function get_screen($screen, $editmode, $effectiveperiod = null) {
	if (is_null($effectiveperiod)) {
		$effectiveperiod = ZBX_MIN_PERIOD;
	}

	if (!$screen) {
		return new CTableInfo(_('No screens defined.'));
	}

	$skip_field = array();
	$screenItems = array();

	foreach ($screen['screenitems'] as $screenItem) {
		$screenItems[] = $screenItem;
		for ($i = 0; $i < $screenItem['rowspan'] || $i == 0; $i++) {
			for ($j = 0; $j < $screenItem['colspan'] || $j == 0; $j++) {
				if ($i != 0 || $j != 0) {
					if (!isset($skip_field[$screenItem['y'] + $i])) {
						$skip_field[$screenItem['y'] + $i] = array();
					}
					$skip_field[$screenItem['y'] + $i][$screenItem['x'] + $j] = 1;
				}
			}
		}
	}

	$table = new CTable(
		new CLink(_('No rows in screen.').SPACE.$screen['name'], 'screenconf.php?config=0&form=update&screenid='.$screen['screenid']),
		($editmode == 0 || $editmode == 2) ? 'screen_view' : 'screen_edit'
	);
	$table->setAttribute('id', 'iframe');

	if ($editmode == 1) {
		$new_cols = array(new CCol(new CImg('images/general/zero.gif', 'zero', 1, 1)));
		for ($c = 0; $c < $screen['hsize'] + 1; $c++) {
			$add_icon = new CImg('images/general/closed.gif', null, null, null, 'pointer');
			$add_icon->addAction('onclick', 'javascript: location.href = \'screenedit.php?config=1&screenid='.$screen['screenid'].'&add_col='.$c.'\';');
			array_push($new_cols, new CCol($add_icon));
		}
		$table->addRow($new_cols);
	}

	$empty_screen_col = array();

	for ($r = 0; $r < $screen['vsize']; $r++) {
		$new_cols = array();
		$empty_screen_row = true;

		if ($editmode == 1) {
			$add_icon = new CImg('images/general/closed.gif', null, null, null, 'pointer');
			$add_icon->addAction('onclick', 'javascript: location.href = \'screenedit.php?config=1&screenid='.$screen['screenid'].'&add_row='.$r.'\';');
			array_push($new_cols, new CCol($add_icon));
		}

		for ($c = 0; $c < $screen['hsize']; $c++) {
			if (isset($skip_field[$r][$c])) {
				continue;
			}
			$item_form = false;

			$screenItem = false;
			foreach ($screenItems as $tmprow) {
				if ($tmprow['x'] == $c && $tmprow['y'] == $r) {
					$screenItem = $tmprow;
					break;
				}
			}

			if ($screenItem) {
				$screenitemid = $screenItem['screenitemid'];
				$resourcetype = $screenItem['resourcetype'];
				$resourceid = $screenItem['resourceid'];
				$width = $screenItem['width'];
				$height = $screenItem['height'];
				$colspan = $screenItem['colspan'];
				$rowspan = $screenItem['rowspan'];
				$elements = $screenItem['elements'];
				$valign = $screenItem['valign'];
				$halign = $screenItem['halign'];
				$style = $screenItem['style'];
				$url = $screenItem['url'];
				$dynamic = $screenItem['dynamic'];
				$sort_triggers = $screenItem['sort_triggers'];
			}
			else {
				$screenitemid = 0;
				$resourcetype = 0;
				$resourceid = 0;
				$width = 0;
				$height = 0;
				$colspan = 1;
				$rowspan = 1;
				$elements = 0;
				$valign = VALIGN_DEFAULT;
				$halign = HALIGN_DEFAULT;
				$style = 0;
				$url = '';
				$dynamic = 0;
				$sort_triggers = SCREEN_SORT_TRIGGERS_DATE_DESC;
			}

			if ($screenitemid > 0) {
				$empty_screen_row = false;
				$empty_screen_col[$c] = 1;
			}

			if ($editmode == 1 && $screenitemid != 0) {
				$action = 'screenedit.php?form=update'.url_param('screenid').'&screenitemid='.$screenitemid;
			}
			elseif ($editmode == 1 && $screenitemid == 0) {
				$action = 'screenedit.php?form=update'.url_param('screenid').'&x='.$c.'&y='.$r;
			}
			else {
				$action = null;
			}

			/*
			 * Edit form
			 */
			if ($editmode == 1 && isset($_REQUEST['form'])
					&& isset($_REQUEST['x']) && $_REQUEST['x'] == $c
					&& isset($_REQUEST['y']) && $_REQUEST['y'] == $r) {
				$screenView = new CView('configuration.screen.constructor.edit', array('screen' => $screen));
				$item = $screenView->render();
				$item_form = true;
			}
			elseif ($editmode == 1 && isset($_REQUEST['form'])
					&& isset($_REQUEST['screenitemid']) && bccomp($_REQUEST['screenitemid'], $screenitemid) == 0) {
				$screenView = new CView('configuration.screen.constructor.edit', array('screen' => $screen));
				$item = $screenView->render();
				$item_form = true;
			}
			/*
			 * Graph
			 */
			elseif ($screenitemid != 0  && $resourcetype == SCREEN_RESOURCE_GRAPH) {
				if ($editmode == 0) {
					$action = 'charts.php?graphid='.$resourceid.url_param('period').url_param('stime');
				}

				// GRAPH & ZOOM features
				$dom_graph_id = 'graph_'.$screenitemid.'_'.$resourceid;
				$containerid = 'graph_cont_'.$screenitemid.'_'.$resourceid;
				$graphDims = getGraphDims($resourceid);
				$graphDims['graphHeight'] = $height;
				$graphDims['width'] = $width;
				$graph = get_graph_by_graphid($resourceid);
				$graphid = $graph['graphid'];
				$legend = $graph['show_legend'];
				$graph3d = $graph['show_3d'];

				// host feature
				if ($dynamic == SCREEN_DYNAMIC_ITEM && isset($_REQUEST['hostid']) && $_REQUEST['hostid'] > 0) {
					$hosts = API::Host()->get(array(
						'hostids' => $_REQUEST['hostid'],
						'output' => array('hostid', 'host')
					));
					$host = reset($hosts);

					$graph = API::Graph()->get(array(
						'graphids' => $resourceid,
						'output' => API_OUTPUT_EXTEND,
						'selectHosts' => API_OUTPUT_REFER,
						'selectGraphItems' => API_OUTPUT_EXTEND
					));
					$graph = reset($graph);

					if (count($graph['hosts']) == 1) {
						// if items from one host we change them, or set calculated if not exist on that host
						if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymax_itemid']) {
							$new_dinamic = get_same_graphitems_for_host(
								array(array('itemid' => $graph['ymax_itemid'])),
								$_REQUEST['hostid'],
								false // false = don't rise Error if item doesn't exist
							);
							$new_dinamic = reset($new_dinamic);
							if (isset($new_dinamic['itemid']) && $new_dinamic['itemid'] > 0) {
								$graph['ymax_itemid'] = $new_dinamic['itemid'];
							}
							else {
								$graph['ymax_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
							}
						}
						if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymin_itemid']) {
							$new_dinamic = get_same_graphitems_for_host(
								array(array('itemid' => $graph['ymin_itemid'])),
								$_REQUEST['hostid'],
								false // false = don't rise Error if item doesn't exist
							);
							$new_dinamic = reset($new_dinamic);
							if (isset($new_dinamic['itemid']) && $new_dinamic['itemid'] > 0) {
								$graph['ymin_itemid'] = $new_dinamic['itemid'];
							}
							else {
								$graph['ymin_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
							}
						}
					}

					$url = ($graph['graphtype'] == GRAPH_TYPE_PIE || $graph['graphtype'] == GRAPH_TYPE_EXPLODED)
							? 'chart7.php'
							: 'chart3.php';
					$url = new Curl($url);
					foreach ($graph as $name => $value) {
						if ($name == 'width' || $name == 'height') {
							continue;
						}
						$url->setArgument($name, $value);
					}

					$new_items = get_same_graphitems_for_host($graph['gitems'], $_REQUEST['hostid'], false);
					foreach ($new_items as $gitem) {
						unset($gitem['gitemid'], $gitem['graphid']);

						foreach ($gitem as $name => $value) {
							$url->setArgument('items['.$gitem['itemid'].']['.$name.']', $value);
						}
					}
					$url->setArgument('name', $host['host'].': '.$graph['name']);
					$url = $url->getUrl();
				}

				$objData = array(
					'id' => $resourceid,
					'domid' => $dom_graph_id,
					'containerid' => $containerid,
					'objDims' => $graphDims,
					'loadSBox' => 0,
					'loadImage' => 1,
					'loadScroll' => 0,
					'dynamic' => 0,
					'periodFixed' => CProfile::get('web.screens.timelinefixed', 1),
					'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
				);

				$default = false;
				if ($graphDims['graphtype'] == GRAPH_TYPE_PIE || $graphDims['graphtype'] == GRAPH_TYPE_EXPLODED) {
					if ($dynamic == SCREEN_SIMPLE_ITEM || empty($url)) {
						$url='chart6.php?graphid='.$resourceid;
						$default = true;
					}

					$timeline = array();
					$timeline['period'] = $effectiveperiod;
					$timeline['starttime'] = date('YmdHis', get_min_itemclock_by_graphid($resourceid));

					if (isset($_REQUEST['stime'])) {
						$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
					}

					$src = $url.'&width='.$width.'&height='.$height.'&legend='.$legend.'&graph3d='.$graph3d.'&period='.$effectiveperiod.url_param('stime');

					$objData['src'] = $src;
				}
				else {
					if ($dynamic == SCREEN_SIMPLE_ITEM || empty($url)) {
						$url = 'chart2.php?graphid='.$resourceid;
						$default = true;
					}

					$src = $url.'&width='.$width.'&height='.$height.'&period='.$effectiveperiod.url_param('stime');

					$timeline = array();
					if (isset($graphid) && !is_null($graphid) && $editmode != 1) {
						$timeline['period'] = $effectiveperiod;
						$timeline['starttime'] = date('YmdHis', time() - ZBX_MAX_PERIOD);

						if (isset($_REQUEST['stime'])) {
							$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
						}
						if ($editmode == 0) {
							$objData['loadSBox'] = 1;
						}
					}
					$objData['src'] = $src;
				}

				if ($editmode || !$default) {
					$item = new CDiv();
				}
				else {
					$item = new CLink(null, $action);
				}

				$item->setAttribute('id', $containerid);

				$item = array($item);
				if ($editmode == 1) {
					$item[] = BR();
					$item[] = new CLink(_('Change'), $action);
				}

				if ($editmode == 2) {
					insert_js('timeControl.addObject("'.$dom_graph_id.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($objData).');');
				}
				else {
					zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($objData).');');
				}
			}
			/*
			 * Simple graph
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_SIMPLE_GRAPH) {
				$dom_graph_id = 'graph_'.$screenitemid.'_'.$resourceid;
				$containerid = 'graph_cont_'.$screenitemid.'_'.$resourceid;

				$graphDims = getGraphDims();
				$graphDims['graphHeight'] = $height;
				$graphDims['width'] = $width;

				$objData = array(
					'id' => $resourceid,
					'domid' => $dom_graph_id,
					'containerid' => $containerid,
					'objDims' => $graphDims,
					'loadSBox' => 0,
					'loadImage' => 1,
					'loadScroll' => 0,
					'dynamic' => 0,
					'periodFixed' => CProfile::get('web.screens.timelinefixed', 1),
					'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD
				);

				// host feature
				if ($dynamic == SCREEN_DYNAMIC_ITEM && isset($_REQUEST['hostid']) && $_REQUEST['hostid'] > 0) {
					if ($newitemid = get_same_item_for_host($resourceid, $_REQUEST['hostid'])) {
						$resourceid = $newitemid;
					}
					else {
						$resourceid = '';
					}
				}

				if ($editmode == 0 && !empty($resourceid)) {
					$action = 'history.php?action=showgraph&itemid='.$resourceid.url_param('period').url_param('stime');
				}

				$timeline = array();
				$timeline['starttime'] = date('YmdHis', time() - ZBX_MAX_PERIOD);

				if (!zbx_empty($resourceid) && $editmode != 1) {
					$timeline['period'] = $effectiveperiod;

					if (isset($_REQUEST['stime'])) {
						$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
					}
					if ($editmode == 0) {
						$objData['loadSBox'] = 1;
					}
				}

				$objData['src'] = zbx_empty($resourceid) ? 'chart3.php?' : 'chart.php?itemid='.$resourceid.'&'.$url.'width='.$width.'&height='.$height;

				if ($editmode) {
					$item = new CDiv();
				}
				else {
					$item = new CLink(null, $action);
				}

				$item->setAttribute('id', $containerid);

				$item = array($item);
				if ($editmode == 1) {
					$item[] = BR();
					$item[] = new CLink(_('Change'), $action);
				}

				if ($editmode == 2) {
					insert_js('timeControl.addObject("'.$dom_graph_id.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($objData).');');
				}
				else {
					zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($objData).');');
				}
			}
			/*
			 * Map
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_MAP) {
				$image_map = new CImg('map.php?noedit=1&sysmapid='.$resourceid.'&width='.$width.'&height='.$height.'&curtime='.time());
				$item = array($image_map);

				if ($editmode == 0) {
					$sysmaps = API::Map()->get(array(
						'sysmapids' => $resourceid,
						'output' => API_OUTPUT_EXTEND,
						'selectSelements' => API_OUTPUT_EXTEND,
						'selectLinks' => API_OUTPUT_EXTEND,
						'nopermissions' => true,
						'preservekeys' => true
					));
					$sysmap = reset($sysmaps);

					$action_map = getActionMapBySysmap($sysmap);
					$image_map->setMap($action_map->getName());
					$item = array($action_map, $image_map);
				}
				elseif ($editmode == 1) {
					$item[] = BR();
					$item[] = new CLink(_('Change'), $action);
				}
			}
			/*
			 * Plain text
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_PLAIN_TEXT) {
				if ($dynamic == SCREEN_DYNAMIC_ITEM && isset($_REQUEST['hostid']) && $_REQUEST['hostid'] > 0) {
					if ($newitemid = get_same_item_for_host($resourceid, $_REQUEST['hostid'])) {
						$resourceid = $newitemid;
					}
					else {
						$resourceid = 0;
					}
				}
				$item = array(get_screen_plaintext($resourceid, $elements, $style));
				if ($editmode == 1) {
					array_push($item,new CLink(_('Change'), $action));
				}
			}
			/*
			 * Hostgroup triggers
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_HOSTGROUP_TRIGGERS) {
				$params = array(
					'groupids' => null,
					'hostids' => null,
					'maintenance' => null,
					'severity' => null,
					'limit' => $elements
				);

				// by default triggers are sorted by date desc, do we need to override this?
				switch ($sort_triggers) {
					case SCREEN_SORT_TRIGGERS_SEVERITY_DESC:
						$params['sortfield'] = 'priority';
						$params['sortorder'] = ZBX_SORT_DOWN;
						break;
					case SCREEN_SORT_TRIGGERS_HOST_NAME_ASC:
						// a little black magic here - there is no such field 'hostname' in 'triggers',
						// but API has a special case for sorting by hostname
						$params['sortfield'] = 'hostname';
						$params['sortorder'] = ZBX_SORT_UP;
						break;
				}

				if ($resourceid > 0) {
					$hostgroups = API::HostGroup()->get(array(
						'groupids' => $resourceid,
						'output' => API_OUTPUT_EXTEND
					));
					$hostgroup = reset($hostgroups);

					$tr_form = new CSpan(_('Group').': '.$hostgroup['name'], 'white');
					$params['groupids'] = $hostgroup['groupid'];
				}
				else {
					$groupid = get_request('tr_groupid', CProfile::get('web.screens.tr_groupid', 0));
					$hostid = get_request('tr_hostid', CProfile::get('web.screens.tr_hostid', 0));

					CProfile::update('web.screens.tr_groupid', $groupid, PROFILE_TYPE_ID);
					CProfile::update('web.screens.tr_hostid', $hostid, PROFILE_TYPE_ID);

					$groups = API::HostGroup()->get(array(
						'monitored_hosts' => true,
						'output' => API_OUTPUT_EXTEND
					));
					order_result($groups, 'name');

					$options = array(
						'monitored_hosts' => true,
						'output' => API_OUTPUT_EXTEND
					);
					if ($groupid > 0) {
						$options['groupids'] = $groupid;
					}
					$hosts = API::Host()->get($options);
					$hosts = zbx_toHash($hosts, 'hostid');
					order_result($hosts, 'host');

					if (!isset($hosts[$hostid])) {
						$hostid = 0;
					}

					$tr_form = new CForm();

					$cmbGroup = new CComboBox('tr_groupid', $groupid, 'submit()');
					$cmbHosts = new CComboBox('tr_hostid', $hostid, 'submit()');
					if ($editmode == 1) {
						$cmbGroup->attr('disabled', 'disabled');
						$cmbHosts->attr('disabled', 'disabled');
					}

					$cmbGroup->addItem(0, _('all'));
					$cmbHosts->addItem(0, _('all'));

					foreach ($groups as $group) {
						$cmbGroup->addItem($group['groupid'], get_node_name_by_elid($group['groupid'], null, ': ').$group['name']);
					}

					foreach ($hosts as $host) {
						$cmbHosts->addItem($host['hostid'], get_node_name_by_elid($host['hostid'], null, ': ').$host['host']);
					}

					$tr_form->addItem(array(_('Group').SPACE, $cmbGroup));
					$tr_form->addItem(array(SPACE._('Host').SPACE, $cmbHosts));

					if ($groupid > 0) {
						$params['groupids'] = $groupid;
					}
					if ($hostid > 0) {
						$params['hostids'] = $hostid;
					}
				}

				$params['screenid'] = $screen['screenid'];

				$item = new CUIWidget('hat_htstatus', make_latest_issues($params, true));
				$item->setDoubleHeader(array(_('STATUS OF TRIGGERS'), SPACE, zbx_date2str(_('[H:i:s]')), SPACE), $tr_form);
				$item = array($item);

				if ($editmode == 1) {
					array_push($item, new CLink(_('Change'), $action));
				}
			}
			/*
			 * Host triggers
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_HOST_TRIGGERS) {
				$params = array(
					'groupids' => null,
					'hostids' => null,
					'maintenance' => null,
					'severity' => null,
					'limit' => $elements
				);

				// by default triggers are sorted by date desc, do we need to override this?
				switch ($sort_triggers) {
					case SCREEN_SORT_TRIGGERS_SEVERITY_DESC:
						$params['sortfield'] = 'priority';
						$params['sortorder'] = ZBX_SORT_DOWN;
						break;
					case SCREEN_SORT_TRIGGERS_HOST_NAME_ASC:
						// a little black magic here - there is no such field 'hostname' in 'triggers',
						// but API has a special case for sorting by hostname
						$params['sortfield'] = 'hostname';
						$params['sortorder'] = ZBX_SORT_UP;
						break;
				}

				if ($resourceid > 0) {
					$hosts = API::Host()->get(array(
						'hostids' => $resourceid,
						'output' => API_OUTPUT_EXTEND
					));
					$host = reset($hosts);

					$tr_form = new CSpan(_('Host').': '.$host['host'], 'white');
					$params['hostids'] = $host['hostid'];
				}
				else {
					$groupid = get_request('tr_groupid', CProfile::get('web.screens.tr_groupid', 0));
					$hostid = get_request('tr_hostid', CProfile::get('web.screens.tr_hostid', 0));

					CProfile::update('web.screens.tr_groupid', $groupid, PROFILE_TYPE_ID);
					CProfile::update('web.screens.tr_hostid', $hostid, PROFILE_TYPE_ID);

					$groups = API::HostGroup()->get(array(
						'monitored_hosts' => true,
						'output' => API_OUTPUT_EXTEND
					));
					order_result($groups, 'name');

					$options = array(
						'monitored_hosts' => true,
						'output' => API_OUTPUT_EXTEND
					);
					if ($groupid > 0) {
						$options['groupids'] = $groupid;
					}
					$hosts = API::Host()->get($options);
					$hosts = zbx_toHash($hosts, 'hostid');
					order_result($hosts, 'host');

					if (!isset($hosts[$hostid])) {
						$hostid = 0;
					}

					$tr_form = new CForm();

					$cmbGroup = new CComboBox('tr_groupid', $groupid, 'submit()');
					$cmbHosts = new CComboBox('tr_hostid', $hostid, 'submit()');
					if ($editmode == 1) {
						$cmbGroup->attr('disabled', 'disabled');
						$cmbHosts->attr('disabled', 'disabled');
					}

					$cmbGroup->addItem(0, _('all'));
					$cmbHosts->addItem(0, _('all'));

					foreach ($groups as $group) {
						$cmbGroup->addItem(
							$group['groupid'],
							get_node_name_by_elid($group['groupid'], null, ': ').$group['name']
						);
					}

					foreach ($hosts as $host) {
						$cmbHosts->addItem(
							$host['hostid'],
							get_node_name_by_elid($host['hostid'], null, ': ').$host['host']
						);
					}

					$tr_form->addItem(array(_('Group').SPACE, $cmbGroup));
					$tr_form->addItem(array(SPACE._('Host').SPACE, $cmbHosts));

					if ($groupid > 0) {
						$params['groupids'] = $groupid;
					}
					if ($hostid > 0) {
						$params['hostids'] = $hostid;
					}
				}

				$params['screenid'] = $screen['screenid'];

				$item = new CUIWidget('hat_trstatus', make_latest_issues($params, true));
				$item->setDoubleHeader(array(_('STATUS OF TRIGGERS'), SPACE, zbx_date2str(_('[H:i:s]')), SPACE), $tr_form);
				$item = array($item);

				if ($editmode == 1) {
					array_push($item, new CLink(_('Change'), $action));
				}
			}
			/*
			 * System status
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_SYSTEM_STATUS) {
				$params = array(
					'groupids' => null,
					'hostids' => null,
					'maintenance' => null,
					'severity' => null,
					'limit' => null,
					'extAck' => 0,
					'screenid' => $screen['screenid']
				);

				$item = new CUIWidget('hat_syssum', make_system_status($params));
				$item->setHeader(_('Status of Zabbix'), SPACE);
				$item->setFooter(_s('Updated: %s', zbx_date2str(_('H:i:s'))));

				$item = array($item);

				if ($editmode == 1) {
					array_push($item, new CLink(_('Change'), $action));
				}
			}
			/*
			 * Host info
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_HOSTS_INFO) {
				$item = array(new CHostsInfo($resourceid, $style));
				if ($editmode == 1) {
					array_push($item, new CLink(_('Change'), $action));
				}
			}
			/*
			 * Triggers info
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_TRIGGERS_INFO) {
				$item = new CTriggersInfo($resourceid, null, $style);
				$item = array($item);
				if ($editmode == 1) {
					array_push($item, new CLink(_('Change'), $action));
				}
			}
			/*
			 * Server info
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_SERVER_INFO) {
				$item = array(new CServerInfo());
				if ($editmode == 1) {
					array_push($item, new CLink(_('Change'), $action));
				}
			}
			/*
			 * Clock
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_CLOCK) {
				$error = null;
				$timeOffset = null;
				$timeZone = null;

				switch ($style) {
					case TIME_TYPE_HOST:
						$items = API::Item()->get(array(
							'itemids' => $resourceid,
							'selectHosts' => API_OUTPUT_EXTEND,
							'output' => API_OUTPUT_EXTEND
						));
						$item = reset($items);
						$host = reset($item['hosts']);

						$timeType = $host['host'];
						preg_match('/([+-]{1})([\d]{1,2}):([\d]{1,2})/', $item['lastvalue'], $arr);

						if (!empty($arr)) {
							$timeZone = $arr[2] * SEC_PER_HOUR + $arr[3] * SEC_PER_MIN;
							if ($arr[1] == '-') {
								$timeZone = 0 - $timeZone;
							}
						}

						if ($lastvalue = strtotime($item['lastvalue'])) {
							$diff = (time() - $item['lastclock']);
							$timeOffset = $lastvalue + $diff;
						}
						else {
							$error = _('NO DATA');
						}
						break;
					case TIME_TYPE_SERVER:
						$error = null;
						$timeType = _('SERVER');
						$timeOffset = time();
						$timeZone = date('Z');
						break;
					default:
						$error = null;
						$timeType = _('LOCAL');
						$timeOffset = null;
						$timeZone = null;
						break;
				}

				if ($width > $height) {
					$width = $height;
				}

				$item = new CFlashClock($width, $height, $action);
				$item->setTimeError($error);
				$item->setTimeType($timeType);
				$item->setTimeZone($timeZone);
				$item->setTimeOffset($timeOffset);

				if ($editmode == 1) {
					$flashclockOverDiv = new CDiv(null, 'flashclock');
					$flashclockOverDiv->setAttribute('style', 'width: '.$width.'px; height: '.$height.'px;');

					$item = array(
						$flashclockOverDiv,
						$item,
						BR(),
						new CLink(_('Change'), $action)
					);
				}
				else {
					$item = array($item);
				}
			}
			/*
			 * Screen
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_SCREEN) {
				$subScreens = API::Screen()->get(array(
					'screenids' => $resourceid,
					'output' => API_OUTPUT_EXTEND,
					'selectScreenItems' => API_OUTPUT_EXTEND
				));
				$subScreen = reset($subScreens);
				$item = array(get_screen($subScreen, ($editmode == 1 || $editmode == 2) ? 2 : 0, $effectiveperiod));
				if ($editmode == 1) {
					array_push($item, new CLink(_('Change'), $action));
				}
			}
			/*
			 * Triggers overview
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_TRIGGERS_OVERVIEW) {
				$hostids = array();
				$res = DBselect('SELECT DISTINCT hg.hostid FROM hosts_groups hg WHERE hg.groupid='.$resourceid);
				while ($tmp_host = DBfetch($res)) {
					$hostids[$tmp_host['hostid']] = $tmp_host['hostid'];
				}

				$item = array(get_triggers_overview($hostids, $style, array('screenid' => $screen['screenid'])));
				if ($editmode == 1) {
					array_push($item, new CLink(_('Change'), $action));
				}
			}
			/*
			 * Data overview
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_DATA_OVERVIEW) {
				$hostids = array();
				$res = DBselect('SELECT DISTINCT hg.hostid FROM hosts_groups hg WHERE hg.groupid='.$resourceid);
				while ($tmp_host = DBfetch($res)) {
					$hostids[$tmp_host['hostid']] = $tmp_host['hostid'];
				}

				$item = array(get_items_data_overview($hostids, $style));
				if ($editmode == 1) {
					array_push($item, new CLink(_('Change'), $action));
				}
			}
			/*
			 * Url
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_URL) {
				$item = array(new CIFrame($url, $width, $height, 'auto'));
				if ($editmode == 1) {
					array_push($item, BR(), new CLink(_('Change'), $action));
				}
			}
			/*
			 * Actions
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_ACTIONS) {
				$item = array(get_history_of_actions($elements));
				if ($editmode == 1) {
					array_push($item, new CLink(_('Change'), $action));
				}
			}
			/*
			 * Events
			 */
			elseif ($screenitemid != 0 && $resourcetype == SCREEN_RESOURCE_EVENTS) {
				$options = array(
					'monitored' => 1,
					'value' => array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE),
					'limit' => $elements
				);

				$showUnknown = CProfile::get('web.events.filter.showUnknown', 0);
				if ($showUnknown) {
					$options['value'] = array(TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE);
				}

				$item = new CTableInfo(_('No events defined.'));
				$item->setHeader(array(
					_('Time'),
					is_show_all_nodes() ? _('Node') : null,
					_('Host'),
					_('Description'),
					_('Value'),
					_('Severity')
				));

				$events = getLastEvents($options);
				foreach ($events as $event) {
					$trigger = $event['trigger'];
					$host = $event['host'];

					$statusSpan = new CSpan(trigger_value2str($event['value']));

					// add colors and blinking to span depending on configuration and trigger parameters
					addTriggerValueStyle(
						$statusSpan,
						$event['value'],
						$event['clock'],
						$event['acknowledged']
					);

					$item->addRow(array(
						zbx_date2str(_('d M Y H:i:s'), $event['clock']),
						get_node_name_by_elid($event['objectid']),
						$host['host'],
						new CLink(
							$trigger['description'],
							'tr_events.php?triggerid='.$event['objectid'].'&eventid='.$event['eventid']
						),
						$statusSpan,
						getSeverityCell($trigger['priority'])
					));
				}

				$item = array($item);
				if ($editmode == 1) {
					array_push($item, new CLink(_('Change'), $action));
				}
			}
			else {
				$item = array(SPACE);
				if ($editmode == 1) {
					array_push($item, BR(), new CLink(_('Change'), $action, 'empty_change_link'));
				}
			}

			$str_halign = 'def';
			if ($halign == HALIGN_CENTER) {
				$str_halign = 'cntr';
			}
			if ($halign == HALIGN_LEFT) {
				$str_halign = 'left';
			}
			if ($halign == HALIGN_RIGHT) {
				$str_halign = 'right';
			}

			$str_valign = 'def';
			if ($valign == VALIGN_MIDDLE) {
				$str_valign = 'mdl';
			}
			if ($valign == VALIGN_TOP) {
				$str_valign = 'top';
			}
			if ($valign == VALIGN_BOTTOM) {
				$str_valign = 'bttm';
			}

			if ($editmode == 1 && !$item_form) {
				$item = new CDiv($item, 'draggable');
				$item->setAttribute('id', 'position_'.$r.'_'.$c);
				$item->setAttribute('data-xcoord', $c);
				$item->setAttribute('data-ycoord', $r);
			}

			$new_col = new CCol($item, $str_halign.'_'.$str_valign.' screenitem');

			if (!empty($colspan)) {
				$new_col->setColSpan($colspan);
			}
			if (!empty($rowspan)) {
				$new_col->setRowSpan($rowspan);
			}
			array_push($new_cols, $new_col);
		}

		if ($editmode == 1) {
			$removeIcon = new CImg('images/general/opened.gif', null, null, null, 'pointer');
			if ($empty_screen_row) {
				$removeRowLink = 'javascript: location.href = "screenedit.php?screenid='.$screen['screenid'].'&rmv_row='.$r.'";';
			}
			else {
				$removeRowLink = 'javascript: if (Confirm("'._('This screen-row is not empty. Delete it?').'")) {'.
					' location.href = "screenedit.php?screenid='.$screen['screenid'].'&rmv_row='.$r.'"; }';
			}
			$removeIcon->addAction('onclick', $removeRowLink);
			array_push($new_cols, new CCol($removeIcon));
		}
		$table->addRow(new CRow($new_cols));
	}

	if ($editmode == 1) {
		$add_icon = new CImg('images/general/closed.gif', null, null, null, 'pointer');
		$add_icon->addAction('onclick', 'javascript: location.href = "screenedit.php?screenid='.$screen['screenid'].'&add_row='.$screen['vsize'].'";');
		$new_cols = array(new CCol($add_icon));

		for ($c = 0; $c < $screen['hsize']; $c++) {
			$removeIcon = new CImg('images/general/opened.gif', null, null, null, 'pointer');
			if (isset($empty_screen_col[$c])) {
				$removeColumnLink = 'javascript: if (Confirm("'._('This screen-column is not empty. Delete it?').'")) {'.
					' location.href = "screenedit.php?screenid='.$screen['screenid'].'&rmv_col='.$c.'"; }';
			}
			else {
				$removeColumnLink = 'javascript: location.href = "screenedit.php?config=1&screenid='.$screen['screenid'].'&rmv_col='.$c.'";';
			}
			$removeIcon->addAction('onclick', $removeColumnLink);
			array_push($new_cols, new CCol($removeIcon));
		}

		array_push($new_cols, new CCol(new CImg('images/general/zero.gif', 'zero', 1, 1)));
		$table->addRow($new_cols);
	}

	return $table;
}
