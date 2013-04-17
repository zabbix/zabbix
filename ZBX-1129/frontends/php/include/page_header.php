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
	require_once('include/perm.inc.php');

	if(!isset($page['type'])) $page['type'] = PAGE_TYPE_HTML;
	if(!isset($page['file'])) $page['file'] = basename($_SERVER['PHP_SELF']);

	if($_REQUEST['fullscreen']=get_request('fullscreen', 0)) define('ZBX_PAGE_NO_MENU', 1);

	require_once('include/menu.inc.php');

	zbx_define_menu_restrictions($page, $ZBX_MENU);

	/* Init CURRENT NODE ID */
	init_nodes();

	/* switch($page["type"]) */
	switch($page['type']){
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
		case PAGE_TYPE_JSON:
			header('Content-Type: application/javascript; charset=UTF-8');
			if(!defined('ZBX_PAGE_NO_MENU')) define('ZBX_PAGE_NO_MENU', 1);
			break;
		case PAGE_TYPE_JSON_RPC:
			header('Content-Type: application/json');
			if(!defined('ZBX_PAGE_NO_MENU')) define('ZBX_PAGE_NO_MENU', 1);
			break;
		case PAGE_TYPE_CSS:
			header('Content-Type: text/css; charset=UTF-8');
			if(!defined('ZBX_PAGE_NO_MENU')) define('ZBX_PAGE_NO_MENU', 1);
			break;
		case PAGE_TYPE_HTML_BLOCK:
			header('Content-Type: text/plain; charset=UTF-8');
			if(!defined('ZBX_PAGE_NO_MENU')) define('ZBX_PAGE_NO_MENU', 1);
			break;
		case PAGE_TYPE_TEXT_FILE:
			header('Content-Type: text/plain; charset=UTF-8');
			header('Content-Disposition: attachment; filename="'.$page['file'].'"');
			if(!defined('ZBX_PAGE_NO_MENU')) define('ZBX_PAGE_NO_MENU', 1);
			break;
		case PAGE_TYPE_HTML:
		default:
			if(!isset($page['encoding']))
				header('Content-Type: text/html; charset='.S_HTML_CHARSET);
			else
				header('Content-Type: text/html; charset='.$page['encoding']);

//			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . " GMT");

			$page_title = '';
			if(isset($ZBX_SERVER_NAME) && !zbx_empty($ZBX_SERVER_NAME)){
				$page_title = $ZBX_SERVER_NAME.': ';
			}

			if(isset($page['title']) && defined($page['title'])){
				$page_title .= constant($page['title']);
			}
			else{
				$page_title .= 'ZABBIX';
				$page['title'] = 'S_ZABBIX';
			}

			if(ZBX_DISTRIBUTED){
				if(isset($ZBX_VIEWED_NODES) && ($ZBX_VIEWED_NODES['selected'] == 0)){ // ALL selected
					$page_title .= ' ('.S_ALL_NODES.') ';
				}
				else if(!empty($ZBX_NODES)){
					$page_title .= ' ('.$ZBX_NODES[$ZBX_CURRENT_NODEID]['name'].')';
				}
			}

			if((defined('ZBX_PAGE_DO_REFRESH') || defined('ZBX_PAGE_DO_JS_REFRESH')) && $USER_DETAILS['refresh']){
				$page_title .= ' ['.S_REFRESHED_EVERY_SMALL.SPACE.$USER_DETAILS['refresh'].SPACE.S_SEC_SMALL.']';
			}
		break;
	}

	// construc menu
	$main_menu	= array();
	$sub_menus	= array();

	$denyed_page_requested = zbx_construct_menu($main_menu, $sub_menus, $page);

	zbx_flush_post_cookies($denyed_page_requested);

	if($page['type'] == PAGE_TYPE_HTML){
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title><?php echo $page_title; ?></title>
	<meta name="Author" content="ZABBIX SIA" />
	<link rel="shortcut icon" href="images/general/zabbix.ico" />
	<link rel="stylesheet" type="text/css" href="css.css" />
<!--[if IE 6]>
	<script type="text/javascript" src="js/ie6fix.js"></script>
	<link rel="stylesheet" type="text/css" href="styles/ie.css" />
<![endif]-->

<?php
	if (!empty($DB['DB'])) {
		$config = select_config();

		$cssFile = getUserTheme($USER_DETAILS);
		if ($cssFile) {
			print('<link rel="stylesheet" type="text/css" href="styles/'.$cssFile.'" />'."\n");
			if (file_exists('styles/ie_'.$cssFile)) {
				print('<!--[if IE 6]><link rel="stylesheet" type="text/css" href="styles/ie_'.$cssFile.'" /><![endif]-->'."\n");
			}
		}
	}

	if($page['file'] == 'sysmap.php')
		print('<link rel="stylesheet" type="text/css" href="imgstore.php?css=1&output=css" />');
?>
<script type="text/javascript">	var PHP_TZ_OFFSET = <?php echo date('Z'); ?>;</script>
<?php
	$path = 'jsLoader.php?ver='.ZABBIX_VERSION.'&lang='.$USER_DETAILS['lang'];
	print('<script type="text/javascript" src="'.$path.'"></script>'."\n");

	if (!empty($page['scripts']) && is_array($page['scripts'])) {
		foreach ($page['scripts'] as $id => $script) {
			$path .= '&files[]='.$script;
		}
		print('<script type="text/javascript" src="'.$path.'"></script>'."\n");
	}
?>
</head>
<body onload="zbxCallPostScripts();">
<?php
	}

	define ('PAGE_HEADER_LOADED', 1);

	if(isset($_REQUEST['print'])){
		if(!defined('ZBX_PAGE_NO_MENU'))
			define('ZBX_PAGE_NO_MENU', 1);

		$req = new CUrl();
		$req->setArgument('print', null);

		$link = new CLink(bold('&laquo;'.S_BACK_BIG), $req->getUrl(), 'small_font', null, 'nosid');
		$link->setAttribute('style','padding-left: 10px;');

		$printview = new CDiv($link,'printless');
		$printview->setAttribute('style','border: 1px #333 dotted;');
		$printview->Show();

	}

	if(!defined('ZBX_PAGE_NO_MENU')){
COpt::compare_files_with_menu($ZBX_MENU);

		$help = new CLink(S_HELP, 'http://www.zabbix.com/documentation/', 'small_font', null, 'nosid');
		$help->setTarget('_blank');
		$support = new CLink(S_GET_SUPPORT, 'http://www.zabbix.com/support.php', 'small_font', null, 'nosid');
		$support->setTarget('_blank');

		$req = new CUrl($_SERVER['REQUEST_URI']);
		$req->setArgument('print', 1);
		$printview = new CLink(S_PRINT, $req->getUrl(), 'small_font', null, 'nosid');

		$page_header_r_col = array($help,'|',$support,'|',$printview);

		if($USER_DETAILS['alias']!=ZBX_GUEST_USER){
			$page_header_r_col[] = array('|');

			array_push($page_header_r_col, new CLink(S_PROFILE, 'profile.php', 'small_font', null, 'nosid'),'|');

			if($USER_DETAILS['debug_mode'] == GROUP_DEBUG_MODE_ENABLED){

				$debug = new CLink(S_DEBUG, '#debug', 'small_font', null, 'nosid');

				$d_script = " if(!isset('state', this)) this.state = 'none'; ".
							" if(this.state == 'none') this.state = 'block'; ".
							" else this.state = 'none'; ".
							" showHideByName('zbx_gebug_info', this.state);";

				$debug->setAttribute('onclick', 'javascript: '.$d_script);

				array_push($page_header_r_col,$debug,'|');
			}

			array_push($page_header_r_col,new CLink(S_LOGOUT, 'index.php?reconnect=1', 'small_font', null, 'nosid'));
		}
		else {
			$page_header_r_col[] = array('|', new CLink(S_LOGIN, 'index.php?reconnect=1', 'small_font', null, 'nosid'));
		}

		$logo = new CLink(new CDiv(SPACE,'zabbix_logo'),'http://www.zabbix.com/', 'image', null, 'nosid');
		$logo->setTarget('_blank');

		$td_r = new CCol($page_header_r_col, 'page_header_r');
		$td_r->setAttribute('width','100%');

		$top_page_row	= array(new CCol($logo, 'page_header_l'), $td_r);
		unset($logo, $page_header_r_col, $help, $support);

		$table = new CTable(NULL,'page_header');
		$table->setCellSpacing(0);
		$table->setCellPadding(5);
		$table->addRow($top_page_row);
		$table->show();

		$menu_table = new CTable(NULL,'menu');
		$menu_table->setCellSpacing(0);
		$menu_table->setCellPadding(5);
		$menu_table->addRow($main_menu);

		$node_form = null;
		if(ZBX_DISTRIBUTED && !defined('ZBX_HIDE_NODE_SELECTION')) {
			insert_js_function('check_all');

			$available_nodes = get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_LIST, PERM_RES_DATA_ARRAY);
			$available_nodes = get_tree_by_parentid($ZBX_LOCALNODEID, $available_nodes, 'masterid'); //remove parent nodes

			if(!empty($available_nodes)) {

				$node_form = new CForm();
				$node_form->setMethod('get');
				$node_form->setAttribute('id', 'node_form');

// +++ create Combo Box with selected nodes +++
				$combo_node_list = null;
				if(count($ZBX_VIEWED_NODES['nodes']) > 0) {
					$combo_node_list = new CComboBox('switch_node', $ZBX_VIEWED_NODES['selected'], 'submit()');

					foreach($ZBX_VIEWED_NODES['nodes'] as $nodeid => $nodedata) {
						$combo_node_list->addItem($nodeid, $nodedata['name']);
					}
				}
// --- ---
				$jscript = 'javascript : '.
					" var pos = getPosition('button_show_tree');".
					" ShowHide('div_node_tree',IE6?'block':'table');".
					' pos.top += 20;'.
					" \$('div_node_tree').setStyle({top: pos.top+'px'});".
					" if(IE6) showPopupDiv('div_node_tree','select_iframe');";		// IE6
				$button_show_tree = new CButton('show_node_tree', S_SELECT_NODES, $jscript); //sdelatj konstatntu!
				$button_show_tree->setType('button');
				$button_show_tree->setAttribute('id', 'button_show_tree');

// +++ Create node tree +++
				$combo_check_all = new CCheckbox('check_all_nodes', null, "javascript : check_all('node_form', this.checked);");

				$node_tree = array();
				$node_tree[0] = array('id' => 0, 'caption' => S_ALL_S, 'combo_select_node' => $combo_check_all, 'parentid' => 0); //root

				foreach($available_nodes as $num => $node) {
					$checked = isset($ZBX_VIEWED_NODES['nodeids'][$node['nodeid']]);
					$combo_select_node = new CCheckbox('selected_nodes['.$node['nodeid'].']', $checked, null, $node['nodeid']);
					$combo_select_node->setAttribute('style','margin: 1px 4px 2px 4px;');

// If not exist parent for node, link it to root (0)
					if(!isset($available_nodes[$node['masterid']])) $node['masterid'] = 0;

					$node_tree[$node['nodeid']] = array(
					'id' => $node['nodeid'],
					'caption' => $node['name'],
					'combo_select_node' => $combo_select_node,
					'parentid' => $node['masterid']);
				}

				$node_tree_captions = array('caption' => bold(S_NODE), 'combo_select_node' => SPACE);

				$node_tree = new CTree('nodes', $node_tree, $node_tree_captions);
// --- ---

				$div_node_tree = new CDiv();
				$div_node_tree->addItem($node_tree->getHTML());

				$div_node_tree->addItem(new CButton('select_nodes', S_SELECT, "javascript: ".
																				" if(IE6) hidePopupDiv('select_iframe');".	//IE6 fix
																				" \$('div_node_tree').setStyle({display:'none'});"));

				$div_node_tree->setAttribute('id', 'div_node_tree');
				$div_node_tree->addStyle('display: none');


				if(!is_null($combo_node_list))
					$node_form->addItem(array(new CSpan(S_CURRENT_NODE.SPACE,'textcolorstyles'), $combo_node_list));
				$node_form->addItem($button_show_tree);
				$node_form->addItem($div_node_tree);
				unset($combo_node_list);
			}
		}

		if(isset($ZBX_SERVER_NAME) && !zbx_empty($ZBX_SERVER_NAME)){
			$tab = new CTable();

			$td_l = new CCol(new CSpan($ZBX_SERVER_NAME, 'textcolorstyles'));
			if(is_null($node_form))
				$td_l->addStyle('padding-right: 5px;');
			else
				$td_l->addStyle('padding-right: 20px; padding-bottom: 2px;');

			$tab->addRow(array($td_l, $node_form));
			$node_form = $tab;
		}

