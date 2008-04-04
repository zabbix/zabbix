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
?>
<?php
	require_once "include/config.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/events.inc.php";
	require_once "include/discovery.inc.php";
	require_once "include/html.inc.php";

	$page["title"] = "S_LATEST_EVENTS";
	$page["file"] = "events.php";
	$page['hist_arg'] = array('groupid','hostid');
	$page['scripts'] = array('calendar.js');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	
	if(PAGE_TYPE_HTML == $page['type']){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}

include_once "include/page_header.php";

?>
<?php
	$allow_discovery = check_right_on_discovery(PERM_READ_ONLY);

	$allowed_sources[] = EVENT_SOURCE_TRIGGERS;
	if($allow_discovery) $allowed_sources[] = EVENT_SOURCE_DISCOVERY;
	
	define('PAGE_SIZE',	100);
	
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"source"=>			array(T_ZBX_INT, O_OPT,	P_SYS,	IN($allowed_sources),	NULL),
		"groupid"=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		"hostid"=>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		"triggerid"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		"start"=>			array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535)."({}%".PAGE_SIZE."==0)",	NULL),
		"next"=>			array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		"prev"=>			array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
// filter
		"filter_rst"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		"filter_set"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
		
		"show_unknown"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		
		'filter_timesince'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),
		'filter_timetill'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),

//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),
	);

	$_REQUEST['source'] = get_request('source', get_profile('web.events.source', 0));

	check_fields($fields);
	
/* AJAX */	
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			update_profile('web.events.filter.state',$_REQUEST['state']);
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------

/* FILTER */
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['triggerid'] = 0;
		$_REQUEST['show_unknown'] = 0;
		
		$_REQUEST['filter_timesince'] = 0;
		$_REQUEST['filter_timetill'] = 0;
	}
	
	$_REQUEST['triggerid'] = get_request('triggerid',get_profile('web.events.filter.triggerid',0));
	$show_unknown = get_request('show_unknown',get_profile('web.events.filter.show_unknown',0));
	
	$_REQUEST['filter_timesince'] = get_request('filter_timesince',get_profile('web.events.filter.timesince',0));
	$_REQUEST['filter_timetill'] = get_request('filter_timetill',get_profile('web.events.filter.timetill',0));
	
	if(($_REQUEST['filter_timetill'] > 0) && ($_REQUEST['filter_timesince'] > $_REQUEST['filter_timetill'])){
		$tmp = $_REQUEST['filter_timesince'];
		$_REQUEST['filter_timesince'] = $_REQUEST['filter_timetill'];
		$_REQUEST['filter_timetill'] = $tmp;
	}
	
	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		update_profile('web.events.filter.triggerid',$_REQUEST['triggerid']);
		update_profile('web.events.filter.show_unknown',$show_unknown);
		
		update_profile('web.events.filter.timesince',$_REQUEST['filter_timesince']);
		update_profile('web.events.filter.timetill',$_REQUEST['filter_timetill']);
	}
// --------------

	validate_sort_and_sortorder('e.clock',ZBX_SORT_DOWN);
	
	$source = get_request('source', EVENT_SOURCE_TRIGGERS);
	update_profile('web.events.source',$source);
	
?>
<?php
	$_REQUEST["start"] = get_request("start", 0);
	$_REQUEST["start"]-=(isset($_REQUEST["prev"]))?PAGE_SIZE:0;
	$_REQUEST["start"]+=(isset($_REQUEST["next"]))?PAGE_SIZE:0;
	$_REQUEST["start"]=($_REQUEST["start"])?$_REQUEST["start"]:0;
	
