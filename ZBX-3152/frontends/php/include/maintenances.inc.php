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
		$str = S_AT.SPACE.date('H',$timeperiod['start_date']).':'.date('i',$timeperiod['start_date']).SPACE.S_ON_SMALL.SPACE.zbx_date2str(S_MAINTENANCES_SCHEDULE_DATE_FORMAT,$timeperiod['start_date']);
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
				$days.= getDayOfWeekCaption($i+1);
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
				$months.= getMonthCaption($i+1);
			}
		}

		if($timeperiod['dayofweek']>0){
			$days = '';
			$dayofweek = zbx_num2bitstr($timeperiod['dayofweek'],true);
			$length = zbx_strlen($dayofweek);
			for($i=0; $i < $length; $i++){
				if($dayofweek[$i] == 1){
					if(!zbx_empty($days)) $days.=', ';
					$days.= getDayOfWeekCaption($i+1);
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