// 1st level menu
		$table = new CTable();
		$table->setCellSpacing(0);
		$table->setCellPadding(0);
		$table->setAttribute('style','width: 100%;');

		$r_col = new CCol($node_form);
		$r_col->setAttribute('align','right');
//		$r_col->setAttribute('style','text-align: right;');

		$table->addRow(array($menu_table,$r_col));

		$page_menu = new CDiv();
		$page_menu->setAttribute('id','mmenu');
		$page_menu->addItem($table);
//----

// 2nd level menu
		$sub_menu_table = new CTable(NULL,'sub_menu');
		$sub_menu_table->setCellSpacing(0);
		$sub_menu_table->setCellPadding(5);

		$menu_divs = array();
		$menu_selected = false;
		foreach($sub_menus as $label => $sub_menu){

			$sub_menu_row = array();
			foreach($sub_menu as $id => $sub_page){
				if(empty($sub_page['menu_text'])) $sub_page['menu_text'] = SPACE;

				$sub_menu_item = new CLink($sub_page['menu_text'], $sub_page['menu_url'], $sub_page['class'].' nowrap');
				if($sub_page['selected']) $sub_menu_item = new CSpan($sub_menu_item, 'active nowrap');
				$sub_menu_row[] = $sub_menu_item;
				$sub_menu_row[] = new CSpan(SPACE.' | '.SPACE, 'divider');
			}

			$sub_menu_div = new CDiv($sub_menu_row);
			$sub_menu_div->setAttribute('id', 'sub_'.$label);
			$sub_menu_div->addAction('onmouseover','javascript: MMenu.submenu_mouseOver();');
			$sub_menu_div->addAction('onmouseout','javascript: MMenu.mouseOut();');

			if(isset($page['menu']) && ($page['menu'] == $label)){
				$menu_selected = true;
				$sub_menu_div->setAttribute('style','display: block;');
				insert_js('MMenu.def_label = '.zbx_jsvalue($label));
			}
			else{
				$sub_menu_div->setAttribute('style','display: none;');
			}

			$menu_divs[] = $sub_menu_div;
		}

		$sub_menu_div = new CDiv(SPACE);
		$sub_menu_div->setAttribute('id', 'sub_empty');
		$sub_menu_div->setAttribute('style','display: '.($menu_selected?'none;':'block;'));

		$menu_divs[] = $sub_menu_div;
		$search_div = null;

		if(($page['file'] != 'index.php') && ($USER_DETAILS['userid'] > 0)){
			$search_form = new CForm('search.php');
			$search_form->setMethod('get');
			$search_form->setAttribute('class','thin');

			$searchBox = new CTextBox('search', get_request('search',''));
			$searchBox->setAttribute('autocomplete', 'off');
			$searchBox->setAttribute('style', 'width: 160px;');

			$search_form->addItem(new CDiv(array(S_SEARCH_BIG.': ', $searchBox)));

			$search_div = new CDiv($search_form);
			$search_div->setAttribute('id','zbx_search');
			$search_div->setAttribute('class','zbx_search');

			zbx_add_post_js("var sid = createSuggest('search');");
		}

		$sub_menu_table->addRow(array($menu_divs, $search_div));

		$page_menu->addItem($sub_menu_table);
