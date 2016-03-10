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
		$data = $this->xml_to_array($xml);
		$xml->close();
		return $data;
	}

	/**
	 * Method for recursive processing of xml dom nodes.
	 *
	 * @param XMLReader $xml
	 * @param array $nodes
	 *
	 * @return array|string
	 */
	protected function xml_to_array(XMLReader $xml, array &$nodes = []) {
		$data = '';
		while ($xml->read()) {
			switch ($xml->nodeType) {
				case XMLReader::ELEMENT:
					$node_name = $xml->name;
					if (array_key_exists($xml->depth, $nodes) && $nodes[$xml->depth]['name'] === $xml->name) {
						$nodes[$xml->depth]['count']++;
						$node_name .= count($xml->name);
					}
					else {
						$nodes[$xml->depth] = [
							'name' => $xml->name,
							'count' => 1
						];
					}

					if ($xml->depth < max(array_keys($nodes))) {
						foreach ($nodes as $key => $node) {
							if ($xml->depth < $key) {
								unset($nodes[$key]);
							}
						}
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
						 * We assume that an element with attributes always contains child elements, not a text node
						 * works for 1.8 XML.
						 */
						$xmlToArray = $this->xml_to_array($xml, $nodes);
						if (is_array($xmlToArray)) {
							foreach ($xmlToArray as $name => $value) {
								$data[$node_name][$name] = $value;
							}
						}
					}
					else {
						$data[$node_name] = $xml->isEmptyElement ? '' : $this->xml_to_array($xml, $nodes);
					}
					break;

				case XMLReader::TEXT:
					if (is_array($data)) {
						if (is_array($data)) {
							array_pop($nodes);
						}

						$patch = '';

						foreach ($nodes as $key => $node) {
							$patch .= ($node['count'] > 1)
								? '/'.$node['name'].'('.$node['count'].')'
								: '/'.$node['name'];
						}

						throw new Exception(_s('Invalid XML text "%1$s": %2$s.', $patch,
							_s('unexpected text "%1$s"', trim($xml->value))
						));
					}
					else {
						$data = $xml->value;
					}
					break;

				case XMLReader::END_ELEMENT:
					return $data;
			}
		}

		return $data;
	}
}
