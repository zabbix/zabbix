<?php

class CScreenItemExportElement extends CExportElement {

	public function __construct(array $screenItem) {
		parent::__construct('screen_item', $screenItem);

		$this->addResource($screenItem['resourceid']);
	}

	protected function requiredFields() {
		return array('resourcetype', 'width', 'height', 'x', 'y', 'colspan', 'rowspan', 'elements', 'valign', 'halign',
			'style', 'url', 'dynamic', 'sort_triggers');
	}

	protected function addResource(array $resource) {
		$this->addElement(new CExportElement('resource', $resource));
	}

}
