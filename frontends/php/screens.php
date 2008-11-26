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
	require_once 'include/config.inc.php';
	require_once 'include/graphs.inc.php';
	require_once 'include/screens.inc.php';
	require_once 'include/blocks.inc.php';
	require_once 'include/nodes.inc.php';


	$page['title'] = "S_CUSTOM_SCREENS";
	$page['file'] = 'screens.php';
	$page['hist_arg'] = array('config','elementid');
	$page['scripts'] = array('gmenu.js','scrollbar.js','sbox.js','sbinit.js'); //do not change order!!!

	$_REQUEST['config'] = get_request('config',get_profile('web.screens.config',0));

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);
	
	if((1 != $_REQUEST['config']) && (PAGE_TYPE_HTML == $page['type'])){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}
	
include_once 'include/page_header.php';

?>

<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'config'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),	null), // 0 - screens, 1 - slides

		'groupid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),
		'hostid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),
		
// STATUS OF TRIGGER
		'tr_groupid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
		'tr_hostid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),


		'elementid'=>	array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,NULL),
		'step'=>		array(T_ZBX_INT, O_OPT,  P_SYS,		BETWEEN(0,65535),NULL),
		'from'=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	BETWEEN(0,65535*65535),NULL),
		'period'=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	null,NULL),
		'stime'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	NULL,NULL),

		'reset'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'reset'"),NULL),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,		IN('0,1,2'),		NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),

		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'add','remove'"),NULL)
	);

	check_fields($fields);

	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			update_profile('web.screens.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		if(in_array($_REQUEST['favobj'],array('screenid','slideshowid'))){
			$result = false;
			if('add' == $_REQUEST['action']){
				$result = add2favorites('web.favorite.screenids',$_REQUEST['favid'],$_REQUEST['favobj']);
				if($result){
					print('$("addrm_fav").title = "'.S_REMOVE_FROM.' '.S_FAVORITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){rm4favorites("'.$_REQUEST['favobj'].'","'.$_REQUEST['favid'].'",0);}'."\n");
				}
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.screenids',$_REQUEST['favid'],ZBX_FAVORITES_ALL,$_REQUEST['favobj']);
				
				if($result){
					print('$("addrm_fav").title = "'.S_ADD_TO.' '.S_FAVORITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){ add2favorites("'.$_REQUEST['favobj'].'","'.$_REQUEST['favid'].'");}'."\n");
				}
			}			

			if((PAGE_TYPE_JS == $page['type']) && $result){
				print('switchElementsClass("addrm_fav","iconminus","iconplus");');
			}
		}
	}	

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
?>
<?php
	$config = $_REQUEST['config'] = get_request('config', 0);

	if( 2 != $_REQUEST['fullscreen'] )
		update_profile('web.screens.config', $_REQUEST['config'],PROFILE_TYPE_INT);

	$_REQUEST['elementid'] = get_request('elementid',get_profile('web.screens.elementid', null));

	if( 2 != $_REQUEST['fullscreen'] )
		update_profile('web.screens.elementid',$_REQUEST['elementid']);

	$_REQUEST['period'] = get_request('period',get_profile('web.screens.period', ZBX_PERIOD_DEFAULT, null, $_REQUEST['elementid']));
	if($_REQUEST['period'] >= ZBX_MIN_PERIOD){
		update_profile('web.screens.period',$_REQUEST['period'], PROFILE_TYPE_INT, $_REQUEST['elementid']);
	}
