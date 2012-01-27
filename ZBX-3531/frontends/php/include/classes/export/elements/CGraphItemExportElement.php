<?php

class CGraphItemExportElement extends CNodeExportElement{

	public function __construct($graphItem) {
		$requiredField = array('sortorder', 'drawtype', 'color', 'yaxisside', 'calc_fnc', 'type');
		$graphItem = ArrayHelper::getByKeys($graphItem, $requiredField);
		parent::__construct('graph_item', $graphItem);
	}

}
