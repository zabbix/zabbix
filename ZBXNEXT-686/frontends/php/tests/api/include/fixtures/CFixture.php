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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Base class for loading fixtures.
 */
abstract class CFixture {

	/**
	 * @param array $params
	 *
	 * @return array
	 *
	 * @throws InvalidArgumentException if the feature could not be loaded due to misconfiguration
	 * @throws UnexpectedValueException if the feature could not be loaded due to external reasons
	 */
	public abstract function load(array $params);

	/**
	 * Checks that all required parameters are present in $params.
	 *
	 * @param array $params		array of given parameters
	 * @param array $required	array of required parameters
	 *
	 * @throws InvalidArgumentException	if a parameter is missing
	 */
	protected function checkMissingParams(array $params, array $required) {
		foreach ($required as $param) {
			if (!isset($params[$param])) {
				throw new InvalidArgumentException(sprintf('Missing "%1$s" parameter.', $param));
			}
		}
	}

}
