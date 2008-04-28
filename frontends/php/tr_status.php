<?php
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
	require_once "include/config.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/acknow.inc.php";
	require_once "include/triggers.inc.php";
	require_once "include/events.inc.php";
	require_once "include/scripts.inc.php";

	$page["file"] = "tr_status.php";
	$page["title"] = "S_STATUS_OF_TRIGGERS";
	$page['scripts'] = array('blink.js');
	$page['hist_arg'] = array('groupid','hostid','compact','onlytrue','noactions','select');

?>
<?php
	$tr_hash = calc_trigger_hash();

	$triggers_hash = get_cookie('zbx_triggers_hash', '0,0');

	$new=explode(",",$tr_hash);
	$old=explode(",",$triggers_hash);

	zbx_set_post_cookie("zbx_triggers_hash",$tr_hash,time()+1800);

	$triggers_hash = get_cookie('zbx_triggers_hash', '0,0');
	
	$new=explode(",",$tr_hash);
	$old=explode(",",$triggers_hash);

	if( $old[1] != $new[1] ){
		if( $new[0] < $old[0] )	// Number of trigger decreased
			$status = "off";
		else			// Number of trigger increased
			$status = "on";

		$files_apdx = array(
			5 => 'disaster',
			4 => 'high',
			3 => 'average',
			2 => 'warning',
			1 => 'information',
			0 => 'not_classified');

		$prior_dif = $new[0]-$old[0];

		krsort($files_apdx);
		foreach($files_apdx as $priority => $apdx){
			if(round($prior_dif / pow(100, $priority)) != 0){
				$audio = 'audio/trigger_'.$status.'_'.$apdx.'.wav';
				break;
			}
		}

		if(!isset($audio) || !file_exists($audio))
			$audio = 'audio/trigger_'.$status.'.wav';
	}

?>
<?php
	define('ZBX_PAGE_DO_REFRESH', 1);

	if(isset($_REQUEST["fullscreen"]))
		define('ZBX_PAGE_NO_MENU', 1);
	
include_once "include/page_header.php";
	
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"groupid"=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),
		"hostid"=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),
		"noactions"=>	array(T_ZBX_STR, O_OPT,  null,	IN('"true","false"'), null),
		"compact"=>	array(T_ZBX_STR, O_OPT,  null,	IN('"true","false"'), null),
		
		"show_triggers"=>	array(T_ZBX_INT, O_OPT,  null, null, null),
		"show_events"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	null,	null),
		"select"=>	array(T_ZBX_STR, O_OPT,  null,	IN('"true","false"'), null),
		"txt_select"=>	array(T_ZBX_STR, O_OPT,  null,	null, null),
		"fullscreen"=>	array(T_ZBX_STR, O_OPT,  null,	null, null),
		"btnSelect"=>	array(T_ZBX_STR, O_OPT,  null,  null, null)
	);

	check_fields($fields);
	validate_sort_and_sortorder();

	$_REQUEST['show_triggers']	=	get_request('show_triggers', get_profile('web.tr_status.show_triggers', TRIGGERS_OPTION_ONLYTRUE));
	$_REQUEST['show_events']	=	get_request('show_events',get_profile('web.tr_status.show_events', EVENTS_OPTION_NOEVENT));
	
	$_REQUEST['noactions']		=	get_request('noactions', get_profile('web.tr_status.noactions', 'true'));
	$_REQUEST['compact']		=	get_request('compact', get_profile('web.tr_status.compact', 'true'));

	$options = array("allow_all_hosts","monitored_hosts","with_monitored_items");//,"always_select_first_host");
	if(!$ZBX_WITH_SUBNODES)	array_push($options,"only_current_node");
	
	validate_group_with_host(PERM_READ_ONLY,$options,"web.tr_status.groupid","web.tr_status.hostid");

	update_profile('web.tr_status.show_triggers',$_REQUEST['show_triggers']);
	update_profile('web.tr_status.show_events',$_REQUEST['show_events']);

	update_profile('web.tr_status.noactions',$_REQUEST['noactions']);
	update_profile('web.tr_status.compact',$_REQUEST['compact']);
	
	$config=select_config();
	
?>
<?php
	if(isset($audio)){
		play_sound($audio);
	}
