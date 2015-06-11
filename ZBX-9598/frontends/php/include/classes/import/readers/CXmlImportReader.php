<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
		libxml_use_internal_errors(true);
		libxml_disable_entity_loader(true);
		$result = simplexml_load_string($string, null, LIBXML_IMPORT_FLAGS);
		if (!$result) {
			$errors = libxml_get_errors();
			libxml_clear_errors();

			foreach ($errors as $error) {
				$text = '';

				switch ($error->level) {
					case LIBXML_ERR_WARNING:
						$text .= _s('XML file contains warning %1$s:', $error->code);
						break;
					case LIBXML_ERR_ERROR:
						$text .= _s('XML file contains error %1$s:', $error->code);
						break;
					case LIBXML_ERR_FATAL:
						$text .= _s('XML file contains fatal error %1$s:', $error->code);
						break;
				}

				$text .= trim($error->message).' [ Line: '.$error->line.' | Column: '.$error->column.' ]';
				throw new Exception($text);
			}
		}

		$xml = new XMLReader();
		$xml->xml($string);

		$array = $this->xmlToArray($xml);
		$xml->close();

		$array = $this->hexToAscii($array);

		return $array;
	}

	/**
	 * Recursive function to convert hex string \x%x format to ASCII characters. The escaped strings \\x
	 * are translated to \x.
	 *
	 * Exaple: abc\\x1b\x1b\\x1bdef -> abc\x1b&#27;\x1bdef
	 * where \\x1b is an escaped string and \x1b is ASCII control character.
	 *
	 * @param array $array		array containing import data
	 *
	 * @return array
	 */
	protected function hexToAscii(array $array) {
		foreach ($array as &$value) {
			if (is_array($value)) {
				$value = $this->hexToAscii($value);
			}
			else {
				// convert all \x%x to ASCII characters except if \x is preceding with another \
				$value = preg_replace_callback('/(?<!\\\)\\\x[a-zA-Z0-9]{1,2}/',
					function ($matches) {
						foreach ($matches as &$match) {
							$match = chr(hexdec(str_replace('\x', '', $match)));
						}
						unset($match);

						return $matches[0];
					},
					$value
				);

				// convert all \\x to \x
				$value = preg_replace('/\\\\\\\x/', '\\\x', $value);
			}
		}
		unset($value);

		return $array;
	}

	/**
	 * Method for recursive processing of xml dom nodes.
	 *
	 * @param XMLReader $xml
	 *
	 * @return array|string
	 */
	protected function xmlToArray(XMLReader $xml) {
		$array = '';
		while ($xml->read()) {
			switch ($xml->nodeType) {
				case XMLReader::ELEMENT:
					$nodeName = $xml->name;
					if (isset($array[$nodeName])) {
						$nodeName .= count($array);
					}

					// a special case for 1.8 import where attributes are still used
					// attributes must be added to the array as if they where child elements
					if ($xml->hasAttributes) {
						while ($xml->moveToNextAttribute()) {
							$array[$nodeName][$xml->name] = $xml->value;
						}

						// we assume that an element with attributes always contains child elements, not a text node
						// works for 1.8 XML
						$xmlToArray = $this->xmlToArray($xml);
						if (is_array($xmlToArray)) {
							foreach ($xmlToArray as $name => $value) {
								$array[$nodeName][$name] = $value;
							}
						}
					}
					else {
						$array[$nodeName] = $xml->isEmptyElement ? '' : $this->xmlToArray($xml);
					}

					break;

				case XMLReader::TEXT:
					$array = $xml->value;
					break;

				case XMLReader::END_ELEMENT:
					return $array;
			}
		}

		return $array;
	}
}
