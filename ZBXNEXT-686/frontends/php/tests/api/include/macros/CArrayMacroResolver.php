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


/**
 * A class for working with array macros.
 */
class CArrayMacroResolver {

	/**
	 * Expands all array macros in $array using the values from $data.
	 *
	 * An array macro is a macro in the form of @key.key2@. To expand the macro in the example,
	 * the method will look for value $data['key']['key2'].
	 *
	 * String keys should be defined using dots, but numeric keys using square brackets.
	 * For example @key.subkey[2]@ is equivalent to $data['key']['subkey'][2].
	 *
	 * @param array $array  an array to resolve macros in
	 * @param array $data   an array to take the data from
	 *
	 * @return array	the source array with the macros expanded
	 *
	 * @throws InvalidArgumentException if a macro cannot be resolved
	 */
	public function resolve(array $array, array $data) {
		array_walk_recursive($array, function (&$value, $index) use ($data) {
			if (!is_string($value)) {
				return;
			}

			// extract variables
			preg_match_all("/@(?:[a-z0-9[\\]._]+)@/i", $value, $matches);

			if (count($matches) == 1 && count($matches[0]) > 0) {
				foreach ($matches[0] as $macro) {
					$keys = preg_split('/(\[|]\.|\.|\])/', trim($macro, '@'), -1, PREG_SPLIT_NO_EMPTY);

					if (count($keys) > 0) {
						try {
							$newValue = $this->drillIn($data, $keys);
						}
						catch (InvalidArgumentException $e) {
							throw new InvalidArgumentException(
								sprintf('Cannot resolve macro "%1$s": %2$s',
									$macro,
									$e->getMessage()
								)
							);
						}

						$value = str_replace($macro, $newValue, $value);
					}
					else {
						throw new InvalidArgumentException(sprintf('Incorrect macro "%1$s"', $macro));
					}
				}
			}
		});

		return $array;
	}

	/**
	 * Drill in function - returns item from array $data defined by $keys.
	 *
	 * @param array $data
	 * @param array $keys
	 *
	 * @return mixed
	 *
	 * @throws InvalidArgumentException	if a value does not exist
	 */
	protected function drillIn(array $data, array $keys) {
		foreach ($keys as $i => $key) {
			if (!is_array($data)) {
				throw new InvalidArgumentException(sprintf(
					'value of "%1$s" is not an array', $keys[$i - 1]
				));
			}
			elseif(!isset($data[$key])) {
				throw new InvalidArgumentException(sprintf(
					'key "%1$s" is not set', $key
				));
			}

			$data = $data[$key];
		}

		return $data;
	}

}
