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
	require_once('include/config.inc.php');
	require_once('include/perm.inc.php');

	global $USER_DETAILS;
	global $ZBX_LOCALNODEID, $ZBX_LOCMASTERID;
	global $ZBX_CURMASTERID;
	global $page;

COpt::profiling_start("page");
	
	unset($denyed_page_requested);

	if(!isset($page['type'])) $page['type'] = PAGE_TYPE_HTML;
	if(!isset($page['file'])) $page['file'] = basename($_SERVER['PHP_SELF']);
	
	if($_REQUEST['fullscreen']=get_request('fullscreen', 0)) define('ZBX_PAGE_NO_MENU', 1);

	include_once('include/locales/en_gb.inc.php');
	process_locales();
	set_zbx_locales();
	/* Init CURRENT NODE ID */

	init_nodes();

	/* set Page header */
	switch($page["type"]){
		case PAGE_TYPE_IMAGE:
			set_image_header();
			define('ZBX_PAGE_NO_MENU', 1);
			break;
		case PAGE_TYPE_XML:
			header('Content-Type: text/xml');
			header('Content-Disposition: attachment; filename="'.$page['file'].'"');
			if(!defined('ZBX_PAGE_NO_MENU')) define('ZBX_PAGE_NO_MENU', 1);
			break;
		case PAGE_TYPE_JS:
			header('Content-Type: application/javascript; charset=UTF-8');
			if(!defined('ZBX_PAGE_NO_MENU')) define('ZBX_PAGE_NO_MENU', 1);
			break;
		case PAGE_TYPE_HTML_BLOCK:
			header('Content-Type: text/plain; charset=UTF-8');
			if(!defined('ZBX_PAGE_NO_MENU')) define('ZBX_PAGE_NO_MENU', 1);
			break;
		case PAGE_TYPE_HTML:
		default:
			if(!isset($page['encoding'])) 
				header('Content-Type: text/html; charset='.S_HTML_CHARSET);
			else 
				header('Content-Type: text/html; charset='.$page['encoding']);

			if(isset($page['title']) && defined($page['title']))	$page['title'] = constant($page['title']);
			
			if(!isset($page['title'])) $page['title'] = 'ZABBIX';
			
			if(defined('ZBX_DISTRIBUTED')){
				if(isset($DB['DB']) && ($curr_node_data = DBfetch(DBselect('SELECT * FROM nodes WHERE nodeid='.get_current_nodeid(false)))))
					$page['title'] .= ' ('.$curr_node_data['name'].')';
			}
			
			if((defined('ZBX_PAGE_DO_REFRESH') || defined('ZBX_PAGE_DO_JS_REFRESH')) && $USER_DETAILS['refresh']){
				$page['title'] .= ' [refreshed every '.$USER_DETAILS['refresh'].' sec]';
			}
		break; 
	} 

	/* switch($page["type"]) */

	/* NOTE - menu array format:
		first level:
			'label' 		= main menu title.
			'default_page_id	= default page url from 'pages' then opened menu.
			'pages'			= collection of pages whitch displayed from this menu
						this pages are saved a last visited submenu of main menu.

		second level (pages):
			'url'	= 	real url for this page
			'label'	= 	submenu title, if missed menu skipped, but remmembed as last visited page.
			'sub_pages'	= collection of pages for displaying but dont remember as last visited.
	*/
	
	$ZBX_MENU = array(
		'view'=>array(
				'label'			=> S_MONITORING,
				'node_perm'		=> PERM_READ_LIST,
				'default_page_id'	=> 0,
				'pages'=>array(
					array(
							'url'=>'dashboard.php',
							'label'=>S_DASHBOARD,
						),
					array(
							'url'=>'overview.php',
							'label'=>S_OVERVIEW	
						),
					array(
							'url'=>'httpmon.php',
							'label'=>S_WEB,
							'sub_pages'=>array('httpdetails.php')
						),
					array(
							'url'=>'latest.php',
							'label'=>S_LATEST_DATA,
							'sub_pages'=>array('history.php','chart.php')
						),
					array(
							'url'=>'tr_status.php',
							'label'=>S_TRIGGERS,
							'sub_pages'=>array('acknow.php','tr_comments.php','chart4.php','scripts_exec.php')
						),
					array(
							'url'=>'events.php',
							'label'=>S_EVENTS,
							'sub_pages'=>array('tr_events.php')
						),
					array(
							'url'=>'charts.php',
							'label'=>S_GRAPHS,
							'sub_pages'=>array('chart2.php','chart3.php','chart6.php','chart7.php')
						),
					array(
							'url'=>'screens.php',
							'label'=>S_SCREENS,
							'sub_pages'=>array('slides.php')
						),
					array(
							'url'=>'maps.php',
							'label'=>S_MAPS,
							'sub_pages'=>array('map.php')
						),
					array(
							'url'=>'discovery.php',
							'label'=>S_DISCOVERY, 
							'user_type'=>USER_TYPE_ZABBIX_ADMIN),
					array(
							'url'=>'srv_status.php',
							'label'=>S_IT_SERVICES,
							'forse_disable_subnodes' => true,
							'sub_pages'=>array('report3.php','report7.php','chart_sla.php','chart5.php')
						),
					array('url'=>'vtext.php'),
					array('url'=>'chart3.php')
					)
				),
		'cm'=>array(
				'label'			=> S_INVENTORY,
				'node_perm'		=> PERM_READ_LIST,
				'default_page_id'	=> 0,
				'pages'=>array(
					array('url'=>'hostprofiles.php'	,'label'=>S_HOSTS	)
					)
				),
		'reports'=>array(
				'label'			=> S_REPORTS,
				'node_perm'		=> PERM_READ_LIST,
				'default_page_id'	=> 0,
				'pages'=>array(
					array('url'=>'report1.php',	'label'=>S_STATUS_OF_ZABBIX	),
					array('url'=>'report2.php',	'label'=>S_AVAILABILITY_REPORT	),
					array('url'=>'report5.php',	'label'=>S_TRIGGERS_TOP_100	),
					array(
							'url'=>'report6.php',	
							'label'=>S_BAR_REPORTS,
							'sub_pages'=>array('popup_period.php','popup_bitem.php','chart_bar.php')
						),
					)
				),
		'config'=>array(
				'label'			=> S_CONFIGURATION,
				'user_type'		=> USER_TYPE_ZABBIX_ADMIN,
				'node_perm'		=> PERM_READ_LIST,
				'default_page_id'	=> 0,
				'forse_disable_subnodes'=> true,
				'pages'=>array(
					array(
							'url'=>'config.php',
							'label'=>S_GENERAL,
							'sub_pages'=>array('image.php')
						),
					array(
							'url'=>'httpconf.php',
							'label'=>S_WEB,
							'sub_pages'=>array('popup_httpstep.php')
						),
					array(
							'url'=>'hosts.php',
							'label'=>S_HOSTS
						),
					array(
							'url'=>'items.php',
							'label'=>S_ITEMS,
							'sub_pages'=>array('tr_logform.php')		
						),
					array(
							'url'=>'triggers.php',
							'label'=>S_TRIGGERS,
							'sub_pages'=>array('popup_trexpr.php')
						),
					array('url'=>'actionconf.php'	,'label'=>S_ACTIONS),
					array('url'=>'graphs.php'	,'label'=>S_GRAPHS,
						'sub_pages'=>array('popup_gitem.php')
						),
					array('url'=>'screenconf.php'	,'label'=>S_SCREENS,
						'sub_pages'=>array('screenedit.php')
						),
					array('url'=>'sysmaps.php'	,'label'=>S_MAPS,
						'sub_pages'=>array('sysmap.php','popup_link_tr.php')
						),
					array('url'=>'services.php'	,'label'=>S_IT_SERVICES,
						'sub_pages'=>array('services_form.php')	
						),
					array('url'=>'discoveryconf.php','label'=>S_DISCOVERY),
					array('url'=>'exp_imp.php'	,'label'=>S_EXPORT_IMPORT),
					array('url'=>'popup.php')
					)
				),
		'admin'=>array(
				'label'			=> S_ADMINISTRATION,
				'user_type'		=> USER_TYPE_SUPER_ADMIN,
				'node_perm'		=> PERM_READ_WRITE,
				'default_page_id'	=> 1,
				'forse_disable_subnodes'=> true,
				'pages'=>array(
					ZBX_DISTRIBUTED ? array('url'=>'nodes.php'	,'label'=>S_NODES) : null ,
					array('url'=>'authentication.php'	,'label'=>S_AUTHENTICATION),
					array('url'=>'users.php'	,'label'=>S_USERS		,
						'sub_pages'=>array('popup_media.php','popup_usrgrp.php','popup_right.php','popup_users.php')
						),
					array('url'=>'media_types.php'	,'label'=>S_MEDIA_TYPES		),
					array('url'=>'scripts.php'	,'label'=>S_SCRIPTS),
					array('url'=>'audit.php'	,'label'=>S_AUDIT		),
					array('url'=>'queue.php'	,'label'=>S_QUEUE	),
					array('url'=>'report4.php'	,'label'=>S_NOTIFICATIONS	),
					array('url'=>'locales.php'	,'label'=>S_LOCALES	),
					array('url'=>'instal.php'	,'label'=>S_INSTALLATION	,
						'sub_pages'=>array('setup.php','warning.php'))
					)
				),
		'login'=>array(
				'label'			=> S_LOGIN,
				'default_page_id'	=> 0,
				'forse_disable_subnodes'=> true,
				'pages'=>array(
					array('url'=>'index.php',
						'sub_pages'=>array('profile.php')
						)
					)
				)
		);

	$main_menu_row	= array();
	$sub_menu_row	= array();

	foreach($ZBX_MENU as $label => $sub){
// Check permissions for main menu
		unset($deny);
		if(!defined('ZBX_PAGE_NO_AUTHERIZATION')){
			if(isset($sub['user_type'])){
				if($USER_DETAILS['type'] < $sub['user_type']){
					$deny = true;
				}
			}

			if(isset($sub['node_perm'])){ 
				if(0 == count(get_accessible_nodes_by_user($USER_DETAILS,$sub['node_perm']))){
					$deny = true;
				}
			}

			if($label=='login'){
				$deny = true;				
/*				if(isset($USER_DETAILS['sessionid']))
					$sub['label'] = S_LOGOUT;
				else
					unset($deny);
*/
			}
		}

// End of main menu permissions checking

		unset($menu_url);
		foreach($sub['pages'] as $id => $sub_pages){

			if(!defined('ZBX_PAGE_NO_AUTHERIZATION')){
				if(isset($sub_pages['user_type'])){
					if($USER_DETAILS['type'] < $sub_pages['user_type']){
						unset($sub['pages'][$id]);
						continue;
					}
				}

				if(isset($sub_pages['node_perm'])){
					if ( 0 == count(get_accessible_nodes_by_user($USER_DETAILS,$sub_pages['node_perm']))){
						unset($sub['pages'][$id]);
						continue;
					}
				}
			}

			if(isset($page_exist)) continue; 
				
			if($page['file'] == $sub_pages['url']){
				if(isset($sub_pages['label'])){
					$menu_url = $sub_pages['url'];
				}
				
				$page_exist = true;
				$page['menu'] = $label;
				$sub['pages'][$id]['active'] = true; /* mark as active */
			}
			else if(isset($sub_pages['sub_pages'])){
				if(str_in_array($page['file'], $sub_pages['sub_pages'])){
					if(isset($sub_pages['label'])){
						$menu_url = $sub_pages['url'];
					}
					$page_exist = true;
					$page['menu'] = $label;
					$sub['pages'][$id]['active'] = true; /* mark as active */
				}					
			}

			if(isset($page_exist) &&
				(isset($sub['forse_disable_subnodes']) || isset($sub_pages['forse_disable_subnodes'])) && 
				!defined('ZBX_DISABLE_SUBNODES'))
			{
					define('ZBX_DISABLE_SUBNODES', 1);
			}
		}

		if(isset($menu_url)){ /* active menu */
			$class = 'active';

			update_profile('web.menu.'.$label.'.last', $menu_url, PROFILE_TYPE_STR);

			if(isset($deny)){
				$denyed_page_requested = true;
				continue;
			}

			foreach($sub['pages'] as $sub_pages){
				if(!isset($sub_pages['label'])) continue;

				$label = new CLink($sub_pages['label'], $sub_pages['url'],'highlight');
				if(isset($sub_pages['active'])) $label = new CSpan($label, 'active');
				
				array_push($sub_menu_row, 
					$label,
					new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider')
					);
			}
		}
		else{
			if(isset($deny)) continue;

			$class = 'horizontal_menu_n';

			$menu_url = get_profile('web.menu.'.$label.'.last',false);

			if(!$menu_url)
				$menu_url = $sub['pages'][$sub['default_page_id']]['url'];
		}

		array_push($main_menu_row, new CCol(new CLink($sub['label'], $menu_url, 'highlight'),$class));
		unset($menu_url, $class);
	}

	if((!isset($page_exist) && $page['type']!=PAGE_TYPE_XML)){
		$denyed_page_requested = true;
	}

	zbx_flush_post_cookies(isset($denyed_page_requested));

	if($page['type'] == PAGE_TYPE_HTML)
	{
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title><?php echo $page['title']; ?></title>
<?php 
	if(defined('ZBX_PAGE_DO_REFRESH') && $USER_DETAILS["refresh"]) { 
		echo '<meta http-equiv="refresh" content="'.$USER_DETAILS["refresh"].'" />';
	}
?>
 
<meta name="Author" content="ZABBIX SIA" />
<link rel="stylesheet" type="text/css" href="css.css" />

<?php
	if(isset($DB['DB']) && !is_null($DB['DB'])){
		$css = false;
		$config=select_config();		
		if(isset($config['default_theme']) && file_exists('styles/'.$config['default_theme'])){
			$css = $config['default_theme'];
		}

		if(isset($USER_DETAILS['theme']) && ($USER_DETAILS['theme']!=ZBX_DEFAULT_CSS) && ($USER_DETAILS['alias']!=ZBX_GUEST_USER)){
			if(file_exists('styles/'.$USER_DETAILS['theme'])){
				$css = $USER_DETAILS['theme'];
			}
		}
		
		if($css){
			echo '<link rel="stylesheet" type="text/css" href="styles/'.$css.'" />'."\n";
			$ico = ereg_replace('.*(\_[a-z]+).*',"\\1",$css);
			if(file_exists('images/general/zabbix'.$ico.'.ico'))
				echo '<link rel="shortcut icon" href="images/general/zabbix'.$ico.'.ico" />';
			else
				echo '<link rel="shortcut icon" href="images/general/zabbix.ico" />';
		}
		else{
			echo '<link rel="shortcut icon" href="images/general/zabbix.ico" />';
		}
	}
?>

<script type="text/javascript" src="js/prototype.js"></script>
<script type="text/javascript" src="js/common.js"></script>
<script type="text/javascript" src="js/gpc.js"></script>
<script type="text/javascript" src="js/ajax_req.js"></script>
<script type="text/javascript" src="js/chkbxrange.js"></script>
<?php
	if(isset($page['scripts']) && is_array($page['scripts'])){
		foreach($page['scripts'] as $id => $script){
			print('    <script type="text/javascript" src="js/'.$script.'"></script>'."\n");
		}
	}
?>
</head>
<body onload="zbxCallPostScripts();">
<?php
	}

	define ('PAGE_HEADER_LOADED', 1);

	if(isset($_REQUEST['print'])){
		define('ZBX_PAGE_NO_MENU', 1);
		$req = substr($_SERVER['REQUEST_URI'],0,strpos($_SERVER['REQUEST_URI'],'print')-1);
		
		$link = new CLink(bold('&laquo;'.S_BACK_BIG), $req, 'small_font');
		$link->addOption('style','padding-left: 10px;');
		
		$printview = new CDiv($link,'printless');
		$printview->addOption('style','border: 1px #333 dotted;');
		$printview->Show();
		
	}

	if(!defined('ZBX_PAGE_NO_MENU')){
COpt::compare_files_with_menu($ZBX_MENU);

		$help = new CLink(S_HELP, 'http://www.zabbix.com/documentation.php', 'small_font');
		$help->setTarget('_blank');
		$support = new CLink(S_GET_SUPPORT, 'http://www.zabbix.com/support.php', 'small_font');
		$support->setTarget('_blank');
		$printview = new CLink(S_PRINT, $_SERVER['REQUEST_URI'].(empty($_GET)?'?':'&').'print=1', 'small_font');
		
		$page_header_r_col = array($help,'|',$support,'|',$printview);

		if($USER_DETAILS['alias']!=ZBX_GUEST_USER){
			$page_header_r_col[] = array('|', 
						new CLink(S_PROFILE, 'profile.php', 'small_font'),'|', 
						new CLink(S_LOGOUT, 'index.php?reconnect=1', 'small_font'));
		} 
		else {
			$page_header_r_col[] = array('|', new CLink(S_LOGIN, 'index.php?reconnect=1', 'small_font'));
		}

		$logo = new CLink(new CDiv(SPACE,'zabbix_logo'),'http://www.zabbix.com/');
		$logo->setTarget('_blank');
//		$logo = new CDiv(SPACE,'zabbix_logo');
//		$logo->AddAction('onclick',"javascript: document.location = 'http://www.zabbix.com';");		
		
		$td_r = new CCol($page_header_r_col, 'page_header_r');
		$td_r->addOption('width','100%');

		$top_page_row	= array(new CCol($logo, 'page_header_l'), $td_r);
		unset($logo, $page_header_r_col, $help, $support);

		$table = new CTable(NULL,'page_header');
		$table->setCellSpacing(0);
		$table->setCellPadding(5);
		$table->addRow($top_page_row);
		$table->Show();

		$menu_table = new CTable(NULL,'menu');
		$menu_table->setCellSpacing(0);
		$menu_table->setCellPadding(5);
		$menu_table->addRow($main_menu_row);

		$node_form = null;

		if(ZBX_DISTRIBUTED){
			$lst_nodes = new CComboBox('switch_node', get_current_nodeid(false), 'submit()');
			$available_nodes = get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_LIST);

			$db_nodes = DBselect('SELECT * '.
						' FROM nodes '.
						' WHERE '.DBcondition('nodeid',$available_nodes).
						' ORDER BY name ');
			while($node_data = DBfetch($db_nodes)){
				$lst_nodes->addItem($node_data['nodeid'],$node_data['name']);
			}

			if($lst_nodes->ItemsCount() > 0){
				$node_form = new CForm();
				$node_form->addItem(array(new CSpan(S_CURRENT_NODE,'textcolorstyles'),$lst_nodes));
				unset($lst_nodes);

				if(!defined('ZBX_DISABLE_SUBNODES')){
					global $ZBX_WITH_SUBNODES;

					$cmd_show_subnodes = new CComboBox('show_subnodes', !empty($ZBX_WITH_SUBNODES) ? 1 : 0, 'submit()');
					$cmd_show_subnodes->addItem(0, S_CURRENT_NODE_ONLY);
					$cmd_show_subnodes->addItem(1, S_WITH_SUBNODES);

					$node_form->addItem(array(SPACE, new CSpan(S_SHOW,'textcolorstyles'), $cmd_show_subnodes));
				}

// No need for the button anymore
//				$node_form->AddItem(new CButton('submit',S_SWITCH_NODE));
			}
		}

		$table = new CTable();
		$table->setCellSpacing(0);
		$table->setCellPadding(0);
		$table->addOption('style','width: 100%;');

		$r_col = new CCol($node_form);
		$r_col->AddOption('align','right');
