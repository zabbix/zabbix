<?php

class CXmlWriterExportWriter extends XMLWriter implements CExportWriter {

	public function write(CExportElement $elem) {
		$this->openMemory();
		$this->setIndent(true);
		$this->setIndentString('    ');
		$this->startDocument('1.0', 'UTF-8');

		$this->fromExportElement($elem);

		$this->endDocument();

		return $this->outputMemory();
	}

	private function fromExportElement(CExportElement $elem) {
		$this->startElement($elem->getName());

		$data = $elem->getData();

		foreach ($data as $name => $value) {
			$this->startElement($name);
			$this->text($value);
			$this->endElement();
		}

		$childElements = $elem->getChilds();
		foreach ($childElements as $childElement) {
			$this->fromExportElement($childElement);
		}

		$this->endElement();
	}
}
