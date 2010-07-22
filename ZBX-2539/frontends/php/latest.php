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
require_once('include/hosts.inc.php');
require_once('include/items.inc.php');

$page['title'] = 'S_LATEST_DATA';
$page['file'] = 'latest.php';
$page['hist_arg'] = array('groupid','hostid','show','select','open','applicationid');
$page['scripts'] = array('effects.js', 'class.cswitcher.js');

$page['type'] = detect_page_type(PAGE_TYPE_HTML);

define('ZBX_PAGE_MAIN_HAT','hat_latest');

if(PAGE_TYPE_HTML == $page['type']){
	define('ZBX_PAGE_DO_REFRESH', 1);
}
//	define('ZBX_PAGE_DO_JS_REFRESH', 1);

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),
		'hostid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		NULL),

		'fullscreen'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	IN('0,1'),		NULL),
// filter
		'select'=>			array(T_ZBX_STR, O_OPT, NULL,	NULL,		NULL),
		'filter_rst'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		'filter_set'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
	);

	check_fields($fields);

?>
<?php
/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.latest.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
/*
		else if('refresh' == $_REQUEST['favobj']){
			switch($_REQUEST['favref']){
				case ZBX_PAGE_MAIN_HAT:
					include_once('blocks/latest.page.php');
					break;
			}
		}
//*/
	}
	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		include_once('include/page_footer.php');
		exit();
	}
//--------


/* FILTER */
	$_REQUEST['select'] = get_request('select', CProfile::get('web.latest.filter.select', ''));
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['select'] = '';
	}
	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.latest.filter.select', $_REQUEST['select'], PROFILE_TYPE_STR);
	}
// --------------

	$latest_wdgt = new CWidget();

// Header
	$fs_icon = new CDiv(SPACE,'fullscreen');
	$fs_icon->setAttribute('title',$_REQUEST['fullscreen']?S_NORMAL.' '.S_VIEW:S_FULLSCREEN);
	$fs_icon->addAction('onclick', 'javascript: document.location = "?fullscreen='.($_REQUEST['fullscreen']?'0':'1').'";');
	$latest_wdgt->addPageHeader(S_LATEST_DATA_BIG,$fs_icon);

