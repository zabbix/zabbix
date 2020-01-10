<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


use CController as Action;

class CLegacyAction extends Action {

	/**
	 * Check user input.
	 *
	 * @return bool
	 */
	public function checkInput() {
		return true;
	}

	/**
	 * Check permission.
	 *
	 * @return bool
	 */
	public function checkPermissions() {
		$user_type = CWebUser::getType();
		$denied = [];

		if ($user_type < USER_TYPE_ZABBIX_ADMIN) {
			$denied = [
				'hostgroups.php', 'templates.php', 'hosts.php', 'items.php', 'triggers.php', 'graphs.php',
				'applications.php', 'host_discovery.php', 'disc_prototypes.php', 'trigger_prototypes.php',
				'host_prototypes.php', 'httpconf.php', 'maintenance.php', 'actionconf.php', 'discoveryconf.php',
				'services.php'
			];
		}

		if ($user_type != USER_TYPE_SUPER_ADMIN) {
			$denied = array_merge($denied, [
				'auditlogs.php', 'auditacts.php', 'report4.php', 'correlation.php', 'adm.gui.php', 'adm.housekeeper.php',
				'adm.images.php', 'adm.iconmapping.php', 'adm.regexps.php', 'adm.macros.php', 'adm.valuemapping.php',
				'adm.workingtime.php', 'adm.triggerseverities.php', 'adm.triggerdisplayoptions.php', 'adm.other.php',
				'autoreg.edit', 'module.list', 'module.edit', 'usergrps.php', 'queue.php'
			]);
		}

		return !in_array($this->getAction(), $denied);
	}

	public function doAction() {

	}

	/**
	 * Check session id.
	 *
	 * @return bool
	 */
	protected function checkSID() {
		return true;
	}
}
