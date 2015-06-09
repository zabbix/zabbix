<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CScreenPlainText extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		// if screen is defined in template, then 'real_resourceid' is defined and should be used
		if (!empty($this->screenitem['real_resourceid'])) {
			$this->screenitem['resourceid'] = $this->screenitem['real_resourceid'];
		}

		if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && !empty($this->hostid)) {
			$newitemid = get_same_item_for_host($this->screenitem['resourceid'], $this->hostid);
			$this->screenitem['resourceid'] = !empty($newitemid)? $newitemid : 0;
		}

		if ($this->screenitem['resourceid'] == 0) {
			$table = new CTableInfo();
			$table->setHeader([_('Timestamp'), _('Item')]);

			return $this->getOutput($table);
		}

		$items = CMacrosResolverHelper::resolveItemNames([get_item_by_itemid($this->screenitem['resourceid'])]);
		$item = reset($items);

		switch ($item['value_type']) {
			case ITEM_VALUE_TYPE_TEXT:
			case ITEM_VALUE_TYPE_LOG:
				$orderField = 'id';
				break;
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
			default:
				$orderField = ['itemid', 'clock'];
		}

		$host = get_host_by_itemid($this->screenitem['resourceid']);

		$table = new CTableInfo();
		$table->setHeader([_('Timestamp'), $host['name'].NAME_DELIMITER.$item['name_expanded']]);

		$stime = zbxDateToTime($this->timeline['stime']);

		$histories = API::History()->get([
			'history' => $item['value_type'],
			'itemids' => $this->screenitem['resourceid'],
			'output' => API_OUTPUT_EXTEND,
			'sortorder' => ZBX_SORT_DOWN,
			'sortfield' => $orderField,
			'limit' => $this->screenitem['elements'],
			'time_from' => $stime,
			'time_till' => $stime + $this->timeline['period']
		]);
		foreach ($histories as $history) {
			switch ($item['value_type']) {
				case ITEM_VALUE_TYPE_FLOAT:
					sscanf($history['value'], '%f', $value);
					break;
				case ITEM_VALUE_TYPE_TEXT:
				case ITEM_VALUE_TYPE_STR:
				case ITEM_VALUE_TYPE_LOG:
					$value = $this->screenitem['style'] ? new CJsScript($history['value']) : $history['value'];
					break;
				default:
					$value = $history['value'];
					break;
			}

			if ($item['valuemapid'] > 0) {
				$value = applyValueMap($value, $item['valuemapid']);
			}

			$class = $this->screenitem['style'] ? null : 'pre';

			$table->addRow([
				zbx_date2str(DATE_TIME_FORMAT_SECONDS, $history['clock']),
				(new CCol($value))->addClass($class)
			]);
		}

		return $this->getOutput($table);
	}
}
