<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Dashboard element.
 */
class CDashboardElement extends CElement {

	/**
	 * @inheritdoc
	 */
	public static function find() {
		return (new CElementQuery('class:dashboard-grid'))->asDashboard();
	}

	/**
	 * Get dashboard title as text.
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->query('xpath://h1[@id="page-title-general"]')->one()->getText();
	}

	/**
	 * Check if dashboard is empty.
	 *
	 * @return boolean
	 */
	public function isEmpty() {
		return ($this->query('xpath:.//div[@class="dashboard-widget-placeholder"]')->one(false)->isValid());
	}

	/**
	 * Get dashboard widgets.
	 *
	 * @return CElementCollection
	 */
	public function getWidgets() {
		return $this->query("xpath:.//div[".CXPathHelper::fromClass("dashboard-grid-widget").
				" or ".CXPathHelper::fromClass("dashboard-grid-iterator")."]")->asWidget()->all();
	}

	/**
	 * Get widget by name.
	 *
	 * @param string  $name            widget name
	 * @param boolean $should_exist    if method is allowed to return null as a result
	 *
	 * @return CWidgetElement|CNullElement
	 */
	public function getWidget($name, $should_exist = true) {
		$query = $this->query('xpath:.//div[contains(@class, "dashboard-grid-widget-head") or'.
				' contains(@class, "dashboard-grid-iterator-head")]/h4[text()='.
				CXPathHelper::escapeQuotes($name).']/../../..');

		if ($should_exist) {
			$query->waitUntilPresent();
		}

		$widget = $query->asWidget()->one($should_exist);
		if ($widget->isValid() && $should_exist) {
				$widget->waitUntilReady();
		}

		return $widget;
	}

	/**
	 * Get dashboard controls section.
	 *
	 * @return CElement
	 */
	public function getControls() {
		return $this->query('xpath://ul[@id="dashboard-control"]')->one();
	}

	/**
	 * Begin dashboard editing.
	 *
	 * @return $this
	 */
	public function edit() {
		$controls = $this->getControls();

		if (!$controls->query('xpath:.//nav[@class="dashboard-edit"]')->one()->isDisplayed()) {
			$controls->query('id:dashboard-edit')->one()->click();
			$controls->query('xpath:.//nav[@class="dashboard-edit"]')->waitUntilVisible();
		}

		return $this;
	}

	/**
	 * Open dashboard properties overlay dialog.
	 *
	 * @return COverlayDialogElement
	 */
	public function editProperties() {
		$this->checkIfEditable();
		$this->getControls()->query('id:dashboard-config')->one()->click();

		return $this->query('xpath://div[contains(@class, "overlay-dialogue")][@data-dialogueid="dashboard_properties"]')
				->waitUntilVisible()->asOverlayDialog()->one()->waitUntilReady();
	}

	/**
	 * Open widget adding form.
	 * Dashboard should be in editing mode.
	 *
	 * @return COverlayDialogElement
	 */
	public function addWidget() {
		$this->checkIfEditable();
		$this->getControls()->query('id:dashboard-add-widget')->one()->click();

		return $this->query('xpath://div[contains(@class, "overlay-dialogue")][@data-dialogueid="widget_properties"]')
				->waitUntilVisible()->asOverlayDialog()->one()->waitUntilReady();
	}

	/**
	 * Cancel dashboard editing.
	 *
	 * @return $this
	 */
	public function cancelEditing() {
		$controls = $this->getControls();

		if ($controls->query('xpath:.//nav[@class="dashboard-edit"]')->one()->isDisplayed()) {
			$controls->query('id:dashboard-cancel')->one()->click(true);

			if (CElementQuery::getPage()->isAlertPresent()) {
				CElementQuery::getPage()->acceptAlert();
			}

			if (!$controls->isStalled()) {
				$controls->query('xpath:.//nav[@class="dashboard-edit"]')->waitUntilNotVisible();
			}
		}

		return $this;
	}