?>
<?php
	$source = get_request('source', EVENT_SOURCE_TRIGGERS);

	$r_form = new CForm();
	$r_form->SetMethod('get');
	
	$r_form->AddOption('name','events_menu');

	if(EVENT_SOURCE_TRIGGERS == $source){
	
	    $available_groups= get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY, null, null, get_current_nodeid());
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, PERM_READ_ONLY, null, null, get_current_nodeid());
		
		$available_triggers = get_accessible_triggers(PERM_READ_ONLY, null, get_current_nodeid());

		if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid']>0)){
			$sql = 'SELECT DISTINCT h.hostid '.
					' FROM hosts h, functions f, items i'.
					' WHERE i.itemid=f.itemid '.
						' AND h.hostid=i.hostid '.
						' AND f.triggerid IN ('.$available_triggers.') '.
						' AND f.triggerid='.$_REQUEST['triggerid'];
						
			if($host = DBfetch(DBselect($sql,1))){
				$_REQUEST['hostid'] = $host['hostid'];							
				if($group = DBfetch(DBselect('SELECT DISTINCT hg.groupid '.
									' FROM hosts_groups hg'.
									' WHERE hg.hostid='.$_REQUEST['hostid'].
										' AND hg.hostid in ('.$available_hosts.') ')))
				{
					$_REQUEST['groupid'] = $group['groupid'];
				}
			}
			else{
				unset($_REQUEST['triggerid']);
			}
		}
		
		validate_group_with_host(PERM_READ_ONLY, array("allow_all_hosts","monitored_hosts","with_items"));

		$cmbGroup = new CComboBox("groupid",$_REQUEST["groupid"],"submit()");
		$cmbHosts = new CComboBox("hostid",$_REQUEST["hostid"],"submit()");

		$cmbGroup->AddItem(0,S_ALL_SMALL);
		
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
		}
		$r_form->AddItem(array(S_GROUP.SPACE,$cmbGroup));
		
		$cmbHosts->AddItem(0,S_ALL_SMALL);

		$sql='SELECT DISTINCT h.hostid,h.host '.
			' FROM hosts h,items i,hosts_groups hg '.
			' WHERE h.status='.HOST_STATUS_MONITORED.
				' AND h.hostid=i.hostid '.
				($_REQUEST['groupid']?' AND hg.groupid='.$_REQUEST['groupid']:'').
				' AND hg.hostid=h.hostid '.
				' AND h.hostid in ('.$available_hosts.') '.
			' ORDER BY h.host';
			
		$result=DBselect($sql);
		while($row=DBfetch($result)){
			$cmbHosts->AddItem(
					$row['hostid'],
					get_node_name_by_elid($row['hostid']).$row['host']
					);
		}

		$r_form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
	}
	
	if($allow_discovery){
		$cmbSource = new CComboBox('source', $source, 'submit()');
		$cmbSource->AddItem(EVENT_SOURCE_TRIGGERS, S_TRIGGER);
		$cmbSource->AddItem(EVENT_SOURCE_DISCOVERY, S_DISCOVERY);
		$r_form->AddItem(array(SPACE.S_SOURCE.SPACE, $cmbSource));
	}

	show_table_header(S_HISTORY_OF_EVENTS_BIG.SPACE.date("[H:i:s]",time()),$r_form);
	
	if($source == EVENT_SOURCE_DISCOVERY){
		$table = get_history_of_discovery_events($_REQUEST["start"], PAGE_SIZE);
	}
	else{
		$config = select_config();

		$sql_from = $sql_cond = '';

		if($_REQUEST["hostid"] > 0){
			$sql_cond = ' and h.hostid='.$_REQUEST["hostid"];
		}
		else if($_REQUEST["groupid"] > 0){
			$sql_from = ', hosts_groups hg ';
			$sql_cond = ' and h.hostid=hg.hostid and hg.groupid='.$_REQUEST["groupid"];
		}
		else{
			$sql_from = ', hosts_groups hg ';
			$sql_cond = ' and h.hostid in ('.$available_hosts.') ';
		}
		
		$sql_cond.=(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid']>0))?(' AND t.triggerid='.$_REQUEST['triggerid'].' '):'';

//---
		$triggers = array();
		$trigger_list = '';

		$sql = 'SELECT DISTINCT t.triggerid,t.priority,t.description,t.expression,h.host,t.type '.
				' FROM triggers t, functions f, items i, hosts h '.$sql_from.
				' WHERE t.triggerid IN ('.$available_triggers.') '.
					' AND t.triggerid=f.triggerid '.
					' AND f.itemid=i.itemid '.
					' AND i.hostid=h.hostid '.
					' AND h.status='.HOST_STATUS_MONITORED.
					$sql_cond;
							
		$rez = DBselect($sql);
		while($rowz = DBfetch($rez)){
			$triggers[$rowz['triggerid']] = $rowz;
			$trigger_list.=$rowz['triggerid'].',';
		}

		$trigger_list = '('.trim($trigger_list,',').')';
		$sql_cond=($show_unknown == 0)?(' AND e.value<>'.TRIGGER_VALUE_UNKNOWN.' '):('');
		$sql_cond.=($_REQUEST['filter_timesince']>0)?' AND e.clock>'.$_REQUEST['filter_timesince']:' AND e.clock>100';
		$sql_cond.=($_REQUEST['filter_timetill']>0)?' AND e.clock<'.$_REQUEST['filter_timetill']:'';

		$table = new CTableInfo(S_NO_EVENTS_FOUND); 
		$table->SetHeader(array(
//				make_sorting_link(S_TIME,'e.eventid'),
				make_sorting_link(S_TIME,'e.clock'),
				is_show_subnodes() ? S_NODE : null,
				$_REQUEST["hostid"] == 0 ? S_HOST : null,
				S_DESCRIPTION,
				S_STATUS,
				S_SEVERITY,
				S_DURATION,
				($config['event_ack_enable'])?S_ACK:NULL,
				S_ACTIONS
			));

		if(!empty($triggers)){
			$sql = 'SELECT e.eventid, e.objectid as triggerid, e.clock, e.value, e.acknowledged '.
					' FROM events e '.
					' WHERE (e.object+0)='.EVENT_OBJECT_TRIGGER.
						' AND e.objectid IN '.$trigger_list.
						$sql_cond.
					order_by('e.clock');
//SDI($sql);
			$result = DBselect($sql,10*($_REQUEST['start']+PAGE_SIZE));
		}

		$col=0;
		$skip = $_REQUEST['start'];

		while(!empty($triggers) && ($col<PAGE_SIZE) && ($row=DBfetch($result))){
			
			if($skip > 0){
				if((0 == $show_unknown) && ($row['value'] == TRIGGER_VALUE_UNKNOWN)) continue;
				$skip--;
				continue;
			}
		
			if($row["value"] == TRIGGER_VALUE_FALSE){
				$value=new CCol(S_FALSE_BIG,"off");
			}
			elseif($row["value"] == TRIGGER_VALUE_TRUE){
				$value=new CCol(S_TRUE_BIG,"on");
			}
			else{
				$value=new CCol(S_UNKNOWN_BIG,"unknown");
			}

			$row = array_merge($triggers[$row['triggerid']],$row);
			if((0 == $show_unknown) && (!event_initial_time($row,$show_unknown))) continue;
			
			$duration = zbx_date2age($row['clock']);
			if($next_event = get_next_event($row,$show_unknown)){
				$duration = zbx_date2age($row['clock'],$next_event['clock']);
			}
// Actions								
			$actions= new CTable(' - ');

			$sql='SELECT COUNT(a.alertid) as cnt_all'.
					' FROM alerts a,events e'.
					' WHERE a.eventid='.$row['eventid'].
						' AND e.eventid = a.eventid'.
						' AND e.object='.EVENT_OBJECT_TRIGGER.
						' AND e.objectid IN ('.$available_triggers.') ';
					
			$alerts=DBfetch(DBselect($sql));

			if(isset($alerts['cnt_all']) && ($alerts['cnt_all'] > 0)){
				$mixed = 0;
// Sent
				$sql='SELECT COUNT(a.alertid) as sent '.
						' FROM alerts a,events e'.
						' WHERE a.eventid='.$row['eventid'].
							' AND a.status='.ALERT_STATUS_SENT.
							' AND e.eventid = a.eventid'.
							' AND e.object='.EVENT_OBJECT_TRIGGER.
							' AND e.objectid IN ('.$available_triggers.') ';

				$tmp=DBfetch(DBselect($sql));
				$alerts['sent'] = $tmp['sent'];
				$mixed+=($alerts['sent'])?ALERT_STATUS_SENT:0;
// In progress
				$sql='SELECT COUNT(a.alertid) as inprogress '.
						' FROM alerts a,events e'.
						' WHERE a.eventid='.$row['eventid'].
							' AND a.status='.ALERT_STATUS_NOT_SENT.
							' AND e.eventid = a.eventid'.
							' AND e.object='.EVENT_OBJECT_TRIGGER.
							' AND e.objectid IN ('.$available_triggers.') ';

				$tmp=DBfetch(DBselect($sql));
				$alerts['inprogress'] = $tmp['inprogress'];
// Failed
				$sql='SELECT COUNT(a.alertid) as failed '.
						' FROM alerts a,events e'.
						' WHERE a.eventid='.$row['eventid'].
							' AND a.status='.ALERT_STATUS_FAILED.
							' AND e.eventid = a.eventid'.
							' AND e.object='.EVENT_OBJECT_TRIGGER.
							' AND e.objectid IN ('.$available_triggers.') ';

				$tmp=DBfetch(DBselect($sql));
				$alerts['failed'] = $tmp['failed'];
				$mixed+=($alerts['failed'])?ALERT_STATUS_FAILED:0;


				if($alerts['inprogress']){
					$status = new CSpan(S_IN_PROGRESS,'orange');
				}
				else if(ALERT_STATUS_SENT == $mixed){
					$status = new CSpan(S_OK,'green');
				}
				else if(ALERT_STATUS_FAILED == $mixed){
					$status = new CSpan(S_FAILED,'red');
				}
				else{
					$tdl = new CCol(($alerts['sent'])?(new CSpan($alerts['sent'],'green')):SPACE);
					$tdl->AddOption('width','10');
					
					$tdr = new CCol(($alerts['failed'])?(new CSpan($alerts['failed'],'red')):SPACE);
					$tdr->AddOption('width','10');

					$status = new CRow(array($tdl,$tdr));
				}

				$actions->AddRow($status);
			}
//--------		

			if($config['event_ack_enable']){
				if($row['acknowledged'] == 1){
					$ack=new CLink(S_YES,'acknow.php?eventid='.$row['eventid'],'action');
				}
				else{
					$ack= new CLink(S_NO,'acknow.php?eventid='.$row['eventid'],'on');
				}
			}

			$table->AddRow(array(
				date("Y.M.d H:i:s",$row["clock"]),
				is_show_subnodes() ? get_node_name_by_elid($row['triggerid']) : null,
				$_REQUEST["hostid"] == 0 ? $row['host'] : null,
				new CLink(
					expand_trigger_description_by_data($row, ZBX_FLAG_EVENT),
					"tr_events.php?triggerid=".$row["triggerid"].'&eventid='.$row['eventid'],
					"action"
					),
				$value,
				new CCol(get_severity_description($row["priority"]), get_severity_style($row["priority"])),
				$duration,
				($config['event_ack_enable'])?$ack:NULL,
				$actions
			));
				
			$col++;
		}
	}


/************************* FILTER **************************/
/***********************************************************/

	$prev = 'Prev 100';
	$next='Next 100';
	if($_REQUEST["start"] > 0){
		$prev = new Clink('Prev '.PAGE_SIZE, 'events.php?prev=1'.url_param('start'),'styled');
	}
	
	if($table->GetNumRows() >= PAGE_SIZE){
		$next = new Clink('Next '.PAGE_SIZE, 'events.php?next=1'.url_param('start'),'styled');
	}	

	$filterForm = new CFormTable(S_FILTER);//,'events.php?filter_set=1','POST',null,'sform');
	$filterForm->AddOption('name','zbx_filter');
	$filterForm->AddOption('id','zbx_filter');
	$filterForm->SetMethod('get');

	if(EVENT_SOURCE_TRIGGERS == $source){
	
		$script = new CScript("javascript: if(CLNDR['events_since'].clndr.setSDateFromOuterObj()){". 
								"$('filter_timesince').value = parseInt(CLNDR['events_since'].clndr.sdt.getTime()/1000);}".
							"if(CLNDR['events_till'].clndr.setSDateFromOuterObj()){". 
								"$('filter_timetill').value = parseInt(CLNDR['events_till'].clndr.sdt.getTime()/1000);}"
							);
		$filterForm->AddAction('onsubmit',$script);
		
		$filterForm->AddVar('filter_timesince',($_REQUEST['filter_timesince']>0)?$_REQUEST['filter_timesince']:'');
		$filterForm->AddVar('filter_timetill',($_REQUEST['filter_timetill']>0)?$_REQUEST['filter_timetill']:'');
//*	
		$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');
		$clndr_icon->AddAction('onclick',"javascript: var pos = getPosition(this); pos.top+=10; pos.left+=16; CLNDR['events_since'].clndr.clndrshow(pos.top,pos.left);");
		
		$filtertimetab = new CTable();
		$filtertimetab->AddOption('width','10%');
		$filtertimetab->SetCellPadding(0);
		$filtertimetab->SetCellSpacing(0);
	
		$filtertimetab->AddRow(array(
								S_FROM, 
								new CNumericBox('filter_since_day',(($_REQUEST['filter_timesince']>0)?date('d',$_REQUEST['filter_timesince']):''),2),
								'/',
								new CNumericBox('filter_since_month',(($_REQUEST['filter_timesince']>0)?date('m',$_REQUEST['filter_timesince']):''),2),
								'/',
								new CNumericBox('filter_since_year',(($_REQUEST['filter_timesince']>0)?date('Y',$_REQUEST['filter_timesince']):''),4),
								new CNumericBox('filter_since_hour',(($_REQUEST['filter_timesince']>0)?date('H',$_REQUEST['filter_timesince']):''),2),
								':',
								new CNumericBox('filter_since_minute',(($_REQUEST['filter_timesince']>0)?date('i',$_REQUEST['filter_timesince']):''),2),
								$clndr_icon
						));
		zbx_add_post_js('create_calendar(null,["filter_since_day","filter_since_month","filter_since_year","filter_since_hour","filter_since_minute"],"events_since");');
	
		$clndr_icon->AddAction('onclick',"javascript: var pos = getPosition(this); pos.top+=10; pos.left+=16; CLNDR['events_till'].clndr.clndrshow(pos.top,pos.left);");
		$filtertimetab->AddRow(array(
								S_TILL, 
								new CNumericBox('filter_till_day',(($_REQUEST['filter_timetill']>0)?date('d',$_REQUEST['filter_timetill']):''),2),
								'/',
								new CNumericBox('filter_till_month',(($_REQUEST['filter_timetill']>0)?date('m',$_REQUEST['filter_timetill']):''),2),
								'/',
								new CNumericBox('filter_till_year',(($_REQUEST['filter_timetill']>0)?date('Y',$_REQUEST['filter_timetill']):''),4),
								new CNumericBox('filter_till_hour',(($_REQUEST['filter_timetill']>0)?date('H',$_REQUEST['filter_timetill']):''),2),
								':',
								new CNumericBox('filter_till_minute',(($_REQUEST['filter_timetill']>0)?date('i',$_REQUEST['filter_timetill']):''),2),
								$clndr_icon
						));
		zbx_add_post_js('create_calendar(null,["filter_till_day","filter_till_month","filter_till_year","filter_till_hour","filter_till_minute"],"events_till");');
		
		zbx_add_post_js('addListener($("filter_icon"),"click",CLNDR[\'events_since\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'events_since\'].clndr));'.
						'addListener($("filter_icon"),"click",CLNDR[\'events_till\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'events_till\'].clndr));'
						);
		
		$filterForm->AddRow(S_PERIOD, $filtertimetab);
//*/	
		$filterForm->AddVar('triggerid',$_REQUEST['triggerid']);
		
		if(isset($_REQUEST['triggerid']) && ($_REQUEST['triggerid']>0)){
			$trigger = expand_trigger_description($_REQUEST['triggerid']);
		} else{
			$trigger = "";
		}
		$row = new CRow(array(
						new CCol(S_TRIGGER,'form_row_l'),
						new CCol(array(
									new CTextBox("trigger",$trigger,96,'yes'),
									new CButton("btn1",S_SELECT,"return PopUp('popup.php?"."dstfrm=".$filterForm->GetName()."&dstfld1=triggerid&dstfld2=trigger"."&srctbl=triggers&srcfld1=triggerid&srcfld2=description&real_hosts=1');",'T')
								),'form_row_r')
							));
							
		$filterForm->AddRow($row);

		$filterForm->AddVar('show_unknown',$show_unknown);
		
		$unkcbx = new CCheckBox('show_unk',$show_unknown,null,'1');
		$unkcbx->SetAction('javascript: create_var("'.$filterForm->GetName().'", "show_unknown", (this.checked?1:0), 0); ');
		
		$filterForm->AddRow(S_SHOW_UNKNOWN,$unkcbx);

		$reset = new CButton("filter_rst",S_RESET);
		$reset->SetType('button');
		$reset->SetAction('javascript: var uri = new url(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

		$filterForm->AddItemToBottomRow($reset);
		$filterForm->AddItemToBottomRow(new CButton("filter_set",S_FILTER));
	}

	$navigation = array(
						new CSpan(array('&laquo; ',$prev),'textcolorstyles'),
						new CSpan(' | ','divider'),
						new CSpan(array($next,' &raquo;'),'textcolorstyles'));
						
	$filter = create_filter(S_FILTER,$navigation,$filterForm,'tr_filter',get_profile('web.events.filter.state',0));
	$filter->Show();
//-------

	$table->Show();
	
	show_thin_table_header(SPACE,$navigation);
?>
<?php

include_once "include/page_footer.php";

?>
