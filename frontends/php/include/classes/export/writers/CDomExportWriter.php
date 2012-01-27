<?php

class CDomExportWriter implements CExportWriter {

	public function write(CExportElement $elem) {
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;

		$this->fromExportElement($doc, $elem);

		return $doc->saveXML();
	}

	private function fromExportElement(DOMDocument $doc, CExportElement $elem) {
		$parentNode = $doc->appendChild(new DOMElement($elem->getName()));

		$data = $elem->getData();

		foreach ($data as $key => $value) {
			$node = $parentNode->appendChild(new DOMElement($key));
			$node->appendChild(new DOMText($value));
		}

		$childElements = $elem->getChilds();
		foreach ($childElements as $childElement) {
			$child = $this->fromExportElement($doc, $childElement);
			$parentNode->appendChild($child);
		}

		return $parentNode;
	}

}
