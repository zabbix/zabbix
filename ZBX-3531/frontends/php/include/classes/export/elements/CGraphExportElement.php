<?php

class CGraphExportElement extends CExportElement{

	public function __construct($graph) {
		parent::__construct('graph', $graph);
	}

	protected function requiredFields() {
		return array('name', 'width', 'height', 'yaxismin', 'yaxismax', 'show_work_period', 'show_triggers',
			'graphtype', 'show_legend', 'show_3d', 'percent_left', 'percent_left', 'percent_right', 'ymin_type', 'ymax_type');
	}

}
