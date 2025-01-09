<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


class CTextAreaFlexible extends CTextArea {
	/**
	 * Default CSS class name for textarea element.
	 */
	const ZBX_STYLE_CLASS = 'textarea-flexible';

	/**
	 * An options array.
	 *
	 * @var array
	 */
	protected $options = [
		'add_post_js' => true,
		'maxlength' => 255,
		'readonly' => false,
		'rows' => 1
	];

	/**
	 * CTextAreaFlexible constructor.
	 *
	 * @param string $name
	 * @param string $value                   (optional)
	 * @param array  $options                 (optional)
	 * @param bool   $options['add_post_js']  (optional)
	 * @param int    $options['maxlength']    (optional)
	 * @param bool   $options['readonly']     (optional)
	 * @param int    $options['rows']         (optional)
	 */
	public function __construct($name, $value = '', array $options = []) {
		$this->options = array_merge($this->options, $options);

		parent::__construct($name, $value, $this->options);

		$this->addClass(self::ZBX_STYLE_CLASS);

		if ($this->options['add_post_js']) {
			zbx_add_post_js($this->getPostJS());
		}
	}

	/**
	 * Get content of all Javascript code.
	 *
	 * @return string  Javascript code.
	 */
	public function getPostJS() {
		return 'jQuery("#'.$this->getId().'").textareaFlexible();';
	}
}
