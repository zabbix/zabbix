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


require_once dirname(__FILE__) . '/../../include/CWebTest.php';

class testMultiselectDialogs extends CWebTest {

	/**
	 * Function for opening multiselects in a form and check their contents.
	 *
	 * @param CFormElement    $form            form where checks are performed
	 * @param array           $multiselects    multiselect fields to be checked
	 */
	protected function checkMultiselectDialogs($form, $multiselects) {
		foreach ($multiselects as $multiselect) {
			$count = count($multiselect);
			$multiselect_form = $form;

			foreach ($multiselect as $field => $parameters) {
				// Open multiselect dialog.
				$dialog = $multiselect_form->getField($field)->edit();
				$this->checkErrorsAndTitle($dialog, $parameters['title']);

				if (array_key_exists('filter', $parameters)) {
					$this->checkOverlayFilter($dialog, $parameters['title'], $parameters['filter']);
				}

				if (CTestArrayHelper::get($parameters, 'empty', false)) {
					$this->checkOverlayStud($dialog, $parameters['title']);
				}

				// Set form element of current overlay dialog if multiple dialogs layers are opened.
				if ($count > 1) {
					$multiselect_form = $dialog->asForm(['normalized' => true]);
				}
			}

			if ($count > 1) {
				COverlayDialogElement::closeAll(true);
			}
			else {
				$dialog->close();
			}
		}
	}

	/**
	 * Function for checking dialog title and error absence in it.
	 *
	 * @param COverlayDialogElement    $dialog    dialog form where checks are performed
	 * @param string                   $title     title of a dialog
	 */
	protected function checkErrorsAndTitle($dialog, $title) {
		$this->assertEquals($title, $dialog->getTitle());

		// Check that opened dialog does not contain any error messages.
		$this->assertFalse($dialog->query('xpath:.//*[contains(@class, "msg-bad")]')->exists());
	}

	/**
	 * Function for asserting additional filter in multeselect's overlay.
	 *
	 * @param COverlayDialogElement    $dialog    dialog form where checks are performed
	 * @param string                   $title     title of a dialog
	 * @param array                    $filter    filter parameters passed in format: ['<filter_label>' => '<filter_value>']
	 */
	protected function checkOverlayFilter($dialog, $title, $filter = null) {
		// For some overlays filter has special selector.
		$filter_selector = (in_array($title, ['SLA', 'Service', 'Services']))
			? $dialog->query('id:services-filter-name')
			: $dialog->query('xpath:.//div[@class="multiselect-control"]')->asMultiselect();

		if ($filter === null) {
			$this->assertFalse($filter_selector->exists());
		}
		else {
			$this->assertEquals(key($filter), $dialog->query('tag:label')->one()->getText());
			$this->assertEquals(array_values($filter), [$filter_selector->one()->getValue()]);
		}
	}

	/**
	 * Function for checking stud's text when overlay is empty.
	 *
	 * @param COverlayDialogElement    $dialog    dialog form where checks are performed
	 * @param string                   $title     title of a dialog
	 */
	protected function checkOverlayStud($dialog, $title) {
		$text = (in_array($title, ['Templates', 'Hosts', 'Triggers']))
			? "Filter is not set\nUse the filter to display results"
			: 'No data found';
		$this->assertEquals($text, $dialog->query('class:no-data-message')->one()->getText());
	}
}
