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
	require_once "include/acknow.inc.php";
	require_once "include/events.inc.php";
	require_once "include/triggers.inc.php";
	require_once "include/html.inc.php";

	$page["title"]		= "S_EVENT_DETAILS";
	$page["file"]		= "tr_events.php";
	$page['hist_arg'] = array('triggerid');
	$page['scripts'] = array('calendar.js');
	
	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	
	include_once "include/page_header.php";
?>
<?php
	define('PAGE_SIZE',	100);
	
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"triggerid"=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		PAGE_TYPE_HTML.'=='.$page['type']),

		"start"=>			array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(0,65535)."({}%".PAGE_SIZE."==0)",	NULL),
		"next"=>			array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		"prev"=>			array(T_ZBX_STR, O_OPT,	P_SYS,	NULL,			NULL),
		
/* actions */
		"save"=>		array(T_ZBX_STR,O_OPT,	P_ACT|P_SYS, null,	null),
		"cancel"=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		
// filter
		"filter_rst"=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		"filter_set"=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
		
		"show_unknown"=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		
		'filter_timesince'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),
		'filter_timetill'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),

// ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	IN("'filter','hat'"),		NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY,	'isset({favobj})'),
	);
	
	check_fields($fields);

/* AJAX */	
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.tr_events.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state']);
		}
		if('filter' == $_REQUEST['favobj']){
//			echo 'alert("'.$_REQUEST['favid'].' : '.$_REQUEST['state'].'");';
			update_profile('web.tr_events.filter.state',$_REQUEST['state']);
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------

	if(!check_right_on_trigger_by_triggerid(PERM_READ_ONLY, $_REQUEST["triggerid"]))
		access_deny();


/* FILTER */	
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['show_unknown'] = 0;
		
		$_REQUEST['filter_timesince'] = 0;
		$_REQUEST['filter_timetill'] = 0;
	}
	
	$show_unknown = get_request('show_unknown',get_profile('web.tr_events.filter.show_unknown',0));
	
	$_REQUEST['filter_timesince'] = get_request('filter_timesince',get_profile('web.tr_events.filter.timesince',0));
	$_REQUEST['filter_timetill'] = get_request('filter_timetill',get_profile('web.tr_events.filter.timetill',0));
	
	if(($_REQUEST['filter_timetill'] > 0) && ($_REQUEST['filter_timesince'] > $_REQUEST['filter_timetill'])){
		$tmp = $_REQUEST['filter_timesince'];
		$_REQUEST['filter_timesince'] = $_REQUEST['filter_timetill'];
		$_REQUEST['filter_timetill'] = $tmp;
	}
	
	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		update_profile('web.tr_events.filter.show_unknown',$show_unknown);
		
		update_profile('web.tr_events.filter.timesince',$_REQUEST['filter_timesince']);
		update_profile('web.tr_events.filter.timetill',$_REQUEST['filter_timetill']);
	}
// --------------

	$trigger_data = DBfetch(DBselect('SELECT h.host, t.* '.
						' FROM hosts h, items i, functions f, triggers t '.
	                   	' WHERE i.itemid=f.itemid '.
							' AND f.triggerid=t.triggerid '.
							' AND t.triggerid='.$_REQUEST["triggerid"].
							' AND h.hostid=i.hostid '.
							' AND '.DBin_node('t.triggerid')));
?>
<?php
	$_REQUEST["start"] = get_request("start", 0);
	$_REQUEST["start"]-=(isset($_REQUEST["prev"]))?PAGE_SIZE:0;
	$_REQUEST["start"]+=(isset($_REQUEST["next"]))?PAGE_SIZE:0;
	$_REQUEST["start"]=($_REQUEST["start"])?$_REQUEST["start"]:0;

	
	$trigger_data['exp_expr'] = explode_exp($trigger_data["expression"],1);
	$trigger_data['exp_desc'] =  expand_trigger_description_by_data($trigger_data);
	
	show_table_header(array(S_EVENTS_BIG.': "'.$trigger_data['exp_desc'].'"',SPACE,$trigger_data['exp_expr']), null);

	$table_eventlist = make_small_eventlist($_REQUEST['triggerid'],$trigger_data,$show_unknown);

