<?php

class CExportElement {

	protected $data;
	protected $name;
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

	/**
	 * Get child elements.
	 *
	 * @return array
	 */
	public function getChilds() {
		return $this->childElements;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Gets this elements data.
	 *
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	public function toArray() {
		$array = $this->getData();
		$childs = $this->getChilds();

		$namesList = array();
		$duplicateNames = false;
		foreach ($childs as $child) {
			if (isset($namesList[$child->getName()])) {
				$duplicateNames = true;
				break;
			}
			$namesList[$child->getName()] = 1;
		}

		if (count($childs) <= 1 && empty($array)) {
			$duplicateNames = true;
		}

		foreach ($childs as $child) {
			if ($duplicateNames) {
				$array[] = $child->toArray();
			}
			else {
				$array[$child->getName()] = $child->toArray();
			}
		}

		return $array;
	}

	protected function cleanData() {
		$requiredFields = $this->requiredFields();
		$referenceFields = $this->referenceFields();
		foreach ($referenceFields as $field) {
			if (isset($this->data[$field])) {
				$requiredFields[] = $field;
			}
		}
		if ($requiredFields) {
			$this->data = ArrayHelper::getByKeys($this->data, $requiredFields);
		}
	}

	protected function renameData() {
		$fieldMap = $this->fieldNameMap();
		foreach ($this->data as $key => $value) {
			if (isset($fieldMap[$key])) {
				$this->data[$fieldMap[$key]] = $value;
				unset($this->data[$key]);
			}
		}
	}

	protected function requiredFields() {
		return array();
	}

	protected function referenceFields() {
		return array();
	}

	protected function fieldNameMap() {
		return array();
	}

}
