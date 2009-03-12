<?php
/* 
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
	require_once "include/config.inc.php";
	require_once "include/hosts.inc.php";
	require_once "include/forms.inc.php";

	$page["title"] = "S_HOST_PROFILES";
	$page["file"] = "hostprofiles.php";
	$page['hist_arg'] = array('groupid','hostid');
	
include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'groupid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		'hostid'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,	NULL),
		'prof_type'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	null,	NULL),
	);
	check_fields($fields);
	validate_sort_and_sortorder('h.host',ZBX_SORT_UP);
	
	$reset_hostid = (isset($_REQUEST['hostid'])) ? false : true;
	
	$params = array();
	$options = array('allow_all_hosts','real_hosts');
	if(!$ZBX_WITH_SUBNODES)	array_push($options,'only_current_node');	
	foreach($options as $option) $params[$option] = 1;

	$PAGE_GROUPS = get_viewed_groups(PERM_READ_ONLY, $params);
	$PAGE_HOSTS = get_viewed_hosts(PERM_READ_ONLY, $PAGE_GROUPS['selected'], $params);
	validate_group($PAGE_GROUPS, $PAGE_HOSTS, $reset_hostid);
		
	$r_form = new CForm();
	$r_form->setMethod('get');

/// +++ create "Host Groups" combobox +++ ///
	$cmbGroups = new CComboBox('groupid',$PAGE_GROUPS['selected'],'javascript: submit();');
	$cmbGroups->addItem(0, S_ALL_S);

	//select groups where hosts with profiles exists
	$sql = 'SELECT hg.groupid, g.name '.
			' FROM hosts_profiles p, hosts_profiles_ext pe, hosts_groups hg, groups g'.
			' WHERE (hg.hostid=p.hostid OR hg.hostid=pe.hostid) '.
				' AND g.groupid=hg.groupid '.
				' AND '.DBcondition('hg.groupid', $PAGE_GROUPS['groupids']).
			' GROUP BY hg.groupid';

			$result = DBselect($sql);
	while($row = DBfetch($result)) {
		$cmbGroups->addItem($row['groupid'], get_node_name_by_elid($row['groupid']).$row['name']);
	}
	$r_form->addItem(array(S_GROUP.SPACE,$cmbGroups));
/// --- --- ///

/// +++ find out what type of profile selected group hosts contains	+++ ///
/// if they contain only one type profile, combobox with Profile types won't appear ///
	$profile_types = 0;
	$sql_where = '';
	if($_REQUEST['groupid'] > 0){
		$sql_where = ' AND hg.groupid='.$_REQUEST['groupid'];
	}
	else {
		$sql_where = ' AND '.DBcondition('hg.groupid', $PAGE_GROUPS['groupids']);

	}
	$sql = 'SELECT p.hostid'.
			' FROM hosts_profiles p, hosts_groups hg'.
			' WHERE hg.hostid=p.hostid '.
				$sql_where;
	$result = DBselect($sql,1);
	if(DBfetch($result)) $profile_types += 1;

	$sql = 'SELECT pe.hostid'.
			' FROM hosts_profiles_ext pe, hosts_groups hg'.
			' WHERE hg.hostid=pe.hostid '.
				$sql_where;				
	$result = DBselect($sql,1);
	if(DBfetch($result)) $profile_types += 2;
	
	switch($profile_types) {
		case 1:
			$prof_type = 0;
		break;
		case 2:
			$prof_type = 1;
		break;
		case 3:
			$prof_type = get_request('prof_type',0);
			$cmbProf = new CComboBox('prof_type', $prof_type, 'javascript: submit();');
			$cmbProf->additem(0, S_NORMAL);
			$cmbProf->additem(1, S_EXTENDED);
			$r_form->addItem(array(SPACE.S_HOST_PROFILES.SPACE,$cmbProf));
		break;
	}
/// --- --- ///	
	
	show_table_header(S_HOST_PROFILES_BIG, $r_form);
?>

<?php
	if(isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0)){
		echo SBR;

		if($prof_type){
			insert_host_profile_ext_form();
		}
		else{
			insert_host_profile_form();
		}
	}
	else{
		$table = new CTableInfo();
		if($prof_type){
			$table->setHeader(array(
				is_show_subnodes() ? make_sorting_link(S_NODE,'h.hostid') : null,
				make_sorting_link(S_HOST,'h.host'),
			   ($_REQUEST['groupid'] > 0)?null:make_sorting_link(S_GROUP,'g.name'),
				make_sorting_link(S_DEVICE_OS_SHORT,'hpe.device_os_short'),
				make_sorting_link(S_DEVICE_HW_ARCH,'hpe.device_hw_arch'),
				make_sorting_link(S_DEVICE_TYPE,'hpe.device_type'),
				make_sorting_link(S_DEVICE_STATUS,'hpe.device_status'))
			);
			
			$sql_where = '';
			if($_REQUEST['groupid'] > 0){
				$sql_where = ' AND hg.groupid='.$_REQUEST['groupid'];
			}
			$sql='SELECT DISTINCT g.name, h.hostid,h.host,hpe.device_os_short,hpe.device_hw_arch,hpe.device_type,hpe.device_status'.
				' FROM hosts h,hosts_profiles_ext hpe,hosts_groups hg,groups g '.
				' WHERE h.hostid=hpe.hostid '.
					' AND h.hostid=hg.hostid '.
					' AND g.groupid=hg.groupid '.
					' AND '.DBcondition('h.hostid',$PAGE_HOSTS['hostids']).
					$sql_where.
				order_by('h.host,h.hostid,g.name,hpe.device_os_short,hpe.device_hw_arch,hpe.device_type,hpe.device_status');
			$result=DBselect($sql);
			while($row=DBfetch($result)){
				$table->AddRow(array(
					get_node_name_by_elid($row['hostid']),
					new CLink($row["host"],"?hostid=".$row["hostid"].url_param("groupid").'&prof_type='.$prof_type,"action"),
					($_REQUEST["groupid"] > 0)?null:$row["name"],
					$row["device_os_short"],
					$row["device_hw_arch"],
					$row["device_type"],
					$row["device_status"]
				));
			}

		}
		else{
			$table->setHeader(array(
				is_show_subnodes() ? make_sorting_link(S_NODE,'h.hostid') : null,
				make_sorting_link(S_HOST,'h.host'),
				make_sorting_link(S_NAME,'p.name'),
				make_sorting_link(S_OS,'p.os'),
				make_sorting_link(S_SERIALNO,'p.serialno'),
				make_sorting_link(S_TAG,'p.tag'),
				make_sorting_link(S_MACADDRESS,'p.macaddress'))
			);
			
			$sql_from = '';
			$sql_where = '';
			if($_REQUEST['groupid'] > 0){
				$sql_from = ', hosts_groups hg ';
				$sql_where = ' and h.hostid=hg.hostid AND hg.groupid='.$_REQUEST['groupid'];
			}
			$sql='SELECT h.hostid,h.host,p.name,p.os,p.serialno,p.tag,p.macaddress'.
				' FROM hosts h,hosts_profiles p '.$sql_from.
				' WHERE h.hostid=p.hostid'.
					' and '.DBcondition('h.hostid',$PAGE_HOSTS['hostids']).
					$sql_where.
				order_by('h.host,h.hostid,p.name,p.os,p.serialno,p.tag,p.macaddress');
			$result=DBselect($sql);
			while($row=DBfetch($result)){
				$table->AddRow(array(
					get_node_name_by_elid($row['hostid']),
					new CLink($row["host"],'?hostid='.$row['hostid'].url_param('groupid').'&prof_type='.$prof_type,"action"),
					$row["name"],
					$row["os"],
					$row["serialno"],
					$row["tag"],
					$row["macaddress"]
				));
			}
		}
		$table->show();
	}

include_once "include/page_footer.php";
?>