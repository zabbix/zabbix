<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CFlickerfreeScreenPlainText extends CFlickerfreeScreenItem {

	private $hostid;

	public function __construct(array $options = array()) {
		parent::__construct($options);

		$this->hostid = get_request('hostid', 0);
	}

	public function get() {
		if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && !empty($this->hostid)) {
			$newitemid = get_same_item_for_host($this->screenitem['resourceid'], $this->hostid);
			if (!empty($newitemid)) {
				$this->screenitem['resourceid'] = $newitemid;
			}
			else {
				$this->screenitem['resourceid'] = 0;
			}
		}

		return $this->getOutput(get_screen_plaintext($this->screenitem['resourceid'], $this->screenitem['elements'], $this->screenitem['style']));
	}
}
