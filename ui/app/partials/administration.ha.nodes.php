<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


/**
 * @var CPartial $this
 * @var array    $data
 */

$nodes_table = (new CTableInfo())
	->setHeader([_('Name'), _('Address'), _('Last access'), _('Status')])
	->setHeadingColumn(0)
	->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER)
	->addClass(ZBX_STYLE_LIST_TABLE_STICKY_FOOTER);

if ($data['ha_cluster_enabled']) {
	foreach ($data['ha_nodes'] as $node) {
		$status_element = new CCol();

		switch($node['status']) {
			case ZBX_NODE_STATUS_STOPPED:
				$status_element
					->addItem(_('Stopped'))
					->addClass(ZBX_STYLE_GREY);
				break;

			case ZBX_NODE_STATUS_STANDBY:
				$status_element->addItem(_('Standby'));
				break;

			case ZBX_NODE_STATUS_ACTIVE:
				$status_element
					->addItem(_('Active'))
					->addClass(ZBX_STYLE_GREEN);
				break;

			case ZBX_NODE_STATUS_UNAVAILABLE:
				$status_element
					->addItem(_('Unavailable'))
					->addClass(ZBX_STYLE_RED);
				break;
		}

		$nodes_table->addRow([
			(new CCol($node['name']))->addClass(ZBX_STYLE_NOWRAP),
			$node['address'].':'.$node['port'],
			(new CCol(convertUnitsS(time() - $node['lastaccess'])))
				->setAttribute('title', zbx_date2str(DATE_TIME_FORMAT_SECONDS, $node['lastaccess'])),
			$status_element
		]);
	}

	if ($data['failover_delay'] !== null) {
		$nodes_table
			->addItem(
				new CTag('tfoot', true,
					(new CRow(
						(new CCol(_s('Fail-over delay: %1$s', $data['failover_delay'])))
							->addClass('table-info')
							->setColSpan(4)
					))
				)
			);
	}
}

$nodes_table->show();
