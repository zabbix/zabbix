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
	require_once('include/forms.inc.php');

	$page['title'] = 'S_HOST_PROFILES';
	$page['file'] = 'hostprofiles.php';
	$page['hist_arg'] = array('groupid', 'hostid');

	include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		'hostid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		'prof_type'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	NULL,	NULL),
	);

	check_fields($fields);
	validate_sort_and_sortorder('host', ZBX_SORT_UP);
?>
<?php

	$options = array(
		'groups' => array(
			'real_hosts' => 1,
		),
		'groupid' => get_request('groupid', null),
	);
	$pageFilter = new CPageFilter($options);
	$_REQUEST['groupid'] = $pageFilter->groupid;


	$_REQUEST['hostid'] = get_request('hostid', 0);
// permission check, imo should be remuved in future.
	if($_REQUEST['hostid'] > 0){
		$res = CHost::get(array('real_hosts' => 1, 'hostids' => $_REQUEST['hostid']));
		if(empty($res)) access_deny();
	}


	$_REQUEST['prof_type'] = get_request('prof_type', 0);

	$hostprof_wdgt = new CWidget();

	$profile_form = new CForm(null, 'get');
	$cmbProf = new CComboBox('prof_type', $_REQUEST['prof_type'], 'javascript: submit();');
	$cmbProf->additem(0, S_NORMAL);
	$cmbProf->additem(1, S_EXTENDED);
	$profile_form->addItem(array(SPACE.S_HOST_PROFILES.SPACE, $cmbProf));

	$hostprof_wdgt->addPageHeader(S_HOST_PROFILES_BIG, $profile_form);


	if($_REQUEST['hostid'] > 0){
		if($_REQUEST['prof_type']){
			$hostprof_wdgt->addItem(insert_host_profile_ext_form());
		}
		else{
			$hostprof_wdgt->addItem(insert_host_profile_form());
		}
	}
	else{
		$sortfield = getPageSortField('host');
		$sortorder = getPageSortOrder();
		$options = array(
			'extendoutput' => 1,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'select_profile' => 1,
			'select_groups' => 1,
			'limit' => ($config['search_limit']+1)
		);
		if($pageFilter->groupsSelected){
			if($pageFilter->groupid > 0)
				$options['groupids'] = $pageFilter->groupid;
		}
		else{
			$options['groupids'] = array();
		}
		$hosts = CHost::get($options);


// unset hosts without profiles, and copy some profile fileds to the uppers array level for sorting
		$pr = ($_REQUEST['prof_type'] == 0) ? 'profile' : 'profile_ext';
		$profile = array();
		foreach($hosts as $num => $host){
			if(empty($host[$pr])){
				unset($hosts[$num]);
			}
			else{
				if($_REQUEST['prof_type'] == 0){
					$hosts[$num]['pr_name'] = $host['profile']['name'];
					$hosts[$num]['pr_os'] = $host['profile']['os'];
					$hosts[$num]['pr_serialno'] = $host['profile']['serialno'];
					$hosts[$num]['pr_tag'] = $host['profile']['tag'];
					$hosts[$num]['pr_macaddress'] = $host['profile']['macaddress'];
				}
				else{
					$hosts[$num]['pre_device_os_short'] = $host['profile_ext']['device_os_short'];
					$hosts[$num]['pre_device_hw_arch'] = $host['profile_ext']['device_hw_arch'];
					$hosts[$num]['pre_device_type'] = $host['profile_ext']['device_type'];
					$hosts[$num]['pre_device_status'] = $host['profile_ext']['device_status'];
				}
			}
		}

		$r_form = new CForm(null, 'get');
		$r_form->addVar('prof_type', $_REQUEST['prof_type']);
		$r_form->addItem(array(S_GROUP.SPACE, $pageFilter->getGroupsCB(true)));

		$hostprof_wdgt->addHeader(S_HOSTS_BIG, $r_form);

		$numrows = new CDiv();
		$numrows->setAttribute('name', 'numrows');
		$hostprof_wdgt->addHeader($numrows);


		order_result($hosts, $sortfield, $sortorder);
		$paging = getPagingLine($hosts);

		$table = new CTableInfo();

		if(0 == $_REQUEST['prof_type']){
			$table->setHeader(array(
				is_show_all_nodes() ? make_sorting_header(S_NODE, 'hostid') : null,
				make_sorting_header(S_HOST, 'host'),
				S_GROUP,
				make_sorting_header(S_NAME, 'pr_name'),
				make_sorting_header(S_OS, 'pr_os'),
				make_sorting_header(S_SERIALNO, 'pr_serialno'),
				make_sorting_header(S_TAG, 'pr_tag'),
				make_sorting_header(S_MACADDRESS, 'pr_macaddress'))
			);
		}
		else{
			$table->setHeader(array(
				is_show_all_nodes() ? make_sorting_header(S_NODE, 'hostid') : null,
				make_sorting_header(S_HOST, 'host'),
				S_GROUP,
				make_sorting_header(S_DEVICE_OS_SHORT, 'pre_device_os_short'),
				make_sorting_header(S_DEVICE_HW_ARCH, 'pre_device_hw_arch'),
				make_sorting_header(S_DEVICE_TYPE, 'pre_device_type'),
				make_sorting_header(S_DEVICE_STATUS, 'pre_device_status'))
			);
		}

		foreach($hosts as $host){
			$host_groups = array();
			foreach($host['groups'] as $group){
				$host_groups[] = $group['name'];
			}
			natcasesort($host_groups);
			$host_groups = implode(', ', $host_groups);

			$row = array(
				get_node_name_by_elid($host['hostid']),
				new CLink($host['host'],'?hostid='.$host['hostid'].url_param('groupid').'&prof_type='.$_REQUEST['prof_type']),
				$host_groups);
			if(0 == $_REQUEST['prof_type']){
				$row[] = zbx_str2links($host['profile']['name']);
				$row[] = zbx_str2links($host['profile']['os']);
				$row[] = zbx_str2links($host['profile']['serialno']);
				$row[] = zbx_str2links($host['profile']['tag']);
				$row[] = zbx_str2links($host['profile']['macaddress']);
			}
			else{
				$row[] = zbx_str2links($host['profile_ext']['device_os_short']);
				$row[] = zbx_str2links($host['profile_ext']['device_hw_arch']);
				$row[] = zbx_str2links($host['profile_ext']['device_type']);
				$row[] = zbx_str2links($host['profile_ext']['device_status']);
			}

			$table->addRow($row);
		}

		$table = array($paging, $table, $paging);
		$hostprof_wdgt->addItem($table);
	}

	$hostprof_wdgt->show();


include_once('include/page_footer.php');
?>
