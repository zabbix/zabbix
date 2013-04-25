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
	$page['hist_arg'] = array('elementid', 'screenname');
	$page['scripts'] = array('class.calendar.js','gtlc.js');

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

		'elementid'=>	array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,null),
		'screenname'=>	array(T_ZBX_STR, O_OPT,	P_SYS,	null,null),
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
			CProfile::update('web.screens.hats.'.$_REQUEST['favref'].'.state',$_REQUEST['state'], PROFILE_TYPE_INT);
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

		// saving fixed/dynamic setting to profile
		if('timelinefixedperiod' == $_REQUEST['favobj']){
			if(isset($_REQUEST['favid'])){
				CProfile::update('web.screens.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
			}
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
?>
<?php

	//whether we should use screen name to fetch a screen (if this is false, elementid is used)
	$use_screen_name = isset($_REQUEST['screenname']);

	//getiing element id from GET paramters
	$elementid = get_request('elementid', false);
	//if none is provided
	if($elementid === false && !$use_screen_name){
		//get element id saved in profile from the last visit
		$elementid = CProfile::get('web.screens.elementid', null);
		//this flag will be used in case this element does not exist
		$id_has_been_fetched_from_profile = true;
	}
	else{
		$id_has_been_fetched_from_profile = false;
	}

	$screens_wdgt = new CWidget();

	$scroll_div = new CDiv();
	$scroll_div->setAttribute('id', 'scrollbar_cntr');
	$screens_wdgt->addFlicker($scroll_div, CProfile::get('web.screens.filter.state',1));

	$formHeader = new CForm();
	$cmbConfig = new CComboBox('config', 'screens.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
	$cmbConfig->addItem('screens.php', S_SCREENS);
	$cmbConfig->addItem('slides.php', S_SLIDESHOWS);
	$formHeader->addItem($cmbConfig);


	$screens = CScreen::get(array(
		'nodeids' => get_current_nodeid(),
		'output' => API_OUTPUT_EXTEND
	));

	//if screen name is provided it takes priority over elementid
	if ($use_screen_name) {
		$screens = zbx_toHash($screens, 'name');
		$elementIdentifier = $_REQUEST['screenname'];
	}
	else {
		$screens = zbx_toHash($screens, 'screenid');
		$elementIdentifier = $elementid;
	}

	order_result($screens, 'name');

	//no screens defined at all
	if(empty($screens)){
		$screens_wdgt->addPageHeader(S_SCREENS_BIG, $formHeader);
		$screens_wdgt->addItem(BR());
		$screens_wdgt->addItem(new CTableInfo(S_NO_SCREENS_DEFINED));
		$screens_wdgt->show();
	}
	//if screen we are searching for does not exist and was not fetched from profile
	elseif(!isset($screens[$elementIdentifier]) && !$id_has_been_fetched_from_profile){
		$error_msg = $use_screen_name
					 ? sprintf(S_ERROR_SCREEN_WITH_NAME_DOES_NOT_EXIST, $elementIdentifier)
					 : sprintf(S_ERROR_SCREEN_WITH_ID_DOES_NOT_EXIST, $elementIdentifier);

		show_error_message($error_msg);
	}
	else{
		if(!isset($screens[$elementIdentifier])){
			//this means id was fetched from profile and this screen does not exist
			//in this case we need to show the first one
			$screen = reset($screens);
		}
		else{
			$screen = $screens[$elementIdentifier];
		}

		//if elementid is used to fetch an element, saving it in profile
		if(2 != $_REQUEST['fullscreen'] && !$use_screen_name){
			CProfile::update('web.screens.elementid', $screen['screenid'], PROFILE_TYPE_ID);
		}

		$effectiveperiod = navigation_bar_calc('web.screens', $screen['screenid'], true);

		$element_name = $screen['name'];

// PAGE HEADER {{{
		$icon = get_icon('favourite', array(
			'fav' => 'web.favorite.screenids',
			'elname' => 'screenid',
			'elid' => $screen['screenid'],
		));
		$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));

		$screens_wdgt->addPageHeader(S_SCREENS_BIG, array($formHeader, SPACE, $icon, $fs_icon));
		$screens_wdgt->addItem(BR());
// }}} PAGE HEADER


// HEADER {{{
		$form = new CForm(null, 'get');
		$form->addVar('fullscreen', $_REQUEST['fullscreen']);

		$cmbElements = new CComboBox('elementid', $screen['screenid'], 'submit()');
		foreach($screens as $snum => $scr){
			/**
			 * Adding htmlspecialchars function to output of the screen name, so
			 * that it would be available to use symbols like ">" in screen names
			 * @see ZBX-2844
			 * @author Konstantin Buravcov
			 */
			$displayed_screen_name = htmlspecialchars(get_node_name_by_elid($scr['screenid'], null, ': ').$scr['name']);
			$cmbElements->addItem($scr['screenid'], $displayed_screen_name);
		}
		$form->addItem(array(S_SCREENS.SPACE, $cmbElements));

		$screens_wdgt->addHeader($element_name, $form);
// }}} HEADER

		if((2 != $_REQUEST['fullscreen']) && check_dynamic_items($screen['screenid'], 0)){
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

		$element = get_screen($screen['screenid'], 0, $effectiveperiod);

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
				'mainObject' => 1,
				'periodFixed' => CProfile::get('web.screens.timelinefixed', 1)
			);

			zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
			zbx_add_post_js('timeControl.processObjects();');
		}

		$screens_wdgt->addItem($element);
		$screens_wdgt->show();

		echo SBR;
	}


include_once('include/page_footer.php');
?>
