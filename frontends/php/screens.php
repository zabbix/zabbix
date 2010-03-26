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
	require_once('include/config.inc.php');
	require_once('include/graphs.inc.php');
	require_once('include/screens.inc.php');
	require_once('include/blocks.inc.php');

	$page['title'] = 'S_CUSTOM_SCREENS';
	$page['file'] = 'screens.php';
	$page['hist_arg'] = array('elementid');
	$page['scripts'] = array('scriptaculous.js?load=effects,dragdrop','class.calendar.js','gtlc.js');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

	if(PAGE_TYPE_HTML == $page['type']){
		define('ZBX_PAGE_DO_REFRESH', 1);
	}

	include_once('include/page_header.php');

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),
		'hostid'=>		array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),

// STATUS OF TRIGGER
		'tr_groupid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),
		'tr_hostid'=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID,		null),

		'elementid'=>	array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,NULL),
		'step'=>		array(T_ZBX_INT, O_OPT,  P_SYS,		BETWEEN(0,65535),NULL),

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
?>
<?php
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			CProfile::update('web.screens.hats.'.$_REQUEST['favid'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}

		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.screens.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		if('timeline' == $_REQUEST['favobj']){
			if(isset($_REQUEST['elementid']) && isset($_REQUEST['period'])){
				navigation_bar_calc('web.screens', $_REQUEST['elementid'],true);
			}
		}

		if(str_in_array($_REQUEST['favobj'],array('screenid','slideshowid'))){
			$result = false;
			if('add' == $_REQUEST['action']){
				$result = add2favorites('web.favorite.screenids',$_REQUEST['favid'],$_REQUEST['favobj']);
				if($result){
					print('$("addrm_fav").title = "'.S_REMOVE_FROM.' '.S_FAVOURITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){rm4favorites("'.$_REQUEST['favobj'].'","'.$_REQUEST['favid'].'",0);}'."\n");
				}
			}
			else if('remove' == $_REQUEST['action']){
				$result = rm4favorites('web.favorite.screenids',$_REQUEST['favid'],$_REQUEST['favobj']);

				if($result){
					print('$("addrm_fav").title = "'.S_ADD_TO.' '.S_FAVOURITES.'";'."\n");
					print('$("addrm_fav").onclick = function(){ add2favorites("'.$_REQUEST['favobj'].'","'.$_REQUEST['favid'].'");}'."\n");
				}
			}

			if((PAGE_TYPE_JS == $page['type']) && $result){
				print('switchElementsClass("addrm_fav","iconminus","iconplus");');
			}
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
?>
<?php
	$elementid = $_REQUEST['elementid'] = get_request('elementid', CProfile::get('web.screens.elementid', null));

	if(2 != $_REQUEST['fullscreen'])
		CProfile::update('web.screens.elementid',$_REQUEST['elementid'], PROFILE_TYPE_ID);

?>
<?php

	$screens_wdgt = new CWidget();
	
	$scroll_div = new CDiv();
	$scroll_div->setAttribute('id','scrollbar_cntr');
	$screens_wdgt->addFlicker($scroll_div, CProfile::get('web.screens.filter.state',1));
	
	$formHeader = new CForm();
	$cmbConfig = new CComboBox('config', 'screens.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConfig->addItem('screens.php', S_SCREENS);
		$cmbConfig->addItem('slides.php', S_SLIDESHOWS);
	$formHeader->addItem($cmbConfig);
	
	
	$screens = CScreen::get(array(
		'nodeids' => get_current_nodeid(),
		'extendoutput' => 1
	));
	$screens = zbx_toHash($screens, 'screenid');
	order_result($screens, 'name');
	
	if(empty($screens)){
		$screens_wdgt->addPageHeader(S_SCREENS_BIG, $formHeader);
		$screens_wdgt->addItem(BR());
		$screens_wdgt->addItem(new CTableInfo(S_NO_SCREENS_DEFINED));
		$screens_wdgt->show();	
	}
	else{
		if(!isset($screens[$elementid])){
			$screen = reset($screens);
			$elementid = $screen['screenid'];	
		}
		
		$effectiveperiod = navigation_bar_calc('web.screens', $_REQUEST['elementid'], true);
		
		$element_name = $screens[$elementid]['name'];
		
// PAGE HEADER {{{
		if(infavorites('web.favorite.screenids',$elementid,'screenid')){
			$icon = new CDiv(SPACE,'iconminus');
			$icon->setAttribute('title',S_REMOVE_FROM.' '.S_FAVOURITES);
			$icon->addAction('onclick', new CJSscript("javascript: rm4favorites('screenid','".$elementid."',0);"));
		}
		else{
			$icon = new CDiv(SPACE,'iconplus');
			$icon->setAttribute('title',S_ADD_TO.' '.S_FAVOURITES);
			$icon->addAction('onclick', new CJSscript("javascript: add2favorites('screenid','".$elementid."');"));
		}
		$icon->setAttribute('id','addrm_fav');

		$url = '?elementid='.$elementid.($_REQUEST['fullscreen']?'':'&fullscreen=1');
		$url.=url_param('groupid').url_param('hostid');

		$fs_icon = new CDiv(SPACE,'fullscreen');
		$fs_icon->setAttribute('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
		$fs_icon->addAction('onclick',new CJSscript("javascript: document.location = '".$url."';"));
		
		$screens_wdgt->addPageHeader(S_SCREENS_BIG, array($formHeader, SPACE, $icon, $fs_icon));
		$screens_wdgt->addItem(BR());
// }}} PAGE HEADER		
	
	
// HEADER {{{
		$form = new CForm(null, 'get');
		$form->addVar('fullscreen', $_REQUEST['fullscreen']);
		
		$cmbElements = new CComboBox('elementid', $elementid, 'submit()');
		foreach($screens as $snum => $screen){
			$cmbElements->addItem($screen['screenid'], get_node_name_by_elid($screen['screenid'], null, ': ').$screen['name']);
		}
		$form->addItem(array(S_SCREENS.SPACE, $cmbElements));
		
		$screens_wdgt->addHeader($element_name, $form);
// }}} HEADER		

		if((2 != $_REQUEST['fullscreen']) && check_dynamic_items($elementid, 0)){
			if(!isset($_REQUEST['hostid'])){
				$_REQUEST['groupid'] = $_REQUEST['hostid'] = 0;
			}

			$options = array('allow_all_hosts', 'monitored_hosts', 'with_items');
			if(!$ZBX_WITH_ALL_NODES) array_push($options, 'only_current_node');

			$params = array();
			foreach($options as $option) $params[$option] = 1;
			$PAGE_GROUPS = get_viewed_groups(PERM_READ_ONLY, $params);
			$PAGE_HOSTS = get_viewed_hosts(PERM_READ_ONLY, $PAGE_GROUPS['selected'], $params);
//SDI($_REQUEST['groupid'].' : '.$_REQUEST['hostid']);
			validate_group_with_host($PAGE_GROUPS,$PAGE_HOSTS);

			$cmbGroups = new CComboBox('groupid', $PAGE_GROUPS['selected'], 'javascript: submit();');
			foreach($PAGE_GROUPS['groups'] as $groupid => $name){
				$cmbGroups->addItem($groupid, get_node_name_by_elid($groupid, null, ': ').$name);
			}
			$form->addItem(array(SPACE.S_GROUP.SPACE, $cmbGroups));


			$PAGE_HOSTS['hosts']['0'] = S_DEFAULT;
			$cmbHosts = new CComboBox('hostid', $PAGE_HOSTS['selected'], 'javascript: submit();');
			foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
				$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid, null, ': ').$name);
			}
			$form->addItem(array(SPACE.S_HOST.SPACE, $cmbHosts));
		}

		$element = get_screen($elementid, 0, $effectiveperiod);

		if(2 != $_REQUEST['fullscreen']){
			$timeline = array(
				'period' => $effectiveperiod,
				'starttime' => time() - ZBX_MAX_PERIOD
			);

			if(isset($_REQUEST['stime'])){
				$bstime = $_REQUEST['stime'];
				$timeline['usertime'] = mktime(substr($bstime,8,2),substr($bstime,10,2),0,substr($bstime,4,2),substr($bstime,6,2),substr($bstime,0,4));
				$timeline['usertime'] += $timeline['period'];
			}

			$dom_graph_id = 'screen_scroll';
			$objData = array(
				'id' => $elementid,
				'domid' => $dom_graph_id,
				'loadSBox' => 0,
				'loadImage' => 0,
				'loadScroll' => 1,
				'scrollWidthByImage' => 0,
				'dynamic' => 0,
				'mainObject' => 1
			);

			zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
			zbx_add_post_js('timeControl.processObjects();');
		}		

		$screens_wdgt->addItem($element);
		$screens_wdgt->show();

		$jsmenu = new CPUMenu(null,170);
		$jsmenu->InsertJavaScript();
		echo SBR;
	}

	
include_once('include/page_footer.php');
?>