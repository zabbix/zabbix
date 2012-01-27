<?php

class CHostGroupExportElement extends CNodeExportElement {

	public function __construct($hostGroup) {
		$hostGroup = ArrayHelper::getByKeys($hostGroup, array('name'));
		parent::__construct('group', $hostGroup);
	}

}
