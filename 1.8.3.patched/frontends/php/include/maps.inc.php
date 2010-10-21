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
/*
 * Function: map_link_drawtypes
 *
 * Description:
 *     Return available drawing types for links
 *
 * Author:
 *     Eugene Grigorjev
 *
 */
	function map_link_drawtypes(){
		return array(
				MAP_LINK_DRAWTYPE_LINE,
				MAP_LINK_DRAWTYPE_BOLD_LINE,
				(function_exists('imagesetstyle') ? MAP_LINK_DRAWTYPE_DOT : null),
				MAP_LINK_DRAWTYPE_DASHED_LINE
			    );
	}

/*
 * Function: map_link_drawtype2str
 *
 * Description:
 *     Represent integer value of links drawing type into the string
 *
 * Author:
 *     Eugene Grigorjev
 *
 */
	function map_link_drawtype2str($drawtype){
		switch($drawtype){
			case MAP_LINK_DRAWTYPE_LINE:		$drawtype = S_LINE;			break;
			case MAP_LINK_DRAWTYPE_BOLD_LINE:	$drawtype = S_BOLD_LINE;	break;
			case MAP_LINK_DRAWTYPE_DOT:			$drawtype = S_DOT;			break;
			case MAP_LINK_DRAWTYPE_DASHED_LINE:	$drawtype = S_DASHED_LINE;	break;
			default: $drawtype = S_UNKNOWN;		break;
		}
	return $drawtype;
	}

	function get_sysmap_by_sysmapid($sysmapid){
		$row = DBfetch(DBselect('SELECT * FROM sysmaps WHERE sysmapid='.$sysmapid));
		if($row){
			return	$row;
		}
		error(S_NO_SYSTEM_MAP_WITH.' sysmapid=['.$sysmapid.']');
		return false;
	}

	function get_sysmaps_element_by_selementid($selementid){
		$sql='select * FROM sysmaps_elements WHERE selementid='.$selementid;
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row){
			return	$row;
		}
		else{
			error(S_NO_SYSMAP_ELEMENT_WITH.' selementid=['.$selementid.']');
		}
	return	$result;
	}

// LINKS

	function add_link($link){
		$link_db_fields = array(
			'sysmapid' => null,
			'label' => '',
			'selementid1' => null,
			'selementid2' => null,
			'drawtype' => 2,
			'color' => 3
		);

		if(!check_db_fields($link_db_fields, $link)){
			$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields for link');
			return false;
		}

		$linkid=get_dbid("sysmaps_links","linkid");

		$result=TRUE;
		foreach($link['linktriggers'] as $id => $linktrigger){
			if(empty($linktrigger['triggerid'])) continue;
			$result&=add_link_trigger($linkid,$linktrigger['triggerid'],$linktrigger['drawtype'],$linktrigger['color']);
		}

		if(!$result){
			return $result;
		}

		$result&=DBexecute('INSERT INTO sysmaps_links '.
			' (linkid,sysmapid,label,selementid1,selementid2,drawtype,color) '.
			' VALUES ('.$linkid.','.$link['sysmapid'].','.zbx_dbstr($link['label']).','.
						$link['selementid1'].','.$link['selementid2'].','.
						$link['drawtype'].','.zbx_dbstr($link['color']).')');

		if(!$result)
			return $result;

	return $linkid;
	}

	function update_link($link){
		$link_db_fields = array(
			'sysmapid' => null,
			'linkid' => null,
			'label' => '',
			'selementid1' => null,
			'selementid2' => null,
			'drawtype' => 2,
			'color' => 3
		);

		if(!check_db_fields($link_db_fields, $link)){
			$result = false;
			$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields for link');
			break;
		}

		$result = delete_all_link_triggers($link['linkid']);

		foreach($link['linktriggers'] as $id => $linktrigger){
			if(empty($linktrigger['triggerid'])) continue;
			$result&=add_link_trigger($link['linkid'],$linktrigger['triggerid'],$linktrigger['drawtype'],$linktrigger['color']);
		}

		if(!$result){
			return $result;
		}

		$result&=DBexecute('UPDATE sysmaps_links SET '.
							' sysmapid='.$link['sysmapid'].', '.
							' label='.zbx_dbstr($link['label']).', '.
							' selementid1='.$link['selementid1'].', '.
							' selementid2='.$link['selementid2'].', '.
							' drawtype='.$link['drawtype'].', '.
							' color='.zbx_dbstr($link['color']).
						' WHERE linkid='.$link['linkid']);
	return	$result;
	}

	function delete_link($linkids){
		zbx_value2array($linkids);

		$result = delete_all_link_triggers($linkids);
		$result&= (bool) DBexecute('DELETE FROM sysmaps_links WHERE '.DBcondition('linkid',$linkids));

	return $result;
	}

	function add_link_trigger($linkid,$triggerid,$drawtype,$color){
		$linktriggerid=get_dbid("sysmaps_link_triggers","linktriggerid");
		$sql = 'INSERT INTO sysmaps_link_triggers (linktriggerid,linkid,triggerid,drawtype,color) '.
					" VALUES ($linktriggerid,$linkid,$triggerid,$drawtype,".zbx_dbstr($color).')';
	return DBexecute($sql);
	}

	function delete_all_link_triggers($linkids){
		zbx_value2array($linkids);

		$result = (bool) DBexecute('DELETE FROM sysmaps_link_triggers WHERE '.DBcondition('linkid',$linkids));
	return $result;
	}

/*
 * Function: check_circle_elements_link
 *
 * Description:
 *     Check for circular map creation
 *
 * Author:
 *     Eugene Grigorjev
 *
 */
	function check_circle_elements_link($sysmapid,$elementid,$elementtype){
		if($elementtype!=SYSMAP_ELEMENT_TYPE_MAP)	return false;

		if(bccomp($sysmapid ,$elementid)==0)	return TRUE;

		$db_elements = DBselect('SELECT elementid, elementtype '.
						' FROM sysmaps_elements '.
						' WHERE sysmapid='.$elementid);

		while($element = DBfetch($db_elements)){
			if(check_circle_elements_link($sysmapid,$element["elementid"],$element["elementtype"]))
				return TRUE;
		}
		return false;
	}


	/******************************************************************************
	 *                                                                            *
	 * Purpose: Delete Element FROM sysmap definition                             *
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function delete_sysmaps_element($selementids){
		zbx_value2array($selementids);
		if(empty($selementids)) return true;

		$result = true;
		$linkids = array();

		$sql = 'SELECT linkid '.
				' FROM sysmaps_links '.
				' WHERE '.DBcondition('selementid1',$selementids).
					' OR '.DBcondition('selementid2',$selementids);
		$res=DBselect($sql);
		while($rows = DBfetch($res)){
			$linkids[] = $rows['linkid'];
		}

		if(!empty($linkids)) $result &= delete_link($linkids);

		if(!$result) return	$result;

	return	DBexecute('DELETE FROM sysmaps_elements WHERE '.DBcondition('selementid',$selementids));
	}

	/******************************************************************************
	 *                                                                            *
	 * Comments: !!! Don't forget sync code with C !!!                            *
	 *                                                                            *
	 ******************************************************************************/
	function delete_sysmaps_elements_with_hostid($hostids){
		zbx_value2array($hostids);
		if(empty($hostids)) return true;

		$db_elements = DBselect('SELECT selementid '.
					' FROM sysmaps_elements '.
					' WHERE '.DBcondition('elementid',$hostids).
						' AND elementtype='.SYSMAP_ELEMENT_TYPE_HOST);

		$selementids = array();
		while($db_element = DBfetch($db_elements)){
			$selementids[$db_element['selementid']] = $db_element['selementid'];
		}
		delete_sysmaps_element($selementids);

	return TRUE;
	}

/******************************************************************************
 *                                                                            *
 * Comments: !!! Don't forget sync code with C !!!                            *
 *                                                                            *
 ******************************************************************************/
	function delete_sysmaps_elements_with_triggerid($triggerids){
		zbx_value2array($triggerids);
		if(empty($triggerids)) return true;

		$db_elements = DBselect('SELECT selementid '.
					' FROM sysmaps_elements '.
					' WHERE '.DBcondition('elementid',$triggerids).
						' AND elementtype='.SYSMAP_ELEMENT_TYPE_TRIGGER);
		$selementids = array();
		while($db_element = DBfetch($db_elements)){
			$selementids[$db_element['selementid']] = $db_element['selementid'];
		}
		delete_sysmaps_element($selementids);
	return TRUE;
	}

	function delete_sysmaps_elements_with_groupid($groupids){
		zbx_value2array($groupids);
		if(empty($groupids)) return true;

		$db_elements = DBselect('SELECT selementid '.
						' FROM sysmaps_elements '.
						' WHERE '.DBcondition('elementid',$groupids).
							' AND elementtype='.SYSMAP_ELEMENT_TYPE_HOST_GROUP);
		$selementids = array();
		while($db_element = DBfetch($db_elements)){
			$selementids[$db_element['selementid']] = $db_element['selementid'];
		}
		delete_sysmaps_element($selementids);

	return TRUE;
	}

