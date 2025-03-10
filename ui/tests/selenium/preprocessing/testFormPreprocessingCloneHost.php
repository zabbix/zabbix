<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

require_once __DIR__.'/../common/testFormPreprocessingClone.php';

/**
 * Test of cloning host with preprocessing steps in items.
 *
 * @backup hosts, items
 */
class testFormPreprocessingCloneHost extends testFormPreprocessingClone {

	public $hostid = 40001;				// Simple form test host.
	public $itemid = 99102;				// StestFormItem.
	public $lldid = 133800;				// testFormDiscoveryRule.
	public $item_prototypeid = 23800;	// testFormItemPrototype1.

	/**
	 * @onBefore prepareLLDPreprocessing, prepareItemPreprocessing, prepareItemPrototypePreprocessing
	 */
	public function testFormPreprocessingCloneHost_CloneHost() {
		$this->executeCloning();
	}
}
