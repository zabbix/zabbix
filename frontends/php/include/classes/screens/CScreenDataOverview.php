<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CScreenDataOverview extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$groupid = $this->screenitem['resourceid'];
		if (!$this->screenitem['elements']) {
			$this->screenitem['elements'] = ZBX_DEFAULT_WIDGET_LINES;
		}

		$groups = API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => $groupid
		]);

		$header = (new CDiv([
			new CTag('h4', true, _('Data overview')),
			(new CList())
				->addItem([_('Group'), ':', SPACE, $groups[0]['name']])
		]))->addClass(ZBX_STYLE_DASHBRD_WIDGET_HEAD);

		$data = [];
		if ($this->screenitem['style'] == STYLE_TOP) {
			list($db_items, $db_hosts, $items_by_name, $hidden_cnt) = getDataOverviewTop((array) $groupid, null,
				$this->screenitem['application']
			);

			$items_by_name = array_slice($items_by_name, 0, $this->screenitem['elements'], true);

			$data['visible_items'] = getDataOverviewCellData($db_hosts, $db_items, $items_by_name,
				ZBX_PROBLEM_SUPPRESSED_FALSE
			);
			$data['db_hosts'] = $db_hosts;
			$data['items_by_name'] = $items_by_name;
			$data['hidden_cnt'] = $hidden_cnt;

			$table = new CPartial('dataoverview.table.top', $data);
		}
		else {
			list($db_items, $db_hosts, $items_by_name, $hidden_cnt) = getDataOverviewLeft((array) $groupid, null,
				$this->screenitem['application']
			);

			$db_hosts = array_slice($db_hosts, 0, $this->screenitem['elements'], true);

			$data['visible_items'] = getDataOverviewCellData($db_hosts, $db_items, $items_by_name,
				ZBX_PROBLEM_SUPPRESSED_FALSE
			);
			$data['db_hosts'] = $db_hosts;
			$data['items_by_name'] = $items_by_name;
			$data['hidden_cnt'] = $hidden_cnt;

			$table = new CPartial('dataoverview.table.left', $data);
		}

		$footer = (new CList())
			->addItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT);

		return $this->getOutput(new CUiWidget(uniqid(), [$header, $table, $footer]));
	}
}
