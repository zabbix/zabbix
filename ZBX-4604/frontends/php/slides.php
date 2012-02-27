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

$page['title'] = 'S_CUSTOM_SLIDES';
$page['file'] = 'slides.php';
$page['hist_arg'] = array('elementid');
$page['scripts'] = array('class.pmaster.js', 'class.calendar.js', 'gtlc.js');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

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
		'elementid'=>	array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID, NULL),
		'step'=>		array(T_ZBX_INT, O_OPT,  P_SYS,		BETWEEN(0,65535),NULL),
		'period'=>		array(T_ZBX_INT, O_OPT,  P_SYS, 	null,NULL),
		'stime'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	NULL,NULL),
		'reset'=>		array(T_ZBX_STR, O_OPT,  P_SYS, 	IN("'reset'"),NULL),
		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,		IN('0,1,2'),		NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		NULL),
		'favid'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NULL,			NULL),
		'favcnt'=>		array(T_ZBX_STR, O_OPT,	null,	null,			null),
		'pmasterid'=>	array(T_ZBX_STR, O_OPT,	P_SYS,	null,			NULL),

		'action'=>		array(T_ZBX_STR, O_OPT, P_ACT, 	IN("'add','remove','refresh','flop'"),	NULL),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({action}) && ("flop"=={action})'),
		'upd_counter'=> array(T_ZBX_INT, O_OPT, P_ACT,  null,		null),
	);

	check_fields($fields);

	$tmpstime = get_request('stime');

	if(isset($_REQUEST['favobj'])){
		$_REQUEST['pmasterid'] = get_request('pmasterid','mainpage');

		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.slides.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
		if('timeline' == $_REQUEST['favobj']){
			if(isset($_REQUEST['elementid']) && isset($_REQUEST['period'])){
				navigation_bar_calc('web.slides', $_REQUEST['elementid'],true);
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

		if('hat' == $_REQUEST['favobj']){
			switch($_REQUEST['favref']){
				case 'hat_slides':
					$elementid = get_request('elementid');

					if(!is_null($elementid)){
						$effectiveperiod = navigation_bar_calc();
						$step = get_request('upd_counter');

						$slideshow = get_slideshow_by_slideshowid($elementid);
						$screen = get_slideshow($elementid, $step);

						$element = get_screen($screen['screenid'],2,$effectiveperiod);

						$refresh_multipl = CProfile::get('web.slides.rf_rate.hat_slides', 1, $elementid);

						if($screen['delay'] > 0) $refresh = $screen['delay'];
						else $refresh = $slideshow['delay'];

						$element->show();

						$script = get_update_doll_script('mainpage', $_REQUEST['favref'], 'frequency', $refresh*$refresh_multipl)."\n";
						$script.= get_update_doll_script('mainpage', $_REQUEST['favref'], 'restartDoll')."\n";
						$script.= 'timeControl.processObjects();';
						insert_js($script);
					}
					else{
						print(SBR.S_NO_SLIDESHOWS_DEFINED);
					}

				break;
			}
		}

		if('set_rf_rate' == $_REQUEST['favobj']){
			if(str_in_array($_REQUEST['favref'],array('hat_slides'))){
				$elementid = $_REQUEST['elementid'];

				CProfile::update('web.slides.rf_rate.hat_slides', $_REQUEST['favcnt'], PROFILE_TYPE_STR, $elementid);

				// $script= get_update_doll_script('mainpage', $_REQUEST['favref'], 'frequency', $_REQUEST['favcnt'])*$refresh."\n";
				// $script.= get_update_doll_script('mainpage', $_REQUEST['favref'], 'stopDoll')."\n";
				// $script.= get_update_doll_script('mainpage', $_REQUEST['favref'], 'startDoll')."\n";

				$menu = array();
				$submenu = array();

				make_refresh_menu('mainpage', $_REQUEST['favref'],$_REQUEST['favcnt'],array('elementid'=> $elementid),$menu,$submenu,2);
				$script = 'page_menu["menu_'.$_REQUEST['favref'].'"] = '.zbx_jsvalue($menu['menu_'.$_REQUEST['favref']]).';'."\n";
				echo $script;
			}
		}

		// saving fixed/dynamic setting to profile
		if('timelinefixedperiod' == $_REQUEST['favobj']){
			if(isset($_REQUEST['favid'])){
				CProfile::update('web.slides.timelinefixed', $_REQUEST['favid'], PROFILE_TYPE_INT);
			}
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
?>
<?php
	$elementid = get_request('elementid', CProfile::get('web.slides.elementid', null));
	if(2 != $_REQUEST['fullscreen']){
		CProfile::update('web.slides.elementid', $elementid, PROFILE_TYPE_ID);
	}

	$slides_wdgt = new CWidget('hat_slides');

	$formHeader = new CForm(null, 'get');
	$cmbConfig = new CComboBox('config', 'slides.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConfig->addItem('screens.php', S_SCREENS);
		$cmbConfig->addItem('slides.php', S_SLIDESHOWS);
	$formHeader->addItem($cmbConfig);


	$slideshows = array();
	$sql = 'SELECT slideshowid, name'.
			' FROM slideshows '.
			' WHERE '.DBin_node('slideshowid');
	$result = DBselect($sql);
	while($slideshow = DBfetch($result)){
		if(slideshow_accessible($slideshow['slideshowid'], PERM_READ_ONLY)){
			$slideshows[$slideshow['slideshowid']] = $slideshow;
		}
	};
	order_result($slideshows, 'name');


	if(empty($slideshows)){
		$slides_wdgt->addPageHeader(S_SLIDESHOWS_BIG, $formHeader);
		$slides_wdgt->addItem(BR());
		$slides_wdgt->addItem(new CTableInfo(S_NO_SLIDESHOWS_DEFINED));
		$slides_wdgt->show();
	}
	else{
		if(!isset($slideshows[$elementid])){
			$slideshow = reset($slideshows);
			$elementid = $slideshow['slideshowid'];
		}

		$effectiveperiod = navigation_bar_calc('web.slides',$elementid, true);
		$screen = get_slideshow($elementid, 0);

// PAGE HEADER {{{

		if($screen){
			$icon = get_icon('favourite', array(
				'fav' => 'web.favorite.screenids',
				'elname' => 'slideshowid',
				'elid' => $elementid,
			));
		}
		else{
			$icon = new CIcon(S_FAVOURITES, 'iconplus');
		}

		$fs_icon = get_icon('fullscreen', array('fullscreen' => $_REQUEST['fullscreen']));

		$refresh_icon = new CIcon(S_MENU, 'iconmenu');
		if($screen){
			$refresh_icon->addAction('onclick', 'javascript: create_page_menu(event,"hat_slides");');
		}


		$slides_wdgt->addPageHeader(S_SLIDESHOWS_BIG, array($formHeader, SPACE, $icon, $refresh_icon, $fs_icon));
// }}} PAGE HEADER

// HEADER {{{
		$form = new CForm(null, 'get');
		$form->addVar('fullscreen', $_REQUEST['fullscreen']);

		$cmbElements = new CComboBox('elementid', $elementid, 'submit()');
		foreach($slideshows as $snum => $slideshow){
			$cmbElements->addItem($slideshow['slideshowid'], get_node_name_by_elid($slideshow['slideshowid'], null, ': ').$slideshow['name']);
		}
		$form->addItem(array(S_SLIDESHOW.SPACE, $cmbElements));

		$slides_wdgt->addHeader($slideshows[$elementid]['name'], $form);
// }}} HEADER

		if($screen){
			if((2 != $_REQUEST['fullscreen']) && check_dynamic_items($elementid, 1)){
				if(!isset($_REQUEST['hostid'])){
					$_REQUEST['groupid'] = $_REQUEST['hostid'] = 0;
				}

				$options = array('allow_all_hosts', 'monitored_hosts', 'with_items');
				if(!$ZBX_WITH_ALL_NODES)	array_push($options, 'only_current_node');

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
				$form->addItem(array(SPACE.S_GROUP.SPACE,$cmbGroups));


				$PAGE_HOSTS['hosts']['0'] = S_DEFAULT;
				$cmbHosts = new CComboBox('hostid',$PAGE_HOSTS['selected'],'javascript: submit();');
				foreach($PAGE_HOSTS['hosts'] as $hostid => $name){
					$cmbHosts->addItem($hostid, get_node_name_by_elid($hostid, null, ': ').$name);
				}
				$form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));
			}


			$element = get_slideshow_by_slideshowid($elementid);
			if($screen['delay'] > 0) $element['delay'] = $screen['delay'];

			show_messages();

// js menu arrays
			$menu = array();
			$submenu = array();
			$refresh_multipl = CProfile::get('web.slides.rf_rate.hat_slides', 1, $elementid);

// workaround for 1.8.2 upgrade, earlier value was integer type, now str
			if(empty($refresh_multipl)){
				$refresh_multipl = 1;
				CProfile::update('web.slides.rf_rate.hat_slides', $refresh_multipl, PROFILE_TYPE_STR, $elementid);
			}

			make_refresh_menu('mainpage', 'hat_slides', $refresh_multipl, array('elementid'=> $elementid), $menu, $submenu, 2);
			insert_js('var page_menu='.zbx_jsvalue($menu).";\n".'var page_submenu='.zbx_jsvalue($submenu).";\n");
// --------------

			$refresh_tab = array(array(
				'id' => 'hat_slides',
				'frequency' => $element['delay']*$refresh_multipl,
				'url' => 'slides.php?elementid='.$elementid.(is_null($tmpstime) ? '' : '&stime='.$tmpstime).url_param('period').url_param('groupid').url_param('hostid'),
				'params'=> array('lastupdate' => time())
			));
			add_doll_objects($refresh_tab);


			$effectiveperiod = navigation_bar_calc();
			if(2 != $_REQUEST['fullscreen']){
// NAV BAR
				$timeline = array();
				$timeline['period'] = $effectiveperiod;
				$timeline['starttime'] = date('YmdHis', time() - ZBX_MAX_PERIOD);

				if(isset($_REQUEST['stime'])){
					$timeline['usertime'] = date('YmdHis', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
				}

				$scroll_div = new CDiv();
				$scroll_div->setAttribute('id','scrollbar_cntr');
				$slides_wdgt->addFlicker($scroll_div, CProfile::get('web.slides.filter.state',1));
				$slides_wdgt->addFlicker(BR(), CProfile::get('web.slides.filter.state',1));


				$objData = array(
					'id' => $elementid,
					'loadSBox' => 0,
					'loadImage' => 0,
					'loadScroll' => 1,
					'scrollWidthByImage' => 0,
					'dynamic' => 0,
					'mainObject' => 1,
					'periodFixed' => CProfile::get('web.slides.timelinefixed', 1)
				);

				zbx_add_post_js('timeControl.addObject("iframe",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
				zbx_add_post_js('timeControl.processObjects();');
			}

	//		$screen = get_slideshow($elementid, 0);
	//		$element = get_screen($screen['screenid'],2,$effectiveperiod);

			$slides_wdgt->addItem(new CSpan(S_LOADING_P, 'textcolorstyles'));
		}
		else{
			$slides_wdgt->addItem(new CTableInfo(S_NO_SLIDES_DEFINED));
		}

		$slides_wdgt->show();
	}

include_once('include/page_footer.php');

?>