	/**
	 * Save dashboard.
	 * Dashboard should be in editing mode.
	 *
	 * @return $this
	 */
	public function save() {
		$controls = $this->getControls();

		if ($controls->query('xpath:.//nav[@class="dashboard-edit"]')->one()->isDisplayed()) {
			$button = $controls->query('id:dashboard-save')->one()->waitUntilClickable();
			$button->getLocationOnScreenOnceScrolledIntoView();
			$button->click();
			$controls->query('xpath:.//nav[@class="dashboard-edit"]')->waitUntilNotVisible();
		}

		return $this;
	}

	/**
	 * Delete widget with the provided name.
	 * Dashboard should be in editing mode.
	 *
	 * @param string $name    name of widget to be deleted
	 *
	 * @return $this
	 */
	public function deleteWidget($name) {
		$this->checkIfEditable();
		$this->query('xpath:.//div[contains(@class, "dashboard-grid-widget-head") or contains(@class,'.
				' "dashboard-grid-iterator-head")]/h4[text()="'.$name.
				'"]/../ul/li/button[@title="Actions"]')->asPopupButton()->one()
				->select('Delete')->waitUntilNotVisible();

		return $this;
	}

	/**
	 * Copy widget with the provided name.
	 *
	 * @param string $name    name of widget to be copied
	 *
	 * @return $this
	 */
	public function copyWidget($name) {
		$this->query('xpath:.//div[contains(@class, "dashboard-grid-widget-head") or contains(@class,'.
				' "dashboard-grid-iterator-head")]/h4[text()="'.$name.
				'"]/../ul/li/button[@title="Actions"]')->asPopupButton()->one()->select('Copy');

		return $this;
	}

	/**
	 * Paste copied widget.
	 * Dashboard should be in editing mode.
	 *
	 * @return $this
	 */
	public function pasteWidget() {
		$this->checkIfEditable();
		$this->getControls()->query('id:dashboard-add')->asPopupButton()->one()->select('Paste widget');

		return $this;
	}

	/**
	 * Replace widget with the provided name to previously copied widget.
	 * Dashboard should be in editing mode.
	 *
	 * @param string $name    name of widget to be replaced
	 *
	 * @return $this
	 */
	public function replaceWidget($name) {
		$this->checkIfEditable();

		$this->query('xpath:.//div[contains(@class, "dashboard-grid-widget-head") or contains(@class,'.
				' "dashboard-grid-iterator-head")]/h4[text()="'.$name.
				'"]/../ul/li/button[@title="Actions"]')->asPopupButton()->one()->select('Paste');

		return $this;
	}

	/**
	 * Checking Dashboard controls state.
	 *
	 * @param boolean $editable    editable state of dashboard
	 *
	 * @return boolean
	 */
	public function isEditable($editable = true) {
		return $this->getControls()->query('xpath:.//nav[@class="dashboard-edit"]')->one()->isDisplayed($editable);
	}

	/**
	 * Checking that Dashboard is in edit mode.
	 *
	 * @param boolean $editable    editable state of dashboard
	 *
	 * @throws \Exception
	 */
	public function checkIfEditable($editable = true) {
		if ($this->isEditable($editable) === false) {
			throw new \Exception('Dashboard is'.($editable ? ' not' : '').' in editing mode.');
		}
	}

	/**
	 * Open page adding form.
	 * Dashboard should be in editing mode.
	 *
	 * @return COverlayDialogElement
	 */
	public function addPage() {
		$this->checkIfEditable();
		$this->getControls()->query('id:dashboard-add')->one()->click();
		$this->query('xpath://ul[@role="menu"]')->asPopupMenu()->one()->select('Add page');

		return $this;
	}

	/**
	 * Select dashboard page by name.
	 *
	 * @param string	$name		page name to be selected
	 * @param integer	$index		expected number of pages with the provided name
	 */
	public function selectPage($name, $index = 1) {
		$selection = '//ul[@class="sortable-list"]//span[@title='.CXPathHelper::escapeQuotes($name).']';
		$this->query('xpath:('.$selection.')['.$index.']')->waitUntilClickable()->one()->click();
		$this->query('xpath:'.$selection.'/../../div[@class="selected-tab"]')->one()->waitUntilPresent();
	}
}
