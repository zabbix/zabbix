<?php

class CGroupExportElement extends CNodeExportElement {

	public function __construct($group) {
		$group = ArrayHelper::getByKeys($group, array('name'));
		parent::__construct('group', $group);
	}

}
