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


class CScreenServerInfo extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$status = get_status();
		$server = ($status['zabbix_server'] == _('Yes'))
			? (new CSpan(_('running')))->addClass(ZBX_STYLE_GREEN)
			: (new CSpan(_('not running')))->addClass(ZBX_STYLE_RED);

		$user_link = CWebUser::$data['alias'];
		if (!CWebUser::isGuest()) {
			$user_link = new CLink($user_link, 'profile.php');
		}

		$server_link = _('Zabbix server');
		if (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN) {
			$server_link = new CLink($server_link, 'zabbix.php?action=report.status');
		}

		$table = new CTableInfo();

		$table->addRow(_('Users (online)').NAME_DELIMITER.$status['users_count'].'('.$status['users_online'].')');
		$table->addRow(new CCol([_('Logged in as'), SPACE, $user_link]));
		$table->addRow(new CCol([$server_link, SPACE, _('is'), SPACE, $server]));
		$table->addRow(new CCol([
			_('Hosts (m/n/t)').NAME_DELIMITER.$status['hosts_count'].'(',
			(new CSpan($status['hosts_count_monitored']))->addClass(ZBX_STYLE_GREEN),
			'/',
			(new CSpan($status['hosts_count_not_monitored']))->addClass(ZBX_STYLE_RED),
			'/',
			(new CSpan($status['hosts_count_template']))->addClass(ZBX_STYLE_GREY),
			')'
		]));
		$table->addRow(new CCol([
			_('Items (m/d/n)').NAME_DELIMITER.$status['items_count'].'(',
			(new CSpan($status['items_count_monitored']))->addClass(ZBX_STYLE_GREEN),
			'/',
			(new CSpan($status['items_count_disabled']))->addClass(ZBX_STYLE_RED),
			'/',
			(new CSpan($status['items_count_not_supported']))->addClass(ZBX_STYLE_GREY),
			')'
		]));
		$table->addRow(new CCol([
			_('Triggers (e/d)[p/o]').NAME_DELIMITER.$status['triggers_count'].
			'('.$status['triggers_count_enabled'].'/'.$status['triggers_count_disabled'].')[',
			(new CSpan($status['triggers_count_on']))->addClass(ZBX_STYLE_GREEN),
			'/',
			(new CSpan($status['triggers_count_off']))->addClass(ZBX_STYLE_RED),
			']'
		]));

		$footer = (new CList())
			->addItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT);

		return $this->getOutput((new CUiWidget(uniqid(), [$table, $footer]))->setHeader(_('Status of Zabbix')));
	}
}
