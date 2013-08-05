<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


/**
 * Class for buttons that open popup windows
 */
class CButtonPopup extends CButton {

	/**
	 * Array of params that should be passed to popup window as get params.
	 *
	 * @var array
	 */
	protected $options = array();

	/**
	 * Width of popup window.
	 *
	 * @var int
	 */
	protected $width = 450;

	/**
	 * Height of popup window.
	 *
	 * @var int
	 */
	protected $height = 450;

	/**
	 * @param array $options
	 */
	public function __construct(array $options) {
		parent::__construct('button_popup', _('Select'), null, 'formlist');

		$this->options = $options;
		return $this;
	}

	/**
	 * Set button caption.
	 *
	 * @param string $caption
	 *
	 * @return CButtonPopup
	 */
	public function setCaption($caption) {
		$this->attr('value', $caption);
		return $this;
	}

	/**
	 * Set popup width.
	 *
	 * @param int $width
	 *
	 * @return CButtonPopup
	 */
	public function setWidth($width) {
		$this->width = $width;
		return $this;
	}

	/**
	 * Set popup height.
	 *
	 * @param int $height
	 *
	 * @return CButtonPopup
	 */
	public function setHeight($height) {
		$this->height = $height;
		return $this;
	}

	/**
	 * Set popup size, both width and height.
	 *
	 * @param int $width
	 * @param int $height
	 *
	 * @return CButtonPopup
	 */
	public function setSize($width, $height) {
		$this->width = $width;
		$this->height = $height;
		return $this;
	}

	/**
	 * Assign js action for popup opening and convert object to html string.
	 *
	 * @param bool $destroy
	 *
	 * @return string
	 */
	public function toString($destroy = true) {
		$this->addAction('onclick', $this->getPopupAction());
		return parent::toString();
	}

	/**
	 * Generate js action for popup window opening.
	 *
	 * @return string
	 */
	protected function getPopupAction() {
		$params = array();
		foreach ($this->options as $field => $value) {
			$params[] = $field.'='.$value;
		}

		$action = 'return PopUp("popup.php?'.implode('&', $params).'", '.$this->width.', '.$this->height.');';

		return $action;
	}
}