//	$cmbGroup = new CComboBox('groupid',$_REQUEST['groupid'],"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."',this.form);");
//	$cmbHosts = new CComboBox('hostid',$_REQUEST['hostid'],"javascript: return updater.onetime_update('".ZBX_PAGE_MAIN_HAT."',this.form);");

	$options = array(
		'groups' => array(
			'monitored_hosts' => 1,
			'with_historical_items' => 1,
		),
		'hosts' => array(
			'monitored_hosts' => 1,
			'with_historical_items' => 1,
		),
		'hostid' => get_request('hostid', null),
		'groupid' => get_request('groupid', null),
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;
	$_REQUEST['hostid'] = $pageFilter->hostid;

	$r_form = new CForm(null, 'get');
	$r_form->addItem(array(S_GROUP.SPACE,$pageFilter->getGroupsCB(true)));
	$r_form->addItem(array(SPACE.S_HOST.SPACE,$pageFilter->getHostsCB(true)));

	$latest_wdgt->addHeader(S_ITEMS_BIG, $r_form);
//-------------

/************************* FILTER **************************/
/***********************************************************/
	$filterForm = new CFormTable();
	$filterForm->setAttribute('name','zbx_filter');
	$filterForm->setAttribute('id','zbx_filter');

	$filterForm->addRow(S_SHOW_ITEMS_WITH_DESCRIPTION_LIKE, new CTextBox('select',$_REQUEST['select'],20));

	$reset = new CButton('filter_rst', S_RESET);
	$reset->setType('button');
	$reset->setAction('javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

	$filterForm->addItemToBottomRow(new CButton('filter_set', S_FILTER));
	$filterForm->addItemToBottomRow($reset);

	$latest_wdgt->addFlicker($filterForm, CProfile::get('web.latest.filter.state',1));
//-------

	validate_sort_and_sortorder('description', ZBX_SORT_UP);
?>
<?php
	$switcherName = 'application_switcher';
	$show_hide_all = new CDiv(SPACE, 'filterclosed');
	$show_hide_all->setAttribute('id', $switcherName);

	$table=new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes()?make_sorting_header(S_NODE,'hostid') : null,
		$show_hide_all,
		($_REQUEST['hostid'] ==0)?make_sorting_header(S_HOST,'host') : NULL,
		make_sorting_header(S_DESCRIPTION,'description'),
		make_sorting_header(S_LAST_CHECK,'lastclock'),
		S_LAST_VALUE,
		S_CHANGE,
		S_HISTORY
	));


	if($pageFilter->hostsSelected){
//		$config = select_config();
		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'filter' => array(
				'status' => array(ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED)
			),
			'pattern' => (!empty($_REQUEST['select']) ? $_REQUEST['select'] : null),
			'select_applications' => API_OUTPUT_EXTEND,
			'templated' => false,
			'webitems' => true,
			'withData' => true,
//			'limit' => ($config['search_limit']+1),
			'select_hosts' => API_OUTPUT_EXTEND,
		);
		if($pageFilter->hostid > 0) $options['hostids'] = $pageFilter->hostid;
		else if($pageFilter->groupid > 0) $options['groupids'] = $pageFilter->groupid;

		$items = CItem::get($options);

		$apps = array();
		foreach($items as $inum => $item){
			$host = reset($item['hosts']);

			if(!empty($item['applications'])){
				foreach($item['applications'] as $a){
					if(!isset($apps[$a['applicationid']])){
						$apps[$a['applicationid']] = array(
							'items' => array(),
							'name' => $a['name'],
							'host' => $host['host'],
							'hostid' => $host['hostid'],
						);
					}

					$item['host'] = $host['host'];
					$apps[$a['applicationid']]['items'][$item['itemid']] = $item;
				}
				unset($items[$inum]);
			}
		}
		order_result($apps, 'name');

		foreach($items as $inum => $item){
			$host = reset($item['hosts']);

			if(!isset($apps['h'.$host['hostid']])){
				$apps['h'.$host['hostid']] = array(
					'host' => $host['host'],
					'hostid' => $host['hostid'],
					'name' => ' - '.S_OTHER_SMALL.' -',
					'items' => array(),
				);
			}
			$item['host'] = $host['host'];
			$apps['h'.$host['hostid']]['items'][$item['itemid']] = $item;
		}
		unset($items);

		foreach($apps as $appid => $app){
			$items_count = count($app['items']);
			if(!$items_count) continue;

			$open_close = new CDiv(SPACE, 'filterclosed');
			$open_close->setAttribute('data-switcherid', $appid);

			$col = new CCol(array(bold($app['name']),' ('.$items_count.SPACE.S_ITEMS.')'));
			$col->setColSpan(5);

			$table->addRow(array(
				get_node_name_by_elid($app['hostid']),
				$open_close,
				($_REQUEST['hostid'] > 0 ) ? null : $app['host'],
				$col
			), 'even_row');


			$sortorder = get_request('sortorder');
			order_result($app['items'], get_request('sort', 'lastclock'), $sortorder == 'ASC' ? ZBX_SORT_UP : ZBX_SORT_DOWN);
			foreach($app['items'] as $itemid => $item){
				$description = item_description($item);

				if(strpos($item['units'], ',') !== false)
					list($item['units'], $item['unitsLong']) = explode(',', $item['units']);
				else
					$item['unitsLong'] = '';

				$lastclock = isset($item['lastclock']) ? zbx_date2str(S_LATEST_ITEMS_TRIGGERS_DATE_FORMAT, $item['lastclock'])
						: ' - ';

				$lastvalue = format_lastvalue($item);

				if(isset($item['lastvalue']) && isset($item['prevvalue'])
						&& in_array($item['value_type'], array(ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64))
						&& ($item['lastvalue']-$item['prevvalue'] != 0)){
					if($item['lastvalue']-$item['prevvalue']<0){
						$change=convert_units($item['lastvalue']-$item['prevvalue'],$item['units']);
					}
					else{
						$change='+'.convert_units($item['lastvalue']-$item['prevvalue'],$item['units']);
					}
					$change=nbsp($change);
				}
				else{
					$change = ' - ';
				}

				if(($item['value_type']==ITEM_VALUE_TYPE_FLOAT) || ($item['value_type']==ITEM_VALUE_TYPE_UINT64)){
					$actions = new CLink(S_GRAPH,'history.php?action=showgraph&itemid='.$item['itemid']);
				}
				else{
					$actions = new CLink(S_HISTORY,'history.php?action=showvalues&period=3600&itemid='.$item['itemid']);
				}

				$item_status = $item['status']==3 ? 'unknown' : null;

				$row = new CRow(array(
					is_show_all_nodes() ? SPACE : null,
					($_REQUEST['hostid']>0) ? NULL : SPACE,
					SPACE,
					new CCol($description, $item_status),
					new CCol($lastclock, $item_status),
					new CCol($lastvalue, $item_status),
					new CCol($change, $item_status),
					$actions
				), 'odd_row');
				$row->setAttribute('data-parentid', $appid);
				$row->addStyle('display: none;');
				$table->addRow($row);
			}
		}
	}

/*
// Refresh tab
	$refresh_tab = array(
		array('id'	=> ZBX_PAGE_MAIN_HAT,
				'interval' 	=> $USER_DETAILS['refresh'],
				'url'	=>	zbx_empty($_SERVER['QUERY_STRING'])?'':'?'.$_SERVER['QUERY_STRING'],
			)
	);
//*/

	$latest_wdgt->addItem($table);
	$latest_wdgt->show();

	zbx_add_post_js("var switcher = new CSwitcher('$switcherName');");
//	add_refresh_objects($refresh_tab);

include_once 'include/page_footer.php';
?>
