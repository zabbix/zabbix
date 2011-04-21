<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php

function screenIdents($screenids){
	$idents = array();

	$options = array(
		'screenids' => $screenids,
		'output' => API_OUTPUT_EXTEND,
		'nodeids'=> get_current_nodeid(true)
	);

	$screens = CScreen::get($options);
	foreach($screens as $inum => $screen){
		$idents[$screen['screenid']] = array(
			'node' => get_node_name_by_elid($screen['screenid'], true),
			'name' => $screen['name']
		);
	}

return $idents;
}

function sysmapIdents($sysmapids){
	$idents = array();

	$options = array(
		'sysmapids' => $sysmapids,
		'output' => API_OUTPUT_EXTEND,
		'nodeids'=> get_current_nodeid(true)
	);

	$sysmaps = CMap::get($options);
	foreach($sysmaps as $snum => $sysmap){
		$idents[$sysmap['sysmapid']] = array(
			'node' => get_node_name_by_elid($sysmap['sysmapid'], true),
			'name' => $sysmap['name']
		);
	}

return $idents;
}

function hostgroupIdents($groupids){
	$idents = array();

	$options = array(
		'groupids' => $groupids,
		'output' => API_OUTPUT_EXTEND,
		'nodeids'=> get_current_nodeid(true)
	);

	$groups = CHostgroup::get($options);
	foreach($groups as $gnum => $group){
		$idents[$group['groupid']] = array(
			'node' => get_node_name_by_elid($group['groupid'], true),
			'name' => $group['name']
		);
	}

return $idents;
}

function hostIdents($hostids){
	$idents = array();

	$options = array(
		'hostids' => $hostids,
		'output' => API_OUTPUT_EXTEND,
		'nodeids'=> get_current_nodeid(true)
	);

	$hosts = CHost::get($options);
	foreach($hosts as $hnum => $host){
		$idents[$host['hostid']] = array(
			'node' => get_node_name_by_elid($host['hostid'], true),
			'host' => $host['host']
		);
	}

return $idents;
}

function itemIdents($itemids){
	$idents = array();

	$options = array(
		'itemids' => $itemids,
		'output' => API_OUTPUT_EXTEND,
		'select_hosts' => array('hostid', 'host'),
		'nodeids'=> get_current_nodeid(true),
		'webitems' => 1,
	);

	$items = CItem::get($options);
	foreach($items as $inum => $item){
		$host = reset($item['hosts']);

		$idents[$item['itemid']] = array(
			'node' => get_node_name_by_elid($item['itemid'], true),
			'host' => $host['host'],
			'key_' => $item['key_']
		);
	}

return $idents;
}

function triggerIdents($triggerids){
	$idents = array();

	$options = array(
		'triggerids' => $triggerids,
		'select_hosts' => array('hostid', 'host'),
		'output' => API_OUTPUT_EXTEND,
		'nodeids'=> get_current_nodeid(true)
	);

	$triggers = CTrigger::get($options);
	foreach($triggers as $tnum => $trigger){
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

function graphIdents($graphids){
	$idents = array();

	$options = array(
		'graphids' => $graphids,
		'select_hosts' => array('hostid', 'host'),
		'output' => API_OUTPUT_EXTEND,
		'nodeids'=> get_current_nodeid(true)
	);

	$graphs = CGraph::get($options);
	foreach($graphs as $inum => $graph){
		$host = reset($graph['hosts']);

		$idents[$graph['graphid']] = array(
			'node' => get_node_name_by_elid($graph['graphid'], true),
			'host' => $host['host'],
			'name' => $graph['name']
		);
	}

return $idents;
}

function imageIdents($imageids){
	$idents = array();

	$options = array(
		'imageids' => $imageids,
		'output' => API_OUTPUT_EXTEND,
		'nodeids'=> get_current_nodeid(true)
	);

	$images = CImage::get($options);
	foreach($images as $inum => $image){
		$idents[$image['imageid']] = array(
			'node' => get_node_name_by_elid($image['imageid'], true),
			'name' => $image['name']
		);
	}

return $idents;
}

function getImageByIdent($ident){
	zbx_value2array($ident);

	if(!isset($ident['name'])) return 0;

	static $images;
	if(is_null($images)){
// get All images
		$images = array();
		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'nodeids' => get_current_nodeid(true)
		);

		$dbImages = CImage::get($options);
		foreach($dbImages as $inum => $img){
			if(!isset($images[$img['name']])) $images[$img['name']] = array();

			$nodeName = get_node_name_by_elid($img['imageid'], true);

			if(!is_null($nodeName))
				$images[$img['name']][$nodeName] = $img;
			else
				$images[$img['name']][] = $img;
		}
//------
	}

	$ident['name'] = trim($ident['name'],' ');
	if(!isset($images[$ident['name']])) return 0;

	$sImages = $images[$ident['name']];

	if(!isset($ident['node'])) return reset($sImages);
	else if(isset($sImages[$ident['node']])) return $sImages[$ident['node']];
	else return 0;
}
?>
