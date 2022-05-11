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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CEventSourceObjectValidator extends CValidator {

	/**
	 * Supported source-object pairs.
	 *
	 * @var array
	 */
	public $pairs = [
		EVENT_SOURCE_TRIGGERS => [
			EVENT_OBJECT_TRIGGER => 1
		],
		EVENT_SOURCE_DISCOVERY => [
			EVENT_OBJECT_DHOST => 1,
			EVENT_OBJECT_DSERVICE => 1
		],
		EVENT_SOURCE_AUTOREGISTRATION => [
			EVENT_OBJECT_AUTOREGHOST => 1
		],
		EVENT_SOURCE_INTERNAL => [
			EVENT_OBJECT_TRIGGER => 1,
			EVENT_OBJECT_ITEM => 1,
			EVENT_OBJECT_LLDRULE => 1
		],
		EVENT_SOURCE_SERVICE => [
			EVENT_OBJECT_SERVICE => 1
		]
	];

	/**
	 * Checks if the given source-object pair is valid.
	 *
	 * @param $value
	 *
	 * @return bool
	 */
	public function validate($value)
	{
		$pairs = $this->pairs;

		$objects = $pairs[$value['source']];
		if (!isset($objects[$value['object']])) {
			$supportedObjects = '';
			foreach ($objects as $object => $i) {
				$supportedObjects .= $object.' - '.eventObject($object).', ';
			}

			$this->setError(
				_s('Incorrect event object "%1$s" (%2$s) for event source "%3$s" (%4$s), only the following objects are supported: %5$s.',
					$value['object'],
					eventObject($value['object']),
					$value['source'],
					eventSource($value['source']),
					rtrim($supportedObjects, ', ')
				)
			);
			return false;
		}

		return true;
	}

}
