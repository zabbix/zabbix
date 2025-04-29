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

require_once 'vendor/autoload.php';

require_once __DIR__.'/../CElement.php';

/**
 * Checkbox list element.
 */
class CCheckboxListElement extends CElement {

	/**
	 * Get collection of checkboxes.
	 *
	 * @return CElementCollection
	 */
	public function getCheckboxes() {
		return $this->query('xpath:.//input[@type="checkbox"]')->asCheckbox()->all();
	}

	/**
	 * Get collection of form label elements.
	 *
	 * @return CElementCollection
	 */
	public function getLabels() {
		return $this->query('xpath:.//label')->all();
	}

	/**
	 * Set checkbox state.
	 *
	 * @param string|array $labels    text of checkbox label(s)
	 * @param boolean $checked        checked or not
	 *
	 * @return $this
	 */
	public function set($labels, $checked) {
		if (is_array($labels)) {
			foreach ($labels as $label) {
				$this->set($label, $checked);
			}
		}
		else {
			$label = $this->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($labels).']')->one(false);
			if (!$label->isValid()) {
				throw new Exception('Failed to find checkbox label by name: "'.$labels.'".');
			}

			$element = $label->query('xpath:../input[@type="checkbox"]')->asCheckbox()->one(false);
			if (!$element->isValid()) {
				throw new Exception('Failed to find checkbox element by label name: "'.$labels.'".');
			}

			$element->set($checked);
		}

		return $this;
	}

	/**
	 * Set checkbox state to checked.
	 *
	 * @param string|array $labels    text of checkbox label(s)
	 *
	 * @return $this
	 */
	public function check($labels) {
		return $this->set($labels, true);
	}

	/**
	 * Set checkbox state to not checked.
	 *
	 * @param string|array $labels    text of checkbox label(s)
	 *
	 * @return $this
	 */
	public function uncheck($labels) {
		return $this->set($labels, true);
	}

	/**
	 * Set state of all checkboxes to checked.
	 *
	 * @return $this
	 */
	public function checkAll() {
		$labels = $this->getLabels()->asText();

		return $this->set($labels, true);
	}

	/**
	 * Set state of all checkboxes to not checked.
	 *
	 * @return $this
	 */
	public function uncheckAll() {
		$labels = $this->getLabels()->asText();

		return $this->set($labels, false);
	}

	/**
	 * Set state of defined checkboxes to checked while making sure that other checkboxes are unchecked.
	 *
	 * @param string|array $labels    text of checkbox label(s)
	 *
	 * @return $this
	 */
	public function fill($labels) {
		if (!is_array($labels)) {
			$labels = [$labels];
		}

		$this->set(array_diff($this->getLabels()->asText(), $labels), false);

		return $this->set($labels, true);
	}

	/**
	 * @inheritdoc
	 */
	public function isEnabled($enabled = true) {
		$all = $this->getCheckboxes();

		return (($all->count() === $all->filter(CElementFilter::ENABLED)->count()) === $enabled);
	}

	/**
	 * @inheritdoc
	 */
	public function getValue() {
		$value = [];

		foreach ($this->getCheckboxes() as $checkbox) {
			if ($checkbox->isChecked() && ($label = $checkbox->getLabel())->isValid()) {
				$value[] = $label->getText();
			}
		}

		return $value;
	}
}
