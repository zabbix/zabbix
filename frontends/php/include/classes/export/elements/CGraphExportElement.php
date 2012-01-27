<?php

class CGraphExportElement extends CNodeExportElement{

	public function __construct($graph) {
		$requiredField = array('name', 'width', 'height', 'yaxismin', 'yaxismax', 'show_work_period', 'show_triggers',
			'graphtype', 'show_legend', 'show_3d', 'percent_left', 'percent_left', 'percent_right', 'ymin_type', 'ymax_type');
		$graph = ArrayHelper::getByKeys($graph, $requiredField);
		parent::__construct('graph', $graph);
	}

}