/*
 * Function: get_action_map_by_sysmapid
 *
 * Description:
 *     Retrieve action for map element
 *
 * Author:
 *     Eugene Grigorjev
 *
 */
	function get_action_map_by_sysmapid($sysmapid){
		$options = array(
				'sysmapids' => $sysmapid,
				'output' => API_OUTPUT_EXTEND,
				'select_selements' => API_OUTPUT_EXTEND,
				'nopermissions' => 1
			);

		$sysmaps = CMap::get($options);
		$sysmap = reset($sysmaps);

	return getActionMapBySysmap($sysmap);
	}

	function getActionMapBySysmap($sysmap){
		$action_map = new CAreaMap('links'.$sysmap['sysmapid']);

		$hostids = array();
		foreach($sysmap['selements'] as $snum => $selement){
			if($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST)
				$hostids[$selement['elementid']] = $selement['elementid'];
		}

		$scripts_by_hosts = CScript::getScriptsByHosts($hostids);

		$options = array(
			'nodeids' => get_current_nodeid(true),
			'hostids' => $hostids,
			'output' => API_OUTPUT_EXTEND,
			'select_groups' => API_OUTPUT_EXTEND,
			'select_triggers' => API_OUTPUT_EXTEND,
			'nopermissions' => 1
		);
		$hosts = CHost::get($options);
		$hosts = zbx_toHash($hosts, 'hostid');

// Draws elements
		$map_info = getSelementsInfo($sysmap);
//SDII($map_info);
		foreach($sysmap['selements'] as $snum => $db_element){
			$url = $db_element['url'];
			$alt = S_LABEL.': '.$db_element['label'];

			if($db_element['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST){
				$host = $hosts[$db_element['elementid']];
				if($host['status'] != HOST_STATUS_MONITORED) continue;

				if(empty($url))	$url='tr_status.php?hostid='.$db_element['elementid'].'&noactions=true&onlytrue=true&compact=true';

				$alt = S_HOST.': '.$host['host'].' '.$alt;
			}
			else if($db_element['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP){
				$map = get_sysmap_by_sysmapid($db_element['elementid']);

				if(empty($url))
					$url='maps.php?sysmapid='.$db_element['elementid'];

				$alt = S_HOST.': '.$map['name'].' '.$alt;
			}
			else if($db_element['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER){
				if(empty($url) && $db_element['elementid']!=0)
					$url='events.php?source=0&triggerid='.$db_element['elementid'].'&nav_time='.(time()-7*86400);
			}
			else if($db_element['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST_GROUP){
				if(empty($url) && $db_element['elementid']!=0)
					$url='events.php?source=0&hostid=0&groupid='.$db_element['elementid'];
			}

			if(empty($url))	continue;

			$back = $map_info[$db_element['selementid']];
//SDII($back);
			$back = get_png_by_selement($db_element, $back);
			if(!$back)	continue;

			$x1_ = $db_element['x'];
			$y1_ = $db_element['y'];
			$x2_ = $db_element['x'] + imagesx($back);
			$y2_ = $db_element['y'] + imagesy($back);

			$r_area = new CArea(array($x1_,$y1_,$x2_,$y2_),$url,$alt,'rect');
			if($db_element['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST){
				$group = reset($hosts[$db_element['elementid']]['groups']);
				$menus = '';

				$host_nodeid = id2nodeid($db_element['elementid']);
				foreach($scripts_by_hosts[$db_element['elementid']] as $id => $script){
					$script_nodeid = id2nodeid($script['scriptid']);
					if( (bccomp($host_nodeid ,$script_nodeid ) == 0))
						$menus.= "['".$script['name']."',\"javascript: openWinCentered('scripts_exec.php?execute=1&hostid=".$db_element["elementid"]."&scriptid=".$script['scriptid']."','".S_TOOLS."',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
				}

				if(!empty($db_element['url']) || !empty($hosts[$db_element['elementid']]['triggers'])){
					$menus.= '['.zbx_jsvalue(S_LINKS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";

					if(!empty($hosts[$db_element['elementid']]['triggers']))
						$menus.= "['".S_STATUS_OF_TRIGGERS."',\"javascript: redirect('tr_status.php?groupid=".$group['groupid']."&hostid=".$db_element['elementid']."&noactions=true&onlytrue=true&compact=true');\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";

					if(!empty($db_element['url']))
						$menus.= "['".S_MAP.SPACE.S_URL."',\"javascript: location.replace('".$url."');\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
				}

				$menus = trim($menus,',');
				$menus="show_popup_menu(event,[[".zbx_jsvalue(S_TOOLS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],".$menus."],180); cancelEvent(event);";

				$r_area->addAction('onclick','javascript: '.$menus);
			}
			$action_map->addItem($r_area);//AddRectArea($x1_,$y1_,$x2_,$y2_, $url, $alt);
		}

		$jsmenu = new CPUMenu(null,170);
		$jsmenu->InsertJavaScript();

	return $action_map;
	}

	function get_icon_center_by_selement($element, $info=null){

		$x = $element['x'];
		$y = $element['y'];

		$image = get_png_by_selement($element, $info);
		if($image){
			$x += imagesx($image) / 2;
			$y += imagesy($image) / 2;
		}

	return array($x, $y);
	}

	function MyDrawLine($image,$x1,$y1,$x2,$y2,$color,$drawtype){
		if($drawtype == MAP_LINK_DRAWTYPE_BOLD_LINE){
			imageline($image,$x1,$y1,$x2,$y2,$color);
			if(abs($x1-$x2) < abs($y1-$y2)){
				$x1++;
				$x2++;
			}
			else{
				$y1++;
				$y2++;
			}

			imageline($image,$x1,$y1,$x2,$y2,$color);
		}
		else if($drawtype == MAP_LINK_DRAWTYPE_DASHED_LINE){
			if(function_exists('imagesetstyle')){
/* Use imagesetstyle+imageline instead of bugged ImageDashedLine */
				$style = array(
					$color, $color, $color, $color,
					IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT
					);

				imagesetstyle($image, $style);
				imageline($image,$x1,$y1,$x2,$y2,IMG_COLOR_STYLED);
			}
			else{
				ImageDashedLine($image,$x1,$y1,$x2,$y2,$color);
			}
		}
		else if($drawtype == MAP_LINK_DRAWTYPE_DOT && function_exists('imagesetstyle')){
			$style = array($color,IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
			imagesetstyle($image, $style);
			imageline($image,$x1,$y1,$x2,$y2,IMG_COLOR_STYLED);
		}
		else{
			imageline($image,$x1,$y1,$x2,$y2,$color);
		}
	}

	function get_png_by_selement($selement, $info){

		switch($info['icon_type']){
			case SYSMAP_ELEMENT_ICON_ON:
				$info['iconid'] = $selement['iconid_on'];
				break;
			case SYSMAP_ELEMENT_ICON_UNKNOWN:
				$info['iconid'] = $selement['iconid_unknown'];
				break;
			case SYSMAP_ELEMENT_ICON_MAINTENANCE:
				$info['iconid'] = $selement['iconid_maintenance'];
				break;
			case SYSMAP_ELEMENT_ICON_DISABLED:
				$info['iconid'] = $selement['iconid_disabled'];
				break;
			case SYSMAP_ELEMENT_ICON_OFF:
			default:
// element image
				$info['iconid'] = $selement['iconid_off'];
				break;
		}

// Process for default icons
		if($info['iconid'] == 0) $info['iconid'] = $selement['iconid_off'];
//------

		$image = get_image_by_imageid($info['iconid']);

		if(!$image){
			return get_default_image(true);
		}

	return imagecreatefromstring($image['image']);
	}

	function get_selement_iconid($selement, $info=null){
		if($selement['selementid'] > 0){
			if(is_null($info)){
// get sysmap
				$options = array(
					'sysmapids' => $selement['sysmapid'],
					'output' => API_OUTPUT_EXTEND
				);
				$sysmaps = CMap::get($options);
				$sysmap = reset($sysmaps);
				$sysmap['selements'] = array($selement);

				$map_info = getSelementsInfo($sysmap);
				$info = reset($map_info);
//-----
			}
//SDI($info);

			switch($info['icon_type']){
				case SYSMAP_ELEMENT_ICON_OFF:
					$info['iconid'] = $selement['iconid_off'];
					break;
				case SYSMAP_ELEMENT_ICON_ON:
					$info['iconid'] = $selement['iconid_on'];
					break;
				case SYSMAP_ELEMENT_ICON_UNKNOWN:
					$info['iconid'] = $selement['iconid_unknown'];
					break;
				case SYSMAP_ELEMENT_ICON_MAINTENANCE:
					$info['iconid'] = $selement['iconid_maintenance'];
					break;
			}

// Process for default icons
			if($info['iconid'] == 0) $info['iconid'] = $selement['iconid_off'];
//------
		}
		else{
			$info['iconid'] = $selement['iconid_off'];
		}

	return $info['iconid'];
	}

	function get_selement_icons(){
// ICONS
		$el_form_menu = array();
		$el_form_menu['icons'] = array();

		$result = DBselect('SELECT * FROM images WHERE imagetype=1 AND '.DBin_node('imageid').' ORDER BY name');
		while($row=DBfetch($result)){
			$row['name'] = get_node_name_by_elid($row['imageid']).$row['name'];
			$el_form_menu['icons'][$row['imageid']] = $row['name'];
		}

		$menu = 'var zbxSelementIcons = '.zbx_jsvalue($el_form_menu, true).';';

	return $menu;
	}

	function convertColor($im,$color){

		$RGB = array(
			hexdec('0x'.substr($color, 0,2)),
			hexdec('0x'.substr($color, 2,2)),
			hexdec('0x'.substr($color, 4,2))
			);


	return imagecolorallocate($im,$RGB[0],$RGB[1],$RGB[2]);
	}

	function expandMapLabels(&$map){
		foreach($map['selements'] as $snum => $selement){
			$map['selements'][$snum]['label_expanded'] = expand_map_element_label_by_data($selement);
		}

		foreach($map['links'] as $lnum => $link){
			$map['links'][$lnum]['label_expanded'] = expand_map_element_label_by_data(null, $link);
		}
	}
/*
 * Function: expand_map_element_label_by_data
 *
 * Description:
 *     substitute simple macros {HOSTNAME}, {HOST.CONN}, {HOST.DNS}, {IPADDRESS} and
 *     functions {hostname:key.min/max/avg/last(...)}
 *     in data string with real values
 *
 * Author:
 *     Aleksander Vladishev
 *
 */
	function expand_map_element_label_by_data($db_element, $link = null){
		$label = (null != $db_element) ? $db_element['label'] : $link['label'];

		if (null != $db_element){
			switch($db_element['elementtype']){
			case SYSMAP_ELEMENT_TYPE_HOST:
			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				while(zbx_strstr($label, '{HOSTNAME}') ||
						zbx_strstr($label, '{HOST.DNS}') ||
						zbx_strstr($label, '{IPADDRESS}') ||
						zbx_strstr($label, '{HOST.CONN}'))
				{
					if($db_element['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST){
						$sql =' SELECT * FROM hosts WHERE hostid='.$db_element['elementid'];
					}
					else if($db_element['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER)
						$sql =	'SELECT h.* '.
							' FROM hosts h,items i,functions f '.
							' WHERE h.hostid=i.hostid '.
								' AND i.itemid=f.itemid '.
								' AND f.triggerid='.$db_element['elementid'];
					else{
// Should never be here
					}

					$db_hosts = DBselect($sql);

					if($db_host = DBfetch($db_hosts)){
						if(zbx_strstr($label, '{HOSTNAME}')){
							$label = str_replace('{HOSTNAME}', $db_host['host'], $label);
						}

						if(zbx_strstr($label, '{HOST.DNS}')){
							$label = str_replace('{HOST.DNS}', $db_host['dns'], $label);
						}

						if(zbx_strstr($label, '{IPADDRESS}')){
							$label = str_replace('{IPADDRESS}', $db_host['ip'], $label);
						}

						if(zbx_strstr($label, '{HOST.CONN}')){
							$label = str_replace('{HOST.CONN}', $db_host['useip'] ? $db_host['ip'] : $db_host['dns'], $label);
						}
					}
				}
				break;
			}

			switch($db_element['elementtype']){
				case SYSMAP_ELEMENT_TYPE_HOST:
				case SYSMAP_ELEMENT_TYPE_MAP:
				case SYSMAP_ELEMENT_TYPE_TRIGGER:
				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					while(zbx_strstr($label, '{TRIGGERS.UNACK}')){
						$label = str_replace('{TRIGGERS.UNACK}', get_triggers_unacknowledged($db_element), $label);
					}
					while(zbx_strstr($label, '{TRIGGERS.PROBLEM.UNACK}')){
						$label = str_replace('{TRIGGERS.PROBLEM.UNACK}', get_triggers_unacknowledged($db_element, true), $label);
					}
					while(zbx_strstr($label, '{TRIGGER.EVENTS.UNACK}')){
						$label = str_replace('{TRIGGER.EVENTS.UNACK}', get_events_unacknowledged($db_element), $label);
					}
					while(zbx_strstr($label, '{TRIGGER.EVENTS.PROBLEM.UNACK}')){
						$label = str_replace('{TRIGGER.EVENTS.PROBLEM.UNACK}', get_events_unacknowledged($db_element, null, TRIGGER_VALUE_TRUE), $label);
					}
					while(zbx_strstr($label, '{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}')){
						$label = str_replace('{TRIGGER.PROBLEM.EVENTS.PROBLEM.UNACK}', get_events_unacknowledged($db_element, TRIGGER_VALUE_TRUE, TRIGGER_VALUE_TRUE), $label);
					}
					while(zbx_strstr($label, '{TRIGGERS.ACK}')){
						$label = str_replace('{TRIGGERS.ACK}', get_triggers_unacknowledged($db_element, null, true), $label);
					}
					while(zbx_strstr($label, '{TRIGGERS.PROBLEM.ACK}')){
						$label = str_replace('{TRIGGERS.PROBLEM.ACK}', get_triggers_unacknowledged($db_element, true, true), $label);
					}
					while(zbx_strstr($label, '{TRIGGER.EVENTS.ACK}')){
						$label = str_replace('{TRIGGER.EVENTS.ACK}', get_events_unacknowledged($db_element, null, null, true), $label);
					}
					while(zbx_strstr($label, '{TRIGGER.EVENTS.PROBLEM.ACK}')){
						$label = str_replace('{TRIGGER.EVENTS.PROBLEM.ACK}', get_events_unacknowledged($db_element, null, TRIGGER_VALUE_TRUE, true), $label);
					}
					while(zbx_strstr($label, '{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}')){
						$label = str_replace('{TRIGGER.PROBLEM.EVENTS.PROBLEM.ACK}', get_events_unacknowledged($db_element, TRIGGER_VALUE_TRUE, TRIGGER_VALUE_TRUE, true), $label);
					}
					break;
			}
		}

/*
		while(false !== ($pos = zbx_strpos($label, '{'))){

			$expr = substr($label, $pos);

			if(false === ($pos = zbx_strpos($expr, '}'))) break;

			$expr = substr($expr, 1, $pos - 1);

			if(false === ($pos = zbx_strpos($expr, ':'))){
				$label = str_replace('{'.$expr.'}', '???', $label);
				continue;
			}

			$host = substr($expr, 0, $pos);
			$key = substr($expr, $pos + 1);

			if(false === ($pos = zbx_strrpos($key, '.'))){
				$label = str_replace('{'.$expr.'}', '???', $label);
				continue;
			}

			$function = substr($key, $pos + 1);
			$key = substr($key, 0, $pos);

			if(false === ($pos = zbx_strpos($function, '('))){
				$label = str_replace('{'.$expr.'}', '???', $label);
				continue;
			}

			$parameter = substr($function, $pos + 1);
			$function = substr($function, 0, $pos);

			if(false === ($pos = zbx_strrpos($parameter, ')'))){
				$label = str_replace('{'.$expr.'}', '???', $label);
				continue;
			}

			$parameter = substr($parameter, 0, $pos);
*/
		$pattern = "/{(?P<host>.+):(?P<key>.+)\.(?P<func>.+)\((?P<param>.+)\)}/u";
		preg_match_all($pattern, $label, $matches);

		foreach($matches[0] as $num => $expr){
			$host = $matches['host'][$num];
			$key = $matches['key'][$num];
			$function = $matches['func'][$num];
			$parameter = $matches['param'][$num];

			$options = array(
				'filter' => array('host' => $host, 'key_' => $key),
				'output' => API_OUTPUT_EXTEND
			);
			$db_item = CItem::get($options);
			$db_item = reset($db_item);
			if(!$db_item){
				$label = str_replace($expr, '???', $label);
				continue;
			}

			switch($db_item['value_type']){
				case ITEM_VALUE_TYPE_FLOAT:
					$history_table = 'history';
					$order_field = 'clock';
					break;
				case ITEM_VALUE_TYPE_UINT64:
					$history_table = 'history_uint';
					$order_field = 'clock';
					break;
				case ITEM_VALUE_TYPE_TEXT:
					$history_table = 'history_text';
					$order_field = 'id';
					break;
				case ITEM_VALUE_TYPE_LOG:
					$history_table = 'history_log';
					$order_field = 'id';
					break;
				default:
// ITEM_VALUE_TYPE_STR
					$history_table = 'history_str';
					$order_field = 'clock';
			}

			if(0 == strcmp($function, 'last')){
				$sql = 'SELECT value '.
						' FROM '.$history_table.
						' WHERE itemid='.$db_item['itemid'].
						' ORDER BY '.$order_field.' DESC';

				$result = DBselect($sql, 1);
				if(NULL == ($row = DBfetch($result)))
					$label = str_replace($expr, '('.S_NO_DATA_SMALL.')', $label);
				else{
					switch($db_item['value_type']){
						case ITEM_VALUE_TYPE_FLOAT:
						case ITEM_VALUE_TYPE_UINT64:
							$value = convert_units($row['value'], $db_item['units']);
							break;
						default:
							$value = $row['value'];
					}

					$label = str_replace($expr, $value, $label);
				}
			}
			else if((0 == strcmp($function, 'min')) || (0 == strcmp($function, 'max')) || (0 == strcmp($function, 'avg'))){

				if($db_item['value_type'] != ITEM_VALUE_TYPE_FLOAT && $db_item['value_type'] != ITEM_VALUE_TYPE_UINT64){
					$label = str_replace($expr, '???', $label);
					continue;
				}

				$now = time(NULL) - $parameter;
				$sql = 'SELECT '.$function.'(value) as value '.
						' FROM '.$history_table.
						' WHERE clock>'.$now.
							' AND itemid='.$db_item['itemid'];

				$result = DBselect($sql);
				if(NULL == ($row = DBfetch($result)) || is_null($row['value']))
					$label = str_replace($expr, '('.S_NO_DATA_SMALL.')', $label);
				else
					$label = str_replace($expr, convert_units($row['value'], $db_item['units']), $label);
			}
			else{
				$label = str_replace($expr, '???', $label);
				continue;
			}
		}

	return $label;
	}

	function get_map_elements($db_element, &$elements){
		switch($db_element['elementtype']){
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				$elements['hosts_groups'][] = $db_element['elementid'];
				break;
			case SYSMAP_ELEMENT_TYPE_HOST:
				$elements['hosts'][] = $db_element['elementid'];
				break;
			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				$elements['triggers'][] = $db_element['elementid'];
				break;
			case SYSMAP_ELEMENT_TYPE_MAP:
				$sql = 'SELECT DISTINCT elementtype,elementid'.
						' FROM sysmaps_elements'.
						' WHERE sysmapid='.$db_element['elementid'];
				$db_mapselements = DBselect($sql);
				while($db_mapelement = DBfetch($db_mapselements)){
					get_map_elements($db_mapelement, $elements);
				}
				break;
		}
	}

	function add_elementNames(&$selements){
		$hostids = array();
		$triggerids = array();
		$mapids = array();
		$hostgroupids = array();

		foreach($selements as $snum => $selement){
			switch($selement['elementtype']){
				case SYSMAP_ELEMENT_TYPE_HOST:
					$hostids[] = $selement['elementid'];
					break;
				case SYSMAP_ELEMENT_TYPE_MAP:
					$mapids[] = $selement['elementid'];
					break;
				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					$triggerids[] = $selement['elementid'];
					break;
				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$hostgroupids[] = $selement['elementid'];
					break;
				case SYSMAP_ELEMENT_TYPE_IMAGE:
				default:
					break;
			}
		}


		$hosts = CHost::get(array('hostids'=>$hostids, 'extendoutput'=>1, 'nopermissions'=>1, 'nodeids' => get_current_nodeid(true)));
		$hosts = zbx_toHash($hosts, 'hostid');

		$maps = CMap::get(array('mapids'=>$mapids, 'extendoutput'=>1, 'nopermissions'=>1, 'nodeids' => get_current_nodeid(true)));
		$maps = zbx_toHash($maps, 'sysmapid');

		$triggers = CTrigger::get(array('triggerids'=>$triggerids, 'extendoutput'=>1, 'nopermissions'=>1, 'nodeids' => get_current_nodeid(true)));
		$triggers = zbx_toHash($triggers, 'triggerid');

		$hostgroups = CHostGroup::get(array('hostgroupids'=>$hostgroupids, 'extendoutput'=>1, 'nopermissions'=>1, 'nodeids' => get_current_nodeid(true)));
		$hostgroups = zbx_toHash($hostgroups, 'groupid');

		foreach($selements as $snum => $selement){
			switch($selement['elementtype']){
				case SYSMAP_ELEMENT_TYPE_HOST:
					$selements[$snum]['elementName'] = $hosts[$selement['elementid']]['host'];
					break;
				case SYSMAP_ELEMENT_TYPE_MAP:
					$selements[$snum]['elementName'] = $maps[$selement['elementid']]['name'];
					break;
				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					$selements[$snum]['elementName'] = expand_trigger_description_by_data($triggers[$selement['elementid']]);
					break;
				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$selements[$snum]['elementName'] = $hostgroups[$selement['elementid']]['name'];
					break;
				case SYSMAP_ELEMENT_TYPE_IMAGE:
				default:
					$selements[$snum]['elementName'] = 'image';
			}
		}
	}

//------------------------------------

/*
 * Function: getTriggersInfo
 * Description: Retrive selement
 * Author: Aly
 */
 	function getTriggersInfo($selements, $highlight=false){
		global $colors;

		$selements_info = array();
		$options = array(
			'nodeids' => get_current_nodeid(true),
			'triggerids' => zbx_objectValues($selements, 'elementid'),
			'expandDescription' => 1,
			'nopermissions' => 1,
			'output' => API_OUTPUT_EXTEND
		);
		$triggers = CTrigger::get($options);
		$triggers = zbx_toHash($triggers, 'triggerid');


		$config = select_config();
		if($highlight && $config['event_ack_enable']){
			$options = array(
				'nodeids' => get_current_nodeid(true),
				'triggerids' => array_keys($triggers),
				'nopermissions' => true,
				'withLastEventUnacknowledged' => true,
				'output' => API_OUTPUT_SHORTEN,
				'filter' => array('value' => TRIGGER_VALUE_TRUE),
			);
			$triggers_unack = CTrigger::get($options);
			$triggers_unack = zbx_toHash($triggers_unack, 'triggerid');
		}

		foreach($selements as $snum => $selement){
			$selements_info[$selement['selementid']] = array();
			$info = &$selements_info[$selement['selementid']];

			if(!isset($triggers[$selement['elementid']])) continue;
			$trigger = $triggers[$selement['elementid']];

// name
			$info['name'] = $trigger['description'];
			$info['elementtype'] = SYSMAP_ELEMENT_TYPE_TRIGGER;
			$info['maintenances'] = array();

			if($trigger['value'] == TRIGGER_VALUE_UNKNOWN)
				$info['latelyChanged'] = false;
			else
				$info['latelyChanged'] = (bool) ((time() - $trigger['lastchange']) < TRIGGER_BLINK_PERIOD);

			$info['triggers'] = array();
			$info['status'] = array();

			$info['type'] = $trigger['value'];
			if($info['type'] == TRIGGER_VALUE_TRUE){
				array_push($info['triggers'], $trigger['triggerid']);
			}

			if($trigger['status'] != TRIGGER_STATUS_ENABLED){
				$info['type'] = TRIGGER_VALUE_FALSE;
				$info['disabled'] = 1;
			}

			$info['status'][$info['type']] = array('count' => 1);
			$info['status'][$info['type']]['priority'] = $trigger['priority'];
			$info['status'][$info['type']]['info'] = $info['name'];

			$info['ack'] = !isset($triggers_unack[$trigger['triggerid']]);
			$info['status']['count_unack'] = (int) !$info['ack'];

			if($info['type'] == TRIGGER_VALUE_TRUE){
				$color = ($info['status'][$info['type']]['priority'] > 3) ? $colors['Red'] : $colors['Dark Red'];

				$info['info'] = array();
				$info['info']['problem'] = array('msg'=>S_PROBLEM_BIG, 'color'=>$color);

				$info['iconid'] = $selement['iconid_on'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;

			}
			else if($info['type'] == TRIGGER_VALUE_UNKNOWN){
				$info['info'] = array();
				$info['info']['unknown'] = array(
					'msg' => S_UNKNOWN_BIG,
					'color' => $colors['Gray']
				);

				$info['iconid'] = $selement['iconid_unknown'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_UNKNOWN;
			}
			else if($info['type'] == TRIGGER_VALUE_FALSE){
				$info['info'] = array();
				$info['info']['ok'] = array(
					'msg'=>S_OK_BIG,
					'color'=>$colors['Dark Green']
				);
				$info['iconid'] = $selement['iconid_off'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
			}

			if(isset($info['disabled']) && $info['disabled'] == 1){
// Disabled
				$info['info'] = array();
				$info['info']['status'] = array(
					'msg'=>S_DISABLED_BIG,
					'color'=>$colors['Dark Red']
				);

				$info['iconid'] = $selement['iconid_disabled'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
			}
//---
			$info['priority'] = isset($info['status'][$info['type']]['priority']) ? $info['status'][$info['type']]['priority'] : 0;
		}


	return $selements_info;
	}

/*
 * Function: getHostsInfo
 * Description: Retrive selement
 * Author: Aly
 */

 	function getHostsInfo($selements, $expandProblem=false, $show_unack=EXTACK_OPTION_ALL){
		global $colors;

		$selements_info = array();

		$options = array(
			'hostids' => zbx_objectValues($selements, 'elementid'),
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => 1,
			'nodeids' => get_current_nodeid(true)
		);
		$hosts = CHost::get($options);
		$hosts = zbx_toHash($hosts, 'hostid');

		$monitored_hostids = array();
		foreach($hosts as $host){
			if(($host['status'] == HOST_STATUS_MONITORED) && ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_OFF))
				$monitored_hostids[] = $host['hostid'];
		}

		$options = array(
			'hostids' => $monitored_hostids,
			'lastChangeSince' => (time() - TRIGGER_BLINK_PERIOD),
			'filter' => array('value' => array(TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE)),
			'output' => API_OUTPUT_SHORTEN,
		);
		$latestTriggers = CTrigger::get($options);
		$latestTriggers = zbx_toHash($latestTriggers, 'triggerid');


		$options = array(
			'hostids' => $monitored_hostids,
			'monitored' => 1,
			'filter' => array('value' => array(TRIGGER_VALUE_UNKNOWN, TRIGGER_VALUE_TRUE)),
			'expandDescription' => true,
			'output' => API_OUTPUT_EXTEND,
			'nodeids' => get_current_nodeid(true)
		);
		$triggers = CTrigger::get($options);
		$triggers = zbx_toHash($triggers, 'triggerid');


		$options = array(
			'triggerids' => array_keys($triggers),
			'withLastEventUnacknowledged' => true,
			'output' => API_OUTPUT_SHORTEN,
			'nodeids' => get_current_nodeid(true),
			'filter' => array('value' => TRIGGER_VALUE_TRUE),
		);
		$unack_triggerids = CTrigger::get($options);
		$unack_triggerids = zbx_toHash($unack_triggerids, 'triggerid');

		foreach($hosts as $hostid => $host){
			$hosts[$hostid]['triggers'] = array();
		}
		foreach($triggers as $trigger){
			foreach($trigger['hosts'] as $host){
				$hosts[$host['hostid']]['triggers'][$trigger['triggerid']] = $trigger;
			}
		}

		foreach($selements as $snum => $selement){
			$selements_info[$selement['selementid']] = array();
			$info = &$selements_info[$selement['selementid']];

			if(!isset($hosts[$selement['elementid']])) continue;
			$host = $hosts[$selement['elementid']];

			$info['name'] = $host['host'];
			$info['elementtype'] = SYSMAP_ELEMENT_TYPE_HOST;
			$info['latelyChanged'] = false;
			$info['maintenances'] = array();
			$info['available'] = $host['available'];
			$info['snmp_available'] = $host['snmp_available'];
			$info['ipmi_available'] = $host['ipmi_available'];
			$info['triggers'] = array();
			$info['status'] = array();

			if($host['status'] == HOST_STATUS_TEMPLATE){
				$info['type'] = TRIGGER_VALUE_FALSE;
				$info['status'][TRIGGER_VALUE_FALSE]['count']	= 0;
				$info['status'][TRIGGER_VALUE_FALSE]['priority'] = 0;
				$info['status'][TRIGGER_VALUE_FALSE]['info']	= S_TEMPLATE_SMALL;
				$info['status'][TRIGGER_VALUE_FALSE]['count_unack']		= 0;
			}
			else if($host['status'] == HOST_STATUS_NOT_MONITORED){
				$info['disabled'] = true;
				$info['info'] = array(
					'status' => array(
						'msg'=>S_DISABLED_BIG,
						'color'=>$colors['Dark Red']
					)
				);

				$info['type'] = TRIGGER_VALUE_FALSE;
				$info['iconid'] = $selement['iconid_disabled'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
			}
			else if($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON){
				$info['maintenance_status'] = true;
				$info['maintenances'][] = $host['hostid'];

				$msg = S_MAINTENANCE_BIG;
				if($host['maintenanceid'] > 0){
					$mnt = get_maintenance_by_maintenanceid($host['maintenanceid']);
					$msg.=' ('.$mnt['name'].')';
				}

				$info['info'] = array(
					'status' => array(
						'msg'=>$msg,
						'color'=>$colors['Orange'],
					)
				);

				$info['type'] = TRIGGER_VALUE_FALSE;
				$info['iconid'] = $selement['iconid_maintenance'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
			}
			else if($host['status'] == HOST_STATUS_MONITORED){
				$info['status'] = array(
					TRIGGER_VALUE_TRUE => array(
						'count' => 0,
						'priority' => 0,
					),
					TRIGGER_VALUE_UNKNOWN => array(
						'count' => 0,
						'priority' => 0,
					),
					TRIGGER_VALUE_FALSE => array(
						'count' => 0,
						'priority' => 0,
					),
					'count_unack' => 0,
				);
				$info['type'] = TRIGGER_VALUE_FALSE;

				foreach($host['triggers'] as $triggerid => $trigger){
					if(($info['type'] != TRIGGER_VALUE_TRUE) && ($trigger['value'] == TRIGGER_VALUE_TRUE)){
						$info['type'] = TRIGGER_VALUE_TRUE;
					}
					else if(($info['type'] != TRIGGER_VALUE_UNKNOWN) && ($trigger['value'] == TRIGGER_VALUE_UNKNOWN)){
						$info['type'] = TRIGGER_VALUE_UNKNOWN;
					}


					if($trigger['value'] == TRIGGER_VALUE_TRUE){
						array_push($info['triggers'], $trigger['triggerid']);
					}
					$info['status'][$trigger['value']]['info'] = $info['name'];


					$info['status'][$trigger['value']]['count']++;

					if(isset($unack_triggerids[$triggerid]))
						$info['status']['count_unack']++;

					if($info['status'][$trigger['value']]['priority'] < $trigger['priority']){
						$info['status'][$trigger['value']]['priority'] = $trigger['priority'];
						if($trigger['value'] == TRIGGER_VALUE_TRUE){
							$info['status'][TRIGGER_VALUE_TRUE]['info'] = $trigger['description'];
						}
					}

					$info['latelyChanged'] = isset($latestTriggers[$triggerid]);
				}

				if($info['type'] == TRIGGER_VALUE_TRUE){
					$color = ($info['status'][$info['type']]['priority'] > 3) ? $colors['Red'] : $colors['Dark Red'];

					$info['info'] = array();
					if(in_array($show_unack, array(EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH))){
						if($info['status'][TRIGGER_VALUE_TRUE]['count'] > 1){
							$msg = $info['status'][TRIGGER_VALUE_TRUE]['count'].' '.S_PROBLEMS;
						}
						else if($expandProblem && isset($info['status'][TRIGGER_VALUE_TRUE]['info'])){
							$msg = $info['status'][TRIGGER_VALUE_TRUE]['info'];
						}
						else{
							$msg = $info['status'][TRIGGER_VALUE_TRUE]['count'].' '.S_PROBLEM;
						}
						$info['info']['problem'] = array(
							'msg' => $msg,
							'color' => $color
						);
					}

					if(in_array($show_unack, array(EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH))){
						if($info['status']['count_unack']){
							$info['info']['unack'] = array(
								'msg' => $info['status']['count_unack'] . ' '.S_UNACKNOWLEDGED,
								'color' => $colors['Dark Red']
							);
						}
					}

					if(isset($info['status'][TRIGGER_VALUE_UNKNOWN]) && $info['status'][TRIGGER_VALUE_UNKNOWN]['count']){
						$info['info']['unknown'] = array(
							'msg' => $info['status'][TRIGGER_VALUE_UNKNOWN]['count'] . ' ' . S_UNKNOWN,
							'color' => $colors['Gray']
						);
					}

					$info['iconid'] = $selement['iconid_on'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
				}
				else if($info['type'] == TRIGGER_VALUE_UNKNOWN){
					$info['info'] = array();
					$info['info']['unknown'] = array(
						'msg' => $info['status'][TRIGGER_VALUE_UNKNOWN]['count'] . ' ' . S_UNKNOWN,
						'color' => $colors['Gray']
					);

					$info['iconid'] = $selement['iconid_unknown'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_UNKNOWN;
				}
				else if($info['type'] == TRIGGER_VALUE_FALSE){
					$info['info'] = array();
					$info['info']['unknown'] = array(
						'msg' => S_OK_BIG,
						'color' => $colors['Dark Green']
					);
					$info['iconid'] = $selement['iconid_off'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
				}

				$info['priority'] = $info['status'][$info['type']]['priority'];
				$info['ack'] = !$info['status']['count_unack'];
			}

/*			else if(($info['available'] == HOST_AVAILABLE_UNKNOWN) &&
				($info['snmp_available'] == HOST_AVAILABLE_UNKNOWN) &&
				($info['ipmi_available'] == HOST_AVAILABLE_UNKNOWN))
			{
// UNKNOWN
				$info['info'] = array();
				$info['info']['unknown'] = array('msg'=>S_UNKNOWN_BIG, 'color'=>$colors['Gray']);

				$info['iconid'] = $selement['iconid_unknown'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_UNKNOWN;
				$info['unavailable'] = HOST_AVAILABLE_UNKNOWN;
			}
			else if(($info['available'] != HOST_AVAILABLE_TRUE) &&
				($info['snmp_available'] != HOST_AVAILABLE_TRUE) &&
				($info['ipmi_available'] != HOST_AVAILABLE_TRUE))
			{
// UNAVAILABLE
				$info['type'] = TRIGGER_VALUE_UNKNOWN;

				$info['info'] = array();
				if(($info['available'] == HOST_AVAILABLE_FALSE))
					$info['info']['availability'] = array('msg'=>S_UNAVAILABLE_BIG, 'color'=>$colors['Red']);
				if(($info['snmp_available'] == HOST_AVAILABLE_FALSE))
					$info['info']['availability'] = array('msg'=>'SNMP '.S_UNAVAILABLE_BIG, 'color'=>$colors['Red']);
				if(($info['ipmi_available'] == HOST_AVAILABLE_FALSE))
					$info['info']['availability'] = array('msg'=>'IPMI '.S_UNAVAILABLE_BIG, 'color'=>$colors['Red']);

				$info['iconid'] = $selement['iconid_on'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
				$info['unavailable'] = HOST_AVAILABLE_FALSE;
			}
//*/

		}
	return $selements_info;
	}

/*
 * Function: getHostGroupsInfo
 * Description: Retrive selement
 * Author: Aly
 */
 	function getHostGroupsInfo($selements, $expandProblem=false, $show_unack=EXTACK_OPTION_ALL){
		global $colors;

		$selements_info = array();
		$options = array(
			'nodeids' => get_current_nodeid(true),
			'groupids' => zbx_objectValues($selements, 'elementid'),
			'select_hosts' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => 1
		);
		$hostgroups = CHostGroup::get($options);
		$hostgroups = zbx_toHash($hostgroups, 'groupid');

		$options = array(
			'groupids' => array_keys($hostgroups),
			'lastChangeSince' => (time() - TRIGGER_BLINK_PERIOD),
			'filter' => array(
				'value' => array(TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE)
			)
		);
		$latestTriggers = CTrigger::get($options);
		$latestTriggers = zbx_toHash($latestTriggers, 'triggerid');

		$options = array(
			'groupids' => array_keys($hostgroups),
			'withLastEventUnacknowledged' => true,
			'output' => API_OUTPUT_SHORTEN,
			'nodeids' => get_current_nodeid(true),
			'filter' => array('value' => TRIGGER_VALUE_TRUE),
		);
		$unack_triggerids = CTrigger::get($options);
		$unack_triggerids = zbx_toHash($unack_triggerids, 'triggerid');

		foreach($selements as $snum => $selement){
			$selements_info[$selement['selementid']] = array();
			$info = &$selements_info[$selement['selementid']];

			if(!isset($hostgroups[$selement['elementid']])) continue;
			$group = $hostgroups[$selement['elementid']];

			$info['name'] = $group['name'];
			$info['elementtype'] = SYSMAP_ELEMENT_TYPE_HOST_GROUP;
			$info['latelyChanged'] = false;
			$info['maintenances'] = array();

			foreach($group['hosts'] as $hnum => $host){
				if($host['status'] == HOST_STATUS_TEMPLATE) continue;

				if($host['status'] != HOST_STATUS_MONITORED){
					$info['type'] = TRIGGER_VALUE_FALSE;
					$info['disabled'] = 1;
				}

				if($host['available'] != HOST_AVAILABLE_TRUE){
					$info['available'] = $host['available'];
				}

				if($host['snmp_available'] != HOST_AVAILABLE_TRUE){
					$info['snmp_available'] = $host['snmp_available'];
				}

				if($host['ipmi_available'] != HOST_AVAILABLE_TRUE){
					$info['ipmi_available'] = $host['ipmi_available'];
				}

				if($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON){
					$info['maintenances'][] = $host['hostid'];
				}
			}

			$options = array(
				'groupids' => $group['groupid'],
				'maintenance' => 0,
				'templated' => 0,
				'monitored' => 1,
				'filter' => array('value' => array(TRIGGER_VALUE_UNKNOWN, TRIGGER_VALUE_TRUE)),
				'output' => API_OUTPUT_EXTEND,
				'nodeids' => get_current_nodeid(true)
			);

			$triggers = CTrigger::get($options);
			$triggers = zbx_toHash($triggers, 'triggerid');

			$info['triggers'] = array();
			$info['status'] = array();
			$info['status']['count_unack'] = 0;

			foreach($triggers as $tnum => $trigger){
				if($trigger['status'] == TRIGGER_STATUS_DISABLED) continue;

				if(!isset($info['type'])){
					$info['type'] = $trigger['value'];
				}
				else if($trigger['value'] == TRIGGER_VALUE_TRUE){
					$info['type'] = $trigger['value'];
				}
				else if($info['type'] != TRIGGER_VALUE_TRUE){
					if(($info['type'] == TRIGGER_VALUE_FALSE) || ($trigger['value'] == TRIGGER_VALUE_UNKNOWN)){
						$info['type'] = $trigger['value'];
					}
				}

				if($trigger['value'] == TRIGGER_VALUE_TRUE){
					$info['triggers'][] = $trigger['triggerid'];
				}

				if(!isset($info['status'][$trigger['value']]))
					$info['status'][$trigger['value']] = array('count' => 0);

				$info['status'][$trigger['value']]['count']++;
				$info['status'][$trigger['value']]['info'] = $info['name'];

				if(isset($unack_triggerids[$trigger['triggerid']]))
					$info['status']['count_unack']++;

				if(!isset($info['status'][$trigger['value']]['priority']) || ($info['status'][$trigger['value']]['priority'] < $trigger['priority'])){
					$info['status'][$trigger['value']]['priority'] = $trigger['priority'];
					if($info['type'] != TRIGGER_VALUE_UNKNOWN){
						$info['status'][$trigger['value']]['info'] = expand_trigger_description_by_data($trigger);
					}
				}

				if(isset($latestTriggers[$trigger['triggerid']])){
					$info['latelyChanged'] = true;
				}
			}

			if(!isset($info['type'])) $info['type'] = TRIGGER_VALUE_FALSE;

			if(!isset($info['status'][TRIGGER_VALUE_FALSE])){
				$info['status'][TRIGGER_VALUE_FALSE]['count']		= 0;
				$info['status'][TRIGGER_VALUE_FALSE]['priority']	= 0;
				$info['status'][TRIGGER_VALUE_FALSE]['info']		= S_OK_BIG;
			}

//----
			$info['info'] = array();

// Host maintenance info
			if(!empty($info['maintenances'])){
				$info['info']['maintenances'] = array(
					'msg' => count($info['maintenances']).' '.S_MAINTENANCE,
					'color' => $colors['Orange']
				);
			}

// Counting problems
			if($info['type'] == TRIGGER_VALUE_TRUE){
				$color = ($info['status'][$info['type']]['priority'] > 3) ? $colors['Red'] : $colors['Dark Red'];

				if(in_array($show_unack, array(EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH))){
					if($info['status'][$info['type']]['count'] > 1)
						$msg = $info['status'][$info['type']]['count'].' '.S_PROBLEMS;
					else if($expandProblem && isset($info['status'][$info['type']]['info']))
						$msg = $info['status'][$info['type']]['info'];
					else
						$msg = $info['status'][$info['type']]['count'].' '.S_PROBLEM;

					$info['info']['problem'] = array('msg'=>$msg, 'color'=>$color);
				}

				if(in_array($show_unack, array(EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH))){
					if($info['status']['count_unack']){
						$info['info']['unack'] = array(
							'msg' => $info['status']['count_unack'] . ' '.S_UNACKNOWLEDGED,
							'color' => $colors['Dark Red']
						);
					}
				}

				if(isset($info['status'][TRIGGER_VALUE_UNKNOWN])){
					$info['info']['unknown'] = array(
						'msg' => $info['status'][TRIGGER_VALUE_UNKNOWN]['count'].' '.S_UNKNOWN,
						'color' => $colors['Gray']
					);
				}

				$info['iconid'] = $selement['iconid_on'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
			}
			else if($info['type'] == TRIGGER_VALUE_UNKNOWN){
				$info['info']['unknown'] = array(
					'msg' => $info['status'][TRIGGER_VALUE_UNKNOWN]['count'].' '.S_UNKNOWN,
					'color' => $colors['Gray']
				);

				if(isset($info['disabled']) && $info['disabled'] == 1)
					$info['iconid'] = $selement['iconid_disabled'];
				else
					$info['iconid'] = $selement['iconid_unknown'];

				$info['icon_type'] = SYSMAP_ELEMENT_ICON_UNKNOWN;
			}
			else if($info['type'] == TRIGGER_VALUE_FALSE){
// Hosts status
				if(!empty($info['maintenances'])){
					$info['iconid'] = $selement['iconid_maintenance'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
				}
				else if(isset($info['disabled']) && $info['disabled'] == 1){
					$info['info']['ok'] = array(
						'msg'=>S_OK_BIG,
						'color'=>$colors['Dark Green']
					);

					$info['iconid'] = $selement['iconid_disabled'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
				}
				else{
					$info['info']['ok'] = array(
						'msg'=>S_OK_BIG,
						'color'=>$colors['Dark Green']
					);

					$info['iconid'] = $selement['iconid_off'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
				}
			}

			$info['ack'] = !$info['status']['count_unack'];
			$info['priority'] = isset($info['status'][$info['type']]['priority']) ? $info['status'][$info['type']]['priority'] : 0;
//---
		}

	return $selements_info;
	}

/*
 * Function: getMapsInfo
 * Description: Retrive selement
 * Author: Aly
 */

 	function getMapsInfo($selements, $expandProblem=false, $show_unack=EXTACK_OPTION_ALL){
		global $colors;

		$selements_info = array();
		$options = array(
			'sysmapids' => zbx_objectValues($selements, 'elementid'),
			'output' => API_OUTPUT_EXTEND,
			'select_selements' => API_OUTPUT_EXTEND,
			'nopermissions' => 1,
			'nodeids' => get_current_nodeid(true)
		);
		$maps = CMap::get($options);
		$maps = zbx_toHash($maps, 'sysmapid');
		foreach($selements as $snum => $selement){
			$selements_info[$selement['selementid']] = array();
			$info = &$selements_info[$selement['selementid']];

			if(!isset($maps[$selement['elementid']])) continue;
			$map = $maps[$selement['elementid']];

			$info['name'] = $map['name'];
			$info['elementtype'] = SYSMAP_ELEMENT_TYPE_MAP;
			$info['latelyChanged'] = false;
			$info['maintenances'] = array();
			$info['ack'] = true;
			$info['status'] = array();
			$info['status']['count_unack'] = 0;

// recursion
			$info['triggers'] = array();
			$infos = getSelementsInfo($map);
//SDII($infos);
			foreach($infos as $inum => $inf){
				if(!isset($info['type'])){
					$info['type'] = $inf['type'];
				}
				else if($inf['type'] == TRIGGER_VALUE_TRUE){
					$info['type'] = $inf['type'];
				}
				else if($info['type'] != TRIGGER_VALUE_TRUE){
					if(($info['type'] == TRIGGER_VALUE_FALSE) || ($inf['type'] == TRIGGER_VALUE_UNKNOWN)){
						$info['type'] = $inf['type'];
					}
				}

				if(isset($inf['latelyChanged']) && $inf['latelyChanged'])
					$info['latelyChanged'] = $inf['latelyChanged'];

				if(!isset($inf['ack']) || !$inf['ack'])
					$info['ack'] = false;

				if(isset($inf['status']['count_unack']))
					$info['status']['count_unack'] += $inf['status']['count_unack'];

				$info['triggers'] = array_merge($info['triggers'], $inf['triggers']);
				$info['maintenances'] = array_merge($info['maintenances'], $inf['maintenances']);

				if(isset($inf['disabled']) && ($inf['disabled'] == 1)) $info['disabled'] = 1;

				foreach($inf['status'] as $type => $typeInfo){
					if(!is_array($typeInfo)) continue;
					if(!isset($info['status'][$type])){
						$info['status'][$type] = array();
						$info['status'][$type]['count'] = 0;
					}
					$info['status'][$type]['count'] += isset($typeInfo['count'])?$typeInfo['count']:1;

					if(!isset($info['status'][$type]['priority']) || ($info['status'][$type]['priority'] < $typeInfo['priority'])){
						$info['status'][$type]['priority'] = $typeInfo['priority'];
						$info['status'][$type]['info'] = isset($typeInfo['info'])?$typeInfo['info']:'';
					}
				}
			}

			$info['triggers'] = array_unique($info['triggers']);
			$info['maintenances'] = array_unique($info['maintenances']);

// Expand single problem
			$count = count($info['triggers']);
			if($count > 0){
				$info['status'][TRIGGER_VALUE_TRUE]['count'] = $count;

				if($info['status'][TRIGGER_VALUE_TRUE]['count'] == 1){
					$tr1 = reset($info['triggers']);
					$sql = 'SELECT DISTINCT t.triggerid,t.priority,t.value,t.description,t.expression,h.host '.
							' FROM triggers t, items i, functions f, hosts h'.
							' WHERE t.triggerid='.$tr1.
								' AND h.hostid=i.hostid'.
								' AND i.itemid=f.itemid '.
								' AND f.triggerid=t.triggerid';
					$db_trigger = DBfetch(DBselect($sql));

					$info['status'][TRIGGER_VALUE_TRUE]['info'] = array();
					$info['status'][TRIGGER_VALUE_TRUE]['info'][] = array('msg' => expand_trigger_description_by_data($db_trigger));
				}
			}

			if(!isset($info['type'])) $info['type'] = TRIGGER_VALUE_FALSE;
//----

			$info['info'] = array();

// Host maintenance info
			if(!empty($info['maintenances'])){
				$info['info']['maintenances'] = array(
					'msg' => count($info['maintenances']).' '.S_MAINTENANCE,
					'color' => $colors['Orange']
				);
			}

// Counting Problems
			if($info['type'] == TRIGGER_VALUE_TRUE){
				$color = ($info['status'][$info['type']]['priority'] > 3) ? $colors['Red'] : $colors['Dark Red'];

				if(in_array($show_unack, array(EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH))){
					if($info['status'][$info['type']]['count'] > 1)
						$msg = $info['status'][$info['type']]['count'].' '.S_PROBLEMS;
					else if($expandProblem && isset($info['status'][$info['type']]['info'])){
						$tmp = reset($info['status'][$info['type']]['info']);
						$msg = $tmp ? $tmp['msg'] : '';
					}
					else
						$msg = $info['status'][$info['type']]['count'].' '.S_PROBLEM;

					$info['info']['problem'] = array('msg'=>$msg, 'color'=>$color);
				}

				if(in_array($show_unack, array(EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH))){
					if($info['status']['count_unack']){
						$info['info']['unack'] = array(
							'msg' => $info['status']['count_unack'] . ' '.S_UNACKNOWLEDGED,
							'color' => $colors['Dark Red']
						);
					}
				}

				if(isset($info['status'][TRIGGER_VALUE_UNKNOWN])){
					$info['info']['unknown'] = array(
						'msg'=>$info['status'][TRIGGER_VALUE_UNKNOWN]['count'].' '.S_UNKNOWN,
						'color'=>$colors['Gray']
					);
				}

				$info['iconid'] = $selement['iconid_on'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
			}
			else if($info['type'] == TRIGGER_VALUE_UNKNOWN){
				$info['info']['unknown'] = array(
					'msg'=>$info['status'][TRIGGER_VALUE_UNKNOWN]['count'].' '.S_UNKNOWN,
					'color'=>$colors['Gray']
				);

				if(isset($info['disabled']) && $info['disabled'] == 1)
					$info['iconid'] = $selement['iconid_disabled'];
				else
					$info['iconid'] = $selement['iconid_unknown'];

				$info['icon_type'] = SYSMAP_ELEMENT_ICON_UNKNOWN;
			}
			else if($info['type'] == TRIGGER_VALUE_FALSE){
				if(!empty($info['maintenances'])){
					$info['iconid'] = $selement['iconid_maintenance'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
				}
				else if(isset($info['disabled']) && $info['disabled'] == 1){
					$info['info']['ok'] = array(
						'msg'=>S_OK_BIG,
						'color'=>$colors['Dark Green']
					);

					$info['iconid'] = $selement['iconid_disabled'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
				}
				else{
					$info['info']['ok'] = array(
						'msg'=>S_OK_BIG,
						'color'=>$colors['Dark Green']
					);

					$info['iconid'] = $selement['iconid_off'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
				}
			}

			$info['ack'] = !$info['status']['count_unack'];
			$info['priority'] = isset($info['status'][$info['type']]['priority']) ? $info['status'][$info['type']]['priority'] : 0;
//----
		}

	return $selements_info;
	}

/*
 * Function: getImagesInfo
 * Description: Retrive selement
 * Author: Aly
 */

	function getImagesInfo($selements){
		global $colors;

		$selements_info = array();
		foreach($selements as $snum => $selement){
			$selements_info[$selement['selementid']] = array();
			$info = &$selements_info[$selement['selementid']];

			$info['name'] = S_IMAGE;
			$info['elementtype'] = SYSMAP_ELEMENT_TYPE_IMAGE;
			$info['latelyChanged'] = false;
			$info['maintenances'] = array();

			$info['type'] = TRIGGER_VALUE_FALSE;

			$info['info'] = array();
			$info['info']['ok'] = array(
								'msg'=>'',
								'color'=>$colors['Black']
							);

			$info['count'] = 0;
			$info['priority'] = 0;

			$info['color'] = $colors['Green'];

			$info['iconid'] = $selement['iconid_off'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;

			$info['triggers'] = array();
			$info['status'] = array();
		}

	return $selements_info;
	}

/*
 * Function: getSelementsInfo
 * Description: Retrive selement
 * Author: Aly
 */

	function getSelementsInfo($sysmap){
		$elements = separateMapElements($sysmap);

		$config = select_config();
		$show_unack = $config['event_ack_enable'] ? $sysmap['show_unack'] : EXTACK_OPTION_ALL;

		$info = array();
		$info += getMapsInfo($elements['sysmaps'], $sysmap['expandproblem'], $show_unack);
		$info += getHostGroupsInfo($elements['hostgroups'], $sysmap['expandproblem'], $show_unack);
		$info += getHostsInfo($elements['hosts'], $sysmap['expandproblem'], $show_unack);
		$info += getTriggersInfo($elements['triggers'], $sysmap['highlight']);
		$info += getImagesInfo($elements['images']);

	return $info;
	}

	function separateMapElements($sysmap){
		$elements = array(
			'sysmaps' => array(),
			'hostgroups' => array(),
			'hosts' => array(),
			'triggers' => array(),
			'images' => array()
		);

		foreach($sysmap['selements'] as $snum => $selement){
			switch($selement['elementtype']){
				case SYSMAP_ELEMENT_TYPE_MAP:
					$elements['sysmaps'][$selement['selementid']] = $selement;
				break;
				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$elements['hostgroups'][$selement['selementid']] = $selement;
				break;
				case SYSMAP_ELEMENT_TYPE_HOST:
					$elements['hosts'][$selement['selementid']] = $selement;
				break;
				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					$elements['triggers'][$selement['selementid']] = $selement;
				break;
				case SYSMAP_ELEMENT_TYPE_IMAGE:
				default:
					$elements['images'][$selement['selementid']] = $selement;
			}
		}

	return $elements;
	}

	function prepareMapExport(&$exportMaps){

		$sysmaps = array();
		$hostgroups = array();
		$hosts = array();
		$triggers = array();
		$images = array();

		foreach($exportMaps as $mnum => $sysmap){
			$selements = separateMapElements($sysmap);

			$sysmaps += zbx_objectValues($selements['sysmaps'], 'elementid');
			$hostgroups += zbx_objectValues($selements['hostgroups'], 'elementid');
			$hosts += zbx_objectValues($selements['hosts'], 'elementid');
			$triggers += zbx_objectValues($selements['triggers'], 'elementid');
			$images += zbx_objectValues($selements['images'], 'elementid');

			foreach($sysmap['selements'] as $snum => $selement){
				if($selement['iconid_off'] > 0) $images[$selement['iconid_off']] = $selement['iconid_off'];
				if($selement['iconid_on'] > 0) $images[$selement['iconid_on']] = $selement['iconid_on'];
				if($selement['iconid_unknown'] > 0) $images[$selement['iconid_unknown']] = $selement['iconid_unknown'];
				if($selement['iconid_disabled'] > 0) $images[$selement['iconid_disabled']] = $selement['iconid_disabled'];
				if($selement['iconid_maintenance'] > 0) $images[$selement['iconid_maintenance']] = $selement['iconid_maintenance'];
			}

			$images[$sysmap['backgroundid']] = $sysmap['backgroundid'];

			foreach($sysmap['links'] as $lnum => $link){
				foreach($link['linktriggers'] as $ltnum => $linktrigger){
					array_push($triggers, $linktrigger['triggerid']);
				}
			}
		}

		$sysmaps = sysmapIdents($sysmaps);
		$hostgroups = hostgroupIdents($hostgroups);
		$hosts = hostIdents($hosts);
		$triggers = triggerIdents($triggers);
		$images = imageIdents($images);

		try{
			foreach($exportMaps as $mnum => &$sysmap){
				unset($sysmap['sysmapid']);
				$sysmap['backgroundid'] = ($sysmap['backgroundid'] > 0)?$images[$sysmap['backgroundid']]:'';

				foreach($sysmap['selements'] as $snum => &$selement){
					unset($selement['sysmapid']);
					switch($selement['elementtype']){
						case SYSMAP_ELEMENT_TYPE_MAP:
							$selement['elementid'] = $sysmaps[$selement['elementid']];
						break;
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$selement['elementid'] = $hostgroups[$selement['elementid']];
						break;
						case SYSMAP_ELEMENT_TYPE_HOST:
							$selement['elementid'] = $hosts[$selement['elementid']];
						break;
						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							$selement['elementid'] = $triggers[$selement['elementid']];
						break;
						case SYSMAP_ELEMENT_TYPE_IMAGE:
						default:
							$selement['elementid'] = $images[$selement['elementid']];
					}

					$selement['iconid_off'] = ($selement['iconid_off'] > 0)?$images[$selement['iconid_off']]:'';
					$selement['iconid_on'] = ($selement['iconid_on'] > 0)?$images[$selement['iconid_on']]:'';
					$selement['iconid_unknown'] = ($selement['iconid_unknown'] > 0)?$images[$selement['iconid_unknown']]:'';
					$selement['iconid_disabled'] = ($selement['iconid_disabled'] > 0)?$images[$selement['iconid_disabled']]:'';
					$selement['iconid_maintenance'] = ($selement['iconid_maintenance'] > 0)?$images[$selement['iconid_maintenance']]:'';
				}
				unset($selement);

				foreach($sysmap['links'] as $lnum => &$link){
					unset($link['sysmapid']);
					unset($link['linkid']);
					foreach($link['linktriggers'] as $ltnum => &$linktrigger){
						unset($linktrigger['linktriggerid']);
						unset($linktrigger['linkid']);
						$linktrigger['triggerid'] = $triggers[$linktrigger['triggerid']];
					}
				}
				unset($linktrigger);
				unset($link);
			}
			unset($sysmap);
		}
		catch(Exception $e){
			throw new Exception($e->getMessage());
		}
//SDII($exportMaps);
	}

	function prepareImageExport($images){
		$formatted = array();

		foreach($images as $inum => $image){
			$formatted[] = array(
				'name' => $image['name'],
				'imagetype' => $image['imagetype'],
				'encodedImage' => base64_encode($image['image']),
			);
		}
		return $formatted;
	}

	function drawMapConnectors(&$im, &$map, &$map_info){

		$links = $map['links'];
		$selements = $map['selements'];

		foreach($links as $lnum => $link){
			if(empty($link)) continue;

			$selement = $selements[$link['selementid1']];
			list($x1, $y1) = get_icon_center_by_selement($selement, $map_info[$link['selementid1']]);

			$selement = $selements[$link['selementid2']];
			list($x2, $y2) = get_icon_center_by_selement($selement, $map_info[$link['selementid2']]);

			$drawtype = $link['drawtype'];
			$color = convertColor($im,$link['color']);

			$linktriggers = $link['linktriggers'];
			order_result($linktriggers, 'triggerid');

			if(!empty($linktriggers)){
				$max_severity=0;
				$options = array();
				$options['nopermissions'] = 1;
				$options['extendoutput'] = 1;
				$options['triggerids'] = array();

				$triggers = array();
				foreach($linktriggers as $lt_num => $link_trigger){
					if($link_trigger['triggerid'] == 0) continue;
					$id = $link_trigger['linktriggerid'];

					$triggers[$id] = zbx_array_merge($link_trigger,get_trigger_by_triggerid($link_trigger['triggerid']));
					if(($triggers[$id]['status'] == TRIGGER_STATUS_ENABLED) && ($triggers[$id]['value'] == TRIGGER_VALUE_TRUE)){
						if($triggers[$id]['priority'] >= $max_severity){
							$drawtype = $triggers[$id]['drawtype'];
							$color = convertColor($im,$triggers[$id]['color']);
							$max_severity = $triggers[$id]['priority'];
						}
					}
				}
			}

			MyDrawLine($im,$x1,$y1,$x2,$y2,$color,$drawtype);
		}
	}

	function drawMapSelements(&$im, &$map, &$map_info){
		$selements = $map['selements'];

		foreach($selements as $selementid => $selement){
			if(empty($selement)) continue;

			$el_info = $map_info[$selementid];
			$img = get_png_by_selement($selement, $el_info);

			$iconX = imagesx($img);
			$iconY = imagesy($img);

			imagecopy($im,$img,$selement['x'],$selement['y'],0,0,$iconX,$iconY);
		}
	}

	function drawMapHighligts(&$im, &$map, &$map_info){
		$selements = $map['selements'];

		foreach($selements as $selementid => $selement){
			if(empty($selement)) continue;

			$el_info = $map_info[$selementid];
			$img = get_png_by_selement($selement, $el_info);

			$iconX = imagesx($img);
			$iconY = imagesy($img);

			if(($map['highlight']%2) == SYSMAP_HIGHLIGH_ON){
				$hl_color = null;
				$st_color = null;

				if($el_info['icon_type'] == SYSMAP_ELEMENT_ICON_ON){
					switch($el_info['priority']){
						case TRIGGER_SEVERITY_DISASTER: 	$hl_color = hex2rgb('FF0000'); break;
						case TRIGGER_SEVERITY_HIGH:  		$hl_color = hex2rgb('FF8888'); break;
						case TRIGGER_SEVERITY_AVERAGE:  	$hl_color = hex2rgb('DDAAAA'); break;
						case TRIGGER_SEVERITY_WARNING:  	$hl_color = hex2rgb('EFEFCC'); break;
						case TRIGGER_SEVERITY_INFORMATION:  $hl_color = hex2rgb('CCE2CC'); break;
						case TRIGGER_SEVERITY_NOT_CLASSIFIED: $hl_color = hex2rgb('C0E0C0'); break;
						default:
					}
				}

				if($el_info['icon_type'] == SYSMAP_ELEMENT_ICON_UNKNOWN){
					$hl_color = hex2rgb('CCCCCC');
				}

				if(isset($el_info['unavailable']))		$st_color = ($el_info['unavailable'] == HOST_AVAILABLE_FALSE) ? hex2rgb('FF0000'): hex2rgb('CCCCCC');
				if(isset($el_info['disabled']))			$st_color = hex2rgb('EEEEEE');
				if(!empty($el_info['maintenances']))	$st_color = hex2rgb('FF9933');

				$mainProblems = array(
					SYSMAP_ELEMENT_TYPE_HOST_GROUP => 1,
					SYSMAP_ELEMENT_TYPE_MAP => 1
				);

				if(isset($mainProblems[$el_info['elementtype']])){
					if(!is_null($hl_color)) $st_color = null;
				}
				else if(!is_null($st_color)){
					$hl_color = null;
				}

				if(!is_null($st_color)){
					$r = $st_color[0];
					$g = $st_color[1];
					$b = $st_color[2];

					imagefilledrectangle($im,
							$selement['x'] - 2,
							$selement['y'] - 2,
							$selement['x'] + $iconX + 2,
							$selement['y'] + $iconY + 2,
							imagecolorallocatealpha($im,$r,$g,$b, 0)
						);
// shadow
					imagerectangle($im,
							$selement['x'] - 2 - 1,
							$selement['y'] - 2 - 1,
							$selement['x'] + $iconX + 2 + 1,
							$selement['y'] + $iconY + 2 + 1,
							imagecolorallocate($im,120,120,120)
						);

					imagerectangle($im,
							$selement['x'] - 2 - 2,
							$selement['y'] - 2 - 2,
							$selement['x'] + $iconX + 2 + 2,
							$selement['y'] + $iconY + 2 + 2,
							imagecolorallocate($im,220,220,220)
						);
				}

				if(!is_null($hl_color)){
					$r = $hl_color[0];
					$g = $hl_color[1];
					$b = $hl_color[2];

					imagefilledellipse($im,
							$selement['x'] + ($iconX / 2),
							$selement['y'] + ($iconY / 2),
							$iconX+20,
							$iconX+20,
							imagecolorallocatealpha($im,$r,$g,$b, 0)
						);

					imageellipse($im,
							$selement['x'] + ($iconX / 2),
							$selement['y'] + ($iconY / 2),
							$iconX+20+1,
							$iconX+20+1,
							imagecolorallocate($im,120,120,120)
					);

					$config = select_config();
					if(isset($el_info['ack']) && $el_info['ack'] && $config['event_ack_enable']){
						imagesetthickness($im, 5);
						imagearc($im,
							$selement['x'] + ($iconX / 2),
							$selement['y'] + ($iconY / 2),
							$iconX+20-3,
							$iconX+20-3,
							0,
							359,
							imagecolorallocate($im,50,150,50)
						);
						imagesetthickness($im, 1);
					}
				}
			}
		}
	}


	function drawMapSelemetsMarks(&$im, &$map, &$map_info){
		global $colors;

		$selements = $map['selements'];

		foreach($selements as $selementid => $selement){
			if(empty($selement)) continue;

			$el_info = $map_info[$selementid];
			if(!$el_info['latelyChanged']) continue;

			$img = get_png_by_selement($selement, $el_info);

			$iconX = imagesx($img);
			$iconY = imagesy($img);

			$hl_color = null;
			$st_color = null;
			if(!isset($_REQUEST['noselements']) && (($map['highlight']%2) == SYSMAP_HIGHLIGH_ON)){
				if($el_info['icon_type'] == SYSMAP_ELEMENT_ICON_ON) $hl_color = true;
				if($el_info['icon_type'] == SYSMAP_ELEMENT_ICON_UNKNOWN) $hl_color = true;

				if(isset($el_info['unavailable']))		$st_color = true;
				if(isset($el_info['disabled']))			$st_color = true;
				if(!empty($el_info['maintenances']))	$st_color = true;
			}

			$mainProblems = array(
				SYSMAP_ELEMENT_TYPE_HOST_GROUP => 1,
				SYSMAP_ELEMENT_TYPE_MAP => 1
			);

			if(isset($mainProblems[$el_info['elementtype']])) if(!is_null($hl_color)) $st_color = null;
			else if(!is_null($st_color)) $hl_color = null;

			$markSize = $iconX/2;//sqrt(pow($iconX/2,2) + pow($iconX/2,2));
			if($hl_color) $markSize += 12;
			else if($st_color) $markSize += 8;
			else $markSize += 3;


			$marks = 'tlbr';
			if($map['label_type'] != MAP_LABEL_TYPE_NOTHING){
				$label_location = $selement['label_location'];
				if(is_null($label_location) || ($label_location < 0)) $label_location = $map['label_location'];

				switch($label_location){
					case MAP_LABEL_LOC_TOP: $marks = 'lbr'; break;
					case MAP_LABEL_LOC_LEFT: $marks = 'tbr'; break;
					case MAP_LABEL_LOC_RIGHT: $marks = 'tlb'; break;
					case MAP_LABEL_LOC_BOTTOM:
					default: $marks = 'tlr';
				}
			}

			imageVerticalMarks($im, $selement['x']+($iconX/2), $selement['y']+($iconY/2), $markSize, $colors['Red'], $marks);
//*/
		}
	}
	function drawMapLinkLabels(&$im, &$map, &$map_info){
		global $colors;

		$links = $map['links'];
		$selements = $map['selements'];

		foreach($links as $lnum => $link){
			if(empty($link)) continue;
			if(empty($link['label'])) continue;

			$selement = $selements[$link['selementid1']];
			list($x1, $y1) = get_icon_center_by_selement($selement, $map_info[$link['selementid1']]);

			$selement = $selements[$link['selementid2']];
			list($x2, $y2) = get_icon_center_by_selement($selement, $map_info[$link['selementid2']]);

			$drawtype = $link['drawtype'];
			$color = convertColor($im,$link['color']);

			$linktriggers = $link['linktriggers'];
			order_result($linktriggers, 'triggerid');

			if(!empty($linktriggers)){
				$max_severity=0;
				$options = array();
				$options['nopermissions'] = 1;
				$options['extendoutput'] = 1;
				$options['triggerids'] = array();

				$triggers = array();
				foreach($linktriggers as $lt_num => $link_trigger){
					if($link_trigger['triggerid'] == 0) continue;
					$id = $link_trigger['linktriggerid'];

					$triggers[$id] = zbx_array_merge($link_trigger, get_trigger_by_triggerid($link_trigger['triggerid']));
					if(($triggers[$id]['status'] == TRIGGER_STATUS_ENABLED) && ($triggers[$id]['value'] == TRIGGER_VALUE_TRUE)){
						if($triggers[$id]['priority'] >= $max_severity){
							$drawtype = $triggers[$id]['drawtype'];
							$color = convertColor($im,$triggers[$id]['color']);
							$max_severity = $triggers[$id]['priority'];
						}
					}
				}
			}

			$label = $link['label'];

			$label = str_replace("\r", '', $label);
			$strings = explode("\n", $label);

			$box_width = 0;
			$box_height = 0;

			foreach($strings as $snum => $str)
				$strings[$snum] = expand_map_element_label_by_data(null, array('label'=>$str));

			foreach($strings as $snum => $str){
				$dims = imageTextSize(8,0,$str);

				$box_width = ($box_width > $dims['width'])?$box_width:$dims['width'];
				$box_height+= $dims['height']+2;
			}

			$boxX_left = round(($x1 + $x2) / 2 - ($box_width/2) - 6);
			$boxX_right = round(($x1 + $x2) / 2 + ($box_width/2) + 6);

			$boxY_top = round(($y1 + $y2) / 2 - ($box_height/2) - 4);
			$boxY_bottom = round(($y1 + $y2) / 2 + ($box_height/2) + 2);

			switch($drawtype){
				case MAP_LINK_DRAWTYPE_DASHED_LINE:
				case MAP_LINK_DRAWTYPE_DOT:
					dashedrectangle($im, $boxX_left, $boxY_top, $boxX_right, $boxY_bottom, $color);
					break;
				case MAP_LINK_DRAWTYPE_BOLD_LINE:
					imagerectangle($im, $boxX_left-1, $boxY_top-1, $boxX_right+1, $boxY_bottom+1, $color);
				case MAP_LINK_DRAWTYPE_LINE:
				default:
					imagerectangle($im, $boxX_left, $boxY_top, $boxX_right, $boxY_bottom, $color);
			}

			imagefilledrectangle($im, $boxX_left+1, $boxY_top+1, $boxX_right-1, $boxY_bottom-1, $colors['White']);


			$increasey = 4;
			foreach($strings as $snum => $str){
				$dims = imageTextSize(8,0,$str);

				$labelx = ($x1 + $x2) / 2 - ($dims['width']/2);
				$labely = $boxY_top + $increasey;

				imagetext($im, 8, 0, $labelx, $labely+$dims['height'], $colors['Black'], $str);

				$increasey += $dims['height']+2;
			}
		}
	}

	function drawMapLabels(&$im, &$map, &$map_info){
		global $colors;

		if($map['label_type'] == MAP_LABEL_TYPE_NOTHING) return;

		$selements = $map['selements'];
		$all_strings = '';
		$label_lines = array();
		$status_lines = array();
		foreach($selements as $selementid => $selement){
			if(!isset($label_lines[$selementid])) $label_lines[$selementid] = array();
			if(!isset($status_lines[$selementid])) $status_lines[$selementid] = array();

			$msg = expand_map_element_label_by_data($selement);
			$all_strings .= $msg;
			$msgs = explode("\n", $msg);
			foreach($msgs as $msg){
				$label_lines[$selementid][] = array('msg' => $msg);
			}

			$el_info = $map_info[$selementid];
			$el_msgs = array('problem', 'unack', 'maintenances', 'unknown', 'ok', 'status', 'availability');
			foreach($el_msgs as $key => $caption){
				if(!isset($el_info['info'][$caption]) || zbx_empty($el_info['info'][$caption]['msg'])) continue;

				$status_lines[$selementid][] = array(
					'msg' => $el_info['info'][$caption]['msg'],
					'color' => $el_info['info'][$caption]['color']
				);

				$all_strings .= $el_info['info'][$caption]['msg'];
			}
		}

		$allLabelsSize = imageTextSize(8, 0, str_replace("\r", '', str_replace("\n", '', $all_strings)));
		$labelFontHeight = $allLabelsSize['height'];
		$labelFontBaseline = $allLabelsSize['baseline'];

		foreach($selements as $selementid => $selement){
			if(empty($selement)) continue;

			$el_info = $map_info[$selementid];

			$hl_color = null;
			$st_color = null;
			if(!isset($_REQUEST['noselements']) && (($map['highlight']%2) == SYSMAP_HIGHLIGH_ON)){
				if($el_info['icon_type'] == SYSMAP_ELEMENT_ICON_ON) $hl_color = true;
				if($el_info['icon_type'] == SYSMAP_ELEMENT_ICON_UNKNOWN) $hl_color = true;

				if(isset($el_info['unavailable'])) $st_color = true;
				if(isset($el_info['disabled'])) $st_color = true;
				if(!empty($el_info['maintenances'])) $st_color = true;
			}

			if(in_array($el_info['elementtype'], array(SYSMAP_ELEMENT_TYPE_HOST_GROUP, SYSMAP_ELEMENT_TYPE_MAP))
					&& !is_null($hl_color))
				$st_color = null;
			else if(!is_null($st_color))
				$hl_color = null;


			$label_location = (is_null($selement['label_location']) || ($selement['label_location'] < 0))
					? $map['label_location'] : $selement['label_location'];

			$label = array();
			if(($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) && ($map['label_type'] == MAP_LABEL_TYPE_IP)){
				$host = get_host_by_hostid($selement['elementid']);
				$label[] = array('msg' => $host['ip']);
				$label = array_merge($label, $status_lines[$selementid]);
			}
			else if($map['label_type'] == MAP_LABEL_TYPE_STATUS){
				$label = $status_lines[$selementid];
			}
			else if($map['label_type'] == MAP_LABEL_TYPE_NAME){
				$label[] = array('msg' => $el_info['name']);
			}
			else{
				$label = array_merge($label_lines[$selementid], $status_lines[$selementid]);
			}
			if(zbx_empty($label)) continue;

			$w = 0;
			foreach($label as $str){
				$dims = imageTextSize(8, 0, $str['msg']);
				$w = max($w, $dims['width']);
			}
			$h = count($label) * $labelFontHeight;

			$x = $selement['x'];
			$y = $selement['y'];
			$img = get_png_by_selement($selement, $el_info);
			$iconX = imagesx($img);
			$iconY = imagesy($img);

			if(!is_null($hl_color)) $icon_hl = 14;
			else if(!is_null($st_color)) $icon_hl = 6;
			else $icon_hl = 2;

			switch($label_location){
				case MAP_LABEL_LOC_TOP:
					$y_rec = $y - $icon_hl - $h - 6;
					$x_rec = $x + $iconX/2 - $w/2;
					break;
				case MAP_LABEL_LOC_LEFT:
					$y_rec = $y - $h/2 + $iconY/2;
					$x_rec = $x - $icon_hl - $w;
					break;
				case MAP_LABEL_LOC_RIGHT:
					$y_rec = $y - $h/2 + $iconY/2;
					$x_rec = $x + $iconX + $icon_hl;
					break;
				case MAP_LABEL_LOC_BOTTOM:
				default:
					$y_rec = $y + $iconY + $icon_hl;
					$x_rec = $x + $iconX/2 - $w/2;
			}
//		$y_rec += 30;
//		imagerectangle($im, $x_rec-2-1, $y_rec-3, $x_rec+$w+2+1, $y_rec+($oc*4)+$h+3, $label_color);
//		imagefilledrectangle($im, $x_rec-2, $y_rec-2, $x_rec+$w+2, $y_rec+($oc*4)+$h-2, $colors['White']);

			$increasey = 12;
			foreach($label as $line){
				if(zbx_empty($line['msg'])) continue;

				$str = str_replace("\r", '', $line['msg']);
				$color = isset($line['color']) ? $line['color'] : $colors['Black'];

				$dims = imageTextSize(8, 0, $str);
//				$dims['height'] = $labelFontHeight;
				//$str .= ' - '.$labelFontHeight.' - '.$dims['height'];
				//$str = $dims['width'].'x'.$dims['height'];

				if($label_location == MAP_LABEL_LOC_TOP || $label_location == MAP_LABEL_LOC_BOTTOM){
					$x_label = $x + ceil($iconX/2) - ceil($dims['width']/2);
				}
				else if($label_location == MAP_LABEL_LOC_LEFT)
					$x_label = $x_rec + $w - $dims['width'];
				else
					$x_label = $x_rec;

				imagefilledrectangle(
					$im,
					$x_label-1, $y_rec+$increasey-$labelFontHeight+$labelFontBaseline,
					$x_label+$dims['width']+1, $y_rec+$increasey+$labelFontBaseline,
					$colors['White']
				);
				imagetext($im, 8, 0, $x_label, $y_rec+$increasey, $color, $str);

				$increasey += $labelFontHeight+1;
			}
		}
	}
?>
