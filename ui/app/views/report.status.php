<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


/**
 * @var CView $this
 * @var array $data
 */

require_once __DIR__.'/../../include/blocks.inc.php';

(new CHtmlPage())
	->setTitle(_('System information'))
	->setDocUrl(CDocHelper::getUrl(CDocHelper::REPORT_STATUS))
	->addItem(
		(new CDiv(
			new CPartial('administration.system.info', [
				'system_info' => $data['system_info'],
				'show_software_update_check_details' => true,
				'user_type' => $data['user_type']
			])
		))->addClass(ZBX_STYLE_CONTAINER)
	)
	->addItem(
		($data['user_type'] == USER_TYPE_SUPER_ADMIN && $data['system_info']['ha_cluster_enabled'])
			? (new CDiv(
				new CPartial('administration.ha.nodes', [
					'ha_nodes' => $data['system_info']['ha_nodes'],
					'ha_cluster_enabled' => $data['system_info']['ha_cluster_enabled'],
					'failover_delay' => null
				])
			))->addClass(ZBX_STYLE_CONTAINER)
			: null
	)
	->show();