?>
<?php

	$elementid = get_request('elementid', null);
	if($elementid <= 0) $elementid = null;

	$p_elements = array();
	
	$text = null;
	
	$form = new CForm();
	$form->SetMethod('get');
	
	$form->AddVar('fullscreen',$_REQUEST['fullscreen']);
	if(isset($_REQUEST['period']))	$form->AddVar('period', $_REQUEST['period']);
	if(isset($_REQUEST['stime']))	$form->AddVar('stime', $_REQUEST['stime']);

	$cmbConfig = new CComboBox('config', $config, 'submit()');
	$cmbConfig->AddItem(0, S_SCREENS);
	$cmbConfig->AddItem(1, S_SLIDESHOWS);

	$form->AddItem(array(S_SHOW.SPACE,$cmbConfig));

	$cmbElements = new CComboBox('elementid',$elementid,'submit()');
	unset($screen_correct);
	unset($first_screen);

	if( 0 == $config ){
		$result = DBselect('SELECT screenid as elementid, name '.
				' FROM screens '.
				' WHERE '.DBin_node('screenid').
				' ORDER BY name'
				);
		while($row=DBfetch($result)){
			if(!screen_accessible($row['elementid'], PERM_READ_ONLY))
				continue;

			$cmbElements->AddItem(
					$row['elementid'],
					get_node_name_by_elid($row['elementid']).$row['name']
					);
			if((bccomp($elementid , $row['elementid']) == 0)) $element_correct = 1;
			if(!isset($first_element)) $first_element = $row['elementid'];
		}
	}
	else{
		$result = DBselect('select slideshowid as elementid,name '.
				' from slideshows '.
				' where '.DBin_node('slideshowid').
				' order by name'
				);
		while($row=DBfetch($result)){
			if(!slideshow_accessible($row['elementid'], PERM_READ_ONLY))
				continue;

			$cmbElements->AddItem(
					$row['elementid'],
					get_node_name_by_elid($row['elementid']).$row['name']
					);
			if((bccomp($elementid , $row['elementid']) == 0)) $element_correct = 1;
			if(!isset($first_element)) $first_element = $row['elementid'];
		}
	}

	if(!isset($element_correct) && isset($first_element)){
		$elementid = $first_element;
	}

	if(isset($elementid)){
		if(0 == $config){
			if(!screen_accessible($elementid, PERM_READ_ONLY)) access_deny();
			$element = get_screen_by_screenid($elementid);
		}
		else{
			if(!slideshow_accessible($elementid, PERM_READ_ONLY)) access_deny();
			$element = get_slideshow_by_slideshowid($elementid);
		}
		
		if($element ){
			$text = $element['name'];
		}
	}

	if(0 == $config){
		if($cmbElements->ItemsCount() > 0) $form->AddItem(array(SPACE.S_SCREENS.SPACE,$cmbElements));
	}
	else{
		if($cmbElements->ItemsCount() > 0) $form->AddItem(array(SPACE.S_SLIDESHOW.SPACE,$cmbElements));
	}
	
	
	if((2 != $_REQUEST['fullscreen']) && (0 == $config) && !empty($elementid) && check_dynamic_items($elementid)){
		if(!isset($_REQUEST['hostid'])){
			$_REQUEST['groupid'] = $_REQUEST['hostid'] = 0;
		}
		
		$options = array('allow_all_hosts','monitored_hosts','with_items');//, 'always_select_first_host');
		if(!$ZBX_WITH_SUBNODES)	array_push($options,'only_current_node');
		
		validate_group_with_host(PERM_READ_ONLY,$options);
		
		$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST);
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_LIST);
		
		$cmbGroup = new CComboBox('groupid',$_REQUEST['groupid'],'submit()');
		$cmbHosts = new CComboBox('hostid',$_REQUEST['hostid'],'submit()');
	
		$cmbGroup->AddItem(0,S_ALL_SMALL);
		$cmbHosts->AddItem(0,S_DEFAULT);
						
		$sql = 'SELECT DISTINCT g.groupid,g.name '.
			' FROM groups g, hosts_groups hg, hosts h '.
			' WHERE '.DBcondition('g.groupid',$available_groups).
				' AND hg.groupid=g.groupid '.
				' AND h.hostid=hg.hostid '.
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND EXISTS(SELECT i.itemid FROM items i WHERE i.status='.ITEM_STATUS_ACTIVE.' AND i.hostid=h.hostid ) '.
			' ORDER BY g.name';	

		$result=DBselect($sql);
		while($row=DBfetch($result)){
			$cmbGroup->AddItem(
					$row['groupid'],
					get_node_name_by_elid($row['groupid']).$row['name']
					);
		}
		
		$form->AddItem(array(SPACE.S_GROUP.SPACE,$cmbGroup));
		
		$sql_from = '';
		$sql_where = '';
		if($_REQUEST['groupid'] > 0){
			$sql_from .= ',hosts_groups hg ';
			$sql_where.= ' AND hg.hostid=h.hostid AND hg.groupid='.$_REQUEST['groupid'];
		}
		$sql='SELECT DISTINCT h.hostid,h.host '.
			' FROM hosts h '.$sql_from.
			' WHERE '.DBcondition('h.hostid',$available_hosts).
				$sql_where.
				' AND h.status='.HOST_STATUS_MONITORED.
				' AND EXISTS(SELECT i.itemid FROM items i WHERE i.status='.ITEM_STATUS_ACTIVE.' AND i.hostid=h.hostid ) '.
			' ORDER BY h.host';

		$result=DBselect($sql);
		while($row=DBfetch($result)){
			$cmbHosts->AddItem(
					$row['hostid'],
					get_node_name_by_elid($row['hostid']).$row['host']
					);
		}
	
		$form->AddItem(array(SPACE.S_HOST.SPACE,$cmbHosts));	
		$p_elements[] = get_table_header($text,$form);
	}
	else if(2 != $_REQUEST['fullscreen']){
		$p_elements[] = get_table_header($text,$form);
	}
