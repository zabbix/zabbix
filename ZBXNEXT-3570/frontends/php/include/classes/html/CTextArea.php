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
	 * @param string	$name
	 * @param string	$value
	 * @param array		$options
	 * @param int		$options['rows']
	 * @param int		$options['maxlength']
	 * @param boolean	$options['readonly']
	 */
	public function __construct($name = 'textarea', $value = '', $options = []) {
		parent::__construct('textarea', true);
		$this->setId(zbx_formatDomId($name));
		$this->setAttribute('name', $name);
		$this->setAttribute('rows', !empty($options['rows']) ? $options['rows'] : ZBX_TEXTAREA_STANDARD_ROWS);
		if (isset($options['readonly'])) {
			$this->setReadonly($options['readonly']);
		}
		$this->addItem($value);

		// set maxlength
		if (!empty($options['maxlength'])) {
			$this->setMaxlength($options['maxlength']);
		}
	}

	public function setReadonly($value) {
		if ($value) {
			$this->setAttribute('readonly', 'readonly');
		}
		else {
			$this->removeAttribute('readonly');
		}
		return $this;
	}

	public function setValue($value = '') {
		$this->addItem($value);
		return $this;
	}

	public function setRows($value) {
		$this->setAttribute('rows', $value);
		return $this;
	}

	public function setCols($value) {
		$this->setAttribute('cols', $value);
		return $this;
	}

	public function setMaxlength($maxlength) {
		$this->setAttribute('maxlength', $maxlength);

		if (!defined('IS_TEXTAREA_MAXLENGTH_JS_INSERTED')) {
			define('IS_TEXTAREA_MAXLENGTH_JS_INSERTED', true);

			// firefox and google chrome have own implementations of maxlength validation on textarea
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
		return $this;
	}

	public function setWidth($value) {
		$this->addStyle('width: '.$value.'px;');
		return $this;
	}
}
