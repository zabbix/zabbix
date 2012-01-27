<?php

class CNodeExportElement extends CExportElement {

	protected $childElements = array();

	/**
	 * @param string $name element name
	 * @param array  $data
	 */
	public function __construct($name, array $data = array()) {
		$this->name = $name;

		$this->data = $data;

		$this->cleanData();
		$this->renameData();
	}

	/**
	 * Add child element.
	 *
	 * @param CExportElement $element
	 */
	public function addElement(CExportElement $element) {
		$this->childElements[] = $element;
	}

	public function elementType() {
		return self::EXPORT_ELEMENT_TYPE_NODE;
	}

	/**
	 * Get child elements.
	 *
	 * @return array
	 */
	public function getChilds() {
		return $this->childElements;
	}

}
