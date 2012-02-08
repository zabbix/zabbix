<?php

class CXmlImportReader extends CImportReader {

	public function read($file) {
		libxml_use_internal_errors(true);

		$xml = new DOMDocument();
		if (!$xml->load($file)) {
			foreach (libxml_get_errors() as $error) {
				$text = '';

				switch ($error->level) {
					case LIBXML_ERR_WARNING:
						$text .= _s('XML file contains errors: Warning %1$s:', $error->code);
						break;

					case LIBXML_ERR_ERROR:
						$text .= _s('XML file contains errors: Error %1$s:', $error->code);
						break;

					case LIBXML_ERR_FATAL:
						$text .= _s('XML file contains errors: Fatal Error %1$s:', $error->code);
						break;
				}

				$text .= trim($error->message) . ' [ Line: '.$error->line.' | Column: '.$error->column.' ]';
				error($text);
				break;
			}

			libxml_clear_errors();
			return false;
		}

		return $this->XmlToArray($xml);
	}

	public function XmlToArray($xml) {
		$array = array();

		foreach ($xml->childNodes as $node) {
			if ($node->nodeType == XML_TEXT_NODE) {
				if ($node->nextSibling) {
					continue;
				}
				if (!$node->isWhitespaceInElementContent()) {
					return $node->nodeValue;
				}
			}

			if ($node->hasChildNodes()) {
				$nodeName = $node->nodeName;

				if (isset($array[$nodeName])) {
					$nodeName .= count($array);
				}
				$array[$nodeName] = $this->XMLtoArray($node);
			}
		}

		return $array;
	}
}
