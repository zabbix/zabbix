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
?>
<?php

function screenIdents($screenids) {
	$idents = array();

	$screens = API::Screen()->get(array(
		'screenids' => $screenids,
		'output' => API_OUTPUT_EXTEND,
		'nodeids' => get_current_nodeid(true)
	));
	foreach ($screens as $screen) {
		$idents[$screen['screenid']] = array(
			'node' => get_node_name_by_elid($screen['screenid'], true),
			'name' => $screen['name']
		);
	}
	return $idents;
}

function sysmapIdents($sysmapids) {
	$idents = array();

	$sysmaps = API::Map()->get(array(
		'sysmapids' => $sysmapids,
		'output' => API_OUTPUT_EXTEND,
		'nodeids' => get_current_nodeid(true)
	));
	foreach ($sysmaps as $sysmap) {
		$idents[$sysmap['sysmapid']] = array(
			'node' => get_node_name_by_elid($sysmap['sysmapid'], true),
			'name' => $sysmap['name']
		);
	}
	return $idents;
}

function hostgroupIdents($groupids) {
	$idents = array();

	$groups = API::HostGroup()->get(array(
		'groupids' => $groupids,
		'output' => API_OUTPUT_EXTEND,
		'nodeids' => get_current_nodeid(true)
	));
	foreach ($groups as $group) {
		$idents[$group['groupid']] = array(
			'node' => get_node_name_by_elid($group['groupid'], true),
			'name' => $group['name']
		);
	}
	return $idents;
}

function hostIdents($hostids) {
	$idents = array();

	$hosts = API::Host()->get(array(
		'hostids' => $hostids,
		'output' => API_OUTPUT_EXTEND,
		'nodeids' => get_current_nodeid(true)
	));
	foreach ($hosts as $host) {
		$idents[$host['hostid']] = array(
			'node' => get_node_name_by_elid($host['hostid'], true),
			'host' => $host['host']
		);
	}
	return $idents;
}

function itemIdents($itemids){
	$idents = array();

	$items = API::Item()->get(array(
		'itemids' => $itemids,
		'output' => API_OUTPUT_EXTEND,
		'selectHosts' => array('hostid', 'host'),
		'nodeids' => get_current_nodeid(true),
		'webitems' => true
	));
	foreach ($items as $item) {
		$host = reset($item['hosts']);

		$idents[$item['itemid']] = array(
			'node' => get_node_name_by_elid($item['itemid'], true),
			'host' => $host['host'],
			'key_' => $item['key_']
		);
	}
	return $idents;
}

function triggerIdents($triggerids) {
	$idents = array();

	$triggers = API::Trigger()->get(array(
		'triggerids' => $triggerids,
		'selectHosts' => array('hostid', 'host'),
		'output' => API_OUTPUT_EXTEND,
		'nodeids' => get_current_nodeid(true)
	));
	foreach ($triggers as $trigger) {
		$host = reset($trigger['hosts']);

		$idents[$trigger['triggerid']] = array(
			'node' => get_node_name_by_elid($host['hostid'], true),
			'host' => $host['host'],
			'description' => $trigger['description'],
			'expression' => explode_exp($trigger['expression'])
		);
	}
	return $idents;
}

function graphIdents($graphids) {
	$idents = array();

	$graphs = API::Graph()->get(array(
		'graphids' => $graphids,
		'selectHosts' => array('hostid', 'host'),
		'output' => API_OUTPUT_EXTEND,
		'nodeids' => get_current_nodeid(true)
	));
	foreach ($graphs as $graph) {
		$host = reset($graph['hosts']);

		$idents[$graph['graphid']] = array(
			'node' => get_node_name_by_elid($graph['graphid'], true),
			'host' => $host['host'],
			'name' => $graph['name']
		);
	}
	return $idents;
}

function imageIdents($imageids) {
	$idents = array();

	$images = API::Image()->get(array(
		'imageids' => $imageids,
		'output' => API_OUTPUT_EXTEND,
		'nodeids' => get_current_nodeid(true)
	));
	foreach ($images as $image) {
		$idents[$image['imageid']] = array(
			'node' => get_node_name_by_elid($image['imageid'], true),
			'name' => $image['name']
		);
	}
	return $idents;
}

function getImageByIdent($ident) {
	zbx_value2array($ident);

	if (!isset($ident['name'])) {
		return 0;
	}

	static $images;
	if (is_null($images)) {
		$images = array();

		$dbImages = API::Image()->get(array(
			'output' => API_OUTPUT_EXTEND,
			'nodeids' => get_current_nodeid(true)
		));
		foreach ($dbImages as $image) {
			if (!isset($images[$image['name']])) {
				$images[$image['name']] = array();
			}

			$nodeName = get_node_name_by_elid($image['imageid'], true);

			if (!is_null($nodeName)) {
				$images[$image['name']][$nodeName] = $image;
			}
			else {
				$images[$image['name']][] = $image;
			}
		}
	}

	$ident['name'] = trim($ident['name'], ' ');
	if (!isset($images[$ident['name']])) {
		return 0;
	}

	$searchedImages = $images[$ident['name']];

	if (!isset($ident['node'])) {
		return reset($searchedImages);
	}
	elseif (isset($searchedImages[$ident['node']])) {
		return $searchedImages[$ident['node']];
	}
	else {
		return 0;
	}
}
?>
