<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


require_once dirname(__FILE__).'/../../blocks.inc.php';

class CScreenSystemStatus extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		global $page;

		// rewrite page file
		$page['file'] = $this->pageFile;

		$item = new CUIWidget('hat_syssum', make_system_status(array(
			'groupids' => null,
			'hostids' => null,
			'maintenance' => null,
			'severity' => null,
			'limit' => null,
			'extAck' => 0,
			'screenid' => $this->screenid
		)));
		$item->setHeader(_('Status of Zabbix'), SPACE);
		$item->setFooter(_s('Updated: %s', zbx_date2str(_('H:i:s'))));

		return $this->getOutput($item);
	}
}
