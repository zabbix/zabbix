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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
function get_maintenance_by_maintenanceid($maintenanceid) {
	$sql = 'SELECT m.*'.
			' FROM maintenances m'.
			' WHERE '.DBin_node('m.maintenanceid').
				' AND maintenanceid='.$maintenanceid;
	return DBfetch(DBselect($sql));
}

function timeperiod_type2str($timeperiod_type) {
	switch ($timeperiod_type) {
		case TIMEPERIOD_TYPE_ONETIME:
			return _('One time only');
		case TIMEPERIOD_TYPE_DAILY:
			return _('Daily');
		case TIMEPERIOD_TYPE_WEEKLY:
			return _('Weekly');
		case TIMEPERIOD_TYPE_MONTHLY:
			return _('Monthly');
	}
	return _('Unknown');
}

function shedule2str($timeperiod) {
	$timeperiod['hour'] = floor($timeperiod['start_time'] / SEC_PER_HOUR);
	$timeperiod['minute'] = floor(($timeperiod['start_time'] - ($timeperiod['hour'] * SEC_PER_HOUR)) / SEC_PER_MIN);
	if ($timeperiod['hour'] < 10) {
		$timeperiod['hour'] = '0'.$timeperiod['hour'];
	}
	if ($timeperiod['minute'] < 10) {
		$timeperiod['minute'] = '0'.$timeperiod['minute'];
	}

	$str = _('At').SPACE.$timeperiod['hour'].':'.$timeperiod['minute'].SPACE._('on').SPACE;

	if ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_ONETIME) {
		$str = _('At').SPACE.date('H', $timeperiod['start_date']).':'.date('i',$timeperiod['start_date']).SPACE._('on').SPACE.zbx_date2str(_('d M Y'), $timeperiod['start_date']);
	}
	elseif ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_DAILY) {
		$str .= _('every').SPACE.(($timeperiod['every'] > 1) ? $timeperiod['every'].SPACE._('days') : _('day'));
	}
	elseif ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_WEEKLY) {
		$days = '';
		$dayofweek = zbx_num2bitstr($timeperiod['dayofweek'], true);
		$length = zbx_strlen($dayofweek);
		for ($i = 0; $i < $length; $i++) {
			if ($dayofweek[$i] == 1) {
				if (!zbx_empty($days)) {
					$days .= ', ';
				}
				$days .= getDayOfWeekCaption($i + 1);
			}
		}
		$str.= _('every').SPACE.$days.SPACE._('of every').SPACE.(($timeperiod['every'] > 1) ? $timeperiod['every'].SPACE._('weeks') : _('week'));
	}
	elseif ($timeperiod['timeperiod_type'] == TIMEPERIOD_TYPE_MONTHLY) {
		$months = '';
		$month = zbx_num2bitstr($timeperiod['month'], true);
		$length = zbx_strlen($month);
		for ($i = 0; $i < $length; $i++) {
			if ($month[$i] == 1) {
				if (!zbx_empty($months)) {
					$months .=', ';
				}
				$months .= getMonthCaption($i + 1);
			}
		}
		if ($timeperiod['dayofweek'] > 0) {
			$days = '';
			$dayofweek = zbx_num2bitstr($timeperiod['dayofweek'], true);
			$length = zbx_strlen($dayofweek);
			for ($i = 0; $i < $length; $i++) {
				if ($dayofweek[$i] == 1) {
					if (!zbx_empty($days)) {
						$days .= ', ';
					}
					$days .= getDayOfWeekCaption($i + 1);
				}
			}

			$every = '';
			switch ($timeperiod['every']) {
				case 1: $every = _('First'); break;
				case 2: $every = _('Second'); break;
				case 3: $every = _('Third'); break;
				case 4: $every = _('Fourth'); break;
				case 5: $every = _('Last'); break;
			}
			$str .= $every.SPACE.$days.SPACE._('of every').SPACE.$months;
		}
		else {
			$str .= _('day').SPACE.$timeperiod['day'].SPACE._('of every').SPACE.$months;
		}
	}
	return $str;
}
?>
