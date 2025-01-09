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


/**
 * Class containing methods for operations with map elements.
 *
 * @return mixed
 */
abstract class CMapElement extends CApiService {

	protected function checkSelementInput(&$selements, $method) {
		$create = ($method === 'createSelements');
		$update = ($method === 'updateSelements');

		$element_types = [SYSMAP_ELEMENT_TYPE_HOST, SYSMAP_ELEMENT_TYPE_MAP, SYSMAP_ELEMENT_TYPE_TRIGGER,
			SYSMAP_ELEMENT_TYPE_HOST_GROUP, SYSMAP_ELEMENT_TYPE_IMAGE
		];

		$elementtype_validator = new CLimitedSetValidator(['values' => $element_types]);

		if ($update) {
			$db_selements = $this->fetchSelementsByIds(zbx_objectValues($selements, 'selementid'));
			$selements = $this->extendFromObjects(zbx_toHash($selements, 'selementid'), $db_selements, ['elementtype', 'elements']);
		}

		foreach ($selements as &$selement) {
			if (!is_array($selement)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if ($create) {
				// Check required parameters.
				$missing_keys = array_diff(['sysmapid', 'elementtype', 'iconid_off'], array_keys($selement));

				if ($missing_keys) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Map element is missing parameters: %1$s', implode(', ', $missing_keys))
					);
				}
			}

			if (array_key_exists('label', $selement)
					&& mb_strlen($selement['label']) > DB::getFieldLength('sysmaps_elements', 'label')) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'label', _('value is too long')
				));
			}

			if (array_key_exists('urls', $selement)) {
				$url_validate_options = ['allow_user_macro' => false];
				if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_HOST) {
					$url_validate_options['allow_inventory_macro'] = INVENTORY_URL_MACRO_HOST;
				}
				elseif ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
					$url_validate_options['allow_inventory_macro'] = INVENTORY_URL_MACRO_TRIGGER;
				}
				else {
					$url_validate_options['allow_inventory_macro'] = INVENTORY_URL_MACRO_NONE;
				}

				foreach ($selement['urls'] as &$url_data) {
					if (!CHtmlUrlValidator::validate($url_data['url'], $url_validate_options)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong value for "url" field.'));
					}

					unset($url_data['sysmapelementurlid'], $url_data['selementid']);
				}
				unset($url_data);
			}

			if (!$elementtype_validator->validate($selement['elementtype'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'elementtype', _s('value must be one of %1$s', implode(', ', $element_types))
				));
			}

			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_IMAGE) {
				unset($selement['elements']);
			}
			else {
				if (!array_key_exists('elements', $selement)) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Map element is missing parameters: %1$s', 'elements')
					);
				}

				if (!is_array($selement['elements'])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
				}

				if (!$selement['elements']) {
					self::exception(ZBX_API_ERROR_PARAMETERS,
						_s('Incorrect value for field "%1$s": %2$s.', 'elements', _('cannot be empty'))
					);
				}
			}

			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				foreach ($selement['elements'] as $element) {
					if (!array_key_exists('triggerid', $element)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Map element is missing parameters: %1$s', 'triggerid')
						);
					}

					if (is_array($element['triggerid'])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
					}
					elseif ($element['triggerid'] === '' || $element['triggerid'] === null
							|| $element['triggerid'] === false) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'triggerid', _('cannot be empty'))
						);
					}
				}
			}
			else {
				if (array_key_exists('elements', $selement)) {
					switch ($selement['elementtype']) {
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$field = 'groupid';
							break;

						case SYSMAP_ELEMENT_TYPE_HOST:
							$field = 'hostid';
							break;

						case SYSMAP_ELEMENT_TYPE_MAP:
							$field = 'sysmapid';
							break;
					}

					$elements = reset($selement['elements']);

					if (!is_array($elements)) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
					}

					if (!array_key_exists($field, $elements)) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Map element is missing parameters: %1$s', $field)
						);
					}

					if (is_array($elements[$field])) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
					}
					elseif ($elements[$field] === '' || $elements[$field] === null || $elements[$field] === false) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', $field, _('cannot be empty'))
						);
					}

					if (count($selement['elements']) > 1) {
						self::exception(ZBX_API_ERROR_PARAMETERS,
							_s('Incorrect value for field "%1$s": %2$s.', 'elements', _('incorrect element count'))
						);
					}
				}
			}

			if (isset($selement['iconid_off']) && $selement['iconid_off'] == 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('No icon for map element ""%1$s".',
					array_key_exists('label', $selement) ? $selement['label'] : ''
				));
			}

			if ($create) {
				$selement['urls'] = array_key_exists('urls', $selement) ? $selement['urls'] : [];
			}
		}
		unset($selement);

		// check permissions to used objects
		if (!CMapHelper::checkSelementPermissions($selements)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		return $update ? $db_selements : true;
	}

	/**
	 * Checks that shape color attributes are valid.
	 *
	 * @throws APIException if input is invalid.
	 *
	 * @param array $shapes			An array of shapes.
	 */
	protected function checkShapeInput($shapes) {
		$color_validator = new CColorValidator();
		$fields = ['border_color', 'background_color', 'font_color'];

		foreach ($shapes as $shape) {
			foreach ($fields as $field) {
				if (array_key_exists($field, $shape) && $shape[$field] !== ''
						&& !$color_validator->validate($shape[$field])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, $color_validator->getError());
				}
			}
		}
	}

	/**
	 * Returns a hash of map elements with the given IDs. The result also includes URL assigned to the elements.
	 *
	 * @param array $selementIds
	 *
	 * @return array
	 */
	protected function fetchSelementsByIds(array $selementIds) {
		$selements = API::getApiService()->select('sysmaps_elements', [
			'output' => API_OUTPUT_EXTEND,
			'filter' => ['selementid' => $selementIds],
			'preservekeys' => true
		]);

		if ($selements) {
			foreach ($selements as &$selement) {
				$selement['urls'] = [];
				$selement['elements'] = [];
			}
			unset($selement);

			$selementUrls = API::getApiService()->select('sysmap_element_url', [
				'output' => API_OUTPUT_EXTEND,
				'filter' => ['selementid' => $selementIds]
			]);
			foreach ($selementUrls as $selementUrl) {
				$selements[$selementUrl['selementid']]['urls'][] = $selementUrl;
			}

			$selement_triggers = API::getApiService()->select('sysmap_element_trigger', [
				'output' => ['selement_triggerid', 'selementid', 'triggerid'],
				'filter' => ['selementid' => $selementIds]
			]);

			foreach ($selement_triggers as $selement_trigger) {
				$selements[$selement_trigger['selementid']]['elements'][] = [
					'selement_triggerid' => $selement_trigger['selement_triggerid'],
					'triggerid' => $selement_trigger['triggerid']
				];
			}

			$single_element_types = [SYSMAP_ELEMENT_TYPE_HOST, SYSMAP_ELEMENT_TYPE_MAP, SYSMAP_ELEMENT_TYPE_HOST_GROUP];
			foreach ($selements as &$selement) {
				if (in_array($selement['elementtype'], $single_element_types)) {
					switch ($selement['elementtype']) {
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$field = 'groupid';
							break;

						case SYSMAP_ELEMENT_TYPE_HOST:
							$field = 'hostid';
							break;

						case SYSMAP_ELEMENT_TYPE_MAP:
							$field = 'sysmapid';
							break;
					}
					$selement['elements'][] = [$field => $selement['elementid']];
				}

				unset($selement['elementid']);
			}
			unset($selement);
		}

		return $selements;
	}

	protected function checkLinkInput($links, $method) {
		$update = ($method == 'updateLink');
		$delete = ($method == 'deleteLink');

		// permissions
		if ($update || $delete) {
			$linkDbFields = ['linkid' => null];

			$dbLinks = API::getApiService()->select('sysmap_element_url', [
				'filter' => ['selementid' => zbx_objectValues($links, 'linkid')],
				'output' => ['linkid'],
				'preservekeys' => true
			]);
		}
		else {
			$linkDbFields = [
				'sysmapid' => null,
				'selementid1' => null,
				'selementid2' => null
			];
		}

		$colorValidator = new CColorValidator();

		foreach ($links as $link) {
			if (!check_db_fields($linkDbFields, $link)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for map link.'));
			}

			if (isset($link['color']) && !$colorValidator->validate($link['color'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $colorValidator->getError());
			}

			if ($update || $delete) {
				if (!isset($dbLinks[$link['linkid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}
			}

			if (array_key_exists('label', $link)
					&& mb_strlen($link['label']) > DB::getFieldLength('sysmaps_links', 'label')) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Incorrect value for field "%1$s": %2$s.',
					'label', _('value is too long')
				));
			}
		}

		return true;
	}

	/**
	 * Add element to sysmap.
	 *
	 * @param array $elements[0,...]['sysmapid']
	 * @param array $elements[0,...]['elementid']
	 * @param array $elements[0,...]['elementtype']
	 * @param array $elements[0,...]['label']
	 * @param array $elements[0,...]['x']
	 * @param array $elements[0,...]['y']
	 * @param array $elements[0,...]['iconid_off']
	 * @param array $elements[0,...]['iconid_on']
	 * @param array $elements[0,...]['iconid_disabled']
	 * @param array $elements[0,...]['urls'][0,...]
	 * @param array $elements[0,...]['label_location']
	 *
	 * @return array
	 */
	protected function createSelements(array $selements) {
		$selements = zbx_toArray($selements);

		$this->checkSelementInput($selements, __FUNCTION__);

		$single_element_types = [SYSMAP_ELEMENT_TYPE_HOST, SYSMAP_ELEMENT_TYPE_MAP, SYSMAP_ELEMENT_TYPE_HOST_GROUP];
		foreach ($selements as &$selement) {
			if (in_array($selement['elementtype'], $single_element_types)) {
				$selement['elementid'] = reset($selement['elements'][0]);
			}
			elseif ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				unset($selement['elementid']);
			}
		}
		unset($selement);

		$selementids = DB::insert('sysmaps_elements', $selements);

		$triggerids = [];

		foreach ($selementids as $key => $selementid) {
			if ($selements[$key]['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				foreach ($selements[$key]['elements'] as $element) {
					$triggerids[$element['triggerid']] = true;
				}
			}
		}

		$db_triggers = API::Trigger()->get([
			'output' => ['triggerid', 'priority'],
			'triggerids' => array_keys($triggerids),
			'preservekeys' => true
		]);

		$triggers = [];

		foreach ($selementids as $key => $selementid) {
			if ($selements[$key]['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				foreach ($selements[$key]['elements'] as $element) {
					$priority = $db_triggers[$element['triggerid']]['priority'];
					$triggers[$selementid][$priority][] = [
						'selementid' => $selementid,
						'triggerid' => $element['triggerid']
					];
				}
				krsort($triggers[$selementid]);
			}
		}

		$triggers_to_add = [];

		foreach ($triggers as $selement_triggers) {
			foreach ($selement_triggers as $selement_trigger_priorities) {
				foreach ($selement_trigger_priorities as $selement_trigger_priority) {
					$triggers_to_add[] = $selement_trigger_priority;
				}
			}
		}

		DB::insert('sysmap_element_trigger', $triggers_to_add);

		$insertUrls = [];

		foreach ($selementids as $key => $selementid) {
			foreach ($selements[$key]['urls'] as $url) {
				$url['selementid'] = $selementid;

				$insertUrls[] = $url;
			}
		}

		DB::insert('sysmap_element_url', $insertUrls);

		$this->createSelementsTags($selements, $selementids);

		return ['selementids' => $selementids];
	}

	/**
	 * Update element to sysmap.
	 *
	 * @param array $elements[0,...]['selementid']
	 * @param array $elements[0,...]['sysmapid']
	 * @param array $elements[0,...]['elementid']
	 * @param array $elements[0,...]['elementtype']
	 * @param array $elements[0,...]['label']
	 * @param array $elements[0,...]['x']
	 * @param array $elements[0,...]['y']
	 * @param array $elements[0,...]['iconid_off']
	 * @param array $elements[0,...]['iconid_on']
	 * @param array $elements[0,...]['iconid_disabled']
	 * @param array $elements[0,...]['url']
	 * @param array $elements[0,...]['label_location']
	 */
	protected function updateSelements(array $selements) {
		$selements = zbx_toArray($selements);
		$selementIds = [];

		$db_selements = $this->checkSelementInput($selements, __FUNCTION__);

		$update = [];
		$urlsToDelete = [];
		$urlsToUpdate = [];
		$urlsToAdd = [];
		$triggers_to_add = [];
		$triggers_to_delete = [];
		$triggerids = [];

		foreach ($selements as &$selement) {
			$db_selement = $db_selements[$selement['selementid']];

			// Change type from something to trigger.
			if ($selement['elementtype'] != $db_selement['elementtype']
					&& $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				$selement['elementid'] = 0;

				foreach ($selement['elements'] as $element) {
					$triggerids[$element['triggerid']] = true;
				}
			}

			// Change type from trigger to something.
			if ($selement['elementtype'] != $db_selement['elementtype']
					&& $db_selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				foreach ($db_selement['elements'] as $db_element) {
					$triggers_to_delete[] = $db_element['selement_triggerid'];
				}
			}

			if ($selement['elementtype'] != SYSMAP_ELEMENT_TYPE_IMAGE
					&& $selement['elementtype'] != SYSMAP_ELEMENT_TYPE_TRIGGER) {
				$selement['elementid'] = reset($selement['elements'][0]);
			}

			$db_elements = $db_selement['elements'];

			foreach ($db_selement['elements'] as &$element) {
				unset($element['selement_triggerid']);
			}
			unset($element);

			if ($selement['elementtype'] == $db_selement['elementtype']
					&& $selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				foreach ($db_elements as $element) {
					$triggers_to_delete[] = $element['selement_triggerid'];
				}

				foreach ($selement['elements'] as $element) {
					$triggerids[$element['triggerid']] = true;
				}
			}

			$update[] = [
				'values' => $selement,
				'where' => ['selementid' => $selement['selementid']]
			];
			$selementIds[] = $selement['selementid'];

			if (!isset($selement['urls'])) {
				continue;
			}

			$diffUrls = zbx_array_diff($selement['urls'], $db_selement['urls'], 'name');

			// add
			foreach ($diffUrls['first'] as $newUrl) {
				$newUrl['selementid'] = $selement['selementid'];
				$urlsToAdd[] = $newUrl;
			}

			// update url
			foreach ($diffUrls['both'] as $updUrl) {
				$urlsToUpdate[] = [
					'values' => $updUrl,
					'where' => [
						'selementid' => $selement['selementid'],
						'name' => $updUrl['name']
					]
				];
			}

			// delete url
			$urlsToDelete = array_merge($urlsToDelete, zbx_objectValues($diffUrls['second'], 'sysmapelementurlid'));
		}
		unset($selement);

		$this->updateElementsTags($selements);

		$db_triggers = API::Trigger()->get([
			'output' => ['triggerid', 'priority'],
			'triggerids' => array_keys($triggerids),
			'preservekeys' => true
		]);

		$triggers = [];

		foreach ($selements as $key => $selement) {
			if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_TRIGGER) {
				$selementid = $selement['selementid'];

				foreach ($selement['elements'] as $element) {
					$priority = $db_triggers[$element['triggerid']]['priority'];
					$triggers[$selementid][$priority][] = [
						'selementid' => $selementid,
						'triggerid' => $element['triggerid']
					];
				}
				krsort($triggers[$selementid]);
			}
		}

		$triggers_to_add = [];

		foreach ($triggers as $selement_triggers) {
			foreach ($selement_triggers as $selement_trigger_priorities) {
				foreach ($selement_trigger_priorities as $selement_trigger_priority) {
					$triggers_to_add[] = $selement_trigger_priority;
				}
			}
		}

		DB::update('sysmaps_elements', $update);

		if (!empty($urlsToDelete)) {
			DB::delete('sysmap_element_url', ['sysmapelementurlid' => $urlsToDelete]);
		}

		if (!empty($urlsToUpdate)) {
			DB::update('sysmap_element_url', $urlsToUpdate);
		}

		if (!empty($urlsToAdd)) {
			DB::insert('sysmap_element_url', $urlsToAdd);
		}

		if ($triggers_to_delete) {
			DB::delete('sysmap_element_trigger', ['selement_triggerid' => $triggers_to_delete]);
		}

		if ($triggers_to_add) {
			DB::insert('sysmap_element_trigger', $triggers_to_add);
		}

		return ['selementids' => $selementIds];
	}

	/**
	 * Delete element from map.
	 *
	 * @param array $selements							multidimensional array with selement objects
	 * @param array $selements[0, ...]['selementid']	selementid to delete
	 */
	protected function deleteSelements(array $selements) {
		$selements = zbx_toArray($selements);
		$selementIds = zbx_objectValues($selements, 'selementid');

		DB::delete('sysmaps_elements', ['selementid' => $selementIds]);

		return $selementIds;
	}

	/**
	 * Add shape to sysmap.
	 *
	 * @param array $shapes							Multidimensional array with shape properties.
	 */
	protected function createShapes(array $shapes) {
		$shapes = zbx_toArray($shapes);

		$this->checkShapeInput($shapes);

		DB::insert('sysmap_shape', $shapes);
	}

	/**
	 * Update shapes to sysmap.
	 *
	 * @param array $shapes							Multidimensional array with shape properties.
	 */
	protected function updateShapes(array $shapes) {
		$shapes = zbx_toArray($shapes);

		$this->checkShapeInput($shapes);

		$update = [];
		foreach ($shapes as $shape) {
			$shapeid = $shape['sysmap_shapeid'];
			unset($shape['sysmap_shapeid']);

			if ($shape) {
				$update[] = [
					'values' => $shape,
					'where' => ['sysmap_shapeid' => $shapeid]
				];
			}
		}

		DB::update('sysmap_shape', $update);
	}

	/**
	 * Delete shapes from map.
	 *
	 * @param array $shapes							Multidimensional array with shape properties.
	 */
	protected function deleteShapes(array $shapes) {
		$shapes = zbx_toArray($shapes);
		$shapeids = zbx_objectValues($shapes, 'sysmap_shapeid');

		DB::delete('sysmap_shape', ['sysmap_shapeid' => $shapeids]);
	}

	/**
	 * Create link.
	 *
	 * @param array $links
	 * @param array $links[0,...]['sysmapid']
	 * @param array $links[0,...]['selementid1']
	 * @param array $links[0,...]['selementid2']
	 * @param array $links[0,...]['drawtype']
	 * @param array $links[0,...]['color']
	 *
	 * @return array
	 */
	protected function createLinks(array $links) {
		$links = zbx_toArray($links);

		$this->checkLinkInput($links, __FUNCTION__);

		$linkIds = DB::insert('sysmaps_links', $links);

		return ['linkids' => $linkIds];
	}

	protected function updateLinks($links) {
		$links = zbx_toArray($links);

		$this->checkLinkInput($links, __FUNCTION__);

		$udpateLinks = [];
		foreach ($links as $link) {
			$udpateLinks[] = ['values' => $link, 'where' => ['linkid' => $link['linkid']]];
		}

		DB::update('sysmaps_links', $udpateLinks);

		return ['linkids' => zbx_objectValues($links, 'linkid')];
	}

	/**
	 * Delete Link from map.
	 *
	 * @param array $links						multidimensional array with link objects
	 * @param array $links[0, ...]['linkid']	link ID to delete
	 *
	 * @return array
	 */
	protected function deleteLinks($links) {
		zbx_value2array($links);
		$linkIds = zbx_objectValues($links, 'linkid');

		$this->checkLinkInput($links, __FUNCTION__);

		DB::delete('sysmaps_links', ['linkid' => $linkIds]);

		return ['linkids' => $linkIds];
	}

	/**
	 * Add link trigger to link (sysmap).
	 *
	 * @param array $links[0,...]['linkid']
	 * @param array $links[0,...]['triggerid']
	 * @param array $links[0,...]['drawtype']
	 * @param array $links[0,...]['color']
	 */
	protected function createLinkTriggers($linkTriggers) {
		$linkTriggers = zbx_toArray($linkTriggers);

		$this->validateCreateLinkTriggers($linkTriggers);

		$linkTriggerIds = DB::insert('sysmaps_link_triggers', $linkTriggers);

		return ['linktriggerids' => $linkTriggerIds];
	}

	protected function validateCreateLinkTriggers(array $linkTriggers) {
		$linkTriggerDbFields = [
			'linkid' => null,
			'triggerid' => null
		];

		$colorValidator = new CColorValidator();

		foreach ($linkTriggers as $linkTrigger) {
			if (!check_db_fields($linkTriggerDbFields, $linkTrigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if (isset($linkTrigger['color']) && !$colorValidator->validate($linkTrigger['color'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $colorValidator->getError());
			}
		}
	}

	protected function updateLinkTriggers($linkTriggers) {
		$linkTriggers = zbx_toArray($linkTriggers);
		$this->validateUpdateLinkTriggers($linkTriggers);

		$linkTriggerIds = zbx_objectValues($linkTriggers, 'linktriggerid');

		$updateLinkTriggers = [];
		foreach ($linkTriggers as $linkTrigger) {
			$updateLinkTriggers[] = [
				'values' => $linkTrigger,
				'where' => ['linktriggerid' => $linkTrigger['linktriggerid']]
			];
		}

		DB::update('sysmaps_link_triggers', $updateLinkTriggers);

		return ['linktriggerids' => $linkTriggerIds];
	}

	protected function validateUpdateLinkTriggers(array $linkTriggers) {
		$linkTriggerDbFields = ['linktriggerid' => null];

		$colorValidator = new CColorValidator();

		foreach ($linkTriggers as $linkTrigger) {
			if (!check_db_fields($linkTriggerDbFields, $linkTrigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}

			if (isset($linkTrigger['color']) && !$colorValidator->validate($linkTrigger['color'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, $colorValidator->getError());
			}
		}
	}

	protected function deleteLinkTriggers($linkTriggers) {
		$linkTriggers = zbx_toArray($linkTriggers);
		$this->validateDeleteLinkTriggers($linkTriggers);

		$linkTriggerIds = zbx_objectValues($linkTriggers, 'linktriggerid');

		DB::delete('sysmaps_link_triggers', ['linktriggerid' => $linkTriggerIds]);

		return ['linktriggerids' => $linkTriggerIds];
	}

	protected function validateDeleteLinkTriggers(array $linkTriggers) {
		$linktriggerDbFields = ['linktriggerid' => null];

		foreach ($linkTriggers as $linkTrigger) {
			if (!check_db_fields($linktriggerDbFields, $linkTrigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Incorrect arguments passed to function.'));
			}
		}
	}

	/**
	 * Create map element tags.
	 *
	 * @param array  $selements
	 * @param int    $selements[]['elementtype']
	 * @param array  $selements[]['tags']
	 * @param string $selements[]['tags'][]['tag']
	 * @param string $selements[]['tags'][]['value']
	 * @param string $selements[]['tags'][]['operator']
	 * @param array  $selementids
	 */
	protected function createSelementsTags(array $selements, array $selementids): void {
		$new_tags = [];
		foreach ($selements as $index => $selement) {
			if (!array_key_exists('tags', $selement)
					|| ($selement['elementtype'] != SYSMAP_ELEMENT_TYPE_HOST
						&& $selement['elementtype'] != SYSMAP_ELEMENT_TYPE_HOST_GROUP)) {
				continue;
			}

			foreach ($selement['tags'] as $tag_add) {
				$new_tags[] = ['selementid' => $selementids[$index]] + $tag_add;
			}
		}

		if ($new_tags) {
			DB::insert('sysmaps_element_tag', $new_tags);
		}
	}

	/**
	 * Update map element tags.
	 *
	 * @param array  $selements
	 * @param string $selements[]['selementid']
	 * @param int    $selements[]['elementtype']
	 * @param array  $selements[]['tags']
	 * @param string $selements[]['tags'][]['tag']
	 * @param string $selements[]['tags'][]['value']
	 * @param string $selements[]['tags'][]['operator']
	 */
	protected function updateElementsTags(array $selements): void {
		// Select tags from database.
		$db_tags = DBselect(
			'SELECT selementtagid,selementid,tag,value,operator'.
			' FROM sysmaps_element_tag'.
			' WHERE '.dbConditionInt('selementid', array_column($selements, 'selementid'))
		);

		array_walk($selements, function (&$selement) {
			$selement['db_tags'] = [];
		});

		while ($db_tag = DBfetch($db_tags)) {
			$selements[$db_tag['selementid']]['db_tags'][] = $db_tag;
		}

		// Find which tags must be added/deleted.
		$new_tags = [];
		$del_tagids = [];
		foreach ($selements as $selement) {
			if ($selement['elementtype'] != SYSMAP_ELEMENT_TYPE_HOST
					&& $selement['elementtype'] != SYSMAP_ELEMENT_TYPE_HOST_GROUP) {
				$del_tagids = array_merge($del_tagids, array_column($selement['db_tags'], 'selementtagid'));
				continue;
			}

			foreach ($selement['db_tags'] as $del_tag_key => $tag_delete) {
				foreach ($selement['tags'] as $new_tag_key => $tag_add) {
					if ($tag_delete['tag'] === $tag_add['tag'] && $tag_delete['value'] === $tag_add['value']
							&& $tag_delete['operator'] === $tag_add['operator']) {
						unset($selement['db_tags'][$del_tag_key], $selement['tags'][$new_tag_key]);
						continue 2;
					}
				}
			}

			$del_tagids = array_merge($del_tagids, array_column($selement['db_tags'], 'selementtagid'));

			foreach ($selement['tags'] as $tag_add) {
				$tag_add['selementid'] = $selement['selementid'];
				$new_tags[] = $tag_add;
			}
		}

		if ($del_tagids) {
			DB::delete('sysmaps_element_tag', ['selementtagid' => $del_tagids]);
		}
		if ($new_tags) {
			DB::insert('sysmaps_element_tag', $new_tags);
		}
	}
}
