<?php

class CXmlImportReader extends CImportReader {

	public function read($file) {
		$xml = new XMLReader();
		$xml->open($file);
		$array = $this->xmlToArray($xml);
		$xml->close();
		return $array;
	}

	function xmlToArray($xml) {
		$array = '';

		while ($xml->read()) {
			switch ($xml->nodeType) {
				case XMLReader::ELEMENT:
					if ($array === null) {
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
