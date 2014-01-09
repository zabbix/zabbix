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
		$result = simplexml_load_string($string);
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
					if (!is_array($array)) {
						$array = array();
					}

					$nodeName = $xml->name;
					if (isset($array[$nodeName])) {
						$nodeName .= count($array);
					}
					$array[$nodeName] = $xml->isEmptyElement ? '' : $this->xmlToArray($xml);
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
