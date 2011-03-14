<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

require_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields=array(
	'groupid' =>	array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
	'hostid' =>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
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
// permission check, imo should be removed in future.
if($_REQUEST['hostid'] > 0){
	$res = API::Host()->get(array(
		'real_hosts' => 1,
		'hostids' => $_REQUEST['hostid']
	));
	if(empty($res)) access_deny();
}


$hostprof_wdgt = new CWidget();
$hostprof_wdgt->addPageHeader(_('HOST PROFILES'));


if($_REQUEST['hostid'] > 0){
	$hostprof_wdgt->addItem(insert_host_profile_form());
}
else{
	$r_form = new CForm('get');
	$r_form->addItem(array(_('Group'), $pageFilter->getGroupsCB(true)));
	$hostprof_wdgt->addHeader(_('HOSTS'), $r_form);

	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');
	$hostprof_wdgt->addHeader($numrows);

	$table = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? make_sorting_header(_('Node'), 'hostid') : null,
		make_sorting_header(_('Host'), 'host'),
		_('Group'),
		make_sorting_header(_('Name'), 'pr_name'),
		make_sorting_header(_('Type'), 'pr_type'),
		make_sorting_header(_('OS'), 'pr_os'),
		make_sorting_header(_('Serial number A'), 'pr_serialno_a'),
		make_sorting_header(_('Tag'), 'pr_tag'),
		make_sorting_header(_('MAC address A'), 'pr_macaddress_a'))
	);


	if($pageFilter->groupsSelected){
		$options = array(
			'output' => array('hostid', 'host'),
			'selectProfile' => true,
			'withProfiles' => true,
			'selectGroups' => API_OUTPUT_EXTEND,
			'limit' => ($config['search_limit'] + 1)
		);
		if($pageFilter->groupid > 0)
			$options['groupids'] = $pageFilter->groupid;

		$hosts = API::Host()->get($options);

// unset hosts without profiles, and copy some profile fields to the uppers array level for sorting
		foreach($hosts as $num => $host){
			$hosts[$num]['pr_name'] = $host['profile']['name'];
			$hosts[$num]['pr_type'] = $host['profile']['type'];
			$hosts[$num]['pr_os'] = $host['profile']['os'];
			$hosts[$num]['pr_serialno_a'] = $host['profile']['serialno_a'];
			$hosts[$num]['pr_tag'] = $host['profile']['tag'];
			$hosts[$num]['pr_macaddress_a'] = $host['profile']['macaddress_a'];
		}

		order_result($hosts, getPageSortField('host'), getPageSortOrder());
		$paging = getPagingLine($hosts);

		foreach($hosts as $host){
			$host_groups = array();
			foreach($host['groups'] as $group){
				$host_groups[] = $group['name'];
			}
			natsort($host_groups);
			$host_groups = implode(', ', $host_groups);

			$row = array(
				get_node_name_by_elid($host['hostid']),
				new CLink($host['host'],'?hostid='.$host['hostid'].url_param('groupid')),
				$host_groups,
				zbx_str2links($host['profile']['name']),
				zbx_str2links($host['profile']['type']),
				zbx_str2links($host['profile']['os']),
				zbx_str2links($host['profile']['serialno_a']),
				zbx_str2links($host['profile']['tag']),
				zbx_str2links($host['profile']['macaddress_a']),
			);

			$table->addRow($row);
		}
	}

	$table = array($paging, $table, $paging);
	$hostprof_wdgt->addItem($table);
}


$hostprof_wdgt->show();


include_once('include/page_footer.php');
?>
