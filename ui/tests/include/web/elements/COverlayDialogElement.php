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
 * Overlay dialog element.
 */
class COverlayDialogElement extends CElement {

	/**
	 * @inheritdoc
	 */
	public function waitUntilReady($timeout = null) {
		$this->query('xpath:.//div[contains(@class, "is-loading")]')->waitUntilNotPresent($timeout);

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public static function find($index = null) {
		$suffix = ($index !== null) ? '['.($index + 1).']' : '';

		return (new CElementQuery('xpath://div['.CXPathHelper::fromClass('overlay-dialogue modal').']'.
				$suffix))->asOverlayDialog();
	}

	/**
	 * Get title text.
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->query('xpath:./div[@class="overlay-dialogue-header"]/h4')->one()->getText();
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
	 *
	 * @param boolean $cancel    true if cancel button, false if close (x) button
	 */
	public function close($cancel = false) {
		$count = COverlayDialogElement::find()->all()->count();
		if ($cancel) {
			$this->getFooter()->query('button:Cancel')->one()->click();
		}
		else {
			$this->query('class:btn-overlay-close')->one()->click();
		}

		if ($count === 1) {
			self::ensureNotPresent();
		}
		else {
			$this->waitUntilNotPresent();
		}
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

	/**
	 * Close all opened overlay dialogues
	 *
	 * @param boolean $cancel		true if cancel button, false if close (x) button
	 */
	public static function closeAll($cancel = false) {
		COverlayDialogElement::find()->all()->reverse()->close($cancel);
	}
}
