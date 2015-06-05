<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CHostsInfo extends CTable {

	public $style;

	public function __construct($groupid = 0, $style = STYLE_HORIZONTAL) {
		$this->groupid = $groupid;
		$this->style = null;

		parent::__construct();
		$this->addClass('hosts_info');
		$this->setOrientation($style);
	}

	public function setOrientation($value) {
		if ($value != STYLE_HORIZONTAL && $value != STYLE_VERTICAL) {
			return $this->error('Incorrect value for SetOrientation "'.$value.'".');
		}
		$this->style = $value;
	}

	public function bodyToString() {
		$this->cleanItems();

		$total = 0;

		// fetch accessible host ids
		$hosts = API::Host()->get([
			'output' => ['hostid'],
			'preservekeys' => true
		]);
		$hostIds = array_keys($hosts);

		if ($this->groupid != 0) {
			$cond_from = ',hosts_groups hg';
			$cond_where = ' AND hg.hostid=h.hostid AND hg.groupid='.zbx_dbstr($this->groupid);
		}
		else {
			$cond_from = '';
			$cond_where = '';
		}

		$db_host_cnt = DBselect(
			'SELECT COUNT(DISTINCT h.hostid) AS cnt'.
			' FROM hosts h'.$cond_from.
			' WHERE h.available='.HOST_AVAILABLE_TRUE.
				' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
				' AND '.dbConditionInt('h.hostid', $hostIds).
				$cond_where
		);

		$host_cnt = DBfetch($db_host_cnt);
		$avail = $host_cnt['cnt'];
		$total += $host_cnt['cnt'];

		$db_host_cnt = DBselect(
			'SELECT COUNT(DISTINCT h.hostid) AS cnt'.
			' FROM hosts h'.$cond_from.
			' WHERE h.available='.HOST_AVAILABLE_FALSE.
				' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
				' AND '.dbConditionInt('h.hostid', $hostIds).
				$cond_where
		);

		$host_cnt = DBfetch($db_host_cnt);
		$notav = $host_cnt['cnt'];
		$total += $host_cnt['cnt'];

		$db_host_cnt = DBselect(
			'SELECT COUNT(DISTINCT h.hostid) AS cnt'.
			' FROM hosts h'.$cond_from.
			' WHERE h.available='.HOST_AVAILABLE_UNKNOWN.
				' AND h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'.
				' AND '.dbConditionInt('h.hostid', $hostIds).
				$cond_where
		);

		$host_cnt = DBfetch($db_host_cnt);
		$uncn = $host_cnt['cnt'];
		$total += $host_cnt['cnt'];

		$header_str = _('Hosts info').SPACE;

		if ($this->groupid != 0) {
			$group = get_hostgroup_by_groupid($this->groupid);
			$header_str .= _('Group').SPACE.'&quot;'.$group['name'].'&quot;';
		}
		else {
			$header_str .= _('All groups');
		}

		$header = (new CCol($header_str))->
			addClass('header');
		if ($this->style == STYLE_HORIZONTAL) {
			$header->setColspan(4);
		}

		$this->addRow($header);

		$avail = (new CCol($avail.'  '._('Available')))->
			addClass('avail');
		$notav = (new CCol($notav.'  '._('Not available')))->
			addClass('notav');
		$uncn = (new CCol($uncn.'  '._('Unknown')))->
			addClass('uncn');
		$total = (new CCol($total.'  '._('Total')))->
			addClass('total');

		if ($this->style == STYLE_HORIZONTAL) {
			$this->addRow([$avail, $notav, $uncn, $total]);
		}
		else {
			$this->addRow($avail);
			$this->addRow($notav);
			$this->addRow($uncn);
			$this->addRow($total);
		}

		return parent::bodyToString();
	}
}
