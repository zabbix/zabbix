<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	
	require_once('include/events.inc.php');
	require_once('include/actions.inc.php');
	
?>
<?php
	function screen_accessible($screenid,$perm){
		global $USER_DETAILS;

		$result = false;

		if(DBfetch(DBselect('SELECT screenid FROM screens WHERE screenid='.$screenid.' AND '.DBin_node('screenid', get_current_nodeid($perm))))){
			$result = true;
			$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
			
			$db_result = DBselect('SELECT * FROM screens_items WHERE screenid='.$screenid);
			while(($ac_data = DBfetch($db_result)) && $result){
				switch($ac_data['resourcetype']){
					case SCREEN_RESOURCE_GRAPH:
						$itemid = array();

						$db_gitems = DBselect('SELECT DISTINCT itemid '.
										' FROM graphs_items '.
										' WHERE graphid='.$ac_data['resourceid']);
						
						while($gitem_data = DBfetch($db_gitems)) array_push($itemid, $gitem_data['itemid']);
						
						if(count($itemid) == 0) $itemid = array(-1);
						// break; /* use same processing as items */
					case SCREEN_RESOURCE_SIMPLE_GRAPH:
						// break; /* use same processing as items */
					case SCREEN_RESOURCE_PLAIN_TEXT:
						if(!isset($itemid))
							$itemid = array($ac_data['resourceid']);

						if(DBfetch(DBselect('SELECT itemid '.
										' FROM items '.
										' WHERE itemid IN ('.implode(',',$itemid).') '.
											' AND '.DBcondition('hostid',$available_hosts,true))))
						{
							$result = false;
						}	

						unset($itemid);
						break;
					case SCREEN_RESOURCE_MAP:
						$result &= sysmap_accessible($ac_data['resourceid'], PERM_READ_ONLY);
						break;
					case SCREEN_RESOURCE_SCREEN:
						$result &= screen_accessible($ac_data['resourceid'],PERM_READ_ONLY);
						break;
					case SCREEN_RESOURCE_SERVER_INFO:
					case SCREEN_RESOURCE_HOSTS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
					case SCREEN_RESOURCE_DATA_OVERVIEW:
					case SCREEN_RESOURCE_CLOCK:
					case SCREEN_RESOURCE_URL:
					case SCREEN_RESOURCE_ACTIONS:
					case SCREEN_RESOURCE_EVENTS:
						/* skip */
						break;
				}
			}
		}
		return $result;
	}

        function        add_screen($name,$hsize,$vsize)
        {
		$screenid=get_dbid("screens","screenid");
                $sql="insert into screens (screenid,name,hsize,vsize) values ($screenid,".zbx_dbstr($name).",$hsize,$vsize)";
                $result=DBexecute($sql);

		if(!$result)
			return $result;

		return $screenid;
        }

        function update_screen($screenid,$name,$hsize,$vsize)
        {
                $sql="update screens set name=".zbx_dbstr($name).",hsize=$hsize,vsize=$vsize where screenid=$screenid";
                return  DBexecute($sql);
        }

        function delete_screen($screenid){
			$result=DBexecute('DELETE FROM screens_items where screenid='.$screenid);
			$result&=DBexecute('DELETE FROM screens_items where resourceid='.$screenid.' and resourcetype='.SCREEN_RESOURCE_SCREEN);
			$result&=DBexecute('DELETE FROM slides where screenid='.$screenid);
			$result&=DBexecute("DELETE FROM profiles WHERE idx='web.favorite.screenids' AND source='screenid' AND value_id=$screenid");
			$result&=DBexecute('DELETE FROM screens where screenid='.$screenid);	
		return	$result;
        }

        function add_screen_item($resourcetype,$screenid,$x,$y,$resourceid,$width,$height,$colspan,$rowspan,$elements,$valign,$halign,$style,$url,$dynamic){
			$sql='DELETE FROM screens_items WHERE screenid='.$screenid.' and x='.$x.' and y='.$y;
			DBexecute($sql);
			
			$screenitemid=get_dbid("screens_items","screenitemid");
			$result=DBexecute('INSERT INTO screens_items '.
								'(screenitemid,resourcetype,screenid,x,y,resourceid,width,height,'.
								' colspan,rowspan,elements,valign,halign,style,url,dynamic) '.
							' VALUES '.
								"($screenitemid,$resourcetype,$screenid,$x,$y,$resourceid,$width,$height,$colspan,".
								"$rowspan,$elements,$valign,$halign,$style,".zbx_dbstr($url).",$dynamic)");

			if(!$result) return $result;
		return $screenitemid;
        }

        function update_screen_item($screenitemid,$resourcetype,$resourceid,$width,$height,$colspan,$rowspan,$elements,$valign,$halign,$style,$url,$dynamic){
			return  DBexecute("UPDATE screens_items SET ".
								"resourcetype=$resourcetype,"."resourceid=$resourceid,"."width=$width,".
								"height=$height,colspan=$colspan,rowspan=$rowspan,elements=$elements,".
								"valign=$valign,halign=$halign,style=$style,url=".zbx_dbstr($url).",dynamic=$dynamic".
							" WHERE screenitemid=$screenitemid");
        }

        function delete_screen_item($screenitemid)
        {
                $sql="DELETE FROM screens_items where screenitemid=$screenitemid";
                return  DBexecute($sql);
        }

	function	get_screen_by_screenid($screenid)
	{
		$result = DBselect("select * from screens where screenid=$screenid");
		$row=DBfetch($result);
		if($row)
		{
			return	$row;
		}
		// error("No screen with screenid=[$screenid]");
		return FALSE;
	}

	function check_screen_recursion($mother_screenid, $child_screenid){
			if((bccomp($mother_screenid , $child_screenid)==0))	return TRUE;

			$db_scr_items = DBselect("select resourceid from screens_items where".
				" screenid=$child_screenid and resourcetype=".SCREEN_RESOURCE_SCREEN);
			while($scr_item = DBfetch($db_scr_items))
			{
				if(check_screen_recursion($mother_screenid,$scr_item["resourceid"]))
					return TRUE; 
			}
			return FALSE;
	}
	


	function get_slideshow($slideshowid, $step, $effectiveperiod=NULL)
	{
		$slide_data = DBfetch(DBselect('select min(step) as min_step, max(step) as max_step from slides '.
					' where slideshowid='.$slideshowid));

		if(!$slide_data || is_null($slide_data['min_step']))
		{
			return new CTableInfo(S_NO_SLIDES_DEFINED);
		}

		if(!isset($step) || $step < $slide_data['min_step'] || $step > $slide_data['max_step'])
		{
			$curr_step = $slide_data['min_step'];
		}
		else
		{
			$curr_step = $step;
		}
		
		if(!isset($step))
		{
			$iframe = new CIFrame('screens.php?config=1&fullscreen=2&elementid='.$slideshowid.'&step='.$curr_step.
					'&period='.$effectiveperiod.url_param('stime').url_param('from'),'99%');
					
			return $iframe;
		}

		$slide_data = DBfetch(DBselect('select sl.screenid,sl.delay,ss.delay as ss_delay from slides sl,slideshows ss '.
				       ' where ss.slideshowid='.$slideshowid.' and ss.slideshowid=sl.slideshowid and sl.step='.$curr_step));

		if( $slide_data['delay'] <= 0 )
		{
			$slide_data['delay'] = $slide_data['ss_delay'];
		}

		simple_js_redirect('screens.php?config=1&fullscreen=2&elementid='.$slideshowid.'&step='.($curr_step + 1).
				'&period='.$effectiveperiod.url_param('stime').url_param('from'),
				$slide_data['delay']);

		return get_screen($slide_data['screenid'],2,$effectiveperiod);
	}
	

	function	slideshow_accessible($slideshowid, $perm)
	{
		$result = false;

		if(DBselect('select slideshowid from slideshows where slideshowid='.$slideshowid.
			' and '.DBin_node('slideshowid', get_current_nodeid($perm))))
		{
			$result = true;
			$db_slides = DBselect('select distinct screenid from slides where slideshowid='.$slideshowid);
			while($slide_data = DBfetch($db_slides))
			{
				if( !($result = screen_accessible($slide_data["screenid"], PERM_READ_ONLY)) ) break;
			}
		}
		return $result;
	}

	function	get_slideshow_by_slideshowid($slideshowid)
	{
		return DBfetch(DBselect('select * from slideshows where slideshowid='.$slideshowid));
	}

	function	validate_slide($slide)
	{
		if(!screen_accessible($slide["screenid"], PERM_READ_ONLY)) return false;

		if( !is_numeric($slide['delay']) ) return false;

		return true;
	}

	function	add_slideshow($name, $delay, $slides)
	{
		foreach($slides as $slide)
		{
			if( !validate_slide($slide) )
				return false;
		}

		$slideshowid = get_dbid('slideshows','slideshowid');
		$result = DBexecute('insert into slideshows (slideshowid,name,delay) '.
			' values ('.$slideshowid.','.zbx_dbstr($name).','.$delay.')');

		$i = 0;
		foreach($slides as $slide)
		{
			$slideid = get_dbid('slides','slideid');
			if( !($result = DBexecute('insert into slides (slideid,slideshowid,screenid,step,delay) '.
				' values ('.$slideid.','.$slideshowid.','.$slide['screenid'].','.($i++).','.$slide['delay'].')')) )
			{
				break;
			}
		}
		
		if( !$result )
		{
			delete_slideshow($slideshowid);
			return false;
		}
		return $slideshowid;
	}

	function	update_slideshow($slideshowid, $name, $delay, $slides){
		foreach($slides as $slide){
			if(!validate_slide($slide))
				return false;
		}

		if(!$result = DBexecute('update slideshows set name='.zbx_dbstr($name).',delay='.$delay.' where slideshowid='.$slideshowid))
			return false;

		DBexecute('DELETE FROM slides where slideshowid='.$slideshowid);

		$i = 0;
		foreach($slides as $slide){
			$slideid = get_dbid('slides','slideid');
			if( !($result = DBexecute('insert into slides (slideid,slideshowid,screenid,step,delay) '.
				' values ('.$slideid.','.$slideshowid.','.$slide['screenid'].','.($i++).','.$slide['delay'].')')) ){
				return false;
			}
		}
		
		return true;
	}

	function delete_slideshow($slideshowid){

		$result = DBexecute('DELETE FROM slideshows where slideshowid='.$slideshowid);
		$result &= DBexecute('DELETE FROM slides where slideshowid='.$slideshowid);
		$result &= DBexecute("DELETE FROM profiles WHERE idx='web.favorite.screenids' AND source='slideshowid' AND value_id=$slideshowid");
		
		return $result;
	}
	

	# Show screen cell containing plain text values
	function	get_screen_plaintext($itemid,$elements){

		if($itemid == 0){
			$table = new CTableInfo(S_ITEM_NOT_EXISTS);
			$table->SetHeader(array(S_TIMESTAMP,S_ITEM));
			return $table;
		}

		global $DB;

		$item=get_item_by_itemid($itemid);
		switch($item["value_type"]){
			case ITEM_VALUE_TYPE_FLOAT:		$history_table = "history";		break;
			case ITEM_VALUE_TYPE_UINT64:	$history_table = "history_uint";	break;
			case ITEM_VALUE_TYPE_TEXT:		$history_table = "history_text";	break;
			case ITEM_VALUE_TYPE_LOG:		$history_table = "history_log";         break;
			default:						$history_table = "history_str";		break;
		}

		$sql='SELECT h.clock,h.value,i.valuemapid '.
			' FROM '.$history_table.' h, items i '.
			' WHERE h.itemid=i.itemid '.
				' AND i.itemid='.$itemid.
			' ORDER BY h.clock DESC';

		$result=DBselect($sql,$elements);

		$host = get_host_by_itemid($itemid);
		
		$table = new CTableInfo();
		$table->SetHeader(array(S_TIMESTAMP,item_description($host['host'].': '.$item["description"],$item["key_"])));
		
		while($row=DBfetch($result)){
			switch($item["value_type"])
			{
				case ITEM_VALUE_TYPE_TEXT:	
					if($DB['TYPE'] == "ORACLE")
					{
						if(isset($row["value"]))
						{
							$row["value"] = $row["value"]->load();
						}
						else
						{
							$row["value"] = "";
						}
					}
					/* do not use break */
				case ITEM_VALUE_TYPE_STR:	
					$value = nl2br(nbsp(htmlspecialchars($row["value"])));
					break;
				
				default:
					$value = $row["value"];
					break;
			}

			if($row["valuemapid"] > 0)
				$value = replace_value_by_map($value, $row["valuemapid"]);

			$table->AddRow(array(date(S_DATE_FORMAT_YMDHMS,$row["clock"]),	$value));
		}
		return $table;
	}

