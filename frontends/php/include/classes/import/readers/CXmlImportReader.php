<?php

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
						$text .= S_XML_FILE_CONTAINS_ERRORS.'. Warning '.$error->code.': ';
						break;
					case LIBXML_ERR_ERROR:
						$text .= S_XML_FILE_CONTAINS_ERRORS.'. Error '.$error->code.': ';
						break;
					case LIBXML_ERR_FATAL:
						$text .= S_XML_FILE_CONTAINS_ERRORS.'. Fatal Error '.$error->code.': ';
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
	 * @param string $xml
	 *
	 * @return array|string
	 */
	protected function xmlToArray($xml) {
		$array = '';
		while ($xml->read()) {
			switch ($xml->nodeType) {
				case XMLReader::ELEMENT:
					if ($array === '') {
						$array = array();
					}

					$nodeName = $xml->name;
					if (isset($array[$nodeName])) {
						$nodeName .= count($array);
					}
					$array[$nodeName] = $xml->isEmptyElement ? array() : $this->xmlToArray($xml);
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
