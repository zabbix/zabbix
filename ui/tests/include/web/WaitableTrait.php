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

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\StaleElementReferenceException;

/**
 * Trait for objects implementing IWaitable interface.
 */
trait WaitableTrait {

	/**
	 * Get object selector (if any) as text.
	 *
	 * @return string
	 */
	public function getSelectorAsText() {
		if (isset($this->by)) {
			return '"'.$this->by->getValue().'" ('.$this->by->getMechanism().')';
		}

		return null;
	}

	/**
	 * Wait until object is ready.
	 *
	 * @return $this
	 */
	public function waitUntilReady() {
		CElementQuery::waitUntil($this, CElementFilter::READY);

		return $this;
	}

	/**
	 * Wait until object is visible.
	 *
	 * @return $this
	 */
	public function waitUntilVisible() {
		CElementQuery::waitUntil($this, CElementFilter::VISIBLE);

		return $this;
	}

	/**
	 * Wait until object is not visible.
	 *
	 * @return $this
	 */
	public function waitUntilNotVisible() {
		CElementQuery::waitUntil($this, CElementFilter::NOT_VISIBLE);

		return $this;
	}

	/**
	 * Wait until object is present.
	 *
	 * @return $this
	 */
	public function waitUntilPresent() {
		CElementQuery::waitUntil($this, CElementFilter::PRESENT);

		return $this;
	}

	/**
	 * Wait until object is not present.
	 *
	 * @return $this
	 */
	public function waitUntilNotPresent() {
		CElementQuery::waitUntil($this, CElementFilter::NOT_PRESENT);

		return $this;
	}

	/**
	 * Wait until object text is present.
	 *
	 * @return $this
	 */
	public function waitUntilTextPresent($text) {
		CElementQuery::waitUntil($this, CElementFilter::TEXT_PRESENT, [$text]);

		return $this;
	}

	/**
	 * Wait until object text is not present.
	 *
	 * @param string $text    text to be present
	 *
	 * @return $this
	 */
	public function waitUntilTextNotPresent($text) {
		CElementQuery::waitUntil($this, CElementFilter::TEXT_NOT_PRESENT, [$text]);

		return $this;
	}

	/**
	 * Wait until object attribute is present.
	 *
	 * @param string $attributes    attributes to be present
	 *
	 * @return $this
	 */
	public function waitUntilAttributesPresent($attributes) {
		CElementQuery::waitUntil($this, CElementFilter::ATTRIBUTES_PRESENT, [$attributes]);

		return $this;
	}

	/**
	 * Wait until object attribute is present.
	 *
	 * @param string $attributes    attributes not be present
	 *
	 * @return $this
	 */
	public function waitUntilAttributesNotPresent($attributes) {
		CElementQuery::waitUntil($this, CElementFilter::ATTRIBUTES_NOT_PRESENT, [$attributes]);

		return $this;
	}


	/**
	 * Wait until object is clickable.
	 *
	 * @return $this
	 */
	public function waitUntilClickable() {
		CElementQuery::waitUntil($this, CElementFilter::CLICKABLE);

		return $this;
	}

	/**
	 * Wait until object is not clickable.
	 *
	 * @return $this
	 */
	public function waitUntilNotClickable() {
		CElementQuery::waitUntil($this, CElementFilter::NOT_CLICKABLE);

		return $this;
	}

	/**
	 * Get logically reversed condition. Acts as "not" statement.
	 *
	 * @param callable $condition    condition to be reversed
	 *
	 * @return callable
	 */
	protected function getReversedCondition($condition) {
		return function () use ($condition) {
			try {
				return !call_user_func($condition);
			}
			catch (NoSuchElementException $e) {
				return true;
			}
			catch (StaleElementReferenceException $e) {
				return true;
			}
		};
	}

	/**
	 * @inheritdoc
	 */
	public function getNotClickableCondition() {
		return $this->getReversedCondition($this->getClickableCondition());
	}

	/**
	 * @inheritdoc
	 */
	public function getNotPresentCondition() {
		return $this->getReversedCondition($this->getPresentCondition());
	}

	/**
	 * @inheritdoc
	 */
	public function getNotVisibleCondition() {
		return $this->getReversedCondition($this->getVisibleCondition());
	}

	/**
	 * @inheritdoc
	 */
	public function getTextNotPresentCondition($text) {
		return $this->getReversedCondition($this->getTextPresentCondition($text));
	}

	/**
	 * @inheritdoc
	 */
	public function getAttributesNotPresentCondition($attributes) {
		return $this->getReversedCondition($this->getAttributesPresentCondition($attributes));
	}

	/**
	 * @inheritdoc
	 */
	public function getNotSelectedCondition() {
		return $this->getReversedCondition($this->getSelectedCondition());
	}
}
