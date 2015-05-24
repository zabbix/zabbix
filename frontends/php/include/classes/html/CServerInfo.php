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


class CServerInfo extends CTable {

	public function __construct() {
		parent::__construct();
		$this->addClass('server_info');
	}

	public function bodyToString() {
		$this->cleanItems();

		$status = get_status();
		$server = ($status['zabbix_server'] == _('Yes'))
			? new CSpan(_('running'), ZBX_STYLE_GREEN)
			: new CSpan(_('not running'), ZBX_STYLE_GREEN);
		$serverLink = (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN)
			? new CLink(_('Zabbix server'), 'zabbix.php?action=report.status')
			: _('Zabbix server');

		$this->addRow(new CCol(_('Zabbix server info'), ZBX_STYLE_NOWRAP.' ui-corner-all ui-widget-header'));
		$this->addRow(_('Updated').NAME_DELIMITER.zbx_date2str(DATE_TIME_FORMAT_SECONDS, time()));
		$this->addRow(_('Users (online)').NAME_DELIMITER.$status['users_count'].'('.$status['users_online'].')');
		$this->addRow(new CCol(array(_('Logged in as').SPACE, new CLink(CWebUser::$data['alias'], 'profile.php'))));
		$this->addRow(new CCol(array($serverLink, SPACE._('is').SPACE, $server)), 'status');
		$this->addRow(new CCol(array(
			_('Hosts (m/n/t)').NAME_DELIMITER.$status['hosts_count'].'(',
			new CSpan($status['hosts_count_monitored'], ZBX_STYLE_GREEN),
			'/',
			new CSpan($status['hosts_count_not_monitored'], ZBX_STYLE_RED),
			'/',
			new CSpan($status['hosts_count_template'], ZBX_STYLE_GREY),
			')'
		)));
		$this->addRow(new CCol(array(
			_('Items (m/d/n)').NAME_DELIMITER.$status['items_count'].'(',
			new CSpan($status['items_count_monitored'], ZBX_STYLE_GREEN),
			'/',
			new CSpan($status['items_count_disabled'], ZBX_STYLE_RED),
			'/',
			new CSpan($status['items_count_not_supported'], ZBX_STYLE_GREY),
			')'
		)));
		$this->addRow(new CCol(array(
			_('Triggers (e/d)[p/o]').NAME_DELIMITER.$status['triggers_count'].
			'('.$status['triggers_count_enabled'].'/'.$status['triggers_count_disabled'].')[',
			new CSpan($status['triggers_count_on'], ZBX_STYLE_GREEN),
			'/',
			new CSpan($status['triggers_count_off'], ZBX_STYLE_RED),
			']'
		)));

		return parent::bodyToString();
	}
}