//---

		$page_menu->show();
	}

//------------------------------------- <HISTORY> ---------------------------------------
	if(isset($page['hist_arg']) && ($USER_DETAILS['alias'] != ZBX_GUEST_USER) && ($page['type'] == PAGE_TYPE_HTML) && !defined('ZBX_PAGE_NO_MENU')){
		$table = new CTable();
		$table->setClass('history');

		$table->setCellSpacing(0);
		$table->setCellPadding(0);

		$history = get_user_history();
		$tr = new CRow(new CCol(S_HISTORY.':','caption'));
		$tr->addItem($history);

		$table->addRow($tr);
		$table->show();
	}
	else if(($page['type'] == PAGE_TYPE_HTML) && !defined('ZBX_PAGE_NO_MENU')) {
		echo SBR;
	}
//------------------------------------ </HISTORY> ---------------------------------------

	unset($ZBX_MENU);

	unset($table, $top_page_row, $menu_table, $node_form);
	unset($main_menu_row);
	unset($db_nodes, $node_data);
	unset($sub_menu_table, $sub_menu_rows);

	if($denyed_page_requested){
		access_deny();
	}

	if($page['type'] == PAGE_TYPE_HTML){
		zbx_add_post_js("var msglistid = initMessages({});");
	}

	show_messages();
?>
