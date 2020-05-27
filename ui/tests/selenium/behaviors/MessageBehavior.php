<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/CBehavior.php';

/**
 * Behavior for name-value parameters in form related tests.
 */
class CMessageBehavior extends CBehavior {

	/**
	 * Function compares the title and details of messages with reference values.
	 *
	 * @param constant			$expected	constant that defines whether the message should be good or bad
	 * @param string			$title		reference title
	 * @param string, array		$details	reference array or string values of which should be present in message details
	 */
	public function assertMessage($expected, $title, $details = null) {
		$message = CMessageElement::find()->one();

		($expected === TEST_GOOD) ? $this->test->assertTrue($message->isGood()) : $this->test->assertTrue($message->isBad());

		$this->test->assertEquals($title, $message->getTitle());

		if ($details !== null) {
			if (!is_array($details)) {
				$details = [$details];
			}
			foreach ($details as $detail) {
				$this->test->assertTrue($message->hasLine($detail));
			}
		}
	}
}
