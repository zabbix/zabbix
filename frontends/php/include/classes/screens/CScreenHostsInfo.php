<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


class CScreenHostsInfo extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$total = 0;

		// fetch accessible host ids
		$hosts = API::Host()->get([
			'output' => ['hostid'],
			'preservekeys' => true
		]);
		$hostids = array_keys($hosts);

		if ($this->screenitem['resourceid'] != 0) {
			$cond_from = ',hosts_groups hg';
			$cond_where = ' AND hg.hostid=h.hostid AND hg.groupid='.zbx_dbstr($this->screenitem['resourceid']);
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
				' AND '.dbConditionInt('h.hostid', $hostids).
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
				' AND '.dbConditionInt('h.hostid', $hostids).
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
				' AND '.dbConditionInt('h.hostid', $hostids).
				$cond_where
		);

		$host_cnt = DBfetch($db_host_cnt);
		$uncn = $host_cnt['cnt'];
		$total += $host_cnt['cnt'];

		$avail = (new CCol($avail.'  '._('Available')))->addClass(ZBX_STYLE_GREEN);
		$notav = (new CCol($notav.'  '._('Not available')))->addClass(ZBX_STYLE_RED);
		$uncn = (new CCol($uncn.'  '._('Unknown')))->addClass(ZBX_STYLE_GREY);
		$total = new CCol($total.'  '._('Total'));

		$header = (new CDiv([
			new CTag('h4', true, _('Hosts info'))
		]))->addClass(ZBX_STYLE_DASHBRD_WIDGET_HEAD);

		if ($this->screenitem['resourceid'] != 0) {
			$groups = API::HostGroup()->get([
				'output' => ['name'],
				'groupids' => [$this->screenitem['resourceid']]
			]);

			$header->addItem((new CList())->addItem([_('Group'), ':', SPACE, $groups[0]['name']]));
		}

		$table = new CTableInfo();

		if ($this->screenitem['style'] == STYLE_HORIZONTAL) {
			$table->addRow([$avail, $notav, $uncn, $total]);
		}
		else {
			$table->addRow($avail);
			$table->addRow($notav);
			$table->addRow($uncn);
			$table->addRow($total);
		}

		$footer = (new CList())
			->addItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT);

		return $this->getOutput(new CUiWidget(uniqid(), [$header, $table, $footer]));
	}
}
