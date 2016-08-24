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
		$data = $this->xmlToArray($xml);

		$xml->close();
		return $data;
	}

	/**
	 * Method for processing xml dom nodes.
	 * Element attributes will be stored as siblings of child elements.
	 *
	 * @param XMLReader $xml
	 *
	 * @throws Exception
	 *
	 * @return array
	 */
	protected function xmlToArray(XMLReader $xml) {
		$nodes = [];
		$data = [];
		$data_alias = [&$data];
		$depth = $xml->depth;

		while ($xml->read()) {
			switch ($xml->nodeType) {
				case XMLReader::ELEMENT:
					$is_deeper = bccomp($xml->depth, $depth);
					$depth = $xml->depth;

					if ($is_deeper == 1) {
						$data_alias[$depth] = &$data_alias[$depth - 1][$node_name];

						if ($data_alias[$depth] === null) {
							$data_alias[$depth] = [];
						}
					}
					elseif ($is_deeper == 0 && !is_array($data_alias[$depth])) {
						array_splice($nodes, $depth);
						throw new Exception(_s('Invalid tag "%1$s": %2$s.', implode('/', $nodes),
							_s('unexpected text "%1$s"', trim($data_alias[$depth]))
						));
					}

					$node_name = $xml->name;
					$nodes[$depth] = $node_name;

					if(array_key_exists($node_name, $data_alias[$depth])) {
						// Add identifier number for repeated element keys.

						$count = count($data_alias[$depth]);

						while (array_key_exists($node_name . $count, $data_alias[$depth])) {
							$count++;
						}

						$node_name .= $count;
						$nodes[$depth] .= '('.$count.')';
					}

					if ($xml->hasAttributes) {
						// Add all attributes as elements.

						while ($xml->moveToNextAttribute()) {
							$data_alias[$depth][$node_name][$xml->name] = $xml->value;
						}
					}
					else {
						$data_alias[$depth][$node_name] = $xml->isEmptyElement ? '' : null;
					}
					break;

				case XMLReader::TEXT:
					$is_deeper = bccomp($xml->depth, $depth);
					$depth = $xml->depth;
					$data_alias[$depth] = &$data_alias[$depth - 1][$node_name];

					if ($is_deeper != 1 || count($data_alias[$depth])) {
						array_splice($nodes, $depth);
						throw new Exception(_s('Invalid tag "%1$s": %2$s.', implode('/', $nodes),
							_s('unexpected text "%1$s"', trim($xml->value))
						));
					}

					$data_alias[$depth] = ($xml->hasValue) ? $xml->value : '';
					break;
			}
		}

		return $data;
	}
}
