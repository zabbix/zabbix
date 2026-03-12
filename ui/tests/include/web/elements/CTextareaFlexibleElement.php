<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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


require_once __DIR__.'/../CElement.php';

/**
 * Textarea flexible element.
 */
class CTextareaFlexibleElement extends CElement {
	/**
	 * Clear the value of textarea flexible element.
	 *
	 * @return $this
	 */
	public function clear() {
		CElementQuery::getDriver()->executeScript('arguments[0].value="";', [$this]);

		return $this;
	}

	/**
	 * Overwrite value in textarea flexible element.
	 *
	 * @param $text    text to be written into the field
	 *
	 * @return $this
	 */
	public function overwrite($text) {
		if ($text === null) {
			$text = '';
		}

		$this->selectValue();
		CElementQuery::getDriver()->executeScript('arguments[0].value = '.json_encode($text).
				',arguments[0].dispatchEvent(new Event("change")),arguments[0].dispatchEvent(new Event("keyup"));', [$this]
		);

		return $this;
	}
}

