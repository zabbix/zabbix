<?php

class CHostApplicationExportElement extends CNodeExportElement {

	public function __construct($application) {
		$application = ArrayHelper::getByKeys($application, array('name'));
		parent::__construct('application', $application);
	}


}
