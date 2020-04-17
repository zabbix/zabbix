<?php declare(strict_types = 1);
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


use CController as CAction;

class CLegacyAction extends CAction {

	/**
	 * Disable SID validation for legacy actions.
	 */
	protected function init(): void {
		$this->disableSIDvalidation();
	}

	public function doAction(): void {
	}

	/**
	 * Check user input.
	 *
	 * @return bool
	 */
	public function checkInput(): bool {
		return true;
	}

	/**
	 * Check permission.
	 *
	 * @return bool
	 */
	public function checkPermissions(): bool {
		$user_type = $this->getUserType();
		$denied = [];

		if ($user_type < USER_TYPE_ZABBIX_USER) {
			$denied = ['chart.php', 'chart2.php', 'chart3.php', 'chart5.php', 'chart6.php', 'chart7.php', 'history.php',
				'hostinventories.php', 'hostinventoriesoverview.php', 'httpdetails.php', 'image.php', 'imgstore.php',
				'jsrpc.php', 'map.import.php', 'map.php', 'overview.php', 'toptriggers.php', 'tr_events.php',
				'screenconf.php', 'screenedit.php', 'screen.import.php', 'screens.php', 'slideconf.php', 'slides.php',
				'srv_status.php', 'sysmap.php', 'sysmaps.php', 'report2.php'
			];
		}

		if ($user_type < USER_TYPE_ZABBIX_ADMIN) {
			$denied = array_merge($denied, ['actionconf.php', 'applications.php', 'conf.import.php',
				'disc_prototypes.php', 'discoveryconf.php', 'graphs.php', 'host_discovery.php', 'host_prototypes.php',
				'hostgroups.php', 'hosts.php', 'httpconf.php', 'items.php', 'maintenance.php', 'report4.php',
				'services.php', 'templates.php', 'trigger_prototypes.php', 'triggers.php'
			]);
		}

		if ($user_type != USER_TYPE_SUPER_ADMIN) {
			$denied = array_merge($denied, ['auditacts.php', 'correlation.php', 'queue.php']);
		}

		return !in_array($this->getAction(), $denied);
	}
}
