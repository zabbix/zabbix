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

	require_once "include/config.inc.php";
	require_once "include/discovery.inc.php";
	$page['hist_arg'] = array('druleid');

	$page["file"] = "discovery.php";
	$page["title"] = "S_STATUS_OF_DISCOVERY";

include_once "include/page_header.php";


//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"druleid"=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),
	);

	check_fields($fields);
	validate_sort_and_sortorder();

	$r_form = new CForm();
	$r_form->SetMethod('get');
	
	$druleid = get_request('druleid', 0);

	$cmbDRules = new CComboBox('druleid',$druleid,'submit()');
	$cmbDRules->AddItem(0,S_ALL_SMALL);
	$db_drules = DBselect('select distinct druleid,name from drules where '.DBin_node('druleid').' order by name');
	while($drule = DBfetch($db_drules))
		$cmbDRules->AddItem(
				$drule['druleid'],
				get_node_name_by_elid($drule['druleid']).$drule['name']
				);
	$r_form->AddItem(array(S_DISCOVERY_RULE.SPACE,$cmbDRules));

	show_table_header(S_STATUS_OF_DISCOVERY_BIG, $r_form);

	$services = array();

	$db_dservices = DBselect('SELECT s.type,s.port FROM dservices s,dhosts h'.
			' WHERE '.DBin_node('s.dserviceid').
			' AND s.dhostid=h.dhostid'.
			($druleid > 0 ? ' AND h.druleid='.$druleid : ''));
	while ($dservice = DBfetch($db_dservices)) {
		$service_name = discovery_check_type2str($dservice['type']).':'.$dservice['port'];
		$services[$service_name] = 1;
	}

	ksort($services);

	$header = array(
			is_show_subnodes() ? new CCol(S_NODE, 'center') : null,
			new CCol(make_sorting_link(S_HOST,'dhostid'), 'center'),
			new CCol(array(S_UPTIME.'/',BR(),S_DOWNTIME),'center')
			);

	foreach ($services as $name => $foo) {
		$header[] = new CImg('vtext.php?text='.$name);
	}

	$table  = new CTableInfo();
	$table->SetHeader($header,'vertical_header');

	$db_drules = DBselect('select distinct druleid,name from drules where '.DBin_node('druleid').
			($druleid > 0 ? ' and druleid='.$druleid : '').
			' order by name');
	while($drule = DBfetch($db_drules)) {
		$discovery_info = array();

		$db_dhosts = DBselect('SELECT dhostid,druleid,ip,status,lastup,lastdown '.
				' FROM dhosts WHERE '.DBin_node('dhostid').
				' AND druleid='.$drule['druleid'].
				order_by('dhostid','status,ip'));
		while($dhost = DBfetch($db_dhosts)){
			$class = 'enabled';
			$time = 'lastup';
			if(DHOST_STATUS_DISABLED == $dhost['status']){
				$class = 'disabled';
				$time = 'lastdown';
			}

			$discovery_info[$dhost['ip']] = array('class' => $class, 'time' => $dhost[$time], 'druleid' => $dhost['druleid']);

			$db_dservices = DBselect('SELECT type,port,status,lastup,lastdown FROM dservices '.
					' WHERE dhostid='.$dhost['dhostid'].
					' order by status,type,port');
			while($dservice = DBfetch($db_dservices)){
				$class = 'active';
				$time = 'lastup';

				if(DSVC_STATUS_DISABLED == $dservice['status']){
					$class = 'inactive';
					$time = 'lastdown';
				}

				$service_name = discovery_check_type2str($dservice['type']).':'.$dservice['port'];

				$discovery_info
					[$dhost['ip']]
					['services']
					[$service_name] = array('class' => $class, 'time' => $dservice[$time]);
			}
		}

		if ($druleid == 0 && !empty($discovery_info)) {
			$table->AddRow(array(get_node_name_by_elid($drule['druleid']),$drule['name'],'','',''));
		}

		foreach($discovery_info as $ip => $h_data)
		{
			$table_row = array(
				get_node_name_by_elid($h_data['druleid']),
				new CSpan($ip, $h_data['class']),
				new CSpan(convert_units(time() - $h_data['time'], 'uptime'), $h_data['class'])
				);
			foreach($services as $name => $foo)
			{
				$class = null; $time = SPACE;

				if(isset($h_data['services'][$name]))
				{
					$class = $h_data['services'][$name]['class'];
					$time = $h_data['services'][$name]['time'];
				}
				$table_row[] = new CCol(SPACE, $class);
			}
			$table->AddRow($table_row);
		}
	}

	$table->Show();
?>
<?php

include_once "include/page_footer.php";

?>
