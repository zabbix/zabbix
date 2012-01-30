<?php

class CMapExportElement extends CExportElement{

	public function __construct(array $map) {
		parent::__construct('map', $map);
	}

	protected function requiredFields() {
		return array('name', 'width', 'height', 'label_type', 'label_location', 'highlight', 'expandproblem',
			'markelements', 'show_unack', 'grid_size', 'grid_show', 'grid_align', 'label_format', 'label_type_host',
			'label_type_hostgroup', 'label_type_trigger', 'label_type_map', 'label_type_image', 'label_string_host',
			'label_string_hostgroup', 'label_string_trigger', 'label_string_map', 'label_string_image', 'expand_macros');
	}

}
