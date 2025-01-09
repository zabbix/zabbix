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
	 * @param integer $timeout    timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilReady($timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::READY, [], $timeout);

		return $this;
	}

	/**
	 * Wait until object is visible.
	 *
	 * @param integer $timeout    timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilVisible($timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::VISIBLE, [], $timeout);

		return $this;
	}

	/**
	 * Wait until object is not visible.
	 *
	 * @param integer $timeout    timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilNotVisible($timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::NOT_VISIBLE, [], $timeout);

		return $this;
	}

	/**
	 * Wait until object is present.
	 *
	 * @param integer $timeout    timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilPresent($timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::PRESENT, [], $timeout);

		return $this;
	}

	/**
	 * Wait until object is not present.
	 *
	 * @param integer $timeout    timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilNotPresent($timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::NOT_PRESENT, [], $timeout);

		return $this;
	}

	/**
	 * Wait until object text is present.
	 *
	 * @param string  $text        text to be present
	 * @param integer $timeout     timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilTextPresent($text, $timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::TEXT_PRESENT, [$text], $timeout);

		return $this;
	}

	/**
	 * Wait until object text is not present.
	 *
	 * @param string  $text        text not to be present
	 * @param integer $timeout     timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilTextNotPresent($text, $timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::TEXT_NOT_PRESENT, [$text], $timeout);

		return $this;
	}

	/**
	 * Wait until object attribute is present.
	 *
	 * @param string|array $attributes    attributes to be present
	 * @param integer      $timeout       timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilAttributesPresent($attributes, $timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::ATTRIBUTES_PRESENT, [$attributes], $timeout);

		return $this;
	}

	/**
	 * Wait until object attribute is not present.
	 *
	 * @param string|array $attributes    attributes not to be present
	 * @param integer      $timeout       timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilAttributesNotPresent($attributes, $timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::ATTRIBUTES_NOT_PRESENT, [$attributes], $timeout);

		return $this;
	}

	/**
	 * Wait until object class is present.
	 *
	 * @param string|array $classes    classes to be present
	 * @param integer      $timeout    timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilClassesPresent($classes, $timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::CLASSES_PRESENT, [$classes], $timeout);

		return $this;
	}

	/**
	 * Wait until object class is not present.
	 *
	 * @param string|array $classes    classes not to be present
	 * @param integer      $timeout    timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilClassesNotPresent($classes, $timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::CLASSES_NOT_PRESENT, [$classes], $timeout);

		return $this;
	}

	/**
	 * Wait until object is clickable.
	 *
	 * @param integer $timeout    timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilClickable($timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::CLICKABLE, [], $timeout);

		return $this;
	}

	/**
	 * Wait until object is not clickable.
	 *
	 * @param integer $timeout    timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilNotClickable($timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::NOT_CLICKABLE, [], $timeout);

		return $this;
	}

	/**
	 * Wait until element count is present.
	 *
	 * @param integer      $count      element count to wait for
	 * @param integer      $timeout    timeout in seconds
	 *
	 * @return $this
	 */
	public function waitUntilCount($count, $timeout = null) {
		CElementQuery::waitUntil($this, CElementFilter::COUNT, [$count], $timeout);

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
	public function getNotEnabledCondition() {
		return $this->getReversedCondition($this->getEnabledCondition());
	}

	/**
	 * @inheritdoc
	 */
	public function getNotReadyCondition() {
		return $this->getReversedCondition($this->getReadyCondition());
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
	public function getClassesNotPresentCondition($classes) {
		return $this->getReversedCondition($this->getClassesPresentCondition($classes));
	}

	/**
	 * @inheritdoc
	 */
	public function getNotSelectedCondition() {
		return $this->getReversedCondition($this->getSelectedCondition());
	}
}
