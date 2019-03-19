<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Overlay dialog element.
 */
class COverlayDialogElement extends CElement {

	/**
	 * @inheritdoc
	 */
	public function waitUntilReady() {
		$this->query('class:preloader')->waitUntilNotPresent();

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public static function find() {
		return (new CElementQuery('xpath://div[@class="overlay-dialogue modal modal-popup"]'))->asOverlayDialog();
	}

	/**
	 * Get title text.
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->query('xpath:./div[@class="dashbrd-widget-head"]/h4')->one()->getText();
	}

	/**
	 * Set context (host / hostgroup) of overlay dialog.
	 *
	 * @param array $context
	 */
	public function setDataContext($context) {
		if (!$context) {
			return $this;
		}

		$query = $this->query('xpath:./div[@class="overlay-dialogue-controls"]//select')->asDropdown()->waitUntilPresent();

		if (!is_array($context)) {
			$query->one()->select($context);
			$this->waitUntilReady();

			return $this;
		}

		$controls = $query->all()->indexByAttribute('name');

		foreach ($context as $name => $value) {
			if (is_array($value) && array_key_exists('name', $value) && array_key_exists('value', $value)) {
				$name = $value['name'];
				$value = $value['value'];
			}

			if ($controls->exists($name)) {
				$controls->get($name)->select($value);
				$this->waitUntilReady();
			}
			else {
				throw new Exception('Cannot set overlay dialog context for \"'.$name.'\" to \"'.$value.'\".');
			}
		}

		return $this;
	}

	/**
	 * Get content of overlay dialog.
	 *
	 * @return CElement
	 */
	public function getContent() {
		return $this->query('class:overlay-dialogue-body')->waitUntilPresent()->one();
	}

	/**
	 * Get content of overlay dialog footer.
	 *
	 * @return CElement
	 */
	public function getFooter() {
		return $this->query('class:overlay-dialogue-footer')->one();
	}

	/**
	 * Close overlay dialog.
	 *
	 * @return $this
	 */
	public function close() {
		$this->query('class:overlay-close-btn')->one()->click();
		return $this->waitUntilNotPresent();
	}
}
