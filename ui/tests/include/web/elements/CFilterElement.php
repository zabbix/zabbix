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

	/*
	 * Get data depending on the filter type. It can be a simple filter with "Filter" and time tabs on the right, or
	 * a filter with multitab, such as on the Problems, Hosts and Latest Data pages in the Monitoring section.
	 *
	 * @return array
	 */
	public function getFilterType() {
		if ($this->query('xpath:./nav')->one(false)->isValid()) {
			// Multitab filter.
			$selected_tab = $this->query('xpath:./nav/ul/li/ul/li['.CXPathHelper::fromClass('selected').']')->one();
			$attribute = 'data-target';
			$is_expanded = $selected_tab->hasClass('expanded');
		}
		else {
			// Last selected tab. It can be closed or open.
			$selected_tab = $this->query('xpath:./ul/li[@tabindex="0"]')->one();
			$attribute = 'aria-controls';
			$is_expanded = filter_var($selected_tab->getAttribute('aria-expanded'), FILTER_VALIDATE_BOOLEAN);
		}

		return [
			'selected_tab' => $selected_tab,
			'attribute' => $attribute,
			'is_expanded' => $is_expanded,
		];
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
		$data_counter = $this->getTab($name)->getAttribute('data-counter');

		if ($data_counter === null) {
			return false;
		}

		return $data_counter;
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
			$tab = $this->query('xpath:.//li[contains(@class, "tabfilter-item-label") and contains(@class, "selected")]');

			if ($tab->all()->count() > 1) {
				CTest::zbxAddWarning('More than one tab selected: '.implode(', ', $tab->all()->asText()));
			}

			return $tab->one();
		}

		// Selected and expanded tab.
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
		$name = $tab->getAttribute('aria-label');

		if ($name === null) {
			return $tab->getText();
		}

		return $name;
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
		return ($this->getSelectedTabName() === $name) === $selected;
	}

	/**
	 * Select filter tab.
	 *
	 * @param string $name  filter name or tab number to be selected
	 *
	 * @return $this
	 */
	public function selectTab($name = null) {
		if ($this->isTabSelected($name)) {
			return $this;
		}

		$tab = $this->getTab($name);
		$tab->click(true);
		$container = $tab->parents('tag:li')->one();
		$container->waitUntilClassesPresent(['selected']);

		if ($this->isExpanded()) {
			$attribute = $this->query('xpath:./nav')->one(false)->isValid() ? 'data-target' : 'aria-controls';
			CElementQuery::waitUntil($this->query('id', $container->getAttribute($attribute)), CElementFilter::VISIBLE);
		}

		return $this;
	}

	/**
	 * Edit filter tab properties.
	 *
	 * @param string $name	filter name to be selected
	 *
	 * @return COverlayDialogElement
	 */
	public function editProperties($name = null) {
		if ($name !== null) {
			$this->selectTab($name);
		}

		$this->getSelectedTab()->query('xpath:.//a[@class="icon-edit"]')->one()->waitUntilClickable()->click(true);

		return COverlayDialogElement::find()->one()->waitUntilReady();
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
		$filter = $this->getFilterType();

		if ($filter['is_expanded'] === $expand) {
			return $this;
		}

		$filter['selected_tab']->query('tag:a')->one()->click();
		$query = $this->query('id', $filter['selected_tab']->getAttribute($filter['attribute']));

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
		$filter = $this->getFilterType();

		return $this->query('id',
				$filter['selected_tab']->getAttribute($filter['attribute']))->one()->isDisplayed($expanded);
	}
}
