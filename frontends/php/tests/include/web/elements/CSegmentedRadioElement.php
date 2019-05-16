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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/../CElement.php';

/**
 * Segmented radio element.
 */
class CSegmentedRadioElement extends CElement {

	/**
	 * Get collection of labels.
	 *
	 * @return CElementCollection
	 */
	public function getLabels() {
		return $this->query('tag:label')->all();
	}

	/**
	 * Get text of selected element.
	 *
	 * @return string
	 */
	public function getText() {
		return $this->query('xpath:.//input[@checked="checked"]/../label')->one()->getText();
	}

	/**
	 * Select label by text.
	 *
	 * @param string $text    label text to be selected
	 *
	 * @return $this
	 */
	public function select($text) {
		$this->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($text).']')->waitUntilVisible()->one()->click();

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function isEnabled($enabled = true) {
		return (($this->query('xpath:.//input[@type="radio"][not(@disabled)]')->count() > 0) === $enabled);
	}

	/**
	 * Alias for select.
	 * @see self::select
	 *
	 * @param string $text    label text to be selected
	 *
	 * @return $this
	 */
	public function fill($text) {
		return $this->select($text);
	}

	/**
	 * Alias for getText.
	 * @see self::getText
	 *
	 * @return string
	 */
	public function getValue() {
		return $this->getText();
	}
}
