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

require_once dirname(__FILE__).'/../CElement.php';

/**
 * Filter tabs element
 */
class CFilterElement extends CElement {

	/**
	 * Filter with multitab on the left, such as on the Problems, Hosts and Latest Data pages in the Monitoring section.
	 */
	const CONTEXT_LEFT = 0;

	/**
	 * Filter with "Filter" and/or time tabs on the right.
	 */
	const CONTEXT_RIGHT = 1;

	protected $context = self::CONTEXT_RIGHT;

	/**
	 * Set filter context.
	 *
	 * @param integer $context    CONTEXT_LEFT or CONTEXT_RIGHT
	 */
	public function setContext($context) {
		if (!in_array($this->context, [self::CONTEXT_LEFT, self::CONTEXT_RIGHT])) {
			throw new Exception('Unknown context is set for filter element.');
		}

		$this->context = $context;

		return $this;
	}

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
	 * Get data depending on the filter type.
	 *
	 * @return array
	 */
	public function getFilterType() {
		if ($this->context === self::CONTEXT_LEFT) {
			return [
				'selected_tab' => $this->query('xpath:./nav/ul/li/ul['.CXPathHelper::fromClass('tabfilter-tabs sortable').
						']/li['.CXPathHelper::fromClass('selected').']')->one(),
				'attribute' => 'data-target',
				'is_expanded' => function ($target) {
					return $target->hasClass('expanded');
				}
			];
		}
		else {
			// Time tab on the right side in multitab filter.
			if ($this->query('xpath:./nav')->one(false)->isValid()) {
				return [
					'selected_tab' => $this->query('xpath:./nav/ul/li/ul[not(contains(@class,"ui-sortable-container"))]'.
							'/li['.CXPathHelper::fromClass('selected').']')->one(),
					'attribute' => 'data-target',
					'is_expanded' => function ($target) {
						return $target->hasClass('expanded');
					}
				];
			}

			return [
				// Last selected tab. It can be closed or open.
				'selected_tab' => $this->query('xpath:./ul/li[@tabindex="0"]')->one(),
				'attribute' => 'aria-controls',
				'is_expanded' =>  function ($target) {
					return filter_var($target->getAttribute('aria-expanded'), FILTER_VALIDATE_BOOLEAN);
				}
			];
		}
	}

	/**
	 * Get filter tab.
	 *
	 * @param string $name  filter name or tab number
	 *
	 * @return CElement
	 */
	public function getTab($name = null) {
		if ($this->context === self::CONTEXT_LEFT) {
			if ($name === null) {
				return $this->query('xpath:.//a[('.CXPathHelper::fromClass('tabfilter-item-link').') and @aria-label="Home"]')
						->one();
			}

			// TODO: fix formatting after git-hook improvements DEV-2396
			$tab = $this->query('xpath:.//a[('.CXPathHelper::fromClass('tabfilter-item-link').') and text()='.CXPathHelper::escapeQuotes($name).']')->one(false);

			if (!$tab->isValid() && is_numeric($name)) {
				$tab = $this->query('xpath:(.//a[@class="tabfilter-item-link"])['.$name.']')->one(false);
			}
		}
		else {
			$tab = $this->query('link', $name)->one(false);
		}

		if (!$tab->isValid()) {
			throw new \Exception('Failed to find tab "'.$name.'"');
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
		return $this->getTab($name)->getAttribute('data-counter') ?? false;
	}

	/**
	 * Get names of saved filters tabs. If there are no saved filters, return null.
	 *
	 * @return array
	 */
	public function getTabsText() {
		$tabs = $this->query('xpath:.//li[not(@data-target="tabfilter_timeselector")]/a[contains(@class, '.
				'"tabfilter-item-link") and not(@aria-label="Home")]')->all();
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
		$filter = $this->getFilterType();

		if ($this->context === self::CONTEXT_LEFT || $this->query('xpath:./nav')->one(false)->isValid()) {
			return $filter['selected_tab'];
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
		if ($this->isTabSelected($name)) {
			$selected = true;
			// Check that time filter tab is not open.
			if ($this->context === self::CONTEXT_LEFT && $this->query('id:tabfilter_timeselector')->one()->isVisible()) {
				$selected = false;
			}

			if ($selected) {
				return $this;
			}
		}

		$tab = $this->getTab($name);
		$tab->click();
		$container = $tab->parents('tag:li')->one();
		$multitab = $this->query('xpath:./nav')->one(false)->isValid();
		$container->waitUntilClassesPresent([$multitab ? 'selected' : 'ui-tabs-active']);

		if ($this->isExpanded()) {
			CElementQuery::waitUntil($this->query('id',
					$container->getAttribute($multitab ? 'data-target' : 'aria-controls')), CElementFilter::VISIBLE);
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

		// TODO: fix after git-hook improvements DEV-2396
		$this->getSelectedTab()->query('xpath:.//a['.CXPathHelper::fromClass('tabfilter-edit').']')->one()->waitUntilClickable()->click();

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

		if (call_user_func($filter['is_expanded'], $filter['selected_tab']) === $expand) {
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
