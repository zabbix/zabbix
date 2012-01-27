<?php

class CMacroExportElement extends CNodeExportElement {

	public function __construct(array $macro) {
		$macro = ArrayHelper::getByKeys($macro, array('macro', 'value'));
		parent::__construct('macro', $macro);
	}
}
