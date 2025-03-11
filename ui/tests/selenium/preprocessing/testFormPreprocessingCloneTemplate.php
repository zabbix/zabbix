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
 * Test of cloning template with preprocessing steps in items.
 *
 * @backup hosts, items
 */
class testFormPreprocessingCloneTemplate extends testFormPreprocessingClone {

	public $hostid = 15000;				// Inheritance test template.
	public $itemid = 15000;				// itemInheritance.
	public $lldid = 15011;				// testInheritanceDiscoveryRule.
	public $item_prototypeid = 15021;	// itemDiscovery.

	/**
	 * @onBefore prepareLLDPreprocessing, prepareItemPreprocessing, prepareItemPrototypePreprocessing
	 */
	public function testFormPreprocessingCloneTemplate_CloneTemplate() {
		$this->executeCloning(true);
	}
}
