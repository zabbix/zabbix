<?php

class CGraphItemExportElement extends CExportElement{

	public function __construct($graphItem) {
		parent::__construct('graph_item', $graphItem);
	}

	protected function requiredFields() {
		return array('sortorder', 'drawtype', 'color', 'yaxisside', 'calc_fnc', 'type');
	}

}
