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

use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\Exception\TimeoutException;

/**
 * Multiselect element.
 */
class CMultiselectElement extends CElement {

	/**
	 * Multiselect fill modes.
	 */
	const MODE_SELECT			= 0;
	const MODE_SELECT_MULTIPLE	= 1;
	const MODE_TYPE				= 2;

	protected static $default_mode = self::MODE_TYPE;
	protected $mode;

	/**
	 * @inheritdoc
	 */
	public static function createInstance(RemoteWebElement $element, $options = []) {
		$instance = parent::createInstance($element, $options);

		if ($instance->mode === null) {
			$instance->mode = self::$default_mode;
		}

		return $instance;
	}

	/**
	 * Set default fill mode.
	 *
	 * @param integer $mode    MODE_SELECT, MODE_SELECT_MULTIPLE or MODE_TYPE
	 */
	public static function setDefaultFillMode($mode) {
		self::$default_mode = $mode;
	}

	/**
	 * Set fill mode.
	 *
	 * @param integer $mode    MODE_SELECT, MODE_SELECT_MULTIPLE or MODE_TYPE
	 *
	 * @return $this
	 */
	public function setFillMode($mode) {
		$this->mode = $mode;

		return $this;
	}

	/**
	 * Remove all elements from multiselect.
	 *
	 * @return $this
	 */
	public function clear() {
		$id = $this->getID();
		$query = $this->query('xpath:.//span['.CXPathHelper::fromClass('zi-remove-smaller').']');
		$elements = $query->all();
		$elements->click();
		$query->waitUntilNotPresent();

		// TODO: reload should be removed after fix DEV-1535
		if ($elements->count() > 0 && $this->parents('class:overlay-dialogue-controls')->exists() && $id === $this->getID()) {
			$this->waitUntilReloaded();
		}

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
		if (is_array($label)) {
			throw new Exception('Select of multiple labels is not supported in single select mode.');
		}

		if ($label === '') {
			return $this->clear();
		}

		$this->edit($context)->query('link:'.$label)->waitUntilVisible(3)->one()->click()->waitUntilNotPresent();

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
				if (!$row->isValid()) {
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
				']/span['.CXPathHelper::fromClass('zi-remove-smaller').']'
		);

		$query->one()->click();
		$query->waitUntilNotPresent();

		return $this;
	}

	/**
	 * Get collection of multiselect controls (buttons).
	 *
	 * @return CElement
	 */
	public function getControls() {
		$buttons = [];
		$xpath = 'xpath:.//button';

		foreach ($this->query($xpath)->all() as $button) {
			$buttons[$button->getText()] = $button;
		}

		return new CElementCollection($buttons);
	}

	/**
	 * Open selection overlay dialog.
	 *
	 * @param mixed $context  overlay dialog context (hostgroup / host)
	 *
	 * @return COverlayDialogElement
	 */
	public function edit($context = null) {
		/* TODO: extend the function for composite elements with two buttons,
		 * Example of such multiselect: [ Input field ] ( Select item ) ( Select prototype )
		 */
		$index = COverlayDialogElement::find()->count();
		$this->getControls()->first()->click();

		return COverlayDialogElement::find($index)->waitUntilPresent()->one()
				->waitUntilReady()->setDataContext($context, $this->mode);
	}

	/**
	 * @inheritdoc
	 */
	public function type($text) {
		if (!is_array($text)) {
			$text = [$text];
		}

		$input = $this->query('xpath:.//input[not(@type="hidden")]|textarea')->one();
		$id = CXPathHelper::escapeQuotes($this->query('class:multiselect')->one()->getAttribute('id'));
		foreach ($text as $value) {
			$input->overwrite($value)->fireEvent('keyup');

			if ($value === null || $value === '') {
				continue;
			}

			$content = CXPathHelper::escapeQuotes($value);
			$prefix = '//div[@data-opener='.$id.']/ul[contains(@class, "multiselect-suggest")]/li';
			$query = $this->query('xpath', implode('|', [
				$prefix.'[@data-label='.$content.']',
				$prefix.'[contains(@data-label,'.$content.')]/span[contains(@class, "suggest-found") and text()='.$content.']',
				$prefix.'[contains(@class, "suggest-new")]/span[text()='.$content.']'
			]));

			for ($i = 0; $i < 2; $i++) {
				try {
					$query->waitUntilPresent();
				}
				catch (TimeoutException $exception) {
					if ($i === 0) {
						$input->overwrite($value)->fireEvent('keyup');
					}
					else {
						throw new Exception('Cannot find value with label "'.$value.'" in multiselect element.');
					}
				}
			}

			$query->one()->click();
		}

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function overwrite($text) {
		return $this->clear()->type($text);
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
		if (!in_array($this->mode, [self::MODE_SELECT, self::MODE_SELECT_MULTIPLE, self::MODE_TYPE])) {
			throw new Exception('Unknown fill mode is set for multiselect element.');
		}

		$this->clear();

		// TODO: for loop and try/catch block should be removed after DEV-1535 is fixed.
		for ($i = 0; $i < 2; $i++) {
			try {
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

							if ($this->mode === self::MODE_SELECT) {
								throw new Exception('Cannot select multiple items in single select mode.');
							}
							elseif ($this->mode === self::MODE_SELECT_MULTIPLE) {
								$this->selectMultiple($label, $context);
							}
							else {
								$this->type($label);
							}
						}

						return $this;
					}
				}

				if ($this->mode === self::MODE_SELECT) {
					return $this->select($labels, $context);
				}
				elseif ($this->mode === self::MODE_SELECT_MULTIPLE) {
					return $this->selectMultiple($labels, $context);
				}

				return $this->type($labels);
			}
			catch (StaleElementReferenceException $exception) {
				if ($i === 1) {
					throw $exception;
				}
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getValue() {
		$selected = $this->getSelected();
		if (is_array($selected) && count($selected) === 0) {
			$selected = '';
		}

		return $selected;
	}

	/**
	 * @inheritdoc
	 */
	public function isEnabled($enabled = true) {
		$input = $this->query('xpath:.//input[not(@type="hidden")]|textarea')->one(false);
		if ($this->query('class:search-disabled')->one(false)->isValid()) {
			if (($input->isValid() && $input->getAttribute('disabled') === null) !== $enabled) {
				return false;
			}
		}
		else {
			if (!$input->isEnabled($enabled)) {
				return false;
			}
		}

		$multiselect = $this->query('class:multiselect')->one(false);
		if ($multiselect->isValid() && ($multiselect->getAttribute('aria-disabled') === 'true'
				|| $multiselect->getAttribute('aria-readonly') === 'true') === $enabled) {
			return false;
		}

		foreach ($this->getControls() as $control) {
			if ($control->isEnabled() !== $enabled) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get list of suggested values.
	 *
	 * @return array
	 */
	public function getSuggestionsText() {
		$id = CXPathHelper::escapeQuotes($this->query('class:multiselect')->one()->getAttribute('id'));
		return $this->query('xpath://div[@data-opener='.$id.']/ul[contains(@class, "multiselect-suggest")]')
				->waitUntilVisible()->query('xpath:./li[not(@class="suggest-hover")]')->all()->asText();
	}
}
