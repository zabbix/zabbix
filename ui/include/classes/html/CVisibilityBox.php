<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CVisibilityBox extends CCheckBox {

	public function __construct($name = 'visibilitybox', $object_id = null, $replace_to = null) {
		$this->object_id = $object_id;
		$this->replace_to = unpack_object($replace_to);

		parent::__construct($name);
		$this->onClick('visibilityStatusChanges(this.checked, '.zbx_jsvalue($this->object_id).', '.
			zbx_jsvalue($this->replace_to).');');
	}

	/**
	 * Set the label for the checkbox and put it on the left.
	 *
	 * @param string $label
	 *
	 * @return CVisibilityBox
	 */
	public function setLabel($label) {
		parent::setLabel($label);
		$this->setLabelPosition(self::LABEL_POSITION_LEFT);

		return $this;
	}

	public function toString($destroy = true) {
		if (!isset($this->attributes['checked'])) {
			zbx_add_post_js('visibilityStatusChanges(false, '.zbx_jsvalue($this->object_id).', '.
				zbx_jsvalue($this->replace_to).');');
		}

		return parent::toString($destroy);
	}
}
