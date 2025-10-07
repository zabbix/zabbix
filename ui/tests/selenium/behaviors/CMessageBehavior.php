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

require_once __DIR__.'/../../include/CBehavior.php';

/**
 * Behavior for global messages.
 */
class CMessageBehavior extends CBehavior {

	/**
	 * Function check message type and compares the title and details of messages with reference values.
	 *
	 * @param constant			$expected	constant that defines whether the message should be good or bad
	 * @param string			$title		reference title
	 * @param string, array		$details	reference array or string values of which should be present in message details
	 */
	public function assertMessage($expected, $title = null, $details = null) {
		$message = CMessageElement::find()->waitUntilVisible()->one();

		if ($expected === TEST_GOOD) {
			$this->test->assertTrue($message->isGood());
		}
		elseif ($expected === TEST_BAD) {
			$this->test->assertTrue($message->isBad());
		}
		else {
			$this->test->assertTrue($message->isWarning());
		}

		if ($title !== null) {
			$this->test->assertEquals($title, $message->getTitle(), 'Message title and the expected title do not match.');
		}

		if ($details !== null) {
			if (!is_array($details)) {
				$details = [$details];
			}

			foreach ($details as $detail) {
				$this->test->assertTrue($message->hasLine($detail), 'Line "'.$detail.'" was not found in message details');
			}
		}
	}

	/**
	 * Compare inline message text and check that corresponding field is highlighted.
	 *
	 * @param CFormElement	$form		form that contains the field with the error
	 * @param array			$fields		array of field selector and expected inline error string pairs
	 */
	public function assertInlineError($form, array $fields) {
		foreach ($fields as $selector => $error_text) {
			$field = $form->getField($selector);
			$field->waitUntilClassesPresent('has-error');

			if ($field->isAttributePresent('data-error-container')) {
				$container_field = $form->query('id', $field->getAttribute('data-error-container'))->one();
				$this->test->assertTrue(in_array($error_text, $container_field->query('class:error')->waitUntilPresent()
						->all()->asText()), $error_text.' was not found among inline error messages.'
				);
			}
			else {
				$this->test->assertEquals($error_text, $field->query('xpath:./../span[@class="error"]|./../../span[@class="error"]')
						->waitUntilPresent()->one()->getText()
				);
			}
		}
	}
}
