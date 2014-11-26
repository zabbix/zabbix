<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 * Class CGoButton
 *
 * Implements button used for mass actions in list view.
 */
class CGoButton extends CSubmit {

	/**
	 * @param string       $name     name of submit button
	 * @param string       $value    value of submit button
	 * @param string       $caption  caption of submit button
	 * @param string|bool  $confirm  if not false, value gets added to submit button attribute "confirm" and is used
	 *                               to show confirmation message before submit.
	 */
	public function __construct($name, $value, $caption, $confirm = false) {
		parent::__construct($name, $caption);

		$this->setAttribute('type', 'submit');
		$this->setAttribute('value', $value);

		if ($confirm) {
			$this->setAttribute('confirm', $confirm);
		}

		$this->addClass('goButton');
	}
}
