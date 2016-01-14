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
						var cellsToRotate = $(".vertical_rotation", this),
							betterCells = [];

						// insert spans
						cellsToRotate.each(function () {
							var cell = $(this);

							var text = $("<span>", {
								text: $.escapeHtml(cell.text())
							});

							if (IE) {
								text.css({"font-family": "monospace"});
							}

							cell.text("").append(text);
						});

						// rotate cells
						cellsToRotate.each(function () {
							var cell = $(this),
								span = cell.children(),
								height = cell.height(),
								width = span.width(),
								transform = (width / 2) + "px " + (width / 2) + "px";

							var css = {
								"transform-origin": transform,
								"-webkit-transform-origin": transform,
								"-moz-transform-origin": transform,
								"-o-transform-origin": transform
							};

							if (IE) {
								css["font-family"] = "monospace";
								css["-ms-transform-origin"] = "50% 50%";
							}

							if (IE9) {
								css["-ms-transform-origin"] = transform;
							}

							var divInner = $("<div>", {
								"class": "vertical_rotation_inner"
							})
							.css(css)
							.append(span.text());

							var div = $("<div>", {
								height: width,
								width: height
							})
							.append(divInner);

							betterCells.push(div);
						});

						cellsToRotate.each(function (i) {
							$(this).html(betterCells[i]);
						});

						// align text to cell center
						cellsToRotate.each(function () {
							var cell = $(this),
								width = cell.width();

							if (width > 30) {
								cell.children().css({
									position: "relative",
									left: width / 2 - 12
								});
							}
						});
					};
				});

				jQuery(document).ready(function() {
					jQuery(".'.$this->getAttribute('class').'").makeVerticalRotation();

					if (IE8 || IE10 || IE11) {
						jQuery(".vertical_rotation_inner").css({
							filter: "progid:DXImageTransform.Microsoft.BasicImage(rotation=2)",
							"writing-mode": "tb-rl"
						});
					}
					else if (IE9) {
						jQuery(".vertical_rotation_inner").css({
							"-ms-transform": "rotate(270deg)"
						});
					}
				});'
			, true);
		}
	}
}
