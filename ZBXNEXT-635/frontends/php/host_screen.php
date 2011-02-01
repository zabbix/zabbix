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

	$page['title'] = 'S_HOST_SCREENS';
	$page['file'] = 'screens.php';
	$page['hist_arg'] = array('elementid');
	$page['scripts'] = array('effects.js','dragdrop.js','class.calendar.js','gtlc.js');

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

		'screenid'=>	array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,NULL),
		'step'=>		array(T_ZBX_INT, O_OPT,  P_SYS,		BETWEEN(0,65535),NULL),

		'period'=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	null,NULL),
		'stime'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	NULL,NULL),

		'reset'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'reset'"),NULL),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,		IN('0,1,2'),		NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'favid'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NULL,			NULL),

		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'add','remove','flop'"),NULL)
	);

	check_fields($fields);
?>
<?php
	if(isset($_REQUEST['favobj'])){
		if('hat' == $_REQUEST['favobj']){
			CProfile::update('web.hostscreen.hats.'.$_REQUEST['favref'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}

		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.hostscreen.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}

		if('timeline' == $_REQUEST['favobj']){
			if(isset($_REQUEST['elementid']) && isset($_REQUEST['period'])){
				navigation_bar_calc('web.hostscreen', $_REQUEST['elementid'],true);
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
	$options = array(
		'groups' => array(
			'monitored_hosts' => 1,
			'with_monitored_items' => 1,
		),
		'hosts' => array(
			'monitored_hosts' => 1,
			'with_monitored_items' => 1,
		),
		'hostid' => get_request('hostid', null),
		'groupid' => get_request('groupid', null),
	);

	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = $pageFilter->hostid;

	$screenid = get_request('screenid', CProfile::get('web.hostscreen.screenid', null));

	if(2 != $_REQUEST['fullscreen'])
		CProfile::update('web.hostscreen.screenid',$screenid, PROFILE_TYPE_ID);


	$screens_wdgt = new CWidget();

	$scroll_div = new CDiv();
	$scroll_div->setAttribute('id','scrollbar_cntr');
	$screens_wdgt->addFlicker($scroll_div, CProfile::get('web.hostscreen.filter.state',1));

	$formHeader = new CForm();
	$cmbConfig = new CComboBox('config', 'screens.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConfig->addItem('screens.php', S_SCREENS);
		$cmbConfig->addItem('slides.php', S_SLIDESHOWS);
//	$formHeader->addItem($cmbConfig);


	$screens = API::TemplateScreen()->get(array(
		'hostids' => $_REQUEST['hostid'],
		'output' => API_OUTPUT_EXTEND
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
		$screen = (!isset($screens[$screenid]))?reset($screens):$screens[$screenid];
		$tmpScreens = API::TemplateScreen()->get(array(
			'screenids' => $screen['screenid'],
			'hostids' => $_REQUEST['hostid'],
			'output' => API_OUTPUT_EXTEND,
			'select_screenitems' => API_OUTPUT_EXTEND
		));
		$screen = reset($tmpScreens);

		$effectiveperiod = navigation_bar_calc('web.screens', $screen['screenid'], true);

// PAGE HEADER {{{
		//KB: Removing favourites icon from screens (ZBX-3129)
		/*
		$icon = get_icon('favourite', array(
			'fav' => 'web.favorite.screenids',
			'elname' => 'screenid',
			'elid' => $screen['screenid'],
		));
		 */
		$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));

		$screens_wdgt->addPageHeader(S_SCREENS_BIG, array($formHeader, SPACE, /*$icon,*/ $fs_icon));
// }}} PAGE HEADER


// HEADER {{{
		$form = new CForm(null, 'get');
		$form->addVar('fullscreen', $_REQUEST['fullscreen']);
		//$form->addItem(array(S_GROUP.SPACE, $pageFilter->getGroupsCB(true)));

		$screens_wdgt->addHeader($screen['name'], $form);
// }}} HEADER

// Header Host
		if($_REQUEST['hostid'] > 0){
			$screens_wdgt->addItem(get_header_host_table($_REQUEST['hostid']));
			$show_host = false;
		}

// Host Screen List
		$screenList = new CList(null, 'objectlist');
		foreach($screens as $snum => $cbScreen){
			$displayed_screen_name = get_node_name_by_elid($cbScreen['screenid'], null, ': ').$cbScreen['name'];

			if(bccomp($cbScreen['screenid'],$screen['screenid']) == 0)
				$screenList->addItem($displayed_screen_name, 'selected');
			else
				$screenList->addItem(new CLink($displayed_screen_name, 'host_screen.php?screenid='.$cbScreen['screenid'].'&hostid='.$_REQUEST['hostid']));
		}

		$screens_wdgt->addItem(new CDiv($screenList, 'objectlist'));
//-----
		$element = get_screen($screen, 0, $effectiveperiod);

		if(2 != $_REQUEST['fullscreen']){
			$timeline = array(
				'period' => $effectiveperiod,
				'starttime' => date('YmdHis', time() - ZBX_MAX_PERIOD)
			);

			if(isset($_REQUEST['stime'])){
				$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
			}

			$dom_graph_id = 'screen_scroll';
			$objData = array(
				'id' => $screen['screenid'],
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
?>
<?php

include_once('include/page_footer.php');

?>