?>                                                                                                             
<?php
	
	$noactions		= get_request('noactions',	'true');
	$compact	 	= get_request('compact',	'true');
	$show_triggers	= get_request('show_triggers',	TRIGGERS_OPTION_ONLYTRUE);
	$show_events 	= get_request('show_events',	EVENTS_OPTION_NOEVENT);
	$select		 	= get_request('select',		'false');
	$txt_select	 	= get_request('txt_select',	'');
	
	if($select == 'false') $txt_select = '';

// if trigger option is NOFALSEFORB than only 1 option avalable for events and we are setting it!!!
	if(TRIGGERS_OPTION_NOFALSEFORB && ($show_triggers == TRIGGERS_OPTION_NOFALSEFORB)){
		$show_events = EVENTS_OPTION_NOFALSEFORB;
	}
	
	if(($show_events == EVENTS_OPTION_NOFALSEFORB) && ($show_triggers != TRIGGERS_OPTION_NOFALSEFORB)){
		$show_events = EVENTS_OPTION_NOEVENT;
	}

	if(!$config['event_ack_enable'] && (($show_events != EVENTS_OPTION_NOEVENT) || ($show_events != EVENTS_OPTION_ALL))){
		$show_events = EVENTS_OPTION_NOEVENT;
	}
?>
<?php
	$r_form = new CForm();
	$r_form->SetMethod('get');

	$cmbGroup = new CComboBox('groupid',$_REQUEST['groupid'],'submit()');
	$cmbHosts = new CComboBox('hostid',$_REQUEST['hostid'],'submit()');

	$cmbGroup->AddItem(0,S_ALL_SMALL);
	$cmbHosts->AddItem(0,S_ALL_SMALL);
	
	$available_groups= get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY);
	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
	
	$scripts_by_hosts = get_accessible_scripts_by_hosts(explode(',',$available_hosts));
	
	$available_triggers = get_accessible_triggers(PERM_READ_ONLY,PERM_RES_IDS_ARRAY);
	
	$result=DBselect('SELECT DISTINCT g.groupid,g.name '.
					' FROM groups g, hosts_groups hg, hosts h, items i '.
					' WHERE g.groupid in ('.$available_groups.') '.
						' AND hg.groupid=g.groupid '.
						' AND h.status='.HOST_STATUS_MONITORED.
						' AND h.hostid=i.hostid '.
						' AND hg.hostid=h.hostid '.
						' AND i.status='.ITEM_STATUS_ACTIVE.
					' ORDER BY g.name');
										
	while($row=DBfetch($result)){
		$cmbGroup->AddItem(
				$row['groupid'],
				get_node_name_by_elid($row['groupid']).$row['name']
				);
		unset($row);
	}
	$r_form->AddItem(array(S_GROUP.SPACE,$cmbGroup));
	
	
	$sql='SELECT DISTINCT h.hostid,h.host '.
		' FROM hosts_groups hg, hosts h, items i, functions f, triggers t '.
		' WHERE '.DBcondition('t.triggerid',$available_triggers).
			' AND t.status='.TRIGGER_STATUS_ENABLED.
			' AND f.triggerid=t.triggerid '.
			' AND i.itemid=f.itemid '.
			' AND i.status='.ITEM_STATUS_ACTIVE.
			' AND h.hostid=i.hostid '.												
			' AND h.status='.HOST_STATUS_MONITORED.
			' AND hg.hostid=h.hostid '.
			($_REQUEST["groupid"]?' AND hg.groupid='.$_REQUEST["groupid"]:'').
		' ORDER BY h.host';
	
	$result=DBselect($sql);
	$flag = false;
	while($row=DBfetch($result)){
		$flag |= (bccomp($_REQUEST['hostid'] , $row['hostid']) == 0);
		$cmbHosts->AddItem(
				$row['hostid'],
				get_node_name_by_elid($row['hostid']).$row['host']
				);
	}
	if(!$flag) $_REQUEST['hostid'] = 0;

	$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
	$r_form->AddVar("compact",$compact);
	$r_form->AddVar("show_triggers",$show_triggers);
	$r_form->AddVar('show_events',$show_events);
	$r_form->AddVar('noactions',$noactions);
	$r_form->AddVar('select',$select);
	$r_form->AddVar('txt_select',$txt_select);
	if(isset($_REQUEST['fullscreen'])) $r_form->AddVar('fullscreen',1);

	show_table_header(
		new CLink(SPACE.S_STATUS_OF_TRIGGERS_BIG.SPACE.date("[H:i:s]",time()),"tr_status.php?show_triggers=$show_triggers".
			"&show_events=$show_events&noactions=$noactions&compact=$compact".
			(!isset($_REQUEST["fullscreen"]) ? '&fullscreen=1' : '')),
		$r_form);
	
	if(!isset($_REQUEST["fullscreen"])){
		$left_col = array();
		
		$tr_form = new CForm('tr_status.php');
		$tr_form->SetMethod('get');
		$tr_form->AddOption('style','display: inline;');
		
		$tr_form->AddVar("compact",$compact);
		$tr_form->AddVar("noactions",$noactions);
		$tr_form->AddVar("select",$select);
		
		$tr_select = new CComboBox('show_triggers',S_TRIGGERS,'javascript: submit();');
		if(TRIGGERS_OPTION_ONLYTRUE){
			$tr_select->Additem(TRIGGERS_OPTION_ONLYTRUE,S_SHOW_ONLY_TRUE,(TRIGGERS_OPTION_ONLYTRUE==$show_triggers)?'yes':'no');
		}
		if(TRIGGERS_OPTION_ALL){
			$tr_select->Additem(TRIGGERS_OPTION_ALL,S_SHOW_ALL,(TRIGGERS_OPTION_ALL==$show_triggers)?'yes':'no');
		}
		if(TRIGGERS_OPTION_NOFALSEFORB && $config['event_ack_enable']){
			$tr_select->Additem(TRIGGERS_OPTION_NOFALSEFORB,S_SHOW_NOFALSEFORB,(TRIGGERS_OPTION_NOFALSEFORB==$show_triggers)?'yes':'no');
		}
		
		$ev_select = new CComboBox('show_events',S_EVENTS,'javascript: submit();');
		if(EVENTS_OPTION_NOEVENT){
			$ev_select->Additem(EVENTS_OPTION_NOEVENT,S_HIDE_ALL,(EVENTS_OPTION_NOEVENT==$show_events)?'yes':'no');
		}
		if(EVENTS_OPTION_ALL){
			$ev_select->Additem(EVENTS_OPTION_ALL,S_SHOW_ALL.SPACE.'('.$config['event_expire'].SPACE.(($config['event_expire']>1)?S_DAYS:S_DAY).')',(EVENTS_OPTION_ALL==$show_events)?'yes':'no');
		}
		if(EVENTS_OPTION_NOT_ACK && $config['event_ack_enable']){
			$ev_select->Additem(EVENTS_OPTION_NOT_ACK,S_SHOW_UNACKNOWLEDGED.SPACE.'('.$config['event_expire'].SPACE.(($config['event_expire']>1)?S_DAYS:S_DAY).')',(EVENTS_OPTION_NOT_ACK==$show_events)?'yes':'no');
		}
		if(EVENTS_OPTION_ONLYTRUE_NOTACK && $config['event_ack_enable']){
			$ev_select->Additem(EVENTS_OPTION_ONLYTRUE_NOTACK,S_SHOW_TRUE_UNACKNOWLEDGED.SPACE.'('.$config['event_expire'].SPACE.(($config['event_expire']>1)?S_DAYS:S_DAY).')',(EVENTS_OPTION_ONLYTRUE_NOTACK==$show_events)?'yes':'no');
		}
//------- JP -------
		if($show_triggers==TRIGGERS_OPTION_NOFALSEFORB){
			$ev_select->Additem(EVENTS_OPTION_NOFALSEFORB,' - ','yes');
			$ev_select->AddOption('disabled','disabled');
		}
		
		$tr_form->AddItem(S_TRIGGERS);
		$tr_form->AddItem($tr_select);
		
		$tr_form->AddItem(SPACE.SPACE.S_EVENTS);
		$tr_form->AddItem($ev_select);
		
		array_push($left_col,$tr_form,SPACE);
			
		array_push($left_col, '[', new CLink($noactions != 'true' ? S_HIDE_ACTIONS : S_SHOW_ACTIONS,
			"tr_status.php?noactions=".($noactions != 'true' ? 'true' : 'false').
			"&show_triggers=$show_triggers&show_events=$show_events&compact=$compact&select=$select&txt_select=$txt_select"
			), ']'.SPACE);

		array_push($left_col, '[', new CLink($compact != 'true' ? S_HIDE_DETAILS: S_SHOW_DETAILS,
			"tr_status.php?compact=".($compact != 'true' ? 'true' : 'false').
			"&show_triggers=$show_triggers&show_events=$show_events&noactions=$noactions&select=$select&txt_select=$txt_select"
			), ']'.SPACE);
		
		array_push($left_col, '[', new CLink($select != 'true' ? S_SELECT : S_HIDE_SELECT,
			"tr_status.php?select=".($select != 'true' ? 'true' : 'false').
			"&show_triggers=$show_triggers&show_events=$show_events&noactions=$noactions&compact=$compact&txt_select=$txt_select"
			), ']');
			
		if($select=='true'){
		
			$form = new CForm();
			$form->SetMethod('get');
			
			$form->AddItem(new CTextBox("txt_select",$txt_select,15));
			$form->AddItem(new CButton("btnSelect", "Select"));
			$form->AddItem(new CButton("btnSelect", "Inverse select"));
			$form->AddVar("compact",$compact);
			$form->AddVar("noactions",$noactions);
			$form->AddVar("select",$select);
			array_push($left_col,BR(),$form);
		}
		show_table_header($left_col);
	}

  	if(isset($_REQUEST["fullscreen"])){
		$triggerInfo = new CTriggersInfo();
		$triggerInfo->HideHeader();
		$triggerInfo->Show();
	}

	if(isset($_REQUEST["fullscreen"])){
		$fullscreen='&fullscreen=1';
	}
	else{
		$fullscreen='';
	}
	
	$m_form = new CForm('acknow.php');
	$m_form->SetName('tr_status');
	
	$table  = new CTableInfo();
	$table->ShowStart();
	
	$header=array();

	$table->SetHeader(array(
		($config['event_ack_enable'])?(new CCheckBox("all_events",false, "CheckAll('".$m_form->GetName()."','all_events','events');")): NULL,
		make_sorting_link(S_SEVERITY,'t.priority'),
		S_STATUS,
		make_sorting_link(S_LAST_CHANGE,'t.lastchange'),
		is_show_subnodes() ? make_sorting_link(S_NODE,'h.hostid') : null,
		$_REQUEST["hostid"] >0 ? null : make_sorting_link(S_HOST,'h.host'),
		make_sorting_link(S_NAME,'t.description'),
		($noactions!='true')?S_ACTIONS:NULL,
		($config['event_ack_enable'])? S_ACKNOWLEDGED : NULL,
		S_COMMENTS
		));
		
	$cond =($_REQUEST['hostid'] > 0)?' AND h.hostid='.$_REQUEST['hostid'].' ':'';
	$cond.=($_REQUEST['groupid']> 0)?' AND hg.groupid ='.$_REQUEST['groupid']:'';
	
	switch($show_triggers){
		case TRIGGERS_OPTION_ALL:
			$cond.='';
			break;
		case TRIGGERS_OPTION_NOFALSEFORB:
			$cond.='';
			break;
		case TRIGGERS_OPTION_ONLYTRUE:
		default:
			$cond.=' AND ((t.value='.TRIGGER_VALUE_TRUE.') OR ((t.value='.TRIGGER_VALUE_FALSE.') AND (('.time().'-t.lastchange)<'.TRIGGER_FALSE_PERIOD.')))';
			break;
	}

	$sql = 'SELECT DISTINCT t.triggerid,t.status,t.description, t.expression,t.priority, '.
							' t.lastchange,t.comments,t.url,t.value,h.host,h.hostid,t.type '.
					' FROM triggers t,hosts h,items i,functions f, hosts_groups hg '.
					' WHERE f.itemid=i.itemid '.
						' AND h.hostid=i.hostid '.
						' AND hg.hostid=h.hostid '.
						' AND t.triggerid=f.triggerid '.
						' AND t.status='.TRIGGER_STATUS_ENABLED.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND '.DBcondition('t.triggerid',$available_triggers).
						' AND h.status='.HOST_STATUS_MONITORED.' '.$cond.
						order_by('h.host,h.hostid,t.description,t.priority,t.lastchange');

	$result = DBselect($sql);

	while($row=DBfetch($result)){
// Check for dependencies
		if(trigger_dependent($row["triggerid"]))	continue;
		$cond = '';
		$event_expire = ($config['event_expire']*86400); // days
		switch($show_events){
			case EVENTS_OPTION_ALL:
				$cond.=' AND (('.time().'-e.clock)<'.$event_expire.')';
				break;
			case EVENTS_OPTION_NOT_ACK:
				$cond.=' AND (('.time().'-e.clock)<'.$event_expire.') AND e.acknowledged=0 ';
				break;
			case EVENTS_OPTION_ONLYTRUE_NOTACK:
				$cond.=' AND (('.time().'-e.clock)<'.$event_expire.') AND e.acknowledged=0 AND e.value='.TRIGGER_VALUE_TRUE;
				break;
			case EVENTS_OPTION_NOFALSEFORB:
				$cond.=' AND e.acknowledged=0 AND ((e.value='.TRIGGER_VALUE_TRUE.') OR ((e.value='.TRIGGER_VALUE_FALSE.') AND t.type='.TRIGGER_MULT_EVENT_DISABLED.'))';
				break;
			case EVENTS_OPTION_NOEVENT:
			default:
				$cond.=' AND 1=2 ';
				break;
		}

		$event_sql = 'SELECT e.eventid, e.value, e.clock, e.objectid as triggerid, e.acknowledged, t.type '.
					' FROM events e, triggers t '.
					' WHERE e.object=0 '.
						' AND e.objectid='.$row['triggerid'].
						' AND t.triggerid=e.objectid '.$cond.
					' ORDER by e.object DESC, e.objectid DESC, e.eventid DESC';

		if($show_triggers == TRIGGERS_OPTION_NOFALSEFORB){
			if(!$row = get_row_for_nofalseforb($row,$event_sql)){
				continue;
			}
		}
		$elements=array();

		$description = expand_trigger_description($row['triggerid']);

		if(isset($_REQUEST["btnSelect"]) && '' != $txt_select && ((zbx_stristr($description, $txt_select)) == ($_REQUEST["btnSelect"]=="Inverse select"))) continue;

		if($row["url"] != "")
		{
			$description = new CLink($description, $row["url"]);
		}

		if($compact != 'true')
		{
			$font = new CTag('font','yes');
			$font->AddOption('color','#000');
			$font->AddOption('size','-2');
			$font->AddItem(explode_exp($row["expression"],1));
			$description = array($description,BR(), $font);
		}
// dependency		
		$dependency = false;
		$dep_table = new CTableInfo();
		$dep_table->AddOption('style', 'width: 200px;');
	
		$sql_dep = 'SELECT * FROM trigger_depends WHERE triggerid_down='.$row['triggerid'];
		$dep_res = DBselect($sql_dep);
		while($dep_row = DBfetch($dep_res)){
			$dep_table->AddRow(SPACE.'-'.SPACE.expand_trigger_description($dep_row['triggerid_up']));
			$dependency = true;
		}
		
		if($dependency){
			$img = new Cimg('images/general/trigg_dep.gif','DEP',12,12);
			$img->AddOption('style','vertical-align: middle; border: 0px;');
			$img->SetHint($dep_table);
			
			$description = array($img,SPACE,$description);
		}
		unset($img, $dep_table, $dependency);		
//------------------------

		if((time(NULL)-$row["lastchange"])<TRIGGER_BLINK_PERIOD){
			$tr_status = new CLink(trigger_value2str($row["value"]));
			$tr_status->AddOption('name','blink');
		}
		else{
			$tr_status = trigger_value2str($row["value"]);
		}
		
		$value = new CSpan($tr_status, get_trigger_value_style($row["value"]));

		if($noactions=='true'){
			$actions=NULL;
		}
		else{
			$actions=array(
				new CLink(S_CHANGE,'triggers.php?form=update&triggerid='.$row["triggerid"].url_param('hostid'),"action"));
		}
	
		$host = null;
		if($_REQUEST['hostid'] < 1){
			$menus = '';

			$host_nodeid = id2nodeid($row['hostid']);
			foreach($scripts_by_hosts[$row['hostid']] as $id => $script){
				$script_nodeid = id2nodeid($script['scriptid']);
				if( (bccomp($host_nodeid ,$script_nodeid ) == 0))
					$menus.= "['".$script['name']."',\"javascript: openWinCentered('scripts_exec.php?execute=1&hostid=".$row['hostid']."&scriptid=".$script['scriptid']."','".S_TOOLS."',760,540,'titlebar=no, resizable=yes, scrollbars=yes, dialog=no');\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
			}

			$menus.= "[".zbx_jsvalue(S_LINKS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
			$menus.= "['".S_LATEST_DATA."',\"javascript: Redirect('latest.php?hostid=".$row['hostid']."')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";

			$menus = rtrim($menus,',');
			$menus="show_popup_menu(event,[[".zbx_jsvalue(S_TOOLS).",null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],".$menus."],180);";
			
			$host = new CSpan($row['host']);
			$host->AddOption('onclick','javascript: '.$menus);
			$host->AddOption('onmouseover',"javascript: this.style.cursor = 'pointer';");
		}

			$table->AddRow(array(
				($config['event_ack_enable'])?SPACE:NULL,
				new CCol(
					get_severity_description($row["priority"]),
					get_severity_style($row["priority"])
					),
				$value,
				new CLink(zbx_date2str(S_DATE_FORMAT_YMDHMS,$row["lastchange"]),"events.php?triggerid=".$row["triggerid"],"action"),								
				get_node_name_by_elid($row['triggerid']),
				$host,
				$description,
				$actions,
				($config['event_ack_enable'])?SPACE:NULL,
				new CLink(($row["comments"] == "") ? S_ADD : S_SHOW,"tr_comments.php?triggerid=".$row["triggerid"],"action")
				));

		$event_limit=0;
		$res_events = DBSelect($event_sql,$config['event_show_max']*100);

		while($row_event=DBfetch($res_events)){
			if(($show_events == EVENTS_OPTION_NOFALSEFORB) && ($row_event['value'] == TRIGGER_VALUE_FALSE)){
				if(!event_initial_time($row_event)){
					continue;
				}
			}

			$value = new CSpan(trigger_value2str($row_event['value']), get_trigger_value_style($row_event['value']));	

			if($config['event_ack_enable']){
				if($row_event['acknowledged'] == 1)
				{
					$acks_cnt = DBfetch(DBselect('SELECT COUNT(*) as cnt FROM acknowledges WHERE eventid='.$row_event['eventid']));
					$ack=array(
						new CSpan(S_YES,"off"),
						SPACE.'('.$acks_cnt['cnt'].SPACE,
						new CLink(S_SHOW,'acknow.php?eventid='.$row_event['eventid'],'action'),')');
				}
				else{
					$ack= new CLink(S_NOT_ACKNOWLEDGED,'acknow.php?eventid='.$row_event['eventid'],'on');
				}
			}

			$description = expand_trigger_description_by_data(
					array_merge($row, array("clock"=>$row_event["clock"])),
					ZBX_FLAG_EVENT);

			if($compact != 'true'){
				$font = new CTag('font','yes');
				$font->AddOption('color','#000');
				$font->AddOption('size','-2');
				$font->AddItem(explode_exp($row["expression"],1));
				$description = array($description, $font);
			}

			$font = new CTag('font','yes');
			$font->AddOption('color','#808080');
			$font->AddItem(array('&nbsp;-&nbsp;',$description));
			$description = $font;
		
			$description = new CCol($description);
			$description->AddOption('style','white-space: normal; width: 90%;');

			$table->AddRow(array(
					($config['event_ack_enable'])?(($row_event['acknowledged'] == 1)?(SPACE):(new CCheckBox('events['.$row_event['eventid'].']', 'no',NULL,$row_event['eventid']))):NULL,
					new CCol(
						get_severity_description($row["priority"]),
						get_severity_style($row["priority"])
						),
					$value,
					new CLink(zbx_date2str(S_DATE_FORMAT_YMDHMS,$row_event['clock']),"events.php?triggerid=".$row["triggerid"],"action"),					
					get_node_name_by_elid($row['triggerid']),
					$host,
					$description,
					$actions,
					($config['event_ack_enable'])?(new CCol($ack,"center")):NULL,
					new CLink(($row["comments"] == "") ? S_ADD : S_SHOW,"tr_comments.php?triggerid=".$row["triggerid"],"action")
					));
			$event_limit++;
			if($event_limit >= $config['event_show_max']) break;
		}
		unset($row,$description, $actions);
	}

	$table->SetFooter(new CCol(array(S_TOTAL.": ",
							$table->GetNumRows(),
							SPACE.SPACE.SPACE,
							($config['event_ack_enable'])?(new CButton('bulkacknowledge',S_BULK_ACKNOWLEDGE,'javascript: submit();')):(SPACE)
					)));
					
	$m_form->AddItem($table);
	$m_form->Show();
	unset($table);
	
	zbx_add_post_js('blink.init();');	
	
	$jsmenu = new CPUMenu(null,170);
	$jsmenu->InsertJavaScript();
?>
<?php
include_once "include/page_footer.php";
?>
