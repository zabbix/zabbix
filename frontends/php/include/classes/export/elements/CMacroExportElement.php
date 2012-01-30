<?php

class CMacroExportElement extends CExportElement {

	public function __construct(array $macro) {
		parent::__construct('macro', $macro);
	}

	protected function requiredFields() {
		return array('macro', 'value');
	}

}
