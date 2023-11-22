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

			foreach ($multiselect as $field => $title) {

				// Open multiselect dialog.
				$dialog = $multiselect_form->getField($field)->edit();
				$this->checkErrorsAndTitle($dialog, $title);

				// Set form element of current overlay dialog if multiple dialogs are opened.
				if ($count > 1) {
					$multiselect_form = $dialog->asForm(['normalized' => true]);
				}
			}

			$this->closeMultiselectDialogs();
		}
	}

	/**
	 * Function for closing all opened multiselect dialogs one by one.
	 */
	protected function closeMultiselectDialogs() {
		$dialogs = COverlayDialogElement::find()->all();

		for ($i = $dialogs->count() - 1; $i >= 0; $i--) {
			$dialogs->get($i)->close(true);
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
}
