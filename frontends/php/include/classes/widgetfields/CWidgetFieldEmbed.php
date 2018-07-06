<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CWidgetFieldEmbed extends CWidgetField {
	protected $item;

	/**
	 * HTML Embed element (not a field).
	 *
	 * This class is temporary solution to make graph preview available in widget configuration window.
	 *
	 * @param object $item   Item to embed.
	 */
	public function __construct($item) {
		//parent::__construct(null, null);
		$this->item = $item;
	}

	public function getItem() {
		return $this->item;
	}

	// Since it is not a field, no need to validate input values.
	public function validate($strict = false) {
		return [];
	}
}
