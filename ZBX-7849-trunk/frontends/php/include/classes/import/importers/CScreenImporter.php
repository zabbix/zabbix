<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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


class CScreenImporter extends CAbstractScreenImporter {

	/**
	 * Import screens.
	 *
	 * @param array $screens
	 *
	 * @return mixed
	 */
	public function import(array $screens) {
		$screens = zbx_toHash($screens, 'name');

		$this->checkCircularScreenReferences($screens);

		do {
			$independentScreens = $this->getIndependentScreens($screens);

			$screensToCreate = array();
			$screensToUpdate = array();
			foreach ($independentScreens as $name) {
				$screen = $screens[$name];
				unset($screens[$name]);

				$screen = $this->resolveScreenReferences($screen);

				if ($screenId = $this->referencer->resolveScreen($screen['name'])) {
					$screen['screenid'] = $screenId;
					$screensToUpdate[] = $screen;
				}
				else {
					$screensToCreate[] = $screen;
				}
			}

			if ($this->options['screens']['createMissing'] && $screensToCreate) {
				$newScreenIds = API::Screen()->create($screensToCreate);
				foreach ($screensToCreate as $num => $newScreen) {
					$screenidId = $newScreenIds['screenids'][$num];
					$this->referencer->addScreenRef($newScreen['name'], $screenidId);
				}
			}
			if ($this->options['screens']['updateExisting'] && $screensToUpdate) {
				API::Screen()->update($screensToUpdate);
			}
		} while (!empty($independentScreens));

		// if there are screens left in $screens, then they have unresolved references
		foreach ($screens as $screen) {
			$unresolvedReferences = array();
			foreach ($screen['screenitems'] as $screenItem) {
				if ($screenItem['resourcetype'] == SCREEN_RESOURCE_SCREEN
						&& !$this->referencer->resolveScreen($screenItem['resource']['name'])) {
					$unresolvedReferences[] = $screenItem['resource']['name'];
				}
			}
			$unresolvedReferences = array_unique($unresolvedReferences);
			throw new Exception(_n('Cannot import screen "%1$s": subscreen "%2$s" does not exist.',
				'Cannot import screen "%1$s": subscreens "%2$s" do not exist.',
				$screen['name'], implode(', ', $unresolvedReferences), count($unresolvedReferences)));
		}
	}

	/**
	 * Check if screens have circular references.
	 * Circular references can be only in screen items that represent another screen.
	 *
	 * @throws Exception
	 * @see checkCircularRecursive
	 *
	 * @param array $screens
	 *
	 * @return void
	 */
	protected function checkCircularScreenReferences(array $screens) {
		foreach ($screens as $screenName => $screen) {
			if (empty($screen['screenitems'])) {
				continue;
			}

			foreach ($screen['screenitems'] as $screenItem) {
				$checked = array($screenName);
				if ($circScreens = $this->checkCircularRecursive($screenItem, $screens, $checked)) {
					throw new Exception(_s('Circular reference in screens: %1$s.', implode(' - ', $circScreens)));
				}
			}
		}
	}

	/**
	 * Recursive function for searching for circular screen references.
	 * If circular reference exist it return array with screens names that fort it.
	 *
	 * @param array $screenItem screen to inspect on current recursive loop
	 * @param array $screens    all screens where circular references should be searched
	 * @param array $checked    screen names that already were processed,
	 *                          should contain unique values if no circular references exist
	 *
	 * @return array|bool
	 */
	protected function checkCircularRecursive(array $screenItem, array $screens, array $checked) {
		// if element is not map element, recursive reference cannot happen
		if ($screenItem['resourcetype'] != SCREEN_RESOURCE_SCREEN) {
			return false;
		}

		$screenName = $screenItem['resource']['name'];

		// if current screen name is already in list of checked screen names,
		// circular reference exists
		if (in_array($screenName, $checked)) {
			// to have nice result containing only screens that have circular reference,
			// remove everything that was added before repeated screen name
			$checked = array_slice($checked, array_search($screenName, $checked));
			// add repeated name to have nice loop like s1->s2->s3->s1
			$checked[] = $screenName;
			return $checked;
		}
		else {
			$checked[] = $screenName;
		}

		// we need to find screen that current element reference to
		// and if it has screen items check all them recursively
		if (!empty($screens[$screenName]['screenitems'])) {
			foreach ($screens[$screenName]['screenitems'] as $sItem) {
				return $this->checkCircularRecursive($sItem, $screens, $checked);
			}
		}

		return false;
	}

	/**
	 * Get screens that don't have screen items that reference not existing screen i.e. screen items references can be resolved.
	 * Returns array with screen names.
	 *
	 * @param array $screens
	 *
	 * @return array
	 */
	protected function getIndependentScreens(array $screens) {
		foreach ($screens as $num => $screen) {
			if (empty($screen['screenitems'])) {
				continue;
			}

			foreach ($screen['screenitems'] as $screenItem) {
				if ($screenItem['resourcetype'] == SCREEN_RESOURCE_SCREEN) {
					if (!$this->referencer->resolveScreen($screenItem['resource']['name'])) {
						unset($screens[$num]);
						continue 2;
					}
				}
			}
		}

		return zbx_objectValues($screens, 'name');
	}
}
