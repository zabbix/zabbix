<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

	public function __construct($message = '...', $class = 'tableinfo') {
		parent::__construct($message, $class);
		$this->setOddRowClass('odd_row');
		$this->setEvenRowClass('even_row');
		$this->attributes['cellpadding'] = 3;
		$this->attributes['cellspacing'] = 1;
		$this->headerClass = 'header';
		$this->footerClass = 'footer';
	}

	/**
	 * Rotate table header text vertical.
	 * Cells must be marked with "vertical_rotation" class.
	 */
	public function makeVerticalRotation() {
		if (!defined('IS_VERTICAL_ROTATION_JS_INSERTED')) {
			define('IS_VERTICAL_ROTATION_JS_INSERTED', true);

			insert_js(
				'jQuery(document).ready(function() {
					jQuery(".'.$this->getAttribute('class').'")
						.makeVerticalRotation();

					if (IE8) {
						jQuery(".vertical_rotation_inner").css({
							filter: "progid:DXImageTransform.Microsoft.BasicImage(rotation=2)"
						});
					}
					else if (IE9) {
						jQuery(".vertical_rotation_inner").css({
							"-ms-transform": "rotate(270deg)"
						});
					}

					if (!IE9) {
						jQuery(".vertical_rotation_inner").css({
							"writing-mode": "tb-rl"
						});
					}
				});',
				true
			);
		}
	}
}
