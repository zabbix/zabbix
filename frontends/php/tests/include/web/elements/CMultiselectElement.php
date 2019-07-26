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
	 * @param mixed  $context  overlay dialog context (hostgroup / host)
	 *
	 * @return $this
	 */
	public function select($label, $context = null) {
		$this->edit($context)->query('link:'.$label)->one()->click();
		$this->query('xpath:.//span[@class="subfilter-enabled"][string()='.CXPathHelper::escapeQuotes($label).']')
				->waitUntilPresent();

		return $this;
	}

	/**
	 * Add selection by multiple labels.
	 *
	 * @param array  $labels   array of label texts
	 * @param mixed  $context  multiselect context (hostgroup / host)
	 *
	 * @return $this
	 */
	public function selectMultiple($labels, $context = null) {
		if ($labels) {
			if (!is_array($labels)) {
				$labels = [$labels];
			}

			$overlay = $this->edit($context);
			$table = $overlay->getContent()->asTable();

			foreach ($labels as $label) {
				$row = $table->findRow('Name', $label);
				if ($row === null) {
					throw new Exception('Cannot select row with label "'.$label.'" in multiselect element.');
				}

				$row->select();
			}
			$overlay->getFooter()->query('button:Select')->one()->click();
			$overlay->waitUntilNotPresent();
		}

		return $this;
	}

	/**
	 * Select all possible options.
	 *
	 * @param mixed $context  overlay dialog context (hostgroup / host)
	 *
	 * @return $this
	 */
	public function selectAll($context = null) {
		$overlay = $this->edit($context);
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
	 * @param mixed $context  overlay dialog context (hostgroup / host)
	 *
	 * @return COverlayDialogElement
	 */
	public function edit($context = null) {
		$this->query('xpath:.//div[@class="multiselect-button"]/button')->one()->click();

		return COverlayDialogElement::find()->all()->last()->waitUntilReady()->setDataContext($context);
	}

	/**
	 * Alias for selectMultiple.
	 * @see self::selectMultiple
	 *
	 * @param array $labels    array of label texts
	 * @param mixed $context   overlay dialog context (hostgroup / host)
	 *
	 * @return $this
	 */
	public function fill($labels, $context = null) {
		$this->clear();

		if ($context === null && is_array($labels)) {
			if (array_key_exists('values', $labels)) {
				if (array_key_exists('context', $labels)) {
					$context = $labels['context'];
				}

				$labels = $labels['values'];
			}
			else {
				foreach ($labels as $label) {
					if (is_array($label) && array_key_exists('values', $label)) {
						$context = (array_key_exists('context', $label)) ? $label['context'] : null;
						$label = $label['values'];
					}

					$this->selectMultiple($label, $context);
				}

				return $this;
			}
		}

		return $this->selectMultiple($labels, $context);
	}
}