?>
<?php
	if(isset($elementid)){
		$effectiveperiod = navigation_bar_calc();
		
		if( 0 == $config ){
			$element = get_screen($elementid, 0, $effectiveperiod);
		}
		else{
			$element = get_slideshow($elementid, get_request('step', null), $effectiveperiod);
			zbx_add_post_js('if(typeof(parent) != "undefined") parent.resizeiframe("iframe");
							else resizeiframe("iframe");'."\n");
		}

		if($element){
			$p_elements[] = $element;
		}
		
		$_REQUEST['elementid'] = $elementid;

		if( 2 != $_REQUEST['fullscreen'] ){
		
			$stime = time() - (31536000); // ~1year
			$bstime = time()-$effectiveperiod;
			
			if(isset($_REQUEST['stime'])){
				$bstime = $_REQUEST['stime'];
				$bstime = mktime(substr($bstime,8,2),substr($bstime,10,2),0,substr($bstime,4,2),substr($bstime,6,2),substr($bstime,0,4));
			}
			
 			$script = 	'scrollinit(0,'.$effectiveperiod.','.$stime.',0,'.$bstime.');
						 showgraphmenu("iframe");';
							
			zbx_add_post_js($script); 
			$img = new CImg('images/general/tree/zero.gif','space','20','20');
			
			$p_elements[] = $img;
			$p_elements[] = BR();
//			navigation_bar('screens.php',array('config','elementid'));
		}
	}
	else{
		$p_elements[] = new CTableInfo((0 == $config)?S_NO_SCREENS_DEFINED:S_NO_SLIDESHOWS_DEFINED);
	}
	
	$icon = null;
	$fs_icon = null;
	if(isset($elementid) && $element ){
		if(infavorites('web.favorite.screenids',$elementid,(0 == $config)?'screenid':'slideshowid')){
			$icon = new CDiv(SPACE,'iconminus');
			$icon->AddOption('title',S_REMOVE_FROM.' '.S_FAVORITES);
			$icon->AddAction('onclick',new CScript("javascript: rm4favorites('".((0 == $config)?'screenid':'slideshowid')."','".$elementid."',0);"));
		}
		else{
			$icon = new CDiv(SPACE,'iconplus');
			$icon->AddOption('title',S_ADD_TO.' '.S_FAVORITES);
			$icon->AddAction('onclick',new CScript("javascript: add2favorites('".((0 == $config)?'screenid':'slideshowid')."','".$elementid."');"));
		}
		$icon->AddOption('id','addrm_fav');
		
		$url = '?elementid='.$elementid.($_REQUEST['fullscreen']?'':'&fullscreen=1');
		$url.=url_param('groupid').url_param('hostid');
		
		$fs_icon = new CDiv(SPACE,'fullscreen');
		$fs_icon->AddOption('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
		$fs_icon->AddAction('onclick',new CScript("javascript: document.location = '".$url."';"));	
	}
	
	if( 2 == $_REQUEST['fullscreen']){
		echo unpack_object($p_elements);
	}
	else{
		$screens_hat = create_hat(
				(0 == $config)?S_SCREENS_BIG:S_SLIDESHOWS_BIG,
				$p_elements,
				array($icon,$fs_icon),
				'hat_screens',
				get_profile('web.screens.hats.hat_screens.state',1)
		);
		
		$screens_hat->Show();
	}
	
	$jsmenu = new CPUMenu(null,170);
	$jsmenu->InsertJavaScript();
?>
<?php

include_once 'include/page_footer.php';

?>
