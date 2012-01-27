<?php

abstract class CExportElement {

	const EXPORT_ELEMENT_TYPE_NODE = 'node';
	const EXPORT_ELEMENT_TYPE_TEXT = 'text';

	protected $data;
	protected $name;

	abstract public function elementType();
	abstract public function getChilds();

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
		// TODO: natural sorting
		ksort($this->data);
		return $this->data;
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

	protected function cleanData() {
		$requiredFields = $this->requiredFields();
		$referenceFields = $this->referenceFields();
		foreach ($referenceFields as $field) {
			if (isset($this->data[$field])) {
				$requiredFields[] = $field;
			}
		}
		$this->data = ArrayHelper::getByKeys($this->data, $requiredFields);
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

}
