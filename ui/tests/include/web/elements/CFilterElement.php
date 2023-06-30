<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
 * Filter tabs element
 */
class CFilterElement extends CElement {

	/**
	 * @inheritdoc
	 */
	public static function find() {
		return (new CElementQuery('xpath://div['.CXPathHelper::fromClass('filter-space').' or '.
				CXPathHelper::fromClass('tabfilter-container').']'))->asFilterElement();
	}

	/*
	 * Get filter form.
	 *
	 * @return CFormElement
	 */
	public function getForm() {
		return $this->query('xpath:./div[@class="tabfilter-content-container"]'.
				'/div[@class="tabfilter-tabs-container"]/div[not(@class="display-none")]/form|./form')->asForm()->one();
	}

	/**
	 * Get filter tab.
	 *
	 * @param string $name  filter name or tab number
	 *
	 * @return CElement
	 */
	public function getTab($name = null) {
		if ($name === null) {
			return $this->query('xpath:.//a[('.CXPathHelper::fromClass('tabfilter-item-link').') and @aria-label="Home"]')
					->one();
		}
		else {
			if ($this->query('xpath:./nav')->one(false)->isValid()) {
				$tab = $this->query('xpath:.//a[@class="tabfilter-item-link" and text()='.
						CXPathHelper::escapeQuotes($name).']')->one(false);

				if (!$tab->isValid() && is_numeric($name)) {
					$tab = $this->query('xpath:(.//a[@class="tabfilter-item-link"])['.$name.']')->one(false);
				}
			}
			else {
				$tab = $this->query('xpath:.//a[text()='.CXPathHelper::escapeQuotes($name).']')->one(false);
			}

			if (!$tab->isValid()) {
				throw new \Exception('Failed to find tab "'.$name.'"');
			}

		}

		return $tab;
	}

	/**
	 * Get the number of filtered entities in the filter tab.
	 *
	 * @param string $name  filter name or tab number
	 *
	 * @return string
	 */
	public function getTabDataCounter($name = null) {
		return $this->getTab($name)->isAttributePresent('data-counter')
			? $this->getTab($name)->getAttribute('data-counter')
			: false;
	}

	/**
	 * Get names of saved filters tabs. If there are no saved filters, return null.
	 *
	 * @return array
	 */
	public function getTabTitles() {
		$tabs = $this->query('xpath:.//a[@class="tabfilter-item-link"]')->all();
		if ($tabs->count() > 0) {
			return $tabs->asText();
		}

		return null;
	}

	/**
	 * Get selected filter tab.
	 *
	 * @return CElement
	 */
	public function getSelectedTab() {
		if ($this->query('xpath:./nav')->one(false)->isValid()) {
			return $this->query('xpath:.//li[contains(@class, "tabfilter-item-label") and contains(@class, "selected")]')
					->one();
		}

		$tab = $this->query('xpath:./ul/li[@aria-selected="true"]')->one(false);

		if (!$tab->isValid()) {
			throw new \Exception('None of the filter tabs are selected');
		}

		return $tab;
	}

	/**
	 * Get name of selected filter tab.
	 *
	 * @return string
	 */
	public function getSelectedTabName() {
		$tab = $this->getSelectedTab()->query('tag:a')->one();

		return $tab->isAttributePresent('aria-label') ? $tab->getAttribute('aria-label') : $tab->getText();
	}

	/**
	 * Check if tab is selected.
	 *
	 * @param string $name       filter name or tab number to be checked
	 * @param boolean $selected  tab selected or not
	 *
	 * @return boolean
	 */
	public function isTabSelected($name = null, $selected = true) {
		$tab = $this->getTab($name)->parents('tag:li')->one();

		if ($this->query('xpath:./nav')->one(false)->isValid()) {
			return $tab->hasClass('selected') === $selected;
		}

		return $tab->getAttribute('aria-selected') === json_encode($selected);
	}

	/**
	 * Select filter tab.
	 *
	 * @param string $name  filter name or tab number to be selected
	 *
	 * @return $this
	 */
	public function selectTab($name = null) {
		$tab = $this->getTab($name);
		$tab->click(true);
		$container = $tab->parents('tag:li')->one();
		$container->waitUntilClassesPresent(['selected']);

		$attribute = $this->query('xpath:./nav')->one(false)->isValid() ? 'data-target' : 'aria-controls';
		$query = $this->query('id', $container->getAttribute($attribute));
		CElementQuery::waitUntil($query, $this->isExpanded() ? CElementFilter::VISIBLE : CElementFilter::NOT_VISIBLE);

		return $this;
	}

	/**
	 * Edit filter tab properties.
	 *
	 * @param string $name	filter name to be selected
	 *
	 * @return $this
	 */
	public function editProperties($name = null) {
		if ($name !== null) {
			$this->selectTab($name);
		}

		$this->getSelectedTab()->query('xpath:.//a[@class="icon-edit"]')->one()->waitUntilReady()->click(true);

		return $this;
	}

	/**
	 * Collapse filter.
	 */
	public function close() {
		$this->expand(false);
	}

	/**
	 * Expand filter.
	 */
	public function open() {
		$this->expand(true);
	}

	/**
	 * Expand or collapse filter.
	 *
	 * @param boolean $expand    filter mode
	 */
	public function expand($expand = true) {
		if ($this->query('xpath:./nav')->one(false)->isValid()) {
			// Multitab filter.
			$tab = $this->query('xpath:./nav/ul/li/ul/li['.CXPathHelper::fromClass('selected').']')->one();
			if ($tab->hasClass('expanded') === $expand) {
				return $this;
			}

			$attribute = 'data-target';
		}
		else {
			// Simple filter with "Filter" and time tabs on the right.
			$tab = $this->query('xpath:./ul/li[@tabindex="0"]')->one();
			if ($tab->getAttribute('aria-expanded') === json_encode($expand)) {
				return $this;
			}

			$attribute = 'aria-controls';
		}

		$tab->query('tag:a')->one()->click();
		$query = $this->query('id', $tab->getAttribute($attribute));

		CElementQuery::waitUntil($query, $expand ? CElementFilter::VISIBLE : CElementFilter::NOT_VISIBLE);
	}

	/**
	 * Checking filter state.
	 *
	 * @param boolean $expanded    expanded state of filter
	 *
	 * @return boolean
	 */
	public function isExpanded($expanded = true) {
		if ($this->query('xpath:./nav')->one(false)->isValid()) {
			// Multitab filter.
			$tab = $this->query('xpath:./nav/ul/li/ul/li['.CXPathHelper::fromClass('selected').']')->one();
			$attribute = 'data-target';
		}
		else {
			$tab = $this->query('xpath:./ul/li[@tabindex="0"]')->one();
			$attribute = 'aria-controls';
		}

		return $this->query('id', $tab->getAttribute($attribute))->one()->isDisplayed($expanded);
	}
}
