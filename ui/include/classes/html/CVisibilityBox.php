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


class CVisibilityBox extends CCheckBox {

	private $object_id;
	private $replace_to;

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
