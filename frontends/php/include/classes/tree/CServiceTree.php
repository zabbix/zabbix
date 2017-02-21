<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * A class for rendering service trees.
 *
 * @see createServiceMonitoringTree() and createServiceConfigurationTree() for a way of creating trees from services
 */
class CServiceTree extends CTree {

	/**
	 * Returns a column object for the given row and field. Add additional service tree related formatting.
	 *
	 * @param $rowId
	 * @param $colName
	 *
	 * @return CCol
	 */
	protected function makeCol($rowId, $colName) {
		$config = select_config();

		switch ($colName) {
			case 'status':
				if (zbx_is_int($this->tree[$rowId][$colName]) && $this->tree[$rowId]['id'] > 0) {
					$status = $this->tree[$rowId][$colName];

					// do not show the severity for information and unclassified triggers
					if (in_array($status, [TRIGGER_SEVERITY_INFORMATION, TRIGGER_SEVERITY_NOT_CLASSIFIED])) {
						return (new CCol(_('OK')))->addClass(ZBX_STYLE_GREEN);
					}
					else {
						return (new CCol(getSeverityName($status, $config)))->addClass(getSeverityStyle($status));
					}
				}
				break;

			case 'sla':
				return parent::makeCol($rowId, $colName)->addClass(ZBX_STYLE_CELL_WIDTH);
		}

		return parent::makeCol($rowId, $colName);
	}
}
