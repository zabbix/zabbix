<?php declare(strict_types = 0);
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


namespace Widgets\TrigOver\Includes;

use CButtonIcon,
	CCol,
	CMenuPopupHelper,
	CSettingsHelper,
	CSeverityHelper,
	CSpan,
	CTableInfo,
	CUrl;

class ViewHelper {

	/**
	 * Creates and returns a trigger status cell for the trigger overview table.
	 *
	 * @param array $trigger
	 * @param array $dependencies
	 *
	 * @return CCol
	 */
	public static function getTriggerOverviewCell(array $trigger, array $dependencies): CCol {
		$column = (new CCol([
			array_key_exists($trigger['triggerid'], $dependencies)
				? self::makeTriggerDependencies($dependencies[$trigger['triggerid']])
				: [],
			$trigger['problem']['acknowledged'] == 1
				? (new CSpan())->addClass(ZBX_ICON_CHECK)
				: null
		]))
			->addClass(CSeverityHelper::getStyle((int) $trigger['priority'], $trigger['value'] == TRIGGER_VALUE_TRUE))
			->addClass(ZBX_STYLE_CURSOR_POINTER);

		$eventid = 0;
		$blink_period = timeUnitToSeconds(CSettingsHelper::get(CSettingsHelper::BLINK_PERIOD));
		$duration = time() - $trigger['lastchange'];

		if ($blink_period > 0 && $duration < $blink_period) {
			$column->addClass('blink');
			$column->setAttribute('data-time-to-blink', $blink_period - $duration);
			$column->setAttribute('data-toggle-class', ZBX_STYLE_BLINK_HIDDEN);
		}

		if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
			$eventid = $trigger['problem']['eventid'];
			$show_update_problem = true;
		}
		else {
			$show_update_problem = false;
		}

		$column->setMenuPopup(CMenuPopupHelper::getTrigger([
			'triggerid' => $trigger['triggerid'],
			'backurl' => (new CUrl('zabbix.php'))
				->setArgument('action', 'dashboard.view')
				->getUrl(),
			'eventid' => $eventid,
			'show_update_problem' => $show_update_problem
		]));

		return $column;
	}

	/**
	 * Returns icons with tooltips for triggers with dependencies.
	 *
	 * @param array $dependencies
	 *        array $dependencies['up']    (optional) The list of "Dependent" triggers.
	 *        array $dependencies['down']  (optional) The list of "Depends on" triggers.
	 *
	 * @return array
	 */
	public static function makeTriggerDependencies(array $dependencies): array {
		$result = [];

		foreach (['down', 'up'] as $type) {
			if (array_key_exists($type, $dependencies)) {
				$table = (new CTableInfo())
					->setAttribute('style', 'max-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
					->setHeader([$type === 'down' ? _('Depends on') : _('Dependent')]);

				foreach ($dependencies[$type] as $description) {
					$table->addRow($description);
				}

				$result[] = (new CButtonIcon($type === 'down' ? ZBX_ICON_BULLET_ALT_DOWN : ZBX_ICON_BULLET_ALT_UP))
					->addClass(ZBX_STYLE_COLOR_ICON)
					->setHint($table, '', false);
			}
		}

		return $result;
	}
}
