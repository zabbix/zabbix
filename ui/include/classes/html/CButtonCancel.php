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


class CButtonCancel extends CButton {

	/**
	 * @param string $vars
	 */
	public function __construct(string $vars = '') {
		parent::__construct('cancel', _('Cancel'));

		$this->setVars($vars);
	}

	/**
	 * @param string $value
	 *
	 * @return $this
	 */
	public function setVars(string $value): CButtonCancel {
		$url = (new CUrl('?cancel=1'.$value))->getUrl();

		return $this->onClick("javascript: return redirect('".$url."');");
	}
}
