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
 * @var CView $this
 * @var array $data
 */

require_once __DIR__.'/../../include/blocks.inc.php';

(new CWidget())
	->setTitle(_('System information'))
	->addItem(
		(new CDiv(
			new CPartial('administration.system.info', [
				'system_info' => $data['system_info'],
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
