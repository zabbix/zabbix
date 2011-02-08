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
require_once('include/forms.inc.php');

$_REQUEST['go'] = get_request('go', 'none');
if(($_REQUEST['go'] == 'export') && isset($_REQUEST['hosts'])){
	$EXPORT_DATA = true;
	$page['type'] = PAGE_TYPE_XML;
	$page['file'] = 'zabbix_export.xml';
	require_once('include/export.inc.php');
}
else{
	$EXPORT_DATA = false;
	$page['title'] = 'S_EXPORT_IMPORT';
	$page['file'] = 'export.php';
	$page['hist_arg'] = array('groupid');
}

include_once('include/page_header.php');
?>
<?php
	$fields = array(
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
		'groupid' =>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'hosts' =>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'templates' =>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'items' =>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'triggers' =>	array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'graphs' =>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'macros' =>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
		'update' =>		array(T_ZBX_INT, O_OPT,	null,	DB_ID,		null),
// Actions
		'go' =>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL, NULL),
// form
		'preview' =>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL),
		'export' =>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	NULL,	NULL)
	);

	check_fields($fields);
	validate_sort_and_sortorder('host', ZBX_SORT_UP);
//SDI($_REQUEST);
	$preview = ($_REQUEST['go'] == 'preview') ? true : false;
	$update = get_request('update', null);

