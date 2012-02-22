<?php

class CImportFormatter {

	protected $data;

	public function setData(array $data) {
		$this->data = $data;
	}

	protected function renameData(&$data, $fieldMap) {
		foreach ($data as $key => $value) {
			if (isset($fieldMap[$key])) {
				$data[$fieldMap[$key]] = $value;
				unset($data[$key]);
			}
		}
	}
}
