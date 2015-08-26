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


class CScreenTriggersInfo extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		if ($this->screenitem['resourceid'] == 0) {
			$header_str = _('All groups');
		}
		else {
			$header_str = _('Group').SPACE.
				'&quot;'.get_hostgroup_by_groupid($this->screenitem['resourceid'])['name'].'&quot;';
		}

		$header = new CColHeader($header_str);

		if ($this->screenitem['style'] == STYLE_HORIZONTAL) {
			$header->setColSpan(8);
		}

		return $this->getOutput(
			(new CTriggersInfo($this->screenitem['resourceid']))->
				setOrientation($this->screenitem['style'])->
				setHeader([$header])
		);
	}
}
