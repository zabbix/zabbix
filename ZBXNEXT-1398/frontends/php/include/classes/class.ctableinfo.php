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
				'jQuery(function($) {
					$.fn.makeVerticalRotation = function () {
						var cellsToRotate = $(".vertical_rotation", this);
						var betterCells = [];

						cellsToRotate.each(function () {
							var cell = $(this),
								newText = cell.text(),
								height = cell.height(),
								width = cell.width(),
								widthCss = (width / 2) + "px " + (width / 2) + "px";

							var divInner = $("<div>", {text: newText, class: "rotated"})
								.css({
									"transform-origin": widthCss,
									"-webkit-transform-origin": widthCss,
									"-moz-transform-origin": widthCss,
									"-ms-transform-origin": widthCss,
									"-o-transform-origin": widthCss
								});
							var div = $("<div>", {height: width, width: height}).append(divInner);

							betterCells.push(div);
						});

						cellsToRotate.each(function (i) {
							$(this).html(betterCells[i]);
						});
					};
				});
				jQuery(document).ready(function() {
					jQuery(".'.$this->getAttribute('class').'").makeVerticalRotation();
				});'
			, true);
		}
	}
}