/*
* Function: 
*		check_dynamic_items
*
* Description:
*		Check if in screen are dynamic items, if so return TRUE, esle FALSE
*
* Author: 
*		Aly
*/

	function check_dynamic_items($screenid){
		$sql = 'SELECT screenitemid '.
			' FROM screens_items '.
			' WHERE screenid='.$screenid.
				' AND dynamic='.SCREEN_DYNAMIC_ITEM;
		if(DBfetch(DBselect($sql,1))) return TRUE;
	return FALSE;
	}
	
	function get_screen_item_form(){
		global $USER_DETAILS;
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
		
		$form = new CFormTable(S_SCREEN_CELL_CONFIGURATION,'screenedit.php#form');
		$form->SetHelp('web.screenedit.cell.php');

		if(isset($_REQUEST["screenitemid"])){
			$iresult=DBSelect('SELECT * FROM screens_items'.
							' WHERE screenid='.$_REQUEST['screenid'].
								' AND screenitemid='.$_REQUEST['screenitemid']
							);

			$form->AddVar("screenitemid",$_REQUEST["screenitemid"]);
		} 
		else{
			$form->AddVar("x",$_REQUEST["x"]);
			$form->AddVar("y",$_REQUEST["y"]);
		}

		if(isset($_REQUEST["screenitemid"]) && !isset($_REQUEST["form_refresh"])){
		
			$irow = DBfetch($iresult);
			$resourcetype	= $irow["resourcetype"];
			$resourceid	= $irow["resourceid"];
			$width		= $irow["width"];
			$height		= $irow["height"];
			$colspan	= $irow["colspan"];
			$rowspan	= $irow["rowspan"];
			$elements	= $irow["elements"];
			$valign		= $irow["valign"];
			$halign		= $irow["halign"];
			$style		= $irow["style"];
			$url		= $irow["url"];
			$dynamic	= $irow['dynamic'];
		}
		else{
			$resourcetype	= get_request("resourcetype",	0);
			$resourceid	= get_request("resourceid",	0);
			$width		= get_request("width",		500);
			$height		= get_request("height",		100);
			$colspan	= get_request("colspan",	0);
			$rowspan	= get_request("rowspan",	0);
			$elements	= get_request("elements",	25);
			$valign		= get_request("valign",		VALIGN_DEFAULT);
			$halign		= get_request("halign",		HALIGN_DEFAULT);
			$style		= get_request("style",		0);
			$url		= get_request("url",		"");
			$dynamic	= get_request("dynamic",	SCREEN_SIMPLE_ITEM);
		}

		$form->AddVar("screenid",$_REQUEST["screenid"]);

		$cmbRes = new CCombobox("resourcetype",$resourcetype,"submit()");
		$cmbRes->AddItem(SCREEN_RESOURCE_GRAPH,		S_GRAPH);
		$cmbRes->AddItem(SCREEN_RESOURCE_SIMPLE_GRAPH,	S_SIMPLE_GRAPH);
		$cmbRes->AddItem(SCREEN_RESOURCE_PLAIN_TEXT,	S_PLAIN_TEXT);
		$cmbRes->AddItem(SCREEN_RESOURCE_MAP,		S_MAP);
		$cmbRes->AddItem(SCREEN_RESOURCE_SCREEN,	S_SCREEN);
		$cmbRes->AddItem(SCREEN_RESOURCE_SERVER_INFO,	S_SERVER_INFO);
		$cmbRes->AddItem(SCREEN_RESOURCE_HOSTS_INFO,	S_HOSTS_INFO);
		$cmbRes->AddItem(SCREEN_RESOURCE_TRIGGERS_INFO,	S_TRIGGERS_INFO);
		$cmbRes->AddItem(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,	S_TRIGGERS_OVERVIEW);
		$cmbRes->AddItem(SCREEN_RESOURCE_DATA_OVERVIEW,		S_DATA_OVERVIEW);
		$cmbRes->AddItem(SCREEN_RESOURCE_CLOCK,		S_CLOCK);
		$cmbRes->AddItem(SCREEN_RESOURCE_URL,		S_URL);
		$cmbRes->AddItem(SCREEN_RESOURCE_ACTIONS,	S_HISTORY_OF_ACTIONS);
                $cmbRes->AddItem(SCREEN_RESOURCE_EVENTS,       S_HISTORY_OF_EVENTS);
		$form->AddRow(S_RESOURCE,$cmbRes);

		if($resourcetype == SCREEN_RESOURCE_GRAPH){
	// User-defined graph
			$resourceid = graph_accessible($resourceid)?$resourceid:0;

			$caption = '';
			$id=0;
		
			if($resourceid > 0){
				$result = DBselect('SELECT DISTINCT g.graphid,g.name,n.name as node_name, h.host'.
						' FROM graphs g '.
							' LEFT JOIN graphs_items gi ON g.graphid=gi.graphid '.
							' LEFT JOIN items i ON gi.itemid=i.itemid '.
							' LEFT JOIN hosts h ON h.hostid=i.hostid '.
							' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('g.graphid').
						' WHERE g.graphid='.$resourceid);

				while($row=DBfetch($result)){
					$row["node_name"] = isset($row["node_name"]) ? "(".$row["node_name"].") " : '';
					$caption = $row["node_name"].$row["host"].":".$row["name"];
					$id = $resourceid;
				}
			}

			$form->AddVar('resourceid',$id);
			
			$textfield = new Ctextbox('caption',$caption,75,'yes');
			$selectbtn = new Cbutton('select',S_SELECT,"javascript: return PopUp('popup.php?dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=graphs&srcfld1=graphid&srcfld2=name',800,450);");
			$selectbtn->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');
			
			$form->AddRow(S_GRAPH_NAME,array($textfield,SPACE,$selectbtn));
			
		}
		else if($resourcetype == SCREEN_RESOURCE_SIMPLE_GRAPH){
	// Simple graph
			$caption = '';
			$id=0;
		
			if($resourceid > 0){
				$result=DBselect('SELECT n.name as node_name,h.host,i.description,i.itemid,i.key_ '.
						' FROM hosts h,items i '.
							' LEFT JOIN nodes n on n.nodeid='.DBid2nodeid('i.itemid').
						' WHERE h.hostid=i.hostid '.
							' AND h.status='.HOST_STATUS_MONITORED.
							' AND i.status='.ITEM_STATUS_ACTIVE.
							' AND '.DBcondition('i.hostid',$available_hosts).
							' AND i.itemid='.$resourceid);

				while($row=DBfetch($result)){
					$description_=item_description($row['description'],$row['key_']);
					$row["node_name"] = isset($row["node_name"]) ? "(".$row["node_name"].") " : '';
	
					$caption = $row['node_name'].$row['host'].': '.$description_;
					$id = $resourceid;
				}
			}

			$form->AddVar('resourceid',$id);
			
			$textfield = new Ctextbox('caption',$caption,75,'yes');
			$selectbtn = new Cbutton('select',S_SELECT,"javascript: return PopUp('popup.php?dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=simple_graph&srcfld1=itemid&srcfld2=description',800,450);");
			$selectbtn->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');
			
			$form->AddRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));
		}
		else if($resourcetype == SCREEN_RESOURCE_MAP){
	// Map
			$caption = '';
			$id=0;
		
			if($resourceid > 0){
				$result=DBselect('SELECT n.name as node_name, s.sysmapid,s.name '.
							' FROM sysmaps s'.
								' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('s.sysmapid').
							' WHERE s.sysmapid='.$resourceid);

				while($row=DBfetch($result)){
					if(!sysmap_accessible($row['sysmapid'],PERM_READ_ONLY)) continue;
			
					$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
					$caption = $row['node_name'].$row['name'];
					$id = $resourceid;
				}
			}

			$form->AddVar('resourceid',$id);
			$textfield = new Ctextbox('caption',$caption,60,'yes');
			
			$selectbtn = new Cbutton('select',S_SELECT,"javascript: return PopUp('popup.php?dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=sysmaps&srcfld1=sysmapid&srcfld2=name',400,450);");
			$selectbtn->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');
			
			$form->AddRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));
			
		}
		else if($resourcetype == SCREEN_RESOURCE_PLAIN_TEXT){
// Plain text
			$caption = '';
			$id=0;
			
			if($resourceid > 0){
				$result=DBselect('SELECT n.name as node_name,h.host,i.description,i.itemid,i.key_ '.
						' FROM hosts h,items i '.
							' LEFT JOIN nodes n on n.nodeid='.DBid2nodeid('i.itemid').
						' WHERE h.hostid=i.hostid '.
							' AND h.status='.HOST_STATUS_MONITORED.
							' AND i.status='.ITEM_STATUS_ACTIVE.
							' AND '.DBcondition('i.hostid',$available_hosts).
							' AND i.itemid='.$resourceid);

				while($row=DBfetch($result)){
					$description_=item_description($row['description'],$row['key_']);
					$row["node_name"] = isset($row["node_name"]) ? '('.$row["node_name"].') ' : '';
	
					$caption = $row['node_name'].$row['host'].': '.$description_;
					$id = $resourceid;
				}
			}
			
			$form->AddVar('resourceid',$id);
			
			$textfield = new Ctextbox('caption',$caption,75,'yes');
			$selectbtn = new Cbutton('select',S_SELECT,"javascript: return PopUp('popup.php?dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=plain_text&srcfld1=itemid&srcfld2=description',800,450);");
			$selectbtn->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');
			
			$form->AddRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));
			$form->AddRow(S_SHOW_LINES, new CNumericBox('elements',$elements,2));
		}
		else if($resourcetype == SCREEN_RESOURCE_ACTIONS){
// History of actions
				$form->AddRow(S_SHOW_LINES, new CNumericBox('elements',$elements,2));
	$form->AddVar('resourceid',0);
		}
		else if($resourcetype == SCREEN_RESOURCE_EVENTS){
// History of events
				$form->AddRow(S_SHOW_LINES, new CNumericBox('elements',$elements,2));
				$form->AddVar('resourceid',0);
		}
		else if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW))){
// Overiews
			$caption = '';
			$id=0;
			
			if($resourceid > 0){
				$result=DBselect('SELECT DISTINCT n.name as node_name,g.groupid,g.name '.
						' FROM hosts_groups hg,hosts h,groups g '.
							' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('g.groupid').
						' WHERE g.groupid IN ('.get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY).')'.
							' AND g.groupid=hg.groupid '.
							' AND hg.hostid=h.hostid '.
							' AND h.status='.HOST_STATUS_MONITORED.
							' AND g.groupid='.$resourceid);

				while($row=DBfetch($result)){
					$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';

					$caption = $row['node_name'].$row['name'];
					$id = $resourceid;
				}
			}
			
			$form->AddVar('resourceid',$id);
			
			$textfield = new Ctextbox('caption',$caption,75,'yes');
			$selectbtn = new Cbutton('select',S_SELECT,"javascript: return PopUp('popup.php?dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=overview&srcfld1=groupid&srcfld2=name',800,450);");
			$selectbtn->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');
			
			$form->AddRow(S_GROUP,array($textfield,SPACE,$selectbtn));
		}
		else if($resourcetype == SCREEN_RESOURCE_SCREEN){
// Screens
			$caption = '';
			$id=0;
			
			if($resourceid > 0){
				$result=DBselect('SELECT DISTINCT n.name as node_name,s.screenid,s.name '.
							' FROM screens s '.
								' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('s.screenid').
							' WHERE s.screenid='.$resourceid);

				while($row=DBfetch($result)){
					if(!screen_accessible($row['screenid'], PERM_READ_ONLY)) continue;
					if(check_screen_recursion($_REQUEST['screenid'],$row['screenid'])) continue;
					
					$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
					$caption = $row['node_name'].$row['name'];
					$id = $resourceid;
				}
			}
			
			$form->AddVar('resourceid',$id);
			
			$textfield = new Ctextbox('caption',$caption,60,'yes');
			$selectbtn = new Cbutton('select',S_SELECT,"javascript: return PopUp('popup.php?dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=screens2&srcfld1=screenid&srcfld2=name&screenid=".$_REQUEST['screenid']."',800,450);");
			$selectbtn->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');
			
			$form->AddRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));
		}
		else if($resourcetype == SCREEN_RESOURCE_HOSTS_INFO){  
// HOTS info
			$caption = '';
			$id=0;
			
			if(remove_nodes_from_id($resourceid) > 0){
				$result=DBselect('SELECT DISTINCT n.name as node_name,g.groupid,g.name '.
						' FROM hosts_groups hg, groups g '.
							' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('g.groupid').
						' WHERE g.groupid in ('.get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY).')'.
							' AND g.groupid='.$resourceid);

				while($row=DBfetch($result)){					
					$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
					$caption = $row['node_name'].$row['name'];
					$id = $resourceid;
				}
			}
			else if(remove_nodes_from_id($resourceid)==0){
				$result=DBselect('SELECT DISTINCT n.name as node_name '.
						' FROM nodes n '.
						' WHERE n.nodeid='.id2nodeid($resourceid));

				while($row=DBfetch($result)){					
					$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
					$caption = $row['node_name'].S_MINUS_ALL_GROUPS_MINUS;
					$id = $resourceid;
				}
			}

			$form->AddVar('resourceid',$id);
			
			$textfield = new Ctextbox('caption',$caption,60,'yes');
			$selectbtn = new Cbutton('select',S_SELECT,"javascript: return PopUp('popup.php?dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=host_group_scr&srcfld1=groupid&srcfld2=name',480,450);");
			$selectbtn->AddOption('onmouseover','javascript: this.style.cursor = "pointer";');
			
			$form->AddRow(S_GROUP,array($textfield,SPACE,$selectbtn));
		}
		else{
// SCREEN_RESOURCE_TRIGGERS_INFO,  SCREEN_RESOURCE_CLOCK
			$form->AddVar("resourceid",0);
		}

		if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_HOSTS_INFO,SCREEN_RESOURCE_TRIGGERS_INFO))){
			$cmbStyle = new CComboBox("style", $style);
			$cmbStyle->AddItem(STYLE_HORISONTAL,	S_HORISONTAL);
			$cmbStyle->AddItem(STYLE_VERTICAL,	S_VERTICAL);
			$form->AddRow(S_STYLE,	$cmbStyle);
		}
		else if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW))){
			$cmbStyle = new CComboBox('style', $style);
			$cmbStyle->AddItem(STYLE_LEFT,	S_LEFT);
			$cmbStyle->AddItem(STYLE_TOP,	S_TOP);
			$form->AddRow(S_HOSTS_LOCATION,	$cmbStyle);
		}
		else if($resourcetype == SCREEN_RESOURCE_CLOCK){
			$cmbStyle = new CComboBox("style", $style);
			$cmbStyle->AddItem(TIME_TYPE_LOCAL,	S_LOCAL_TIME);
			$cmbStyle->AddItem(TIME_TYPE_SERVER,	S_SERVER_TIME);
			$form->AddRow(S_TIME_TYPE,	$cmbStyle);
		}
		else{
			$form->AddVar("style",	0);
		}

		if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_URL))){
			$form->AddRow(S_URL, new CTextBox("url",$url,60));
		}
		else{
			$form->AddVar("url",	"");
		}

		if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_CLOCK,SCREEN_RESOURCE_URL))){
			$form->AddRow(S_WIDTH,	new CNumericBox("width",$width,5));
			$form->AddRow(S_HEIGHT,	new CNumericBox("height",$height,5));
		}
		else{
			$form->AddVar("width",	500);
			$form->AddVar("height",	100);
		}

		if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_MAP,
			SCREEN_RESOURCE_CLOCK,SCREEN_RESOURCE_URL))){
			$cmbHalign = new CComboBox("halign",$halign);
			$cmbHalign->AddItem(HALIGN_CENTER,	S_CENTER);
			$cmbHalign->AddItem(HALIGN_LEFT,	S_LEFT);
			$cmbHalign->AddItem(HALIGN_RIGHT,	S_RIGHT);
			$form->AddRow(S_HORISONTAL_ALIGN,	$cmbHalign);
		}
		else{
			$form->AddVar("halign",	0);
		}

		$cmbValign = new CComboBox("valign",$valign);
		$cmbValign->AddItem(VALIGN_MIDDLE,	S_MIDDLE);
		$cmbValign->AddItem(VALIGN_TOP,		S_TOP);
		$cmbValign->AddItem(VALIGN_BOTTOM,	S_BOTTOM);
		$form->AddRow(S_VERTICAL_ALIGN,	$cmbValign);

		$form->AddRow(S_COLUMN_SPAN,	new CNumericBox("colspan",$colspan,2));
		$form->AddRow(S_ROW_SPAN,	new CNumericBox("rowspan",$rowspan,2));

