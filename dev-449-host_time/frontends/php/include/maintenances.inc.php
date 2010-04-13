<?php
/*
** ZABBIX
** Copyright (C) 2000-2008 SIA Zabbix
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
function get_accessible_maintenance_by_user($perm,$perm_res=null,$nodeid=null,$hostid=null,$cache=1){
	global $USER_DETAILS;
	static $available_maintenances;

	$result = array();
	if(is_null($perm_res)) $perm_res = PERM_RES_IDS_ARRAY;
	$nodeid_str =(is_array($nodeid))?implode('',$nodeid):strval($nodeid);
	$hostid_str =(is_array($hostid))?implode('',$hostid):strval($hostid);

	if($cache && isset($available_maintenances[$perm][$perm_res][$nodeid_str][$hostid_str])){
		return $available_maintenances[$perm][$perm_res][$nodeid_str][$hostid_str];
	}

	$available_hosts = get_accessible_hosts_by_user($USER_DETAILS, $perm, PERM_RES_IDS_ARRAY, $nodeid);

	$denied_maintenances = array();
	$available_maintenances = array();

	$sql =	'SELECT DISTINCT m.maintenanceid '.
			' FROM maintenances m, maintenances_hosts mh, maintenances_groups mg, hosts_groups hg '.
			' WHERE '.DBcondition('hg.hostid',$available_hosts,true).
				(!empty($hostid)?' AND hg.hostid='.$hostid:'').
				' AND ('.
					'(mh.hostid=hg.hostid AND m.maintenanceid=mh.maintenanceid)'.
					' OR (mg.groupid=hg.groupid AND m.maintenanceid=mg.maintenanceid))';
//SDI($sql);
	$db_maintenances = DBselect($sql);
	while($maintenance = DBfetch($db_maintenances)){
		$denied_maintenances[] = $maintenance['maintenanceid'];
	}

	$sql = 'SELECT m.maintenanceid '.
			' FROM maintenances m '.
			' WHERE '.DBin_node('m.maintenanceid').
				' AND '.DBcondition('m.maintenanceid',$denied_maintenances,true);
//SDI($sql);
	$db_maintenances = DBselect($sql);
	while($maintenance = DBfetch($db_maintenances)){
		$result[$maintenance['maintenanceid']] = $maintenance['maintenanceid'];
	}

	if(PERM_RES_STRING_LINE == $perm_res){
		if(count($result) == 0)
			$result = '-1';
		else
			$result = implode(',',$result);
	}

	$available_maintenances[$perm][$perm_res][$nodeid_str][$hostid_str] = $result;

return $result;
}

// function: get_maintenance_by_maintenanceid
// author: Aly
function get_maintenance_by_maintenanceid($maintenanceid){
	$sql = 'SELECT m.* '.
			' FROM maintenances m '.
			' WHERE '.DBin_node('m.maintenanceid').
				' AND maintenanceid='.$maintenanceid;

	$maintenance = DBfetch(DBselect($sql));
return $maintenance;
}

// function: add_maintenance
// author: Aly
function add_maintenance($maintenance = array()){
	$db_fields = array('name' => null,
						'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
						'description'=>	'',
						'active_since'=> time(),
						'active_till' => time()+86400,
					);

	if(!check_db_fields($db_fields, $maintenance)){
		error('Incorrect arguments pasted to function [add_maintenance]');
		return false;
	}

	$maintenanceid = get_dbid('maintenances','maintenanceid');

	$result = DBexecute('INSERT INTO maintenances (maintenanceid,name,maintenance_type,description,active_since,active_till) '.
				' VALUES ('.$maintenanceid.','.
						zbx_dbstr($maintenance['name']).','.
						$maintenance['maintenance_type'].','.
						zbx_dbstr($maintenance['description']).','.
						$maintenance['active_since'].','.
						$maintenance['active_till'].')');

return $result?$maintenanceid:false;
}

// function: update_maintenance
// author: Aly
function update_maintenance($maintenanceid, $maintenance = array()){
	$sql = 'SELECT * FROM maintenances WHERE maintenanceid='.$maintenanceid;
	if(!$db_maintenance = DBfetch(DBselect($sql))){
		return false;
	}

	if(!check_db_fields($db_maintenance, $maintenance)){
		error('Incorrect arguments pasted to function [update_maintenance]');
		return false;
	}

	$sql = 'UPDATE maintenances SET '.
				' name='.zbx_dbstr($maintenance['name']).','.
				' maintenance_type='.$maintenance['maintenance_type'].','.
				' description='.zbx_dbstr($maintenance['description']).','.
				' active_since='.$maintenance['active_since'].','.
				' active_till='.$maintenance['active_till'].
			' WHERE maintenanceid='.$maintenanceid;
	$result = DBexecute($sql);

return $result;
}

// function: delete_maintenance
// author: Aly
function delete_maintenance($maintenanceids){
	zbx_value2array($maintenanceids);

	delete_timeperiods_by_maintenanceid($maintenanceids);

	DBexecute('DELETE FROM maintenances_hosts WHERE '.DBcondition('maintenanceid',$maintenanceids));
	DBexecute('DELETE FROM maintenances_groups WHERE '.DBcondition('maintenanceid',$maintenanceids));
	$result = DBexecute('DELETE FROM maintenances WHERE '.DBcondition('maintenanceid',$maintenanceids));

return $result;
}

function save_maintenance_host_links($maintenanceid, $hostids){
	$result = true;

	DBexecute('DELETE FROM maintenances_hosts WHERE maintenanceid='.$maintenanceid);
	foreach($hostids as $id => $hostid)
	{
		$maintenance_hostid = get_dbid('maintenances_hosts','maintenance_hostid');

		$result = DBexecute('INSERT INTO maintenances_hosts (maintenance_hostid,maintenanceid,hostid)'.
				' VALUES ('.$maintenance_hostid.','.$maintenanceid.','.$hostid.')');
	}

return $result;
}

function save_maintenance_group_links($maintenanceid, $groupids){
	$result = true;

	DBexecute('DELETE FROM maintenances_groups WHERE maintenanceid='.$maintenanceid);
	foreach($groupids as $id => $groupid)
	{
		$maintenance_groupid = get_dbid('maintenances_groups','maintenance_groupid');

		$result = DBexecute('INSERT INTO maintenances_groups (maintenance_groupid,maintenanceid,groupid)'.
				' VALUES ('.$maintenance_groupid.','.$maintenanceid.','.$groupid.')');
	}

return $result;

}
// function: add_timeperiod
// author: Aly
function add_timeperiod($timeperiod = array()){
	$db_fields = array('timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
						'every' =>	0,
						'month' =>	0,
						'dayofweek' =>	0,
						'day' =>	0,
						'start_time' =>	0,
						'period' =>	3600,
						'start_date' =>	time()
					);

	if(!check_db_fields($db_fields, $timeperiod)){
		error('Incorrect arguments pasted to function [add_timeperiod]');
		return false;
	}

	$timeperiodid = get_dbid('timeperiods','timeperiodid');

	$result = DBexecute('INSERT INTO timeperiods (timeperiodid,timeperiod_type,every,month,dayofweek,day,start_time,period,start_date) '.
				' VALUES ('.$timeperiodid.','.
						$timeperiod['timeperiod_type'].','.
						$timeperiod['every'].','.
						$timeperiod['month'].','.
						$timeperiod['dayofweek'].','.
						$timeperiod['day'].','.
						$timeperiod['start_time'].','.
						$timeperiod['period'].','.
						$timeperiod['start_date'].')');

return $result?$timeperiodid:false;
}

// function: delete_timeperiods_by_maintenanceid
// author: Aly
function delete_timeperiods_by_maintenanceid($maintenanceids){
	zbx_value2array($maintenanceids);

	$timeperiods = array();
	$sql = 'SELECT DISTINCT mw.maintenanceid, tp.timeperiodid '.
			' FROM timeperiods tp, maintenances_windows mw '.
			' WHERE '.DBcondition('mw.maintenanceid',$maintenanceids).
				' AND tp.timeperiodid=mw.timeperiodid ';

	$db_timeperiods = DBselect($sql);
	while($timeperiod = DBfetch($db_timeperiods)){
		$timeperiods[$timeperiod['timeperiodid']] = $timeperiod['timeperiodid'];
	}

	$result = delete_timeperiod($timeperiods);

return $result;
}


// function: delete_timeperiod
// author: Aly
function delete_timeperiod($timeperiodids){
	zbx_value2array($timeperiodids);

	DBexecute('DELETE FROM timeperiods WHERE '.DBcondition('timeperiodid',$timeperiodids));
	$result = DBexecute('DELETE FROM maintenances_windows WHERE '.DBcondition('timeperiodid',$timeperiodids));

return $result;
}

// function: save_maintenances_winodws
// author: Aly
function save_maintenances_windows($maintenanceid, $timeperiodids){
	zbx_value2array($timeperiodids);
	$result = true;

	DBexecute('DELETE FROM maintenances_windows WHERE maintenanceid='.$maintenanceid);

	foreach($timeperiodids as $id => $timeperiodid){
		$maintenance_timeperiodid = get_dbid('maintenances_windows', 'maintenance_timeperiodid');
		$sql = 'INSERT INTO maintenances_windows (maintenance_timeperiodid,timeperiodid,maintenanceid) '.
				' VALUES ('.$maintenance_timeperiodid.','.$timeperiodid.','.$maintenanceid.')';
		$result = DBexecute($sql);
	}
return $result;
}

// function: timeperiod_type2str
// author: Aly
function timeperiod_type2str($timeperiod_type){
	switch($timeperiod_type){
		case TIMEPERIOD_TYPE_ONETIME:
			$str = S_ONE_TIME_ONLY;
			break;
		case TIMEPERIOD_TYPE_DAILY:
			$str = S_DAILY;
			break;
		case TIMEPERIOD_TYPE_WEEKLY:
			$str = S_WEEKLY;
			break;
		case TIMEPERIOD_TYPE_MONTHLY:
			$str = S_MONTHLY;
			break;
		default:
			$str = S_UNKNOWN;
	}
return $str;
}

// function: shedule2str
// author: Aly
function shedule2str($timeperiod){

	$timeperiod['hour'] = floor($timeperiod['start_time'] / 3600);
	$timeperiod['minute'] = floor(($timeperiod['start_time'] - ($timeperiod['hour'] * 3600)) / 60);

	if($timeperiod['hour'] < 10)	$timeperiod['hour']='0'.$timeperiod['hour'];
	if($timeperiod['minute'] < 10)	$timeperiod['minute']='0'.$timeperiod['minute'];


	$str = S_AT.SPACE.$timeperiod['hour'].':'.$timeperiod['minute'].SPACE.S_ON_SMALL.SPACE;

	if($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME){
		$str = S_AT.SPACE.date('H',$timeperiod['start_date']).':'.date('i',$timeperiod['start_date']).SPACE.S_ON_SMALL.SPACE.date(S_DATE_FORMAT_YMD,$timeperiod['start_date']);
	}
	else if($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY){
		$str .= S_EVERY_SMALL.SPACE.(($timeperiod['every'] > 1) ? $timeperiod['every'].SPACE.S_DAYS_SMALL : S_DAY_SMALL);
	}
	else if($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY){
		$days = '';

		$dayofweek = zbx_num2bitstr($timeperiod['dayofweek'],true);
		$length = zbx_strlen($dayofweek);
		for($i=0; $i < $length; $i++){
			if($dayofweek[$i] == 1){
				if(!zbx_empty($days)) $days.=', ';
				$days.= get_str_dayofweek($i+1);
			}
		}
		$str.= S_EVERY_SMALL.SPACE.$days.SPACE.S_OF_EVERY_SMALL.SPACE.(($timeperiod['every']>1)?$timeperiod['every'].SPACE.S_WEEKS_SMALL:S_WEEK_SMALL);
	}
	else if($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY){
		$months = '';


		$month = zbx_num2bitstr($timeperiod['month'],true);
		$length = zbx_strlen($month);
		for($i=0; $i < $length; $i++){
			if($month[$i] == 1){
				if(!zbx_empty($months)) $months.=', ';
				$months.= get_str_month($i+1);
			}
		}

		if($timeperiod['dayofweek']>0){
			$days = '';
			$dayofweek = zbx_num2bitstr($timeperiod['dayofweek'],true);
			$length = zbx_strlen($dayofweek);
			for($i=0; $i < $length; $i++){
				if($dayofweek[$i] == 1){
					if(!zbx_empty($days)) $days.=', ';
					$days.= get_str_dayofweek($i+1);
				}
			}

			$every = '';
			switch($timeperiod['every']){
				case 1:	$every = S_FIRST; break;
				case 2:	$every = S_SECOND; break;
				case 3: $every = S_THIRD; break;
				case 4: $every = S_FOURTH; break;
				case 5: $every = S_LAST; break;
			}

			$str.= $every.SPACE.$days.SPACE.S_OF_EVERY_SMALL.SPACE.$months;
		}
		else{
			$str.= S_DAY_SMALL.SPACE.$timeperiod['day'].SPACE.S_OF_EVERY_SMALL.SPACE.$months;
		}
	}
return $str;
}

?>
