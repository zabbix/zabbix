<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Dashboard widget element.
 */
class CWidgetElement extends CElement {

	/**
	 * Get refresh interval of widget.
	 *
	 * @return integer
	 */
	public function getRefreshInterval() {
		$action = $this->query('class:btn-widget-action')->one();
		$settings = json_decode($action->getAttribute('data-menu-popup'));

		return $settings->data->currentRate;
	}

	/**
	 * Get header of widget.
	 *
	 * @return string
	 */
	public function getHeaderText() {
		return $this->query('xpath:.//div[contains(@class, "dashbrd-grid-widget-head") or'.
				' contains(@class, "dashbrd-grid-iterator-head")]/h4')->one()->getText();
	}

	/**
	 * Get content of widget.
	 *
	 * @return CElement
	 */
	public function getContent() {
		return $this->query('xpath:.//div[contains(@class, "dashbrd-grid-widget-content") or'.
				' contains(@class, "dashbrd-grid-iterator-content")]')->one();
	}

	/**
	 * Check if widget is editable (widget edit button is present).
	 *
	 * @return boolean
	 */
	public function isEditable() {
		return $this->query('xpath:.//button[@class="btn-widget-edit"]')->one()->isPresent();
	}

	/**
	 * Get widget configuration form.
	 *
	 * @return CFormElement
	 */
	public function edit() {
		$this->query('xpath:.//button[@class="btn-widget-edit"]')->one()->waitUntilPresent()->click(true);
		return $this->query('xpath://div[@data-dialogueid="widgetConfg"]//form')->waitUntilVisible()->asForm()->one();
	}

	/**
	 * @inheritdoc
	 */
	public function getReadyCondition() {
		$target = $this;

		return function () use ($target) {
			return ($target->query('xpath:.//div[contains(@class, "is-loading")]')->one(false)->isValid() === false);
		};
	}
}
