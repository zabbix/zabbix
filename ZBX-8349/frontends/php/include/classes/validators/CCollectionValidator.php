<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CCollectionValidator extends CValidator {

	/**
	 * If set to false, the array cannot be empty.
	 *
	 * @var bool
	 */
	public $empty = false;

	/**
	 * Name of a field that must have unique values in the whole collection.
	 *
	 * @var string
	 */
	public $uniqueField;

	/**
	 * Second field to be used as a uniqueness criteria.
	 *
	 * @var string
	 */
	public $uniqueField2;

	/**
	 * Error message if the array is empty.
	 *
	 * @var string
	 */
	public $messageEmpty;

	/**
	 * Error message if the given value is not an array.
	 *
	 * @var string
	 */
	public $messageType;

	/**
	 * Error message if duplicate objects exist.
	 *
	 * @var string
	 */
	public $messageDuplicate;

	/**
	 * @todo: This exists only because messageInvalid -> messageType change. We should refactor it later.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = array()) {
		if (isset($options['messageInvalid'])) {
			$options['messageType'] = $options['messageInvalid'];
			unset($options['messageInvalid']);
		}

		parent::__construct($options);
	}

	/**
	 * @todo: This exists only because messageInvalid -> messageType change. We should refactor it later.
	 *
	 * @param $name
	 * @param $value
	 *
	 * @throws \Exception
	 */
	public function __set($name, $value) {
		if ($name === 'messageInvalid') {
			$this->messageType = $value;

			return;
		}

		parent::__set($name, $value);
	}

	/**
	 * @todo: This exists only because messageInvalid -> messageType change. We should refactor it later.
	 *
	 * @param $name
	 * @return string
	 */
	public function __get($name) {
		if ($name === 'messageInvalid') {
			return $this->messageType;
		}

		return null;
	}

	/**
	 * Checks if the given array of objects is valid.
	 *
	 * @param array $value
	 *
	 * @return bool
	 */
	public function validate($value)
	{
		if (!is_array($value)) {
			$this->error($this->messageType);

			return false;
		}

		// check if it's empty
		if (!$this->empty && !$value) {
			$this->error($this->messageEmpty);

			return false;
		}

		// check for objects with duplicate values
		if ($this->uniqueField) {
			if ($duplicate = CArrayHelper::findDuplicate($value, $this->uniqueField, $this->uniqueField2)) {
				// since second unique parameter can be absent, we call it in ugly way
				$params = array($this->messageDuplicate, $duplicate[$this->uniqueField]);

				if ($this->uniqueField2) {
					$params[] = $duplicate[$this->uniqueField2];
				}

				call_user_func_array(array($this, 'error'), $params);

				return false;
			}
		}

		return true;
	}

}