// dynamic AddOn
		if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_PLAIN_TEXT))){
			$form->AddRow(S_DYNAMIC_ITEM,	new CCheckBox("dynamic",$dynamic,null,1));
		}

		$form->AddItemToBottomRow(new CButton("save",S_SAVE));
		if(isset($_REQUEST["screenitemid"])){
			$form->AddItemToBottomRow(SPACE);
			$form->AddItemToBottomRow(new CButtonDelete(null,
				url_param("form").url_param("screenid").url_param("screenitemid")));
		}
		$form->AddItemToBottomRow(SPACE);
		$form->AddItemToBottomRow(new CButtonCancel(url_param("screenid")));
		return $form;
	}

	// editmode: 0 - view with actions, 1 - edit mode, 2 - view without any actions
	function get_screen($screenid, $editmode, $effectiveperiod=NULL)
	{
		if(!screen_accessible($screenid, $editmode ? PERM_READ_WRITE : PERM_READ_ONLY))
			access_deny();
		
		if(is_null($effectiveperiod)) 
			$effectiveperiod = ZBX_MIN_PERIOD;

		$result=DBselect('SELECT name,hsize,vsize FROM screens WHERE screenid='.$screenid);
		$row=DBfetch($result);
		if(!$row) return new CTableInfo(S_NO_SCREENS_DEFINED);

		for($r=0;$r<$row["vsize"];$r++)
		{
			for($c=0;$c<$row["hsize"];$c++)
			{
				if(isset($skip_field[$r][$c]))	continue;

				$sql="select * from screens_items where screenid=$screenid and x=$c and y=$r";
				$iresult=DBSelect($sql);
				$irow=DBfetch($iresult);
				if($irow)
				{
					$colspan=$irow["colspan"];
					$rowspan=$irow["rowspan"];
				} else {
					$colspan=0;
					$rowspan=0;
				}
				for($i=0; $i < $rowspan || $i==0; $i++){
					for($j=0; $j < $colspan || $j==0; $j++){
						if($i!=0 || $j!=0)
							$skip_field[$r+$i][$c+$j]=1;
					}
				}
			}
		}
		$table = new CTable(
			new CLink("No rows in screen ".$row["name"],"screenconf.php?config=0&form=update&screenid=".$screenid),
			($editmode == 0 || $editmode == 2) ? "screen_view" : "screen_edit");
		$table->AddOption('id','iframe');
	
		for($r=0;$r<$row["vsize"];$r++)
		{
			$new_cols = array();
			for($c=0;$c<$row["hsize"];$c++)
			{
				$item = array();
				if(isset($skip_field[$r][$c]))		continue;
				
				$iresult=DBSelect("select * from screens_items".
					" where screenid=$screenid and x=$c and y=$r");

				$irow = DBfetch($iresult);
				if($irow){
					$screenitemid	= $irow["screenitemid"];
					$resourcetype	= $irow["resourcetype"];
					$resourceid	= $irow["resourceid"];
					$width		= $irow["width"];
					$height		= $irow["height"];
					$colspan	= $irow["colspan"];
					$rowspan	= $irow["rowspan"];
					$elements	= $irow["elements"];
					$valign		= $irow["valign"];
					$halign		= $irow["halign"];
					$style		= $irow["style"];
					$url		= $irow["url"];
					$dynamic	= $irow['dynamic'];
				}
				else{
					$screenitemid	= 0;
					$resourcetype	= 0;
					$resourceid	= 0;
					$width		= 0;
					$height		= 0;
					$colspan	= 0;
					$rowspan	= 0;
					$elements	= 0;
					$valign		= VALIGN_DEFAULT;
					$halign		= HALIGN_DEFAULT;
					$style		= 0;
					$url		= '';
					$dynamic	= 0;
				}

				if($editmode == 1 && $screenitemid!=0)
					 $action = 'screenedit.php?form=update'.url_param('screenid').'&screenitemid='.$screenitemid.'#form';
				else if ($editmode == 1 && $screenitemid==0)
					$action = 'screenedit.php?form=update'.url_param('screenid').'&x='.$c.'&y='.$r.'#form';
				else
					$action = NULL;

				if($editmode == 1 && isset($_REQUEST["form"]) && 
					isset($_REQUEST["x"]) && $_REQUEST["x"]==$c &&
					isset($_REQUEST["y"]) && $_REQUEST["y"]==$r)
				{ // click on empty field
					$item = get_screen_item_form();
				}
				else if($editmode == 1 && isset($_REQUEST["form"]) &&
					isset($_REQUEST["screenitemid"]) && (bccomp($_REQUEST["screenitemid"], $screenitemid)==0))
				{ // click on element
					$item = get_screen_item_form();
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_GRAPH) )
				{
					if($editmode == 0)
						$action = "charts.php?graphid=$resourceid".url_param("period").url_param("stime");
														
					$graphid = null;						
					$graphtype = GRAPH_TYPE_NORMAL;
					$yaxis = 0;
					
// GRAPH & ZOOM features
					$sql = 'SELECT MAX(g.graphid) as graphid, MAX(g.graphtype) as graphtype, MIN(gi.yaxisside) as yaxissidel, MAX(gi.yaxisside) as yaxissider,'.
								' MAX(g.show_legend) as legend, MAX(g.show_3d) as show3d '.
							' FROM graphs g, graphs_items gi '.
							' WHERE g.graphid='.$resourceid.
								' AND gi.graphid=g.graphid ';
			
					$res = Dbselect($sql);
					while($graph=DBfetch($res)){
						$graphid = $graph['graphid'];
						$graphtype = $graph['graphtype'];
						$yaxis = $graph['yaxissider'];
						$yaxis = ($graph['yaxissidel'] == $yaxis)?($yaxis):(2);
						
						$legend = $graph['legend'];
						$graph3d = $graph['show3d'];
					}
					if($yaxis == 2){
						$shiftXleft = 60;
						$shiftXright = 60;
					}
					else if($yaxis == 0){
						$shiftXleft = 60;
						$shiftXright = 20;
					}
					else{
						$shiftXleft = 10;
						$shiftXright = 60;
					}
//-------------
// Host feature
					if(($dynamic == SCREEN_DYNAMIC_ITEM) && isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0)){
						$def_items = array();
						$di_res = get_graphitems_by_graphid($resourceid);
						while( $gitem = DBfetch($di_res)){
							$def_items[] = $gitem;
						}
	
						$url='';
						if($new_items = get_same_graphitems_for_host($def_items, $_REQUEST['hostid'], false)){
							$url.= make_url_from_gitems($new_items);
						}
						
						$url= make_url_from_graphid($resourceid,false).$url;
					}
//-------------
					
					if(($graphtype == GRAPH_TYPE_PIE) || ($graphtype == GRAPH_TYPE_EXPLODED)){
						if(($dynamic==SCREEN_SIMPLE_ITEM) || empty($url)){
							$url="chart6.php?graphid=$resourceid";
						}
					
						$item = new CLink(
							new CImg($url.'&width='.$width.
											'&height='.$height.
											'&period='.$effectiveperiod.
											url_param('stime').
											'&legend='.$legend.
											'&graph3d='.$graph3d),
							$action
							);
					}
					else {
						if(($dynamic==SCREEN_SIMPLE_ITEM) || empty($url)){
							$url="chart2.php?graphid=$resourceid";
						}
						
						$dom_graph_id = 'graph_'.$screenitemid.'_'.$resourceid;
						$g_img = new CImg($url."&width=$width&height=$height"."&period=$effectiveperiod".url_param("stime"));
						$g_img->AddOPtion('id',$dom_graph_id);

						$item = new CLink($g_img,$action);

						if(!is_null($graphid) && ($editmode != 1)){
							insert_js('	A_SBOX["'.$dom_graph_id.'"] = new Object;'.
										'A_SBOX["'.$dom_graph_id.'"].shiftT = 17;'.
										'A_SBOX["'.$dom_graph_id.'"].shiftL = '.$shiftXleft.';'
									);
									
							if(isset($_REQUEST['stime'])){
								$stime = $_REQUEST['stime'];
								$stime = mktime(substr($stime,8,2),substr($stime,10,2),0,substr($stime,4,2),substr($stime,6,2),substr($stime,0,4));
							}
							else{
								$stime = 'null';
							}
							zbx_add_post_js('graph_zoom_init("'.$dom_graph_id.'",'.$stime.','.$effectiveperiod.','.$width.','.$height.', false);');
						}
					}
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SIMPLE_GRAPH) )
				{
					if($editmode == 0)
						$action = "history.php?action=showgraph&itemid=$resourceid".
                                                        url_param("period").url_param("inc").url_param("dec");

// Host feature
					if(($dynamic == SCREEN_DYNAMIC_ITEM) && isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0)){
						if($newitemid = get_same_item_for_host($resourceid,$_REQUEST['hostid'],false)){
							$resourceid = $newitemid;
						}
						else{
							$resourceid='';
						}
					}
