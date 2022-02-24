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


class CControllerWidgetGraphView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_GRAPH);
		$this->setValidationRules([
			'name' => 'string',
			'edit_mode' => 'in 0,1',
			'dashboardid' => 'db dashboard.dashboardid',
			'fields' => 'json',
			'dynamic_hostid' => 'db hosts.hostid',
			'content_width' => 'int32',
			'content_height' => 'int32'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();
		$edit_mode = (int) $this->getInput('edit_mode', 0);

		$width = (int) $this->getInput('content_width', 100);
		$height = (int) $this->getInput('content_height', 100);

		$dynamic_hostid = $this->getInput('dynamic_hostid', 0);
		$resourceid = null;
		$profileIdx = 'web.dashboard.filter';
		$profileIdx2 = $this->getInput('dashboardid', 0);
		$is_resource_available = true;
		$header_name = $this->getDefaultName();

		if ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_GRAPH && $fields['graphid']) {
			$resource_type = SCREEN_RESOURCE_GRAPH;
			$resourceid = reset($fields['graphid']);
			$graph_dims = getGraphDims($resourceid);
			$graph_dims['graphHeight'] = $height;
			$graph_dims['width'] = $width;
		}
		elseif ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH && $fields['itemid']) {
			$resource_type = SCREEN_RESOURCE_SIMPLE_GRAPH;
			$resourceid = $fields['itemid'][0];
			$graph_dims = getGraphDims();
			$graph_dims['graphHeight'] = $height;
			$graph_dims['width'] = $width;
		}
		else {
			$resource_type = null;
			$graph_dims = getGraphDims();
		}
		$graph_dims['shiftYtop'] = CLineGraphDraw::DEFAULT_TOP_BOTTOM_PADDING;

		$time_control_data = [
			'id' => '',
			'containerid' => '',
			'objDims' => $graph_dims,
			'loadSBox' => 0,
			'loadImage' => 1,
			'reloadOnAdd' => 1
		];

		$flickerfreescreen_data = [
			'id' => '',
			'interval' => CWebUser::getRefresh(),
			'timeline' => [],
			'resourcetype' => $resource_type,
			'profileIdx' => $profileIdx,
			'profileIdx2' => $profileIdx2
		];

		$is_template_dashboard = ($this->getContext() === CWidgetConfig::CONTEXT_TEMPLATE_DASHBOARD);
		$is_dynamic_item = ($is_template_dashboard || $fields['dynamic'] == WIDGET_DYNAMIC_ITEM);

		// Replace graph item by particular host item if dynamic items are used.
		if ($is_dynamic_item && $dynamic_hostid && $resourceid) {
			// Find same simple-graph item in selected $dynamic_hostid host.
			if ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH) {
				$src_items = API::Item()->get([
					'output' => ['key_'],
					'itemids' => $resourceid,
					'webitems' => true
				]);

				$items = API::Item()->get([
					'output' => ['itemid', 'name'],
					'selectHosts' => ['name'],
					'hostids' => $dynamic_hostid,
					'filter' => [
						'key_' => $src_items[0]['key_'],
						'value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]
					],
					'webitems' => true
				]);

				$item = reset($items);
				$resourceid = $items ? $item['itemid'] : null;

				if ($resourceid === null) {
					$is_resource_available = false;
				}
			}
			// Find requested host and change graph details.
			elseif ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_GRAPH) {
				// get host
				$hosts = API::Host()->get([
					'output' => ['hostid', 'host', 'name'],
					'hostids' => $dynamic_hostid
				]);
				$host = reset($hosts);

				// get graph
				$graph = API::Graph()->get([
					'output' => API_OUTPUT_EXTEND,
					'selectGraphItems' => API_OUTPUT_EXTEND,
					'selectHosts' => [],
					'graphids' => $resourceid
				]);
				$graph = reset($graph);

				// If all items are from one host we change them, or set calculated if not exist on that host.
				if ($graph && count($graph['hosts']) == 1) {
					if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymax_itemid']) {
						$new_dynamic = getSameGraphItemsForHost(
							[['itemid' => $graph['ymax_itemid']]],
							$dynamic_hostid,
							false
						);
						$new_dynamic = reset($new_dynamic);

						if ($new_dynamic && array_key_exists('itemid', $new_dynamic) && $new_dynamic['itemid'] > 0) {
							$graph['ymax_itemid'] = $new_dynamic['itemid'];
						}
						else {
							$graph['ymax_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
						}
					}

					if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymin_itemid']) {
						$new_dynamic = getSameGraphItemsForHost(
							[['itemid' => $graph['ymin_itemid']]],
							$dynamic_hostid,
							false
						);
						$new_dynamic = reset($new_dynamic);

						if ($new_dynamic && array_key_exists('itemid', $new_dynamic) && $new_dynamic['itemid'] > 0) {
							$graph['ymin_itemid'] = $new_dynamic['itemid'];
						}
						else {
							$graph['ymin_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
						}
					}
				}

				if ($graph) {
					$graph['hosts'] = $hosts;

					// Search if there are any items available for this dynamic host.
					$new_dynamic = getSameGraphItemsForHost($graph['gitems'], $dynamic_hostid, false);

					if ($new_dynamic) {
						// Add destination host data required by CMacrosResolver::resolveGraphNames().
						foreach ($new_dynamic as &$item) {
							$item['host'] = $host['host'];
						}
						unset($item);

						$graph['name'] = CMacrosResolverHelper::resolveGraphName($graph['name'], $new_dynamic);
					}
					else {
						$is_resource_available = false;
					}
				}
				else {
					$is_resource_available = false;
				}
			}
		}
		else {
			if (!$resourceid) {
				$is_resource_available = false;
			}
			elseif ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH) {
				$items = API::Item()->get([
					'output' => ['name', 'key_', 'delay', 'hostid'],
					'selectHosts' => ['name'],
					'itemids' => $resourceid,
					'filter' => ['value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]],
					'webitems' => true
				]);
				$item = reset($items);

				if (!$item) {
					$is_resource_available = false;
				}
			}
			elseif ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_GRAPH) {
				// get graph, used below
				$graph = API::Graph()->get([
					'output' => API_OUTPUT_EXTEND,
					'selectHosts' => ['name'],
					'graphids' => $resourceid,
					'expandName' => true
				]);
				$graph = reset($graph);

				if (!$graph) {
					$is_resource_available = false;
				}
			}
		}

		if ($is_resource_available) {
			// Build graph action and data source links.
			if ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH) {
				if (!$edit_mode) {
					$time_control_data['loadSBox'] = 1;
				}

				if ($resourceid) {
					$graph_src = (new CUrl('chart.php'))
						->setArgument('itemids', [$resourceid])
						->setArgument('width', $width)
						->setArgument('height', $height)
						->setArgument('legend', $fields['show_legend']);
				}
				else {
					$graph_src = new CUrl('chart3.php');
				}

				$graph_src
					->setArgument('from', '')
					->setArgument('to', '');

				$header_name = $is_template_dashboard
					? $item['name']
					: $item['hosts'][0]['name'].NAME_DELIMITER.$item['name'];
			}
			elseif ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_GRAPH) {
				$graph_src = '';

				$prepend_host_name = $is_template_dashboard
					? false
					: (count($graph['hosts']) == 1 || $is_dynamic_item && $dynamic_hostid != 0);

				$header_name = $prepend_host_name
					? $graph['hosts'][0]['name'].NAME_DELIMITER.$graph['name']
					: $graph['name'];

				if ($is_dynamic_item && $dynamic_hostid && $resourceid) {
					if ($graph['graphtype'] == GRAPH_TYPE_PIE || $graph['graphtype'] == GRAPH_TYPE_EXPLODED) {
						$graph_src = (new CUrl('chart7.php'))
							->setArgument('name', $host['name'].NAME_DELIMITER.$graph['name'])
							->setArgument('graphtype', $graph['graphtype'])
							->setArgument('graph3d', $graph['show_3d']);
					}
					else {
						$graph_src = (new CUrl('chart3.php'))
							->setArgument('name', $host['name'].NAME_DELIMITER.$graph['name'])
							->setArgument('ymin_type', $graph['ymin_type'])
							->setArgument('ymax_type', $graph['ymax_type'])
							->setArgument('ymin_itemid', $graph['ymin_itemid'])
							->setArgument('ymax_itemid', $graph['ymax_itemid'])
							->setArgument('showworkperiod', $graph['show_work_period'])
							->setArgument('showtriggers', $graph['show_triggers'])
							->setArgument('graphtype', $graph['graphtype'])
							->setArgument('yaxismin', $graph['yaxismin'])
							->setArgument('yaxismax', $graph['yaxismax'])
							->setArgument('percent_left', $graph['percent_left'])
							->setArgument('percent_right', $graph['percent_right']);
					}

					$new_graph_items = getSameGraphItemsForHost($graph['gitems'], $dynamic_hostid, false);

					foreach ($new_graph_items as &$new_graph_item) {
						unset($new_graph_item['gitemid'], $new_graph_item['graphid']);
					}
					unset($new_graph_item);

					$graph_src->setArgument('items', $new_graph_items);
				}

				if ($graph_dims['graphtype'] == GRAPH_TYPE_PIE || $graph_dims['graphtype'] == GRAPH_TYPE_EXPLODED) {
					if (!$is_dynamic_item || $graph_src === '') {
						$graph_src = (new CUrl('chart6.php'))
							->setArgument('graphid', $resourceid)
							->setArgument('graph3d', $graph['show_3d']);
					}
				}
				else {
					if (!$is_dynamic_item || $graph_src === '') {
						$graph_src = (new CUrl('chart2.php'))->setArgument('graphid', $resourceid);
					}

					if (!$edit_mode) {
						$time_control_data['loadSBox'] = 1;
					}
				}

				$graph_src
					->setArgument('width', $width)
					->setArgument('height', $height)
					->setArgument('legend', ($fields['show_legend'] && $graph['show_legend']) ? 1 : 0)
					->setArgument('from', '')
					->setArgument('to', '');
			}

			$graph_src
				->setArgument('profileIdx', $profileIdx)
				->setArgument('profileIdx2', $profileIdx2);

			if ($graph_dims['graphtype'] != GRAPH_TYPE_PIE && $graph_dims['graphtype'] != GRAPH_TYPE_EXPLODED) {
				$graph_src->setArgument('outer', '1');
			}

			$graph_src->setArgument('widget_view', '1');
			$time_control_data['src'] = $graph_src->getUrl();

			if ($edit_mode || ($is_template_dashboard && !$this->hasInput('dynamic_hostid'))) {
				$graph_url = null;
			}
			else {
				if ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_GRAPH) {
					if ($is_dynamic_item && $dynamic_hostid) {
						$template_graphs = API::Graph()->get([
							'output' => ['name'],
							'graphids' => [$resourceid]
						]);

						$resourceid = null;

						if ($template_graphs) {
							$host_graphs = API::Graph()->get([
								'output' => ['graphid'],
								'hostids' => [$dynamic_hostid],
								'filter' => [
									'name' => $template_graphs[0]['name']
								]
							]);

							if ($host_graphs) {
								$resourceid = $host_graphs[0]['graphid'];
							}
						}
					}

					$graph_url = null;
				}
				else {
					$graph_url = $this->checkAccess(CRoleHelper::UI_MONITORING_LATEST_DATA)
						? (new CUrl('history.php'))
							->setArgument('itemids', [$resourceid])
							->setArgument('from', '')
							->setArgument('to', '')
						: null;
				}
			}
		}

		$response = [
			'name' => $this->getInput('name', $header_name),
			'is_resource_available' => $is_resource_available,
			'widget' => [
				'graph_url' => ($is_resource_available && $graph_url !== null) ? $graph_url->getUrl() : null,
				'time_control_data' => $time_control_data,
				'flickerfreescreen_data' => $flickerfreescreen_data
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData($response));
	}
}
