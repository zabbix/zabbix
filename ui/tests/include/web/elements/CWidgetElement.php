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
 * Dashboard widget element.
 */
class CWidgetElement extends CElement {

	/**
	 * Get refresh interval of widget.
	 *
	 * @return integer
	 */
	public function getRefreshInterval() {
		$this->getHeader()->hoverMouse();
		$this->query('xpath:.//button[contains(@class, "js-widget-action")]')->waitUntilPresent()->one()->click(true);
		$menu = CPopupMenuElement::find()->waitUntilVisible()->one();
		$aria_label = explode(', ', $menu->getSelected()->getAttribute('aria-label'), 3);

		return $aria_label[1];
	}

	/**
	 * Get time interval of widget.
	 *
	 * @return string
	 */
	public function getTimeInterval() {
		$this->getHeader()->hoverMouse();
		$this->query('xpath:.//li[@class="widget-info-button"]/button')->waitUntilPresent()->one()->click(true);
		$hintbox = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->one()->waitUntilVisible();
		$hint_text = $hintbox->getText();

		return $hint_text;
	}

	/**
	 * Get header of widget.
	 *
	 * @return CElement
	 */
	public function getHeader() {
		return $this->query('xpath:.//div[contains(@class, "dashboard-grid-widget-header") or'.
				' contains(@class, "dashboard-grid-iterator-header")]/h4')->one();
	}

	/**
	 * Get header text of widget.
	 *
	 * @return string
	 */
	public function getHeaderText() {
		return $this->getHeader()->getText();
	}

	/**
	 * Get content of widget.
	 *
	 * @return CElement
	 */
	public function getContent() {
		return $this->query('xpath:.//div[contains(@class, "dashboard-grid-widget-contents") or'.
				' contains(@class, "dashboard-grid-iterator-contents")]')->one();
	}

	/**
	 * Check if widget is editable (widget edit button is present).
	 *
	 * @return boolean
	 */
	public function isEditable() {
		return $this->query('xpath:.//button[contains(@class, "js-widget-edit")]')->one()->isPresent();
	}

	/**
	 * Get widget configuration form.
	 *
	 * @return CFormElement
	 */
	public function edit() {
		$button = $this->query('xpath:.//button[contains(@class, "js-widget-edit")]')->waitUntilPresent()->one();

		if ($button->isVisible(false)) {
			$button->hoverMouse();
		}

		// Edit can sometimes fail, so we have to retry this operation.
		for ($i = 0; $i < 4; $i++) {
			// TODO: remove force: true parameter after DEV-2621 is fixed.
			$button->click(true);

			try {
				// TODO: fix formatting after git-hook improvements DEV-2396.
				return $this->query('xpath://div[@data-dialogueid="widget_properties"]//form')->waitUntilVisible()
						->asForm()->one();
			}
			catch (\Exception $e) {
				if ($i === 1) {
					throw $e;
				}
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getReadyCondition() {
		$target = $this;

		return function () use ($target) {
			return ($target->query('xpath:.//div[contains(@class, "is-loading")]')->one(false)->isValid() === false);
		};
	}

	/**
	 * @inheritDoc
	 */
	public function isReady() {
		return call_user_func([$this, 'getReadyCondition']);
	}
}
