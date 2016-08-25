<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CXmlImportReader extends CImportReader {

	/**
	 * Convert string with xml data to php array.
	 *
	 * @throws Exception
	 *
	 * @param string $string
	 *
	 * @return array
	 */
	public function read($string) {
		if ($string === '') {
			throw new Exception(_s('Cannot read XML: %1$s.', _('XML is empty')));
		}

		libxml_use_internal_errors(true);
		libxml_disable_entity_loader(true);
		$result = simplexml_load_string($string, null, LIBXML_IMPORT_FLAGS);
		if (!$result) {
			$errors = libxml_get_errors();
			libxml_clear_errors();

			foreach ($errors as $error) {
				throw new Exception(_s('Cannot read XML: %1$s.', _s('%1$s [Line: %2$s | Column: %3$s]',
					'('.$error->code.') '.trim($error->message), $error->line, $error->column
				)));
			}
		}

		$xml = new XMLReader();
		$xml->xml($string);
		$data = self::xmlToArray($xml);
		$xml->close();

		return $data;
	}

	/**
	 * Method for recursive processing of xml dom nodes.
	 * Node attributes will be stored in array as siblings of child elements.
	 *
	 * @param XMLReader $xml
	 * @param string    $path
	 *
	 * @throws Exception
	 *
	 * @return array|string
	 */
	protected static function xmlToArray(XMLReader $xml, $path = '') {
		$data = null;

		while ($xml->read()) {
			switch ($xml->nodeType) {
				case XMLReader::ELEMENT:
					if ($data === null) {
						$data = [];
					}
					elseif (!is_array($data)) {
						throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path,
							_s('unexpected text "%1$s"', trim($data))
						));
					}

					$node_name = $xml->name;
					$sub_path = $path.'/'.$node_name;
					if (array_key_exists($node_name, $data)) {
						// Add identifier number for repeated element keys.

						$node_name .= count($data);
						$sub_path .= '('.count($data).')';
					}

					/*
					 * A special case for 1.8 import where attributes are still used attributes must be added to the
					 * array as if they where child elements.
					 */
					if ($xml->hasAttributes) {
						while ($xml->moveToNextAttribute()) {
							$data[$node_name][$xml->name] = $xml->value;
						}

						/*
						 * We assume that an element with attributes never contains text node
						 * works for 1.8 XML.
						 */
						$child_data = $xml->isEmptyElement ? '' : self::xmlToArray($xml, $sub_path);

						if (is_array($child_data)) {
							foreach ($child_data as $child_node_name => $child_node_value) {
								if (array_key_exists($child_node_name, $data[$node_name])) {
									$child_node_name .= count($data[$node_name]);
								}
								$data[$node_name][$child_node_name] = $child_node_value;
							}
						}
						elseif ($child_data !== '') {
							throw new Exception(_s('Invalid tag "%1$s": %2$s.', $sub_path,
								_s('unexpected text "%1$s"', trim($child_data))
							));
						}
					}
					else {
						$data[$node_name] = $xml->isEmptyElement ? '' : self::xmlToArray($xml, $sub_path);
					}
					break;

				case XMLReader::TEXT:
					if ($data === null) {
						$data = $xml->value;
					}
					elseif (is_array($data)) {
						throw new Exception(_s('Invalid tag "%1$s": %2$s.', $path,
							_s('unexpected text "%1$s"', trim($xml->value))
						));
					}

					break;

				case XMLReader::END_ELEMENT:
					/*
					 * For tags with empty value: <dns></dns>.
					 */
					if ($data === null) {
						$data = '';
					}

					return $data;
			}
		}

		return $data;
	}
}
