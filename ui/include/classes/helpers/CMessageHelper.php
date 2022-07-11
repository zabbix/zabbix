<?php declare(strict_types = 0);
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


/**
 * Helper to store success / error messages.
 */
class CMessageHelper {

	public const MESSAGE_TYPE_ERROR   = 'error';
	public const MESSAGE_TYPE_SUCCESS = 'success';
	public const MESSAGE_TYPE_WARNING = 'warning';

	/**
	 * @var string
	 */
	private static $type;

	/**
	 * @var string
	 */
	private static $title;

	/**
	 * Messages array.
	 *
	 * @var array
	 */
	private static $messages = [];

	/**
	 * Schedule messages from form data.
	 *
	 * @var array
	 */
	private static $schedule_messages = [];

	/**
	 * Get messages.
	 *
	 * @return array
	 */
	public static function getMessages(): array {
		return self::$messages;
	}

	/**
	 * Add message.
	 *
	 * @param array $message
	 */
	public static function addMessage(array $message): void {
		if ($message['type'] === self::MESSAGE_TYPE_SUCCESS) {
			self::addSuccess($message['message']);
		}
		elseif ($message['type'] === self::MESSAGE_TYPE_WARNING) {
			self::addWarning($message['message']);
		}
		else {
			self::addError($message['message'], $message['source']);
		}
	}

	/**
	 * Add message with type error.
	 *
	 * @param string $message
	 * @param string $source
	 */
	public static function addError(string $message, string $source = ''): void {
		if (self::$type === null) {
			self::$type = self::MESSAGE_TYPE_ERROR;
		}

		self::$messages[] = [
			'type' => self::MESSAGE_TYPE_ERROR,
			'message' => $message,
			'source' => $source
		];
	}

	/**
	 * Add message with type info.
	 *
	 * @param string $message
	 */
	public static function addSuccess(string $message): void {
		self::$messages[] = [
			'type' => self::MESSAGE_TYPE_SUCCESS,
			'message' => $message
		];
	}

	/**
	 * Add message with type warning.
	 *
	 * @param string $message
	 */
	public static function addWarning(string $message): void {
		self::$messages[] = [
			'type' => self::MESSAGE_TYPE_WARNING,
			'message' => $message
		];
	}

	/**
	 * Get messages title.
	 *
	 * @return string|null
	 */
	public static function getTitle(): ?string {
		return self::$title;
	}

	/**
	 * Set title for error messages.
	 *
	 * @param string $title
	 */
	public static function setErrorTitle(string $title): void {
		self::$type = self::MESSAGE_TYPE_ERROR;
		self::$title = $title;
	}

	/**
	 * Set title for info messages.
	 *
	 * @param $title
	 */
	public static function setSuccessTitle(string $title): void {
		self::$type = self::MESSAGE_TYPE_SUCCESS;
		self::$title = $title;
	}

	/**
	 * Get messages type.
	 *
	 * @return string
	 */
	public static function getType(): ?string {
		return self::$type;
	}

	/**
	 * Clear messages.
	 */
	public static function clear(bool $clear_title = true): void {
		if ($clear_title) {
			self::$title = null;
		}
		self::$messages = [];
	}

	/**
	 * Set messages from FormData.
	 *
	 * @param array $messages
	 */
	public static function setScheduleMessages(array $messages): void {
		self::$schedule_messages = $messages;
	}

	/**
	 * Restore schedule messages.
	 */
	public static function restoreScheduleMessages(array $current_messages = []): void {
		if (self::$schedule_messages) {
			if (array_key_exists('success', self::$schedule_messages) && self::$schedule_messages['success']) {
				self::setSuccessTitle(self::$schedule_messages['success']);
			}

			if (array_key_exists('error', self::$schedule_messages) && self::$schedule_messages['error']) {
				self::setErrorTitle(self::$schedule_messages['error']);
			}

			if (array_key_exists('messages', self::$schedule_messages)) {
				foreach (self::$schedule_messages['messages'] as $message) {
					if (!self::checkDuplicates($message, $current_messages)) {
						continue;
					}

					self::addMessage($message);
				}
			}

			self::$schedule_messages = [];
		}
	}

	/**
	 * Check duplicate from current message for schedule message.
	 *
	 * @param array $message
	 * @param array $current_messages
	 *
	 * @return boolean
	 */
	protected static function checkDuplicates(array $message, array $current_messages): bool {
		foreach ($current_messages as $known_messages) {
			foreach ($known_messages['messages'] as $known_message) {
				if (count(array_diff_assoc($known_message, $message)) === 0) {
					return false;
				}
			}
		}

		return true;
	}
}