/************************* FILTER **************************/
/***********************************************************/

	$prev = 'Prev 100';
	$next='Next 100';
	if($_REQUEST["start"] > 0){
		$prev = new Clink('Prev '.PAGE_SIZE, 'events.php?prev=1'.url_param('start'),'styled');
	}
	
	if($table_eventlist->GetNumRows() >= PAGE_SIZE){
		$next = new Clink('Next '.PAGE_SIZE, 'events.php?next=1'.url_param('start'),'styled');
	}	

	$filterForm = new CFormTable(S_FILTER);//,'events.php?filter_set=1','POST',null,'sform');
	$filterForm->AddOption('name','zbx_filter');
	$filterForm->AddOption('id','zbx_filter');
	$filterForm->SetMethod('get');

	
	$script = new CScript("javascript: if(CLNDR['events_since'].clndr.setSDateFromOuterObj()){". 
							"$('filter_timesince').value = parseInt(CLNDR['events_since'].clndr.sdt.getTime()/1000);}".
						"if(CLNDR['events_till'].clndr.setSDateFromOuterObj()){". 
							"$('filter_timetill').value = parseInt(CLNDR['events_till'].clndr.sdt.getTime()/1000);}"
						);
	$filterForm->AddAction('onsubmit',$script);
	
	$filterForm->AddVar('filter_timesince',($_REQUEST['filter_timesince']>0)?$_REQUEST['filter_timesince']:'');
	$filterForm->AddVar('filter_timetill',($_REQUEST['filter_timetill']>0)?$_REQUEST['filter_timetill']:'');
	
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

	$filterForm->AddVar('show_unknown',$show_unknown);
	
	$unkcbx = new CCheckBox('show_unk',$show_unknown,null,'1');
	$unkcbx->SetAction('javascript: create_var("'.$filterForm->GetName().'", "show_unknown", (this.checked?1:0), 0); ');
	
	$filterForm->AddRow(S_SHOW_UNKNOWN,$unkcbx);

	$reset = new CButton("filter_rst",S_RESET);
	$reset->SetType('button');
	$reset->SetAction('javascript: var uri = new url(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

	$filterForm->AddItemToBottomRow($reset);
	$filterForm->AddItemToBottomRow(new CButton("filter_set",S_FILTER));

	$navigation = array(
						new CSpan(array('&laquo; ',$prev),'textcolorstyles'),
						new CSpan(' | ','divider'),
						new CSpan(array($next,' &raquo;'),'textcolorstyles'));
						
	$filter = create_filter(S_FILTER,$navigation,$filterForm,'tr_filter',get_profile('web.tr_events.filter.state',0));
	$filter->Show();
//-------


$left_tab = new CTable();
$left_tab->SetCellPadding(3);
$left_tab->SetCellSpacing(3);

$left_tab->AddOption('border',0);

$left_tab->AddRow(create_hat(
			S_EVENT_DETAILS,
			make_event_details($_REQUEST['triggerid'],$trigger_data),//null,
			null,
			'hat_eventdetails',
			get_profile('web.tr_events.hats.hat_eventdetails.state',1)
		));
		
$right_tab = new CTable();
$right_tab->SetCellPadding(3);
$right_tab->SetCellSpacing(3);

$right_tab->AddOption('border',0);

$right_tab->AddRow(create_hat(
			S_EVENTS.SPACE.S_LIST,
			$table_eventlist,//null,
			null,
			'hat_eventlist',
			get_profile('web.tr_events.hats.hat_eventlist.state',1)
		));


$td_l = new CCol($left_tab);
$td_l->AddOption('valign','top');

$td_r = new CCol($right_tab);
$td_r->AddOption('valign','top');

$outer_table = new CTable();
$outer_table->AddOption('border',0);
$outer_table->SetCellPadding(1);
$outer_table->SetCellSpacing(1);
$outer_table->AddRow(array($td_l,$td_r));

$outer_table->Show();

show_thin_table_header(SPACE,$navigation);
?>
<?php

include_once "include/page_footer.php";

?>
