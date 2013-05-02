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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CGroupPrototypeValidator extends CValidator {

	/**
	 * Error message if no name and group ID is defined.
	 *
	 * @var string
	 */
	public $messageMissing;

	/**
	 * Error message both name and group ID are defined.
	 *
	 * @var string
	 */
	public $messageBoth;

	/**
	 * Checks that a group prototype contains a name or a group ID and not both.
	 */
	public function validate($value)
	{
		if (empty($value['name']) && empty($value['groupid'])) {
			$this->setError($this->messageMissing);

			return false;
		}

		if (isset($value['name']) && !zbx_empty($value['name']) && !empty($value['groupid'])) {
			$this->error($this->messageBoth);

			return false;
		}

		return true;
	}

}
