<?php declare(strict_types = 1);
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


/**
 * Class to embed script HTML template.
 */
class CScriptTemplate extends CTag {

	/**
	 * Create a <script type="text/x-jquery-tmpl" id="{$id}"> HTML template.
	 *
	 * @param string $id  Template id
	 */
	public function __construct($id) {
		parent::__construct('script', true);
		$this->setAttribute('type', 'text/x-jquery-tmpl');
		$this->setId($id);
	}

	public function addItem($value) {
		if (is_array($value)) {
			array_map([$this, 'addItem'], $value);
		}
		else {
			$this->items[] = $value;
		}

		return $this;
	}

	protected function bodyToString(): string {
		return implode("\n", $this->items);
	}
}
