<?php

class CScreenExportElement extends CExportElement {

	public function __construct(array $screen) {
		parent::__construct('screen', $screen);

		$this->addScreenItems($screen['screenitems']);
	}

	protected function requiredFields() {
		return array('name', 'hsize', 'vsize');
	}

	protected function addScreenItems(array $screenItems) {
		$screenItemsElement = new CExportElement('screen_items');
		foreach ($screenItems as $screenItem) {
			$screenItemsElement->addElement(new CScreenItemExportElement($screenItem));
		}
		$this->addElement($screenItemsElement);
	}

}
