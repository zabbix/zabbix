<?php

abstract class CImportFormatter {

	protected $data;

	public function setData(array $data) {
		$this->data = $data;
	}

	protected function renameData($data, $fieldMap) {
		foreach ($data as $key => $value) {
			if (isset($fieldMap[$key])) {
				$data[$fieldMap[$key]] = $value;
				unset($data[$key]);
			}
		}
		return $data;
	}

	/**
	 * @abstract
	 * @return array
	 */
	abstract public function getGroups();

	abstract public function getTemplates();

	abstract public function getHosts();

	abstract public function getApplications();

	abstract public function getItems();

	abstract public function getDiscoveryRules();

	abstract public function getGraphs();

	abstract public function getTriggers();

	abstract public function getImages();

	abstract public function getMaps();
}
