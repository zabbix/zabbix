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
 * Multiselect element.
 */
class CMultiselectElement extends CElement {

	/**
	 * Remove all elements from multiselect.
	 *
	 * @return $this
	 */
	public function clear() {
		$query = $this->query('xpath:.//span[@class="subfilter-disable-btn"]');
		$query->all()->click();
		$query->waitUntilNotPresent();

		return $this;
	}

	/**
	 * Get labels of selected elements.
	 *
	 * @return array
	 */
	public function getSelected() {
		return $this->query('xpath:.//span[@class="subfilter-enabled"]')->all()->asText();
	}

	/**
	 * Add selection by label.
	 *
	 * @param string $label    label text
	 *
	 * @return $this
	 */
	public function select($label) {
		$this->edit()->query('link:'.$label)->one()->click();
		$this->query('xpath:.//span[@class="subfilter-enabled"][string()='.CXPathHelper::escapeQuotes($label).']')
				->waitUntilPresent();

		return $this;
	}

	/**
	 * Add selection by multiple labels.
	 *
	 * @param array $label    array of label texts
	 *
	 * @return $this
	 */
	public function selectMultiple($labels) {
		if ($labels) {
			$table = $this->edit()->getContent()->asTable();

			foreach ($labels as $label) {
				$table->findRow('Name', $label)->select();
			}
		}

		return $this;
	}

	/**
	 * Select all possible options.
	 *
	 * @return $this
	 */
	public function selectAll() {
		$overlay = $this->edit();
		$overlay->query('xpath:.//input[@name="all_records"]')->one()->click();
		$overlay->getFooter()->query('button:Select')->one()->click();

		$overlay->waitUntilNotPresent();

		return $this;
	}

	/**
	 * Remove selected option.
	 *
	 * @param string $label    label text
	 *
	 * @return $this
	 */
	public function remove($label) {
		$query = $this->query('xpath:.//span[@class="subfilter-enabled"][string()='.CXPathHelper::escapeQuotes($label).
				']/span[@class="subfilter-disable-btn"]'
		);

		$query->one()->click();
		$query->waitUntilNotPresent();

		return $this;
	}

	/**
	 * Open selection overlay dialog.
	 *
	 * @return COverlayDialogElement
	 */
	public function edit() {
		$this->query('xpath:.//div[@class="multiselect-button"]/button')->one()->click();

		return COverlayDialogElement::find()->all()->last()->waitUntilReady();
	}
}
