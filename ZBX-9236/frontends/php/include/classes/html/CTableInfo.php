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


class CTableInfo extends CTable {

	public function __construct($message = null) {
		if ($message === null) {
			$message = _('No data found');
		}
		parent::__construct($message, ZBX_STYLE_LIST_TABLE);
		$this->addMakeVerticalRotationJs = false;
	}

	public function toString($destroy = true) {
		$tableId = $this->getAttribute('id');

		if(!$tableId) {
			$tableId = uniqid('t');
			$this->setAttribute('id', $tableId);
		}

		$string = parent::toString($destroy);

		if($this->addMakeVerticalRotationJs) {
			$string .= get_js(
				'var makeVerticalRotationForTable = function() {
					var table = jQuery("#'.$tableId.'");

					table.makeVerticalRotation();

					if (IE8) {
						jQuery(".vertical_rotation_inner", table).css({
							filter: "progid:DXImageTransform.Microsoft.BasicImage(rotation=2)"
						});
					}
					else if (IE9) {
						jQuery(".vertical_rotation_inner", table).css({
							"-ms-transform": "rotate(270deg)"
						});
					}

					if (!IE9) {
						jQuery(".vertical_rotation_inner", table).css({
							"writing-mode": "tb-rl"
						});
					}
				}

				if(!jQuery.isReady) {
					jQuery(document).ready(makeVerticalRotationForTable);
				}
				else {
					makeVerticalRotationForTable();
				}',
			true);
		}

		return $string;
	}

	/**
	 * Rotate table header text vertical.
	 * Cells must be marked with "vertical_rotation" class.
	 */
	public function makeVerticalRotation() {
		$this->addMakeVerticalRotationJs = true;
	}
}
