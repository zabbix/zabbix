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
	require_once "include/config.inc.php";
	require_once "include/discovery.inc.php";

	$page["file"] = "discovery.php";
	$page["title"] = "S_STATUS_OF_DISCOVERY";

include_once "include/page_header.php";

?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		"druleid"=>	array(T_ZBX_INT, O_OPT, P_SYS,	DB_ID, null),
	);

	check_fields($fields);

?>
<?php
	$r_form = new CForm();

	$druleid = get_request('druleid', 0);

	$cmbDRules = new CComboBox('druleid',$druleid,'submit()');
	$cmbDRules->AddItem(0,S_ALL_SMALL);
	$db_drules = DBselect('select distinct * from drules where '.DBin_node('druleid').' order by name');
	while($drule_data = DBfetch($db_drules))
		$cmbDRules->AddItem(
				$drule_data['druleid'],
				get_node_name_by_elid($drule_data['druleid']).$drule_data['name']
				);
	$r_form->AddItem(array(S_DISCOVERY_RULE.SPACE,$cmbDRules));

	show_table_header(S_STATUS_OF_DISCOVERY_BIG, $r_form);
?>
<?php
	$db_dhosts = DBselect('select * from dhosts'.
		($druleid > 0 ? ' where druleid='.$druleid : '').
		' order by status,ip'
	);

	$services = array();
	$discovery_info = array();

	while($drule_data = DBfetch($db_dhosts))
	{
		$class = 'enabled';
		$time = 'lastup';
		if(DHOST_STATUS_DISABLED == $drule_data['status'])
		{
			$class = 'disabled';
			$time = 'lastdown';
		}

		$discovery_info[$drule_data['ip']] = array('class' => $class, 'time' => $drule_data[$time], 'druleid' => $drule_data['druleid']);

		$db_dservices = DBselect('select * from dservices where dhostid='.$drule_data['dhostid'].' order by status,type,port');
		while($dservice_data = DBfetch($db_dservices))
		{
			$class = 'active';
			$time = 'lastup';

			if(DSVC_STATUS_DISABLED == $dservice_data['status'])
			{
				$class = 'inactive';
				$time = 'lastdown';
			}

			$service_name = discovery_check_type2str($dservice_data['type']).':'.$dservice_data['port'];

			$services[$service_name] = 1;

			$discovery_info
				[$drule_data['ip']]
				['services']
				[$service_name] = array('class' => $class, 'time' => $dservice_data[$time]);
		}
	}

	ksort($services);

	$header = array(
		is_show_subnodes() ? S_NODE : null,
		new CCol(S_HOST, 'center'),
		new CCol(S_UPTIME.'/'.BR.S_DOWNTIME,'center')
		);

	foreach($services as $name => $foo)
	{
		$header[] = new CImg('vtext.php?text='.$name);
	}

	$table  = new CTableInfo();
	$table->SetHeader($header,'vertical_header');

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

	$table->Show();
?>
<?php

include_once "include/page_footer.php";

?>
