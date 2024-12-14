<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/CBehavior.php';

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
}
