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
	require_once("include/config.inc.php");
	require_once("include/perm.inc.php");


	global $USER_DETAILS;
	global $ZBX_LOCALNODEID, $ZBX_LOCMASTERID;
	global $page;

COpt::profiling_start("page");
	
	unset($denyed_page_requested);

	if(!isset($page["type"])) $page["type"] = PAGE_TYPE_HTML;
	if(!isset($page["file"])) $page["file"] = basename($_SERVER['PHP_SELF']);

	/* Authorize user */
	if(!defined('ZBX_PAGE_NO_AUTHERIZATION'))
	{
		check_authorisation();

		include_once "include/locales/".$USER_DETAILS["lang"].".inc.php";
		process_locales();
	}
	else
	{
		$USER_DETAILS = array(
			"alias" =>"guest",
			"userid"=>0,
			"lang"  =>"en_gb",
			"type"  =>"0",
			"node"  =>array(
				"name"  =>'- uncnown -',
				"nodeid"=>0));
	}

	include_once "include/locales/en_gb.inc.php";
	process_locales();

	/* Init CURRENT NODE ID */
	global $ZBX_CURNODEID, $ZBX_CURMASTERID;

	if(!defined('ZBX_PAGE_NO_AUTHERIZATION') && ZBX_DISTRIBUTED)
	{
		$ZBX_CURNODEID = get_cookie('current_nodeid', $ZBX_LOCALNODEID); // Selected node
		if(isset($_REQUEST['switch_node']))
		{
			if($node_data = DBfetch(DBselect("select * from nodes where nodeid=".$_REQUEST['switch_node'])))
			{
				$ZBX_CURNODEID = $_REQUEST['switch_node'];
			}
			unset($node_data);
		}

		if($node_data = DBfetch(DBselect("select * from nodes where nodeid=".$ZBX_CURNODEID)))
		{
			$ZBX_CURMASTERID = $node_data['masterid'];
		}
		
		if(count(get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_LIST,null,PERM_RES_IDS_ARRAY,$ZBX_CURNODEID)) <= 0)
		{
			$denyed_page_requested = true;
			$ZBX_CURNODEID = $ZBX_LOCALNODEID;
			$ZBX_CURMASTERID = $ZBX_LOCMASTERID;
		}
		
		zbx_setcookie("current_nodeid",$ZBX_CURNODEID);
	}
	else
	{
		$ZBX_CURNODEID = $ZBX_LOCALNODEID;
		$ZBX_CURMASTERID = $ZBX_LOCMASTERID;
	}
	
	switch($page["type"])
	{
		case PAGE_TYPE_IMAGE:
			set_image_header();
			define('ZBX_PAGE_NO_MENU', 1);
			break;
		case PAGE_TYPE_XML:
			header('Content-Type: text/xml');
			header('Content-Disposition: attachment; filename="'.$page['file'].'"');
			define('ZBX_PAGE_NO_MENU', 1);
			break;
		case PAGE_TYPE_HTML:
		default:
			header('Content-Type: text/html; charset='.S_HTML_CHARSET);

			if(isset($page['title']) && defined($page['title']))	$page['title'] = constant($page['title']);
			
			if(!isset($page['title'])) $page['title'] = 'ZABBIX';
			
			if(defined('ZBX_DISTRIBUTED'))
			{
				if($curr_node_data = DBfetch(DBselect('select * from nodes where nodeid='.$ZBX_CURNODEID)))
					$page['title'] .= ' ('.$curr_node_data['name'].')';
			}
			if(defined('ZBX_PAGE_DO_REFRESH') && $USER_DETAILS["refresh"])
			{
				$page['title'] .= ' [refreshed every '.$USER_DETAILS['refresh'].' sec]';
				/* header('Refresh: '.$USER_DETAILS["refresh"]); */ /* is not part of the official HTTP specification */
			}
		break; /* case PAGE_TYPE_HTML */
	} /* switch($page["type"]) */

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
		"view"=>array(
				"label"			=> S_MONITORING,
				"default_page_id"	=> 0,
				"pages"=>array(
					array("url"=>"overview.php"	,"label"=>S_OVERVIEW	),
					array("url"=>"latest.php"	,"label"=>S_LATEST_DATA	,
						"sub_pages"=>array("history.php","chart.php")
						),
					array("url"=>"tr_status.php"	,"label"=>S_TRIGGERS	,
						"sub_pages"=>array("tr_events.php","acknow.php","tr_comments.php",
								"chart4.php")
						),
					array("url"=>"queue.php"	,"label"=>S_QUEUE	),
					array("url"=>"events.php"	,"label"=>S_EVENTS	),
					array("url"=>"actions.php"	,"label"=>S_ACTIONS	),
					array("url"=>"maps.php"		,"label"=>S_MAPS	,
						"sub_pages"=>array("map.php")
						),
					array("url"=>"charts.php"	,"label"=>S_GRAPHS	,
						"sub_pages"=>array("chart2.php")
						),
					array("url"=>"screens.php"	,"label"=>S_SCREENS	),
					array("url"=>"srv_status.php"	,"label"=>S_IT_SERVICES	,
						"sub_pages"=>array("report3.php","chart_sla.php","chart5.php")
						),
					array("url"=>"vtext.php"),
					)
				),
		"cm"=>array(
				"label"			=> S_INVENTORY,
				"default_page_id"	=> 0,
				"pages"=>array(
					array("url"=>"hostprofiles.php"	,"label"=>S_HOSTS	)
					)
				),
		"reports"=>array(
				"label"			=> S_REPORTS,
				"default_page_id"	=> 0,
				"pages"=>array(
					array("url"=>"report1.php",	"label"=>S_STATUS_OF_ZABBIX	),
					array("url"=>"report2.php",	"label"=>S_AVAILABILITY_REPORT	),
					array("url"=>"report5.php",	"label"=>S_TRIGGERS_TOP_100	)   
					)
				),
		"config"=>array(
				"label"			=> S_CONFIGURATION,
				"default_page_id"	=> 0,
				"pages"=>array(
					array("url"=>"config.php"	,"label"=>S_GENERAL		,
						"sub_pages"=>array("image.php")
						),
					array("url"=>"hosts.php"	,"label"=>S_HOSTS		),
					array("url"=>"items.php"	,"label"=>S_ITEMS		),
					array("url"=>"triggers.php"	,"label"=>S_TRIGGERS		),
					array("url"=>"actionconf.php"	,"label"=>S_ACTIONS		),
					array("url"=>"sysmaps.php"	,"label"=>S_MAPS		,
						"sub_pages"=>array("sysmap.php")
						),
					array("url"=>"graphs.php"	,"label"=>S_GRAPHS		,
						"sub_pages"=>array("popup_gitem.php")
						),
					array("url"=>"screenconf.php"	,"label"=>S_SCREENS		,
						"sub_pages"=>array("screenedit.php")
						),
					array("url"=>"services.php"	,"label"=>S_IT_SERVICES		),
					array("url"=>"exp_imp.php"	,"label"=>S_EXPORT_IMPORT	),
					array("url"=>"popup.php")
					)
				),
		"admin"=>array(
				"label"			=> S_ADMINISTRATION,
				"default_page_id"	=> 1,
				"pages"=>array(
					ZBX_DISTRIBUTED ? array("url"=>"nodes.php"	,"label"=>S_NODES) : null ,
					array("url"=>"users.php"	,"label"=>S_USERS		,
						"sub_pages"=>array("popup_media.php",
							"popup_usrgrp.php","popup_right.php","popup_users.php")
						),
					array("url"=>"media_types.php"	,"label"=>S_MEDIA_TYPES		),
					array("url"=>"audit.php"	,"label"=>S_AUDIT		),
					array("url"=>"report4.php"	,"label"=>S_NOTIFICATIONS	),
					array("url"=>"instal.php"	,"label"=>S_INSTALLATION	,
						"sub_pages"=>array("setup.php"))
					)
				),
		"login"=>array(
				"label"			=> S_LOGIN,
				"default_page_id"	=> 0,
				"pages"=>array(
					array("url"=>"index.php",
						"sub_pages"=>array("profile.php")
						)
					)
				)
		);

	$main_menu_row	= array();
	$sub_menu_row	= array();

	foreach($ZBX_MENU as $label=>$sub)
	{
// Check permissions
		unset($deny);
		if(!defined('ZBX_PAGE_NO_AUTHERIZATION'))
		{
			if($label!='login' && !isset($USER_DETAILS['type']))
			{
				$deny = true;
			}
			elseif($label=='admin'	&& (!in_array($USER_DETAILS['type'], array(USER_TYPE_SUPER_ADMIN)) ||
				!in_array($ZBX_CURNODEID, get_accessible_nodes_by_user(
					$USER_DETAILS,PERM_READ_WRITE,null,
					PERM_RES_IDS_ARRAY,$ZBX_CURNODEID))))
			{
				$deny = true;
			}
			elseif($label=='config'	&& (
				!in_array($USER_DETAILS['type'], array(USER_TYPE_SUPER_ADMIN, USER_TYPE_ZABBIX_ADMIN)) ||
				!in_array($ZBX_CURNODEID, get_accessible_nodes_by_user(
					$USER_DETAILS,PERM_READ_LIST,null,
					PERM_RES_IDS_ARRAY,$ZBX_CURNODEID))))
			{
				$deny = true;
			}
			elseif(!in_array($ZBX_CURNODEID, get_accessible_nodes_by_user(
					$USER_DETAILS,PERM_READ_LIST,null,
					PERM_RES_IDS_ARRAY,$ZBX_CURNODEID)))
			{
				$deny = true;
			}
		}

// End of check permissions

		unset($menu_url);
		foreach($sub['pages'] as $sub_pages)
		{
				
			if($page['file'] == $sub_pages['url'])
			{
				if(isset($sub_pages['label']))
				{
					$menu_url = $sub_pages['url'];
				}
				$page_exist = true;
				break;
			}
			else if(isset($sub_pages['sub_pages']))
			{
				if(in_array($page['file'], $sub_pages['sub_pages']))
				{
					if(isset($sub_pages['label']))
					{
						$menu_url = $sub_pages['url'];
					}
					$page_exist = true;
					break;
				}					
			}
		}

		if(isset($menu_url)) /* active menu */
		{

			$class = "active";

			update_profile('web.menu.'.$label.'.last', $menu_url);

			if(isset($deny))
			{
				$denyed_page_requested = true;
				continue;
			}

			foreach($sub['pages'] as $sub_pages)
			{
				if(!isset($sub_pages['label'])) continue;

				array_push($sub_menu_row, 
					new CLink($sub_pages['label'], $sub_pages['url'],'highlight'), 
					new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider')
					);
			}
		}
		else
		{
			if(isset($deny)) continue;

			$class = "horizontal_menu_n";

			$menu_url = get_profile('web.menu.'.$label.'.last',false);

			if(!$menu_url)
				$menu_url = $sub['pages'][$sub['default_page_id']]["url"];
		}

		array_push($main_menu_row, new CCol(new CLink($sub['label'], $menu_url, "highlight"),$class));
		unset($menu_url, $class);
	}

	if((!isset($page_exist) && $page['type']!=PAGE_TYPE_XML))
	{
		$denyed_page_requested = true;
	}

	if(isset($denyed_page_requested))	$unset_cookie = time() - 3600;

	global $ZBX_PAGE_COOCIES;

	if(isset($ZBX_PAGE_COOCIES))
	{
		foreach($ZBX_PAGE_COOCIES as $coockie)
		{
			setcookie($coockie[0], $coockie[1], isset($unset_cookie) ? $unset_cookie : $coockie[2]);
		}
		unset($ZBX_PAGE_COOCIES);
	}

	if($page["type"] == PAGE_TYPE_HTML)
	{
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title><?php echo $page['title'] ?></title>
<?php if(defined('ZBX_PAGE_DO_REFRESH') && $USER_DETAILS["refresh"]) { ?>
    <meta http-equiv="refresh" content="<?php echo $USER_DETAILS["refresh"]; ?>">
<?php } ?>
    <link rel="stylesheet" href="css.css">
    <meta name="Author" content="ZABBIX SIA">
  </head>
<body>
<?php
	}

	if(!defined('ZBX_PAGE_NO_MENU'))
	{
COpt::compare_files_with_menu($ZBX_MENU);

		$help = new CLink(S_HELP, "http://www.zabbix.com/manual/v1.1/index.php", "small_font");
		$help->SetTarget('_blank');
		$page_header_r_col = array($help,
			($USER_DETAILS["alias"] != "guest") ?
				array("|", new CLink(S_PROFILE, "profile.php", "small_font")) :
				null
			);
		$logo = new CLink(new CImg("images/general/zabbix.png","ZABBIX"),"http://www.zabbix.com");
		$logo->SetTarget('_blank');

		$top_page_row	= array(new CCol($logo, "page_header_l"), new CCol($page_header_r_col, "page_header_r"));
		unset($logo, $page_header_r_col, $help);

		$table = new CTable(NULL,"page_header");
		$table->SetCellSpacing(0);
		$table->SetCellPadding(5);
		$table->AddRow($top_page_row);
		$table->Show();

		$menu_table = new CTable(NULL,'menu');
		$menu_table->SetCellSpacing(0);
		$menu_table->SetCellPadding(5);
		$menu_table->AddRow($main_menu_row);

		if(ZBX_DISTRIBUTED)
		{
			$lst_nodes = new CComboBox('switch_node', $ZBX_CURNODEID);
			$db_nodes = DBselect('select * from nodes where nodeid in ('.
				get_accessible_nodes_by_user($USER_DETAILS,PERM_READ_LIST).') '.
				' order by name ');
			while($node_data = DBfetch($db_nodes))
			{
				$lst_nodes->AddItem($node_data['nodeid'],$node_data['name']);
			}

			$node_form = new CForm();
			$node_form->AddItem('Current node ['.$ZBX_CURNODEID.'] ');
			$node_form->AddItem($lst_nodes);
			unset($lst_nodes);
			$node_form->AddItem(new CButton('submit',S_SWITCH));
		}
		else
		{
			$node_form = null;
		}
		
		$table = new CTable();
		$table->SetCellSpacing(0);
		$table->SetCellPadding(0);
		$table->options['style'] = "width: 100%;";

		$r_col = new CCol($node_form);
		$r_col->options['style'] = "text-align: right;";
		
		$table->AddRow(array($menu_table,$r_col));
		$table->Show();
		
		$sub_menu_table = new CTable(NULL,'sub_menu');
		$sub_menu_table->SetCellSpacing(0);
		$sub_menu_table->SetCellPadding(5);
		$sub_menu_table->AddRow(new CCol($sub_menu_row));
	
		$sub_menu_table->Show();
	}
	unset($ZBX_MENU);
		
	unset($table, $top_page_row, $menu_table, $node_form);
	unset($main_menu_row);
	unset($db_nodes, $node_data);
	unset($sub_menu_table, $sub_menu_row);

	if(isset($denyed_page_requested))
	{
		access_deny();
	}

	if(version_compare(phpversion(), '5.1.0RC1', '>=') && $page['type'] == PAGE_TYPE_HTML)
	{
		$tmezone = ini_get('date.timezone');
		if(empty($tmezone)) 
		{
			info('Timezone for PHP is not set. Please set "date.timezone" option in php.ini.');
			date_default_timezone_set('UTC');
		}
		unset($tmezone);
	}
?>
