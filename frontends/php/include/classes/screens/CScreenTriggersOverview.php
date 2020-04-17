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


class CScreenTriggersOverview extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$groups = API::HostGroup()->get([
			'output' => ['name'],
			'groupids' => $this->screenitem['resourceid']
		]);

		$header = (new CDiv([
			new CTag('h4', true, _('Trigger overview')),
			(new CList())->addItem([_('Group'), ':', SPACE, $groups[0]['name']])
		]))->addClass(ZBX_STYLE_DASHBRD_WIDGET_HEAD);

		$data = [];
		list($data['db_hosts'], $data['db_triggers'], $data['dependencies'], $data['triggers_by_name'],
			$data['hosts_by_name'], $data['exceeded_hosts'], $data['exceeded_trigs']
		) = getTriggersOverviewData((array) $this->screenitem['resourceid'], $this->screenitem['application']);

		if ($this->screenitem['style'] == STYLE_TOP) {
			$table = new CPartial('trigoverview.table.top', $data);
		}
		else {
			$table = new CPartial('trigoverview.table.left', $data);
		}

		$footer = (new CList())
			->addItem(_s('Updated: %1$s', zbx_date2str(TIME_FORMAT_SECONDS)))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT);

		return $this->getOutput(new CUiWidget(uniqid(), [$header, $table, $footer]));
	}
}
