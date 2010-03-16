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
	require_once('include/images.inc.php');
	require_once('include/hosts.inc.php');
	require_once('include/triggers.inc.php');
	require_once('include/scripts.inc.php');
	require_once('include/maintenances.inc.php');

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

/*
 * Function: sysmap_accessible
 *
 * Description: Check permission for map
 *
 * Return: true on success
 *
 * Author: Aly
 */
	function sysmap_accessible($sysmapid,$perm){
		global $USER_DETAILS;

		$nodes = get_current_nodeid(null,$perm);
		$result = (bool) count($nodes);

		$sql = 'SELECT * '.
				' FROM sysmaps_elements '.
				' WHERE sysmapid='.$sysmapid.
					' AND '.DBin_node('sysmapid', $nodes);
		$db_result = DBselect($sql);
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,$perm,PERM_RES_IDS_ARRAY,get_current_nodeid(true));
//SDI($available_hosts);
		while(($se_data = DBfetch($db_result)) && $result){
			switch($se_data['elementtype']){
				case SYSMAP_ELEMENT_TYPE_HOST:
					if(!isset($available_hosts[$se_data['elementid']])){
						$result = false;
					}
					break;
				case SYSMAP_ELEMENT_TYPE_MAP:
					$result = sysmap_accessible($se_data['elementid'], $perm);
					break;
				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					$available_triggers = get_accessible_triggers($perm, array(), PERM_RES_IDS_ARRAY);
					if(!isset($available_triggers[$se_data['elementid']])){
						$result = false;
					}
					break;
				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$available_groups = get_accessible_groups_by_user($USER_DETAILS,$perm);
					if(!isset($available_groups[$se_data['elementid']])){
						$result = false;
					}
					break;
			}
		}
//SDI($se_data['elementid']);

	return $result;
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

// Add System Map

	function add_sysmap($name,$width,$height,$backgroundid,$highlight,$label_type,$label_location){
		$sysmapid=get_dbid('sysmaps','sysmapid');

		$result=DBexecute('insert into sysmaps (sysmapid,name,width,height,backgroundid,highlight,label_type,label_location)'.
			' VALUES ('.$sysmapid.','.zbx_dbstr($name).','.$width.','.$height.','.$backgroundid.','.$highlight.','.$label_type.','.$label_location.')');

		if(!$result)
			return $result;

	return $sysmapid;
	}

// Update System Map

	function update_sysmap($sysmapid,$name,$width,$height,$backgroundid,$highlight,$label_type,$label_location){
		return	DBexecute('UPDATE sysmaps SET name='.zbx_dbstr($name).',width='.$width.',height='.$height.','.
			'backgroundid='.$backgroundid.',highlight='.$highlight.',label_type='.$label_type.','.
			'label_location='.$label_location.' WHERE sysmapid='.$sysmapid);
	}

