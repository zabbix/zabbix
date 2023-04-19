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
 * Filter container element.
 */
class CFilterContainerElement extends CElement {

	/**
	 * @inheritdoc
	 */
	public static function find() {
		return (new CElementQuery('xpath://div['.CXPathHelper::fromClass('filter-space').']'))->asFilterForm();
	}

	/**
	 * Get filter form.
	 *
	 * @return CFormElement
	 */
	public function getFilterForm() {
		return $this->query('name:zbx_filter')->asForm()->one();
	}

	/**
	 * Get filter container.
	 *
	 * @return CFormElement
	 */
	public function getFilterContainer() {
		return $this->getFilterForm()->query('id:tab_0')->one();
	}

	/**
	 * Collapse filter container.
	 * Filter container should not be in expanded mode.
	 */
	public function close() {
		$this->checkIfExpanded();
		$this->query('link:Filter')->one()->click();
		$this->getFilterContainer()->waitUntilNotVisible();
	}

	/**
	 * Expand filter container.
	 * Filter container should be in collapsed mode.
	 */
	public function open() {
		$this->checkIfExpanded(false);
		$this->query('link:Filter')->one()->click();
		$this->getFilterContainer()->waitUntilVisible();
	}

	/**
	 * Expand or collapse filter container.
	 *
	 * @param boolean $expand    filter container mode
	 */
	public function expand($expand = true) {
		if ($expand) {
			$this->open();
		}
		else {
			$this->close();
		}
	}

	/**
	 * Checking filter container state.
	 *
	 * @param boolean $expanded    expanded state of filter container
	 *
	 * @return boolean
	 */
	public function isExpanded($expanded = true) {
		return $this->getFilterContainer()->isDisplayed($expanded);
	}

	/**
	 * Checking that filter container is expanded.
	 *
	 * @param boolean $expanded    expanded state of filter container
	 *
	 * @throws Exception
	 */
	public function checkIfExpanded($expanded = true) {
		if ($this->isExpanded($expanded) === false) {
			throw new Exception('Filter is'.($expanded ? ' not' : '').' expanded.');
		}
	}
}
