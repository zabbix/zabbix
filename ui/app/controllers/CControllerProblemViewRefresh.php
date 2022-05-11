<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerProblemViewRefresh extends CControllerProblemView {

	protected function doAction() {
		$data = [];

		if ($this->getInput('filter_counters', 0)) {
			$profile = (new CTabFilterProfile(static::FILTER_IDX, static::FILTER_FIELDS_DEFAULT))->read();
			$filters = $this->hasInput('counter_index')
				? [$profile->getTabFilter($this->getInput('counter_index'))]
				: $profile->getTabsWithDefaults();
			$data['filter_counters'] = [];

			foreach ($filters as $index => $tabfilter) {
				if (!$tabfilter['filter_custom_time']) {
					$tabfilter = [
						'from' => $profile->from,
						'to' => $profile->to
					] + $tabfilter;
				}
				else {
					$tabfilter['show'] = TRIGGERS_OPTION_ALL;
				}

				$data['filter_counters'][$index] = $tabfilter['filter_show_counter'] ? $this->getCount($tabfilter) : 0;
			}
		}

		if (($messages = getMessages()) !== null) {
			$data['messages'] = $messages->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