// Delete System Map

	function delete_sysmap($sysmapids){
		zbx_value2array($sysmapids);

		$result = delete_sysmaps_elements_with_sysmapid($sysmapids);
		if(!$result)	return	$result;

		$res=DBselect('SELECT linkid FROM sysmaps_links WHERE '.DBcondition('sysmapid',$sysmapids));
		while($rows = DBfetch($res)){
			$result&=delete_link($rows['linkid']);
		}

		$result = DBexecute('DELETE FROM sysmaps_elements WHERE '.DBcondition('sysmapid',$sysmapids));
		$result &= DBexecute("DELETE FROM profiles WHERE idx='web.favorite.sysmapids' AND source='sysmapid' AND ".DBcondition('value_id',$sysmapids));
		$result &= DBexecute('DELETE FROM screens_items WHERE '.DBcondition('resourceid',$sysmapids).' AND resourcetype='.SCREEN_RESOURCE_MAP);
		$result &= DBexecute('DELETE FROM sysmaps WHERE '.DBcondition('sysmapid',$sysmapids));

	return $result;
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

	function get_link_triggers($linkid){
		$triggers = array();

		$sql = 'SELECT * FROM sysmaps_link_triggers WHERE linkid='.$linkid;
		$res = DBselect($sql);

		while($rows = DBfetch($res)){
			$triggers[] = $rows;
		}
	return $triggers;
	}

	function add_link_trigger($linkid,$triggerid,$drawtype,$color){
		$linktriggerid=get_dbid("sysmaps_link_triggers","linktriggerid");
		$sql = 'INSERT INTO sysmaps_link_triggers (linktriggerid,linkid,triggerid,drawtype,color) '.
					" VALUES ($linktriggerid,$linkid,$triggerid,$drawtype,".zbx_dbstr($color).')';
	return DBexecute($sql);
	}

	function update_link_trigger($linkid,$triggerid,$drawtype,$color){
		$result=delete_link_trigger($linkid,$triggerid);
		$result&=add_link_trigger($linkid,$triggerid,$drawtype,$color);
	return $result;
	}

	function delete_link_trigger($linkid,$triggerid){
	return DBexecute('DELETE FROM sysmaps_link_triggers WHERE linkid='.$linkid.' AND triggerid='.$triggerid);
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

		while($element = DBfetch($db_elements))
		{
			if(check_circle_elements_link($sysmapid,$element["elementid"],$element["elementtype"]))
				return TRUE;
		}
		return false;
	}

// Add Element to system map
	function add_element_to_sysmap($selement){
		$selement_db_fields = array(
			'sysmapid' => null,
			'elementid' => 0,
			'elementtype' => 5,
			'label' => '',
			'label_location' => 0,
			'iconid_off' => null,
			'iconid_on' => 0,
			'iconid_unknown' => 0,
			'iconid_maintenance' => 0,
			'iconid_disabled' => 0,
			'x' => 50,
			'y' => 50,
			'url' => ''
		);
//SDII($selement);
		if(!check_db_fields($selement_db_fields, $selement)){
			$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields for element');
			return false;
		}

//		if($selement['label_location']<0) $selement['label_location']='null';
		if(check_circle_elements_link($selement['sysmapid'],$selement['elementid'],$selement['elementtype'])){
			throw new Exception(S_CIRCULAR_LINK_CANNOT_BE_CREATED.' "'.$selement['label'].'"');
			return false;
		}

		$selementid = get_dbid('sysmaps_elements','selementid');

		$result = DBexecute('INSERT INTO sysmaps_elements '.
							'(selementid,sysmapid,elementid,elementtype,label,label_location,'.
							'iconid_off,iconid_on,iconid_unknown,iconid_maintenance,iconid_disabled,x,y,url)'.
						' VALUES ('.$selementid.','.$selement['sysmapid'].','.$selement['elementid'].','.
									$selement['elementtype'].','.zbx_dbstr($selement['label']).','.$selement['label_location'].','.
									$selement['iconid_off'].','.$selement['iconid_on'].','.$selement['iconid_unknown'].','.
									$selement['iconid_maintenance'].','.$selement['iconid_disabled'].','.
									$selement['x'].','.$selement['y'].','.zbx_dbstr($selement['url']).')');

		if(!$result)
			return $result;

	return $selementid;
	}

// Update Element FROM system map
	function update_sysmap_element($selement){
		$selement_db_fields = array(
			'sysmapid' => null,
			'selementid' => null,
			'elementid' => 0,
			'elementtype' => 5,
			'label' => '',
			'label_location' => 0,
			'iconid_off' => null,
			'iconid_on' => 0,
			'iconid_unknown' => 0,
			'iconid_maintenance' => 0,
			'iconid_disabled' => 0,
			'x' => 50,
			'y' => 50,
			'url' => ''
		);

		if(!check_db_fields($selement_db_fields, $selement)){
			$result = false;
			$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields for element');
			break;
		}

		if(check_circle_elements_link($selement['sysmapid'],$selement['elementid'],$selement['elementtype'])){
			throw new Exception(S_CIRCULAR_LINK_CANNOT_BE_CREATED.' "'.$selement['label'].'"');
			return false;
		}

		return	DBexecute('UPDATE sysmaps_elements '.
					'SET elementid='.$selement['elementid'].', '.
						' elementtype='.$selement['elementtype'].', '.
						' label='.zbx_dbstr($selement['label']).', '.
						' label_location='.$selement['label_location'].', '.
						' x='.$selement['x'].', '.
						' y='.$selement['y'].', '.
						' iconid_off='.$selement['iconid_off'].', '.
						' iconid_on='.$selement['iconid_on'].', '.
						' iconid_unknown='.$selement['iconid_unknown'].', '.
						' iconid_maintenance='.$selement['iconid_maintenance'].', '.
						' iconid_disabled='.$selement['iconid_disabled'].', '.
						' url='.zbx_dbstr($selement['url']).
					' WHERE selementid='.$selement['selementid']);
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

	function delete_sysmaps_elements_with_sysmapid($sysmapids){
		zbx_value2array($sysmapids);
		if(empty($sysmapids)) return true;

		$db_elements = DBselect('SELECT selementid '.
					' FROM sysmaps_elements '.
					' WHERE '.DBcondition('elementid',$sysmapids).
						' AND elementtype='.SYSMAP_ELEMENT_TYPE_MAP);
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
				'extendoutput' => 1,
				'select_selements' => 1,
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
			'extendoutput' => 1,
			'select_groups' => 1,
			'select_triggers' => 1,
			'nopermissions' => 1
		);
		$hosts = CHost::get($options);
		$hosts = zbx_toHash($hosts, 'hostid');

// Draws elements
		$map_info = getSelementsInfo($sysmap);

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

	function get_icon_center_by_selementid($selementid){
		$element = get_sysmaps_element_by_selementid($selementid);
	return get_icon_center_by_selement($element);
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

	function get_png_by_selementid($selementid){
		$selement = DBfetch(DBselect('SELECT * FROM sysmaps_elements WHERE selementid='.$selementid));
		if(!$selement)	return FALSE;

	return get_png_by_selement($selement);
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

	function get_base64_icon($element){
		return base64_encode(get_element_icon($element));
	}

	function get_selement_iconid($selement, $info=null){
		if($selement['selementid'] > 0){
			if(is_null($info)){
				$selements_info = getSelementsInfo(array('selements' => array($selement)));
				$info = reset($selements_info);
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

	function get_element_icon($element){
		$iconid = get_element_iconid($element);

		$image = get_image_by_imageid($iconid);
		$img = imagecreatefromstring($image['image']);

		unset($image);

		$w=imagesx($img);
		$h=imagesy($img);

		if(function_exists('imagecreatetruecolor') && @imagecreatetruecolor(1,1)){
			$im = imagecreatetruecolor($w,$h);
		}
		else{
			$im = imagecreate($w,$h);
		}

		imagefilledrectangle($im,0,0,$w,$h, imagecolorallocate($im,255,255,255));

		imagecopy($im,$img,0,0,0,0,$w,$h);
		imagedestroy($img);

		ob_start();
		imagepng($im);
		$image_txt = ob_get_contents();
		ob_end_clean();

	return $image_txt;
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
					break;
			}
		}

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

			$options = array(
				'filter' => array('host' => $host, 'key_' => $key),
				'output' => API_OUTPUT_EXTEND
			);
			$db_item = CItem::get($options);
			$db_item = reset($db_item);
			if(!$db_item){
				$label = str_replace('{'.$expr.'}', '???', $label);
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
					$label = str_replace('{'.$expr.'}', '('.S_NO_DATA_SMALL.')', $label);
				else{
					switch($db_item['value_type']){
						case ITEM_VALUE_TYPE_FLOAT:
						case ITEM_VALUE_TYPE_UINT64:
							$value = convert_units($row['value'], $db_item['units']);
							break;
						default:
							$value = $row['value'];
					}

					$label = str_replace('{'.$expr.'}', $value, $label);
				}
			}
			else if((0 == strcmp($function, 'min')) || (0 == strcmp($function, 'max')) || (0 == strcmp($function, 'avg'))){

				if($db_item['value_type'] != ITEM_VALUE_TYPE_FLOAT && $db_item['value_type'] != ITEM_VALUE_TYPE_UINT64){
					$label = str_replace('{'.$expr.'}', '???', $label);
					continue;
				}

				$now = time(NULL) - $parameter;
				$sql = 'SELECT '.$function.'(value) as value '.
						' FROM '.$history_table.
						' WHERE clock>'.$now.
							' AND itemid='.$db_item['itemid'];

				$result = DBselect($sql);
				if(NULL == ($row = DBfetch($result)) || is_null($row['value']))
					$label = str_replace('{'.$expr.'}', '('.S_NO_DATA_SMALL.')', $label);
				else
					$label = str_replace('{'.$expr.'}', convert_units($row['value'], $db_item['units']), $label);
			}
			else{
				$label = str_replace('{'.$expr.'}', '???', $label);
				continue;
			}
		}

	return $label;
	}

	function get_triggers_unacknowledged($db_element){
		$elements = array('hosts' => array(), 'hosts_groups' => array(), 'triggers' => array());

		get_map_elements($db_element, $elements);

		$elements['hosts_groups'] = array_unique($elements['hosts_groups']);

		/* select all hosts linked to host groups */
		if (!empty($elements['hosts_groups'])){
			$db_hgroups = DBselect(
					'select distinct hostid'.
					' from hosts_groups'.
					' where '.DBcondition('groupid', $elements['hosts_groups']));
			while (NULL != ($db_hgroup = DBfetch($db_hgroups)))
				$elements['hosts'][] = $db_hgroup['hostid'];
		}

		$elements['hosts'] = array_unique($elements['hosts']);
		$elements['triggers'] = array_unique($elements['triggers']);

/* select all triggers linked to hosts */
		if (!empty($elements['hosts']) && !empty($elements['triggers']))
			$cond = '('.DBcondition('h.hostid', $elements['hosts']).
				' or '.DBcondition('t.triggerid', $elements['triggers']).')';
		else if (!empty($elements['hosts']))
			$cond = DBcondition('h.hostid', $elements['hosts']);
		else if (!empty($elements['triggers']))
			$cond = DBcondition('t.triggerid', $elements['triggers']);
		else
			return '0';


		$cnt = 0;
		$sql = 'SELECT DISTINCT t.triggerid '.
				' FROM triggers t,functions f,items i,hosts h '.
				' WHERE t.triggerid=f.triggerid '.
					' AND f.itemid=i.itemid '.
					' AND i.hostid=h.hostid '.
					' AND i.status='.ITEM_STATUS_ACTIVE.
					' AND h.status='.HOST_STATUS_MONITORED.
					' AND t.status='.TRIGGER_STATUS_ENABLED.
					' AND t.value='.TRIGGER_VALUE_TRUE.
					' AND '.$cond;
		$db_triggers = DBselect($sql);
		while($db_trigger = DBfetch($db_triggers)){
			$sql = 'SELECT eventid,value,acknowledged '.
					' FROM events'.
					' WHERE object='.EVENT_OBJECT_TRIGGER.
						' AND objectid='.$db_trigger['triggerid'].
					' ORDER BY eventid DESC';
			$db_events = DBselect($sql, 1);
			if($db_event= DBfetch($db_events))
				if(($db_event['value'] == TRIGGER_VALUE_TRUE) && ($db_event['acknowledged'] == 0)){
					$cnt++;
				}
		}

	return $cnt;
	}

	function get_map_elements($db_element, &$elements){
		switch ($db_element['elementtype']){
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
 	function getTriggersInfo($selements){
		global $colors;

		$selements_info = array();
		$options = array(
			'triggerids' => zbx_objectValues($selements, 'elementid'),
			'extendoutput' => 1,
			'nopermissions' => 1,
			'nodeids' => get_current_nodeid(true)
			);
		$triggers = CTrigger::get($options);
		$triggers = zbx_toHash($triggers, 'triggerid');
		foreach($selements as $snum => $selement){
			$selements_info[$selement['selementid']] = array();
			$info = &$selements_info[$selement['selementid']];

			if(!isset($triggers[$selement['elementid']])) continue;
			$trigger = $triggers[$selement['elementid']];

// name
			$info['name'] = expand_trigger_description_by_data($trigger);

			$info['triggers'] = array();
			$info['type'] = $trigger['value'];
			if($info['type'] == TRIGGER_VALUE_TRUE){
				array_push($info['triggers'], $trigger['triggerid']);
			}

			if($trigger['status'] != TRIGGER_STATUS_ENABLED){
				$info['type'] = TRIGGER_VALUE_UNKNOWN;
				$info['disabled'] = 1;
			}

			$info[$info['type']] = array('count' => 1);
			$info[$info['type']]['priority'] = $trigger['priority'];
			$info[$info['type']]['info'] = $info['name'];

//----
			if($info['type'] == TRIGGER_VALUE_TRUE){
				$color = ($info[$info['type']]['priority'] > 3) ? $colors['Red'] : $colors['Dark Red'];

				$info['info'] = array();
				$info['info'][] = array('msg'=>S_PROBLEM_BIG, 'color'=>$color);

				$info['iconid'] = $selement['iconid_on'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
			}
			else if($info['type'] == TRIGGER_VALUE_UNKNOWN){
				$info['info'] = array();
				$info['info'][] = array(
									'msg'=>S_UNKNOWN_BIG,
									'color'=>$colors['Gray']
								);

				$info['iconid'] = $selement['iconid_unknown'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_UNKNOWN;
			}
			else if($info['type'] == TRIGGER_VALUE_FALSE){
				$info['info'] = array();
				$info['info'][] = array(
									'msg'=>S_OK_BIG,
									'color'=>$colors['Dark Green']
								);
				$info['iconid'] = $selement['iconid_off'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
			}

			if(isset($info['disabled']) && $info['disabled'] == 1){
// Disabled
				$info['info'] = array();
				$info['info'][] = array('msg'=>S_DISABLED_BIG, 'color'=>$colors['Dark Red']);

				$info['iconid'] = $selement['iconid_disabled'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
			}

			$info['priority'] = isset($info[$info['type']]['priority']) ? $info[$info['type']]['priority'] : 0;
//---
		}

	return $selements_info;
	}

/*
 * Function: getHostsInfo
 * Description: Retrive selement
 * Author: Aly
 */

 	function getHostsInfo($selements, $expandProblem=false){
		global $colors;

		$selements_info = array();

		$options = array(
				'hostids' => zbx_objectValues($selements, 'elementid'),
				'extendoutput' => 1,
				'nopermissions' => 1,
				'select_triggers' => 1,
				'nodeids' => get_current_nodeid(true)
			);
		$hosts = CHost::get($options);
		$hosts = zbx_toHash($hosts, 'hostid');

		foreach($selements as $snum => $selement){
			$selements_info[$selement['selementid']] = array();
			$info = &$selements_info[$selement['selementid']];

			if(!isset($hosts[$selement['elementid']])) continue;
			$host = $hosts[$selement['elementid']];

			$info['name'] = $host['host'];

			if($host['maintenance_status'] == MAINTENANCE_TYPE_NODATA){
				$info['maintenance_status'] = true;
				$info['maintenanceid'] = $host['maintenanceid'];
			}

			if($host['status'] != HOST_STATUS_MONITORED){
				$info['type'] = TRIGGER_VALUE_UNKNOWN;
				$info['disabled'] = 1;
			}

			$info['available'] = $host['available'];
			$info['snmp_available'] = $host['snmp_available'];
			$info['ipmi_available'] = $host['ipmi_available'];

			$info['triggers'] = array();
			foreach($host['triggers'] as $tnum => $trigger){
				if($trigger['status'] == TRIGGER_STATUS_DISABLED) continue;

				if(!isset($info['type'])) $info['type'] = $trigger['value'];
				else if($trigger['value'] == TRIGGER_VALUE_TRUE){
					$info['type'] = $trigger['value'];
				}
				else if($info['type'] != TRIGGER_VALUE_TRUE){
					if(($info['type'] == TRIGGER_VALUE_FALSE) || ($trigger['value'] == TRIGGER_VALUE_UNKNOWN)){
						$info['type'] = $trigger['value'];
					}
				}

				if($trigger['value'] == TRIGGER_VALUE_TRUE){
					array_push($info['triggers'], $trigger['triggerid']);
				}


				if(!isset($info[$trigger['value']]))
					$info[$trigger['value']] = array('count' => 0);


				$info[$trigger['value']]['count']++;
				$info[$trigger['value']]['info'] = $info['name'];


				if(!isset($info[$trigger['value']]['priority']) || ($info[$trigger['value']]['priority'] < $trigger['priority'])){
					$info[$trigger['value']]['priority'] = $trigger['priority'];
					if($info['type'] != TRIGGER_VALUE_UNKNOWN){
						$info[$trigger['value']]['info'] = expand_trigger_description_by_data($trigger);
					}
				}
			}

			if(!isset($info['type'])) $info['type'] = TRIGGER_VALUE_FALSE;

			if($host['status'] == HOST_STATUS_TEMPLATE){
				$info['type'] = TRIGGER_VALUE_UNKNOWN;
				$info[TRIGGER_VALUE_UNKNOWN]['count']	= 0;
				$info[TRIGGER_VALUE_UNKNOWN]['priority'] = 0;
				$info[TRIGGER_VALUE_UNKNOWN]['info']	= S_TEMPLATE_SMALL;
			}
			else if($host['status'] == HOST_STATUS_NOT_MONITORED){
				$info['type'] = TRIGGER_VALUE_UNKNOWN;
				$info[TRIGGER_VALUE_UNKNOWN]['count']	= 0;
				$info[TRIGGER_VALUE_UNKNOWN]['priority']	= 0;
				$info['disabled'] = 1;
			}
			else if(!isset($info[TRIGGER_VALUE_FALSE])){
				$info[TRIGGER_VALUE_FALSE]['count']		= 0;
				$info[TRIGGER_VALUE_FALSE]['priority']	= 0;
				$info[TRIGGER_VALUE_FALSE]['info']		= S_OK_BIG;
			}

//----
// Host unavailable

			if(isset($info['disabled']) && $info['disabled'] == 1){
// Disabled
				$info['info'] = array();
				$info['info'][] = array('msg'=>S_DISABLED_BIG, 'color'=>$colors['Dark Red']);

				$info['iconid'] = $selement['iconid_disabled'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_DISABLED;
			}
/*			else if(($info['available'] == HOST_AVAILABLE_UNKNOWN) &&
				($info['snmp_available'] == HOST_AVAILABLE_UNKNOWN) &&
				($info['ipmi_available'] == HOST_AVAILABLE_UNKNOWN))
			{
// UNKNOWN
				$info['info'] = array();
				$info['info'][] = array('msg'=>S_UNKNOWN_BIG, 'color'=>$colors['Gray']);

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
					$info['info'][] = array('msg'=>S_UNAVAILABLE_BIG, 'color'=>$colors['Red']);
				if(($info['snmp_available'] == HOST_AVAILABLE_FALSE))
					$info['info'][] = array('msg'=>'SNMP '.S_UNAVAILABLE_BIG, 'color'=>$colors['Red']);
				if(($info['ipmi_available'] == HOST_AVAILABLE_FALSE))
					$info['info'][] = array('msg'=>'IPMI '.S_UNAVAILABLE_BIG, 'color'=>$colors['Red']);

				$info['iconid'] = $selement['iconid_on'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
				$info['unavailable'] = HOST_AVAILABLE_FALSE;
			}
//*/
			else if(isset($info['maintenance_status'])){
// Host in maintenance
				$info['type'] = TRIGGER_VALUE_UNKNOWN;

				$msg = S_MAINTENANCE_BIG;
				if($info['maintenanceid'] > 0){
					$mnt = get_maintenance_by_maintenanceid($info['maintenanceid']);
					$msg.=' ('.$mnt['name'].')';
				}

				if(!isset($info['info'])) $info['info'] = array();
				$info['info'][] = array(
									'msg'=>$msg,
									'color'=>$colors['Orange']
								);

				$info['iconid'] = $selement['iconid_maintenance'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
				$info['maintenance'] = 1;
			}
			else{
// AVAILABLE
				if($info['type'] == TRIGGER_VALUE_TRUE){
					$color = ($info[$info['type']]['priority'] > 3) ? $colors['Red'] : $colors['Dark Red'];

					$msg = S_PROBLEM_BIG;
					if($info[$info['type']]['count'] > 1)
						$msg = $info[$info['type']]['count'].' '.S_PROBLEMS;
					else if($expandProblem && isset($info[$info['type']]['info']))
						$msg = $info[$info['type']]['info'];
					else 
						$msg = $info[$info['type']]['count'].' '.S_PROBLEM;


					$info['info'] = array();
					$info['info'][] = array('msg'=>$msg, 'color'=>$color);

					if(isset($info[TRIGGER_VALUE_UNKNOWN])){
						$info['info'][] = array(
											'msg'=>$info[TRIGGER_VALUE_UNKNOWN]['count'].' '.S_UNKNOWN,
											'color'=>$colors['Gray']
										);
					}

					$info['iconid'] = $selement['iconid_on'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
				}
				else if($info['type'] == TRIGGER_VALUE_UNKNOWN){
					$info['info'] = array();
					$info['info'][] = array(
										'msg'=>$info[TRIGGER_VALUE_UNKNOWN]['count'].' '.S_UNKNOWN,
										'color'=>$colors['Gray']
									);

					$info['iconid'] = $selement['iconid_unknown'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_UNKNOWN;
				}
				else if($info['type'] == TRIGGER_VALUE_FALSE){
					$info['info'] = array();
					$info['info'][] = array(
										'msg'=>S_OK_BIG,
										'color'=>$colors['Dark Green']
									);
					$info['iconid'] = $selement['iconid_off'];
					$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
				}
			}

			$info['priority'] = isset($info[$info['type']]['priority']) ? $info[$info['type']]['priority'] : 0;
//---
		}

	return $selements_info;
	}

/*
 * Function: getHostGroupsInfo
 * Description: Retrive selement
 * Author: Aly
 */
 	function getHostGroupsInfo($selements, $expandProblem=false){
		global $colors;

		$selements_info = array();
		$options = array(
				'groupids' => zbx_objectValues($selements, 'elementid'),
				'extendoutput' => 1,
				'nopermissions' => 1,
				'select_hosts' => 1,
				'select_triggers' => 1,
				'nodeids' => get_current_nodeid(true)
			);
		$hostgroups = CHostGroup::get($options);
		$hostgroups = zbx_toHash($hostgroups, 'groupid');

		foreach($selements as $snum => $selement){
			$selements_info[$selement['selementid']] = array();
			$info = &$selements_info[$selement['selementid']];

			if(!isset($hostgroups[$selement['elementid']])) continue;
			$group = $hostgroups[$selement['elementid']];

			$info['name'] = $group['name'];

			foreach($group['hosts'] as $hnum => $host){
				if($host['status'] != HOST_STATUS_MONITORED){
					$info['type'] = TRIGGER_VALUE_UNKNOWN;
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
			}

			$options = array(
				'groupids' => $group['groupid'],
				'extendoutput' => 1,
				'nodeids' => get_current_nodeid(true)
				);

			$triggers = CTrigger::get($options);
			$triggers = zbx_toHash($triggers, 'triggerid');

			$info['triggers'] = array();
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
					array_push($info['triggers'], $trigger['triggerid']);
				}

				if(!isset($info[$trigger['value']]))
					$info[$trigger['value']] = array('count' => 0);

				$info[$trigger['value']]['count']++;
				$info[$trigger['value']]['info'] = $info['name'];


				if(!isset($info[$trigger['value']]['priority']) || ($info[$trigger['value']]['priority'] < $trigger['priority'])){
					$info[$trigger['value']]['priority'] = $trigger['priority'];
					if($info['type'] != TRIGGER_VALUE_UNKNOWN){
						$info[$trigger['value']]['info'] = expand_trigger_description_by_data($trigger);
					}
				}
			}

			if(!isset($info['type'])) $info['type'] = TRIGGER_VALUE_FALSE;

			if(!isset($info[TRIGGER_VALUE_FALSE])){
				$info[TRIGGER_VALUE_FALSE]['count']		= 0;
				$info[TRIGGER_VALUE_FALSE]['priority']	= 0;
				$info[TRIGGER_VALUE_FALSE]['info']		= S_OK_BIG;
			}

//----
			if($info['type'] == TRIGGER_VALUE_TRUE){
				$color = ($info[$info['type']]['priority'] > 3) ? $colors['Red'] : $colors['Dark Red'];

				$msg = S_PROBLEM_BIG;
				if($info[$info['type']]['count'] > 1)
					$msg = $info[$info['type']]['count'].' '.S_PROBLEMS;
				else if($expandProblem && isset($info[$info['type']]['info']))
					$msg = $info[$info['type']]['info'];
				else 
					$msg = $info[$info['type']]['count'].' '.S_PROBLEM;

				$info['info'] = array();
				$info['info'][] = array('msg'=>$msg, 'color'=>$color);

				if(isset($info[TRIGGER_VALUE_UNKNOWN])){
					$info['info'][] = array(
										'msg'=>$info[TRIGGER_VALUE_UNKNOWN]['count'].' '.S_UNKNOWN,
										'color'=>$colors['Gray']
									);
				}

				$info['iconid'] = $selement['iconid_on'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
			}
			else if($info['type'] == TRIGGER_VALUE_UNKNOWN){
				$info['info'] = array();
				$info['info'][] = array(
									'msg'=>$info[TRIGGER_VALUE_UNKNOWN]['count'].' '.S_UNKNOWN,
									'color'=>$colors['Gray']
								);

				if(isset($info['disabled']) && $info['disabled'] == 1)
					$info['iconid'] = $selement['iconid_disabled'];
				else
					$info['iconid'] = $selement['iconid_unknown'];

				$info['icon_type'] = SYSMAP_ELEMENT_ICON_UNKNOWN;
			}
			else if($info['type'] == TRIGGER_VALUE_FALSE){
				$info['info'] = array();
				$info['info'][] = array(
									'msg'=>S_OK_BIG,
									'color'=>$colors['Dark Green']
								);
				$info['iconid'] = $selement['iconid_off'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
			}

			$info['priority'] = isset($info[$info['type']]['priority']) ? $info[$info['type']]['priority'] : 0;
//---
		}

	return $selements_info;
	}

/*
 * Function: getMapsInfo
 * Description: Retrive selement
 * Author: Aly
 */

 	function getMapsInfo($selements, $expandProblem=false){
		global $colors;

		$selements_info = array();
		$options = array(
				'sysmapids' => zbx_objectValues($selements, 'elementid'),
				'extendoutput' => 1,
				'nopermissions' => 1,
				'select_selements' => 1,
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

// recursion
			$info['triggers'] = array();
			$infos = getSelementsInfo($map);

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

//				$info['triggers'] += $inf['triggers'];
				$info['triggers'] = array_merge($info['triggers'], $inf['triggers']);

				if(!isset($info[$info['type']]['count'])) $info[$info['type']]['count'] = 0;
				$info[$info['type']]['count'] += isset($inf['count'])?$inf['count']:1;

				if(!isset($info[$info['type']]['priority']) || ($info[$info['type']]['priority'] < $inf['priority'])){
					$info[$info['type']]['priority'] = $inf['priority'];
					$info[$info['type']]['info'] = $inf['info'];
				}
			}
//SDII($info);
			$count = count($info['triggers']);

			if($count > 0){
				$info[TRIGGER_VALUE_TRUE]['count'] = $count;

				if($info[TRIGGER_VALUE_TRUE]['count'] == 1){
					$tr1 = reset($info['triggers']);
					$sql = 'SELECT DISTINCT t.triggerid,t.priority,t.value,t.description,t.expression,h.host '.
							' FROM triggers t, items i, functions f, hosts h'.
							' WHERE t.triggerid='.$tr1.
								' AND h.hostid=i.hostid'.
								' AND i.itemid=f.itemid '.
								' AND f.triggerid=t.triggerid';
					$db_trigger = DBfetch(DBselect($sql));
					
					$info[TRIGGER_VALUE_TRUE]['info'] = array();
					$info[TRIGGER_VALUE_TRUE]['info'][] = array('msg' => expand_trigger_description_by_data($db_trigger));
				}
			}

			if(!isset($info['type'])) $info['type'] = TRIGGER_VALUE_FALSE;

//----
			if($info['type'] == TRIGGER_VALUE_TRUE){
				$color = ($info[$info['type']]['priority'] > 3) ? $colors['Red'] : $colors['Dark Red'];

				$msg = S_PROBLEM_BIG;
				if($info[$info['type']]['count'] > 1)
					$msg = $info[$info['type']]['count'].' '.S_PROBLEMS;
				else if($expandProblem && isset($info[$info['type']]['info'])){
					if($tmp = reset($info[$info['type']]['info'])){
						$msg = $tmp['msg'];
					}
					else{
						$msg = '';
					}
				}
				else 
					$msg = $info[$info['type']]['count'].' '.S_PROBLEM;

				$info['info'] = array();
				$info['info'][] = array('msg'=>$msg, 'color'=>$color);

				if(isset($info[TRIGGER_VALUE_UNKNOWN])){
					$info['info'][] = array(
										'msg'=>$info[TRIGGER_VALUE_UNKNOWN]['count'].' '.S_UNKNOWN,
										'color'=>$colors['Gray']
									);
				}

				$info['iconid'] = $selement['iconid_on'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_ON;
			}
			else if($info['type'] == TRIGGER_VALUE_UNKNOWN){
				$info['info'] = array();
				$info['info'][] = array(
									'msg'=>$info[TRIGGER_VALUE_UNKNOWN]['count'].' '.S_UNKNOWN,
									'color'=>$colors['Gray']
								);

				if(isset($info['disabled']) && $info['disabled'] == 1)
					$info['iconid'] = $selement['iconid_disabled'];
				else
					$info['iconid'] = $selement['iconid_unknown'];

				$info['icon_type'] = SYSMAP_ELEMENT_ICON_UNKNOWN;
			}
			else if($info['type'] == TRIGGER_VALUE_FALSE){
				$info['info'] = array();
				$info['info'][] = array(
									'msg'=>S_OK_BIG,
									'color'=>$colors['Dark Green']
								);
				$info['iconid'] = $selement['iconid_off'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;
			}


// Host in maintenance
			if(isset($info['maintenance_status'])){
				$info['info'] = array();
				$info['info'][] = array(
									'msg'=>S_IN_MAINTENANCE,
									'color'=>$colors['Orange']
								);

				$info['type'] = TRIGGER_VALUE_UNKNOWN;
				$info['maintenance'] = 1;
				if($maintenance['maintenanceid'] > 0){
					$mnt = get_maintenance_by_maintenanceid($maintenance['maintenanceid']);
					$info['info'].='['.$mnt['name'].']';
				}

				$info['iconid'] = $selement['iconid_maintenance'];
				$info['icon_type'] = SYSMAP_ELEMENT_ICON_MAINTENANCE;
			}

			$info['priority'] = isset($info[$info['type']]['priority']) ? $info[$info['type']]['priority'] : 0;
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

			$info['type'] = TRIGGER_VALUE_FALSE;

			$info['info'] = array();
			$info['info'][] = array(
								'msg'=>'',
								'color'=>$colors['Black']
							);

			$info['count'] = 0;
			$info['priority'] = 0;

			$info['color'] = $colors['Green'];

			$info['iconid'] = $selement['iconid_off'];
			$info['icon_type'] = SYSMAP_ELEMENT_ICON_OFF;

			$info['triggers'] = array();
		}

	return $selements_info;
	}

/*
 * Function: getSelementsInfo
 * Description: Retrive selement
 * Author: Aly
 */

	function getSelementsInfo($sysmap, $expandProblem=false){
		$elements = separateMapElements($sysmap);

		$info = array();
		$info += getMapsInfo($elements['sysmaps'], $expandProblem);
		$info += getHostGroupsInfo($elements['hostgroups'], $expandProblem);
		$info += getHostsInfo($elements['hosts'], $expandProblem);
		$info += getTriggersInfo($elements['triggers']);
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
			throw new exception($e->show_message());
		}
//SDII($exportMaps);
	}
?>