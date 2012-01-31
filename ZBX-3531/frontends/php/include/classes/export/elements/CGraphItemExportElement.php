<?php

class CGraphItemExportElement extends CExportElement{

	public function __construct($graphItem) {
		parent::__construct('graph_item', $graphItem);

		$this->addItem($graphItem['itemid']);
	}

	protected function requiredFields() {
		return array('sortorder', 'drawtype', 'color', 'yaxisside', 'calc_fnc', 'type');
	}

	protected function addItem(array $item) {
		$this->addElement(new CExportElement('item', $item));
	}

}