//		$r_col->AddOption('style','text-align: right;');
		
		$table->addRow(array($menu_table,$r_col));
		$table->Show();
		
		$sub_menu_table = new CTable(NULL,'sub_menu');
		$sub_menu_table->setCellSpacing(0);
		$sub_menu_table->setCellPadding(5);
		
		if(empty($sub_menu_row)) $sub_menu_row = '&nbsp;';
		$sub_menu_table->addRow(new CCol($sub_menu_row));
		$sub_menu_table->Show();
	}
//------------------------------------- <HISTORY> ---------------------------------------
	if(isset($page['hist_arg']) && ($USER_DETAILS['alias'] != ZBX_GUEST_USER) && ($page['type'] == PAGE_TYPE_HTML) && !defined('ZBX_PAGE_NO_MENU')){
		$table = new CTable();
		$table->setClass('history');
		
		$table->setCellSpacing(0);
		$table->setCellPadding(0);

		$history = get_user_history();

		$tr = new CRow(new CCol('History:','caption'));
		$tr->addItem($history);
		
		$table->addRow($tr);
		$table->Show();
	} 
	else if(($page['type'] == PAGE_TYPE_HTML) && !defined('ZBX_PAGE_NO_MENU')) {
		echo SBR;
	}
//------------------------------------ </HISTORY> ---------------------------------------

	unset($ZBX_MENU);
		
	unset($table, $top_page_row, $menu_table, $node_form);
	unset($main_menu_row);
	unset($db_nodes, $node_data);
	unset($sub_menu_table, $sub_menu_row);

	if(isset($denyed_page_requested)){
		access_deny();
	}

	if(version_compare(phpversion(), '5.1.0RC1', '>=') && $page['type'] == PAGE_TYPE_HTML){
		$tmezone = ini_get('date.timezone');
		if(empty($tmezone)) {
			info('Timezone for PHP is not set. Please set "date.timezone" option in php.ini.');
			date_default_timezone_set('UTC');
		}
		unset($tmezone);
	}
	
	show_messages();
?>
