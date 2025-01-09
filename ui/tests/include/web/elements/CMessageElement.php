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


/**
 * Global message element.
 */
class CMessageElement extends CElement {

	/**
	 * Simplified selector for message element that can be located directly on page.
	 *
	 * @param string|CElement    $selector    message element search area
	 * @param boolean            $strict      absolute or relative path to message element
	 *
	 * @return CMessageElement
	 */
	public static function find($selector = null, $strict = false) {
		$prefix = 'xpath:./'.(!$strict ? '/' : '');
		$query = new CElementQuery($prefix.'output[@role="contentinfo" or '.CXPathHelper::fromClass('msg-global').']');
		if ($selector) {
			if (!$selector instanceof CElement) {
				$selector = (new CElementQuery($selector))->waitUntilPresent()->one();
			}
			$query->setContext($selector);
		}
		return $query->waitUntilVisible()->asMessage();
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
		if ($this->getAttribute('class') === 'msg-global msg-bad'){
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
		$this->query('xpath:.//button[contains(@class, "btn-overlay-close")]')->one()->click();
		return $this->waitUntilNotVisible();
	}
}
