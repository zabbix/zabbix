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


class CTextArea extends CTag {

	/**
	 * The "&" symbol in the textarea should be encoded.
	 *
	 * @var int
	 */
	protected $encStrategy = self::ENC_ALL;

	/**
	 * Init textarea.
	 *
	 * @param string $name
	 * @param string $value
	 * @param array  $htmlAttrs
	 */
	public function __construct($name, $value = '', $htmlAttrs = array()) {
		parent::__construct('textarea', 'yes');
		$this->addItem($value);

		$htmlAttrs['class'] = isset($htmlAttrs['class']) ? 'input '.$htmlAttrs['class'] : 'input';
		$htmlAttrs['id'] = zbx_formatDomId($name);
		$htmlAttrs['name'] = $name;
		if (!isset($htmlAttrs['rows'])) {
			$htmlAttrs['rows'] = ZBX_TEXTAREA_STANDARD_ROWS;
		}

		// set width
		if (empty($htmlAttrs['width']) || $htmlAttrs['width'] == ZBX_TEXTAREA_STANDARD_WIDTH) {
			$htmlAttrs['class'] .= ' textarea_standard';
		}
		elseif ($htmlAttrs['width'] == ZBX_TEXTAREA_BIG_WIDTH) {
			$htmlAttrs['class'] .= ' textarea_big';
		}
		else {
			$htmlAttrs['style'] = 'width: '.$htmlAttrs['width'].'px;';
		}

		if (!empty($htmlAttrs['maxlength'])) {
			$this->addMaxlengthJs($htmlAttrs['maxlength']);
		}

		$this->attrs($htmlAttrs);
	}

	public function addMaxlengthJs() {
		if (!defined('IS_TEXTAREA_MAXLENGTH_JS_INSERTED')) {
			define('IS_TEXTAREA_MAXLENGTH_JS_INSERTED', true);

			// firefox and google chrome has own implementation of maxlength validation on textarea
			insert_js('
				if (!CR && !GK) {
					jQuery("textarea[maxlength]").bind("paste contextmenu change keydown keypress keyup", function() {
						var elem = jQuery(this);
						if (elem.val().length > elem.attr("maxlength")) {
							elem.val(elem.val().substr(0, elem.attr("maxlength")));
						}
					});
				}',
			true);
		}
	}
}
