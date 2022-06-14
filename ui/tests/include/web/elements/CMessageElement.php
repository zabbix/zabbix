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

/**
 * Global message element.
 */
class CMessageElement extends CElement {

	/**
	 * @inheritdoc
	 */
	public static function find() {
		return (new CElementQuery('xpath:.//output[@role="contentinfo" or '.
				CXPathHelper::fromClass('msg-global').']'))->waitUntilVisible()->asMessage();
	}

	/**
	 * Check if message is good.
	 *
	 * @return boolean
	 */
	public function isGood() {
		return in_array('msg-good', explode(' ', $this->getAttribute('class')));
	}

	/**
	 * Check if message is bad.
	 *
	 * @return boolean
	 */
	public function isBad() {
		return in_array('msg-bad', explode(' ', $this->getAttribute('class')));
	}

	/**
	 * Check if message is warning.
	 *
	 * @return boolean
	 */
	public function isWarning() {
		return in_array('msg-warning', explode(' ', $this->getAttribute('class')));
	}


	/**
	 * Get message title.
	 *
	 * @return string
	 */
	public function getTitle() {
		if ($this->getAttribute('class') === 'msg-bad msg-global'){
			return strtok($this->getText(), "\n");
		}
		else {
			return $this->query('xpath:./span')->one()->getText();
		}
	}

	/**
	 * Get collection of description lines.
	 *
	 * @return CElementCollection
	 */
	public function getLines() {
		return $this->query('xpath:./div[@class="msg-details"]//li')->all();
	}

	/**
	 * Check if description line exists.
	 *
	 * @param string $text    line to be searched for
	 *
	 * @return boolean
	 */
	public function hasLine($text) {
		foreach ($this->getLines()->asText() as $line) {
			if (strpos($line, $text) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Close message.
	 *
	 * @return $this
	 */
	public function close() {
		$this->query('xpath:.//button[contains(@class, "overlay-close-btn")]')->one()->click();
		return $this->waitUntilNotPresent();
	}
}