?>
<?php

	$hostids = get_request('hosts', array());
	$hostids_templates	= get_request('templates', array());
	$hostids_items		= get_request('items', array());
	$hostids_graphs		= get_request('graphs', array());
	$hostids_triggers	= get_request('triggers', array());

	if($EXPORT_DATA){

/* SELECT HOSTS */
		$params = array(
			'hostids' => $hostids,
			'templated_hosts' => 1,
			'extendoutput' => 1,
			'preservekeys' => 1,
			'select_profile' => 1
		);
		$hosts = CHost::get($params);
		order_result($hosts, 'host');

/* SELECT HOST GROUPS */
		$params = array(
			'hostids' => $hostids,
			'preservekeys' => 1,
			'extendoutput' => 1
		);
		$groups = CHostGroup::get($params);

/* SELECT GRAPHS */
		$params = array(
			'hostids' => zbx_uint_array_intersect($hostids, $hostids_graphs),
			'preservekeys' => 1,
			'extendoutput' => 1
		);
		$graphs = CGraph::get($params);

// SELECT GRAPH ITEMS
		$graphids = zbx_objectValues($graphs, 'graphid');
		$params = array(
			'graphids' => $graphids,
			'extendoutput' => 1,
			'preservekeys' => 1,
			'expand_data' => 1
		);
		$gitems = CGraphItem::get($params);

		foreach($gitems as $gnum => $gitem){
			$gitems[$gitem['gitemid']]['host_key_'] = $gitem['host'].':'.$gitem['key_'];
		}
// SELECT TEMPLATES
		$params = array(
			'hostids' => zbx_uint_array_intersect($hostids, $hostids_templates),
			'preservekeys' => 1,
			'extendoutput' => 1
		);
		$templates = CTemplate::get($params);

// SELECT MACROS
		$params = array(
			'hostids' => $hostids,
			'preservekeys' => 1,
			'extendoutput' => 1
		);
		$macros = CUserMacro::get($params);

// SELECT ITEMS
		$params = array(
			'hostids' => zbx_uint_array_intersect($hostids, $hostids_items),
			'preservekeys' => 1,
			'extendoutput' => 1
		);
		$items = CItem::get($params);

// SELECT APPLICATIONS
		$itemids = zbx_objectValues($items, 'itemid');
//sdii($itemids);
		$params = array(
			'itemids' => $itemids,
			'preservekeys' => 1,
			'extendoutput' => 1
		);
		$applications = Capplication::get($params);
//sdii($applications);

/* SELECT TRIGGERS */
		$params = array(
			'hostids' => zbx_uint_array_intersect($hostids, $hostids_triggers),
			'extendoutput' => 1,
			'preservekeys' => 1,
			'select_dependencies' => 1,
			'expand_data' => 1
		);
		$triggers = CTrigger::get($params);
		foreach($triggers as $tnum => $trigger){
			$triggers[$trigger['triggerid']]['expression'] = explode_exp($trigger['expression'], false);
		}

/* SELECT TRIGGER DEPENDENCIES */
		$dependencies = array();
		foreach($triggers as $tnum => $trigger){
			if(!empty($trigger['dependencies'])){
				if(!isset($dependencies[$trigger['triggerid']])) $dependencies[$trigger['triggerid']] = array();

				$dependencies[$trigger['triggerid']]['trigger'] = $trigger;
				$dependencies[$trigger['triggerid']]['depends_on'] = $trigger['dependencies'];
			}
		}

// izvrashenie, delaem castom polja dlja exporta, dlja descriptiona lezem v massiv s osnovniimi triggerami
		foreach($dependencies as $triggerid => $dep_data){
			$dependencies[$triggerid]['trigger']['host_description'] = $triggers[$triggerid]['host'].':'.$triggers[$triggerid]['description'];
			foreach($dep_data['depends_on'] as $dep_triggerid => $dep_trigger){
				$dependencies[$triggerid]['depends_on'][$dep_triggerid]['host_description'] = $dep_trigger['host'].':'.$dep_trigger['description'];
			}
		}

		// foreach($hosts as $hostid => $host){
			// if(!uint_in_array($hostid, $hostids_templates)) unset($hosts[$hostid]['templates']);
			// if(!uint_in_array($hostid, $hostids_items)) unset($hosts[$hostid]['items']);
			// if(!uint_in_array($hostid, $hostids_graphs)) unset($hosts[$hostid]['graphs']);
			// if(!uint_in_array($hostid, $hostids_triggers)) unset($hosts[$hostid]['triggers']);
		// }
		$data = array(
			'hosts' => $hosts,
			'items' => $items,
			'items_applications' => $applications,
			'graphs' => $graphs,
			'graphs_items' => $gitems,
			'templates' => $templates,
			'macros' => $macros,
			'hosts_groups' => $groups,
			'triggers' => $triggers,
			'dependencies' => $dependencies
		);

		$xml = zbxXML::export($data);

		print($xml);
		exit();
	}

	$form = new CForm();
	$form->setMethod('get');
	$form->addVar('groupid', get_request('groupid', 0));
	$cmbConf = new CComboBox('config', 'export.php', 'javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('export.php',S_EXPORT);
		$cmbConf->addItem('import.php',S_IMPORT);
	$form->addItem($cmbConf);

	$export_wdgt = new CWidget();
	$export_wdgt->addPageHeader(S_EXPORT_BIG, $form);

	if($preview){
		$table = new CTableInfo(S_NO_DATA_FOR_EXPORT);
		$table->setHeader(array(S_HOST, S_ELEMENTS));

		$params = array(
			'hostids' => $hostids,
			'templated_hosts' => 1,
			'extendoutput' => 1,
			'select_templates' => 1,
			'select_items' => 1,
			'select_triggers' => 1,
			'select_graphs' => 1,
			'preservekeys' => 1
		);
		$hosts_all = CHost::get($params);

		foreach($hosts_all as $hnum => $host){
			$hostid = $host['hostid'];

			$el_table = new CTableInfo(S_ONLY_HOST_INFO);

			foreach($host['templates'] as $tnum => $template){
				if(isset($hostids_templates[$hostid])){
					$el_table->addRow(array(S_TEMPLATE, $template['host']));
				}
			}
			foreach($host['items'] as $inum => $item){
				if(isset($hostids_items[$hostid])){
					$el_table->addRow(array(S_ITEM, $item['description']));
				}
			}
			foreach($host['triggers'] as $tnum => $trigger){
				if(isset($hostids_triggers[$hostid])){
					$el_table->addRow(array(S_TRIGGER, $trigger['description']));
				}
			}
			foreach($host['graphs'] as $gnum => $graph){
				if(isset($hostids_graphs[$hostid])){
					$el_table->addRow(array(S_GRAPH, $graph['name']));
				}
			}
			$table->addRow(array(new CCol($host['host'], 'top'), $el_table));
		}


		$form = new CForm(null, 'post');
		$form->setName('hosts');
		$form->addVar('update', true);
		$form->addVar('groupid', $_REQUEST['groupid']);
		$form->addVar('hosts', $hostids);
		$form->addVar('templates', $hostids_templates);
		$form->addVar('items', $hostids_items);
		$form->addVar('graphs', $hostids_graphs);
		$form->addVar('triggers', $hostids_triggers);

// GO box {
		$goBox = new CComboBox('go');
		$goBox->addItem('back', S_BACK);
		$goBox->addItem('preview',S_REFRESH);
		$goBox->addItem('export',S_EXPORT);

// goButton name is necessary!!!
		$goButton = new CButton('goButton', S_GO);
		$goButton->setAttribute('id','goButton');

		$form->addItem(array($goBox, $goButton));
// } GO box
		$table->setFooter(new CCol($form));
		$export_wdgt->addItem($table);

		$jsLocale = array(
			'S_CLOSE',
			'S_NO_ELEMENTS_SELECTED'
		);

		zbx_addJSLocale($jsLocale);

		zbx_add_post_js('chkbxRange.pageGoCount = 1;');
	}
	else{
// Page header {
		$form = new CForm(null, 'post');
		$form->setName('export_hosts_frm');

		$params=array();
		$options = array('only_current_node');
		foreach($options as $option) $params[$option] = 1;
		$PAGE_GROUPS = get_viewed_groups(PERM_READ_WRITE, $params);
		$PAGE_HOSTS = get_viewed_hosts(PERM_READ_WRITE, $PAGE_GROUPS['selected'], $params);
		validate_group($PAGE_GROUPS,$PAGE_HOSTS);
		
		$selected_groupid = $PAGE_GROUPS['selected'];
		
		$cmbGroups = new CComboBox('groupid', $selected_groupid, 'javascript: submit();');
		foreach($PAGE_GROUPS['groups'] as $groupid => $name){
			$cmbGroups->addItem($groupid, $name);
		}
		$form->addItem(array(S_GROUP.SPACE, $cmbGroups));

		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');

		$export_wdgt->addHeader(S_HOSTS_BIG, $form);
		$export_wdgt->addHeader($numrows);
// } Page Header


		$form = new CForm(null, 'post');
		$form->setName('hosts_export');
		$form->addVar('groupid', $selected_groupid);

		$table = new CTableInfo(S_NO_HOSTS_DEFINED);
		$table->setHeader(array(
			new CCheckBox('all_hosts', false, "checkAll('".$form->getName()."','all_hosts','hosts');"),
			make_sorting_header(S_NAME, 'host'),
			make_sorting_header(S_DNS, 'dns'),
			make_sorting_header(S_IP, 'ip'),
			make_sorting_header(S_PORT, 'port'),
			make_sorting_header(S_STATUS, 'status'),
			array(new CCheckBox('all_templates', true, 'checkAll("'.$form->getName().'","all_templates","templates");'), S_TEMPLATES),
			array(new CCheckBox('all_items', true, 'checkAll("'.$form->getName().'","all_items","items");'), S_ITEMS),
			array(new CCheckBox('all_triggers', true, 'checkAll("'.$form->getName().'","all_triggers","triggers");'), S_TRIGGERS),
			array(new CCheckBox('all_graphs', true, 'checkAll("'.$form->getName().'","all_graphs","graphs");'), S_GRAPHS)
		));

// get hosts
		$sortfield = getPageSortField('host');
		$sortorder = getPageSortOrder();
		
		$options = array(
			'templated_hosts' => 1,
			'output' => array('hostid', 'status', $sortfield),
			'editable' => 1,
			'groupids' => ($selected_groupid > 0) ? $selected_groupid : null
		);
		$hosts_all = CHost::get($options);

// workaround for correct sorting...
		if(in_array($sortfield, array('dns', 'ip', 'port'))){
			foreach($hosts_all as $hnum => $host){
				if($host['status'] == HOST_STATUS_TEMPLATE){
					$hosts_all[$hnum][$sortfield] = '';
				}
			}
		}
		
// sorting
		order_result($hosts_all, $sortfield, $sortorder);
		$paging = getPagingLine($hosts_all);
//-------

		$options = array(
			'hostids' => zbx_objectValues($hosts_all, 'hostid'),
			'output' => array('hostid', 'host', 'dns', 'ip', 'port', 'status', 'useip'),
			'templated_hosts' => 1,
			'select_templates' => API_OUTPUT_COUNT,
			'select_items' => API_OUTPUT_COUNT,
			'select_triggers' => API_OUTPUT_COUNT,
			'select_graphs' => API_OUTPUT_COUNT,
		);
		$hosts_all = CHost::get($options);

// workaround for correct sorting... 
		if(in_array($sortfield, array('dns', 'ip', 'port'))){
			foreach($hosts_all as $hnum => $host){
				if($host['status'] == HOST_STATUS_TEMPLATE){
					$hosts_all[$hnum][$sortfield] = '';
				}
			}
		}
		
		order_result($hosts_all, $sortfield, $sortorder);
		
		$count_chkbx = 0;
		foreach($hosts_all as $hnum => $host){
			$hostid = $host['hostid'];

			$status = new CCol(host_status2str($host['status']), host_status2style($host['status']));

			$template_cnt = ($host['templates'] > 0)
				? array(new CCheckBox('templates['.$hostid.']', (isset($hostids_templates[$hostid]) || !isset($update)), NULL, $hostid), $host['templates'])
				: '-';

			$item_cnt = ($host['items'] > 0)
				? array(new CCheckBox('items['.$hostid.']', (isset($hostids_items[$hostid]) || !isset($update)), NULL, $hostid), $host['items'])
				: '-';

			$trigger_cnt = ($host['triggers'] > 0)
				? array(new CCheckBox('triggers['.$hostid.']', (isset($hostids_triggers[$hostid]) || !isset($update)), NULL, $hostid), $host['triggers'])
				: '-';

			$graph_cnt = ($host['graphs'] > 0)
				? array(new CCheckBox('graphs['.$hostid.']', (isset($hostids_graphs[$hostid]) || !isset($update)), NULL, $hostid), $host['graphs'])
				: '-';

			if($host['status'] == HOST_STATUS_TEMPLATE){
				$ip = $dns = $port = '-';
			}
			else{
				$ip = empty($host['ip']) ? '-' : $host['ip'];
				$dns = empty($host['dns']) ? '-' : $host['dns'];
				($host['useip'] == 1) ? $ip = bold($ip) : $dns = bold($dns);
				$port = empty($host['port']) ? '-' : $host['port'];
			}

			$checked = (isset($hostids[$hostid]));
			if($checked) $count_chkbx++;

			$table->addRow(array(
				new CCheckBox('hosts['.$hostid.']', $checked, NULL, $hostid),
				$host['host'],
				$dns,
				$ip,
				$port,
				$status,
				$template_cnt,
				$item_cnt,
				$trigger_cnt,
				$graph_cnt
			));
		}

// goBox {
		$goBox = new CComboBox('go');
		$goBox->addItem('export', S_EXPORT);
		$goBox->addItem('preview', S_PREVIEW);

		// goButton name is necessary!!!
		$goButton = new CButton('goButton', S_GO.' ('.$count_chkbx.')');
		$goButton->setAttribute('id','goButton');

		$jsLocale = array(
			'S_CLOSE',
			'S_NO_ELEMENTS_SELECTED'
		);
		zbx_addJSLocale($jsLocale);

		zbx_add_post_js('chkbxRange.pageGoName = "hosts";');

		$footer = get_table_header(array($goBox, $goButton));

		zbx_add_post_js('chkbxRange.pageGoCount = '.$count_chkbx.';');
// } goBox

		$table = array($paging, $table, $paging, $footer);
		$form->addItem($table);
		$export_wdgt->addItem($form);
	}

	$export_wdgt->show();

include_once('include/page_footer.php');
?>