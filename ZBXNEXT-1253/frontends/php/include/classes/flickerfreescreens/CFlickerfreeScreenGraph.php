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


class CFlickerfreeScreenGraph extends CFlickerfreeScreenItem {

	public function __construct(array $options = array()) {
		parent::__construct($options);
	}

	public function get() {
		if ($this->mode == SCREEN_MODE_PREVIEW) {
			$action = 'charts.php?graphid='.$this->screenitem['resourceid'].url_params(array('period', 'stime'));
		}

		$domGraphid = 'graph_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['resourceid'];
		$containerid = 'graph_cont_'.$this->screenitem['screenitemid'].'_'.$this->screenitem['resourceid'];
		$graphDims = getGraphDims($this->screenitem['resourceid']);
		$graphDims['graphHeight'] = $this->screenitem['height'];
		$graphDims['width'] = $this->screenitem['width'];
		$graph = get_graph_by_graphid($this->screenitem['resourceid']);
		$graphid = $graph['graphid'];
		$legend = $graph['show_legend'];
		$graph3d = $graph['show_3d'];

		// host feature
		if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && isset($_REQUEST['hostid']) && $_REQUEST['hostid'] > 0) {
			$hosts = API::Host()->get(array(
				'hostids' => $_REQUEST['hostid'],
				'output' => array('hostid', 'host')
			));
			$host = reset($hosts);

			$graph = API::Graph()->get(array(
				'graphids' => $this->screenitem['resourceid'],
				'output' => API_OUTPUT_EXTEND,
				'selectHosts' => API_OUTPUT_REFER,
				'selectGraphItems' => API_OUTPUT_EXTEND
			));
			$graph = reset($graph);

			if (count($graph['hosts']) == 1) {
				// if items from one host we change them, or set calculated if not exist on that host
				if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymax_itemid']) {
					$newDinamic = get_same_graphitems_for_host(
						array(array('itemid' => $graph['ymax_itemid'])),
						$_REQUEST['hostid'],
						false // false = don't rise Error if item doesn't exist
					);
					$newDinamic = reset($newDinamic);

					if (isset($newDinamic['itemid']) && $newDinamic['itemid'] > 0) {
						$graph['ymax_itemid'] = $newDinamic['itemid'];
					}
					else {
						$graph['ymax_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
					}
				}

				if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymin_itemid']) {
					$newDinamic = get_same_graphitems_for_host(
						array(array('itemid' => $graph['ymin_itemid'])),
						$_REQUEST['hostid'],
						false // false = don't rise Error if item doesn't exist
					);
					$newDinamic = reset($newDinamic);

					if (isset($newDinamic['itemid']) && $newDinamic['itemid'] > 0) {
						$graph['ymin_itemid'] = $newDinamic['itemid'];
					}
					else {
						$graph['ymin_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
					}
				}
			}

			$url = ($graph['graphtype'] == GRAPH_TYPE_PIE || $graph['graphtype'] == GRAPH_TYPE_EXPLODED)
					? 'chart7.php'
					: 'chart3.php';
			$url = new CUrl($url);

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
			'id' => $this->screenitem['resourceid'],
			'domid' => $domGraphid,
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
			if ($this->screenitem['dynamic'] == SCREEN_SIMPLE_ITEM || empty($url)) {
				$url='chart6.php?graphid='.$this->screenitem['resourceid'];
				$default = true;
			}

			$timeline = array();
			$timeline['period'] = $this->effectiveperiod;
			$timeline['starttime'] = date('YmdHis', get_min_itemclock_by_graphid($this->screenitem['resourceid']));

			if (isset($_REQUEST['stime'])) {
				$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
			}

			$src = $url.'&width='.$this->screenitem['width'].'&height='.$this->screenitem['height'].'&legend='.$this->screenitem['legend'].'&graph3d='.$graph3d.'&period='.$this->effectiveperiod.url_param('stime');

			$objData['src'] = $src;
		}
		else {
			if ($this->screenitem['dynamic'] == SCREEN_SIMPLE_ITEM || empty($url)) {
				$url = 'chart2.php?graphid='.$this->screenitem['resourceid'];
				$default = true;
			}

			$src = $url.'&width='.$this->screenitem['width'].'&height='.$this->screenitem['height'].'&period='.$this->effectiveperiod.url_param('stime');

			$timeline = array();
			if (isset($graphid) && !is_null($graphid) && $this->mode != SCREEN_MODE_EDIT) {
				$timeline['period'] = $this->effectiveperiod;
				$timeline['starttime'] = date('YmdHis', time() - ZBX_MAX_PERIOD);

				if (isset($_REQUEST['stime'])) {
					$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
				}
				if ($this->mode == SCREEN_MODE_PREVIEW) {
					$objData['loadSBox'] = 1;
				}
			}
			$objData['src'] = $src;
		}

		if ($this->mode || !$default) {
			$item = new CDiv();
		}
		else {
			$item = new CLink(null, $action);
		}

		$item->setAttribute('id', $containerid);

		$item = array($item);
		if ($this->mode == SCREEN_MODE_EDIT) {
			$item[] = BR();
			$item[] = new CLink(_('Change'), $action);
		}

		if ($this->mode == SCREEN_MODE_VIEW) {
			insert_js('timeControl.addObject("'.$domGraphid.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($objData).');');
		}
		else {
			zbx_add_post_js('timeControl.addObject("'.$domGraphid.'", '.zbx_jsvalue($timeline).', '.zbx_jsvalue($objData).');');
		}

		return $item;
	}
}