//-------------
					$url = (empty($resourceid))?'chart3.php?':"chart.php?itemid=$resourceid&";
					$item = new CLink(
						new CImg($url."width=$width&height=$height"."&period=$effectiveperiod".url_param("stime")),
						$action
						);
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_MAP) ){
				
					$image_map = new CImg("map.php?noedit=1&sysmapid=$resourceid"."&width=$width&height=$height");
					
					if($editmode == 0){
						$action_map = get_action_map_by_sysmapid($resourceid);
						$image_map->SetMap($action_map->GetName());
						$item = array($action_map,$image_map);
					} 
					else {
						$item = new CLink($image_map, $action);
					}
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_PLAIN_TEXT) ){
// Host feature
					if(($dynamic == SCREEN_DYNAMIC_ITEM) && isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0)){
						if($newitemid = get_same_item_for_host($resourceid,$_REQUEST['hostid'],false)){
							$resourceid = $newitemid;
						}
						else{
							$resourceid=0;
						}
					}
//-------------
					$item = array(get_screen_plaintext($resourceid,$elements));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_HOSTS_INFO) ){
					$item = array(new CHostsInfo($resourceid, $style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_TRIGGERS_INFO) ){
					$item = array(new CTriggersInfo($style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SERVER_INFO) ){
					$item = array(new CServerInfo());
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_CLOCK) ){
					$item = new CFlashClock($width, $height, $style, $action);
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SCREEN) ){
					$item = array(get_screen($resourceid, 2, $effectiveperiod));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_TRIGGERS_OVERVIEW) ){
					$item = array(get_triggers_overview($resourceid,$style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_DATA_OVERVIEW) ){
					$item = array(get_items_data_overview($resourceid,$style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_URL) ){
					$item = array(new CIFrame($url,$width,$height,"auto"));
					if($editmode == 1)	array_push($item,BR(),new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_ACTIONS) ){
					$item = array(get_history_of_actions(0, $elements));
					if($editmode == 1)      array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_EVENTS) ){
					$item = array(get_history_of_triggers_events(0, $elements));
					if($editmode == 1)      array_push($item,new CLink(S_CHANGE,$action));
				}
				else{
					$item = array(SPACE);
					if($editmode == 1)	array_push($item,BR(),new CLink(S_CHANGE,$action));
				}

				$str_halign = "def";
				if($halign == HALIGN_CENTER)	$str_halign = "cntr";
				if($halign == HALIGN_LEFT)	$str_halign = "left";
				if($halign == HALIGN_RIGHT)	$str_halign = "right";

				$str_valign = "def";
				if($valign == VALIGN_MIDDLE)	$str_valign = "mdl";
				if($valign == VALIGN_TOP)	$str_valign = "top";
				if($valign == VALIGN_BOTTOM)	$str_valign = "bttm";

				$new_col = new CCol($item,$str_halign."_".$str_valign);

				if($colspan) $new_col->SetColSpan($colspan);
				if($rowspan) $new_col->SetRowSpan($rowspan);

				array_push($new_cols, $new_col);
			}
			$table->AddRow(new CRow($new_cols));
		}
		return $table;
	}
?>
