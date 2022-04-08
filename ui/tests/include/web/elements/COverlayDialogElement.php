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
 * Overlay dialog element.
 */
class COverlayDialogElement extends CElement {

	/**
	 * @inheritdoc
	 */
	public function waitUntilReady() {
		$this->query('xpath:.//div[contains(@class, "is-loading")]')->waitUntilNotPresent();

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public static function find() {
		return (new CElementQuery('xpath://div['.CXPathHelper::fromClass('overlay-dialogue modal').']'))->asOverlayDialog();
	}

	/**
	 * Get title text.
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->query('xpath:./div[@class="dashboard-widget-head"]/h4')->one()->getText();
	}

	/**
	 * Set context (host / hostgroup) of overlay dialog.
	 *
	 * This code should be different from older versions of Zabbix (< 5.0).
	 *
	 * @param array    $context
	 * @param integer  $mode    multiselect fill mode, or null if default
	 */
	public function setDataContext($context, $mode = null) {
		if (!$context) {
			return $this;
		}

		// Assuming that we are looking for a single multiselect...
		$element = $this->query('xpath:./div[@class="overlay-dialogue-controls"]//./div[@class="multiselect-control"]')
				->asMultiselect()->one();

		if ($mode !== null) {
			$element->setFillMode($mode);
		}

		$element->fill($context);
		$this->waitUntilReady();

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
	 */
	public function close() {
		$this->query('class:overlay-close-btn')->one()->click();
		$this->ensureNotPresent();
	}

	/**
	 * Wait until overlay dialogue and overlay dialogue background is not visible one page.
	 */
	public static function ensureNotPresent() {
		(new CElementQuery('xpath', '//*['.CXPathHelper::fromClass('overlay-dialogue-body').' or '.
				CXPathHelper::fromClass('overlay-bg').']'))->waitUntilNotVisible();
	}

	/**
	 * Scroll the dialog to the top position.
	 */
	public function scrollToTop() {
		CElementQuery::getDriver()->executeScript('arguments[0].scrollTo(0, 0)', [$this->getContent()]);
	}
}
