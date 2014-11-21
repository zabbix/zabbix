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


/**
 * Class containing methods for operations with map elements.
 *
 * @package API
 */
abstract class CMapElement extends CZBXAPI {

	protected function checkSelementInput(&$selements, $method) {
		$update = ($method == 'updateSelements');
		$delete = ($method == 'deleteSelements');

		// permissions
		if ($update || $delete) {
			$selementDbFields = array('selementid' => null);

			$dbSelements = $this->fetchSelementsByIds(zbx_objectValues($selements, 'selementid'));

			if ($update) {
				$selements = $this->extendFromObjects($selements, $dbSelements, array('elementtype', 'elementid'));
			}
		}
		else {
			$selementDbFields = array(
				'sysmapid' => null,
				'elementid' => null,
				'elementtype' => null,
				'iconid_off' => null,
				'urls' => array()
			);
		}

		foreach ($selements as &$selement) {
			if (!check_db_fields($selementDbFields, $selement)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for element.'));
			}

			if ($update || $delete) {
				if (!isset($dbSelements[$selement['selementid']])) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('No permissions to referred object or it does not exist!'));
				}

				$dbSelement = array_merge($dbSelements[$selement['selementid']], $selement);
			}
			else {
				$dbSelement = $selement;
			}

			if (isset($selement['iconid_off']) && $selement['iconid_off'] == 0) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('No icon for map element "%s".', $selement['label']));
			}

			if ($this->checkCircleSelementsLink($dbSelement['sysmapid'], $dbSelement['elementid'], $dbSelement['elementtype'])) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Circular link cannot be created for map element "%s".', $dbSelement['label']));
			}
		}
		unset($selement);

		// check permissions to used objects
		$this->checkSelementPermissions($selements);

		return ($update || $delete) ? $dbSelements : true;
	}

	/**
	 * Checks that the user has write permissions to objects used in the map elements.
	 *
	 * @throws APIException if the user has no permissions to at least one of the objects
	 *
	 * @param array $selements
	 */
	protected function checkSelementPermissions(array $selements) {
		if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
			return;
		}

		$hostIds = $groupIds = $triggerIds = $mapIds = array();
		foreach ($selements as $selement) {
			switch ($selement['elementtype']) {
				case SYSMAP_ELEMENT_TYPE_HOST:
					$hostIds[$selement['elementid']] = $selement['elementid'];
					break;
				case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
					$groupIds[$selement['elementid']] = $selement['elementid'];
					break;
				case SYSMAP_ELEMENT_TYPE_TRIGGER:
					$triggerIds[$selement['elementid']] = $selement['elementid'];
					break;
				case SYSMAP_ELEMENT_TYPE_MAP:
					$mapIds[$selement['elementid']] = $selement['elementid'];
					break;
			}
		}

		if (($hostIds && !API::Host()->isWritable($hostIds))
				|| ($groupIds && !API::HostGroup()->isWritable($groupIds))
				|| ($triggerIds && !API::Trigger()->isWritable($triggerIds))
				|| ($mapIds && !API::Map()->isWritable($mapIds))) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
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
		$selements = API::getApi()->select('sysmaps_elements', array(
			'output' => API_OUTPUT_EXTEND,
			'filter' => array('selementid' => $selementIds),
			'preservekeys' => true
		));

		if ($selements) {
			foreach ($selements as &$selement) {
				$selement['urls'] = array();
			}
			unset($selement);

			$selementUrls = API::getApi()->select('sysmap_element_url', array(
				'output' => API_OUTPUT_EXTEND,
				'filter' => array('selementid' => $selementIds)
			));
			foreach ($selementUrls as $selementUrl) {
				$selements[$selementUrl['selementid']]['urls'][] = $selementUrl;
			}
		}

		return $selements;
	}

	protected function checkLinkInput($links, $method) {
		$update = ($method == 'updateLink');
		$delete = ($method == 'deleteLink');

		// permissions
		if ($update || $delete) {
			$linkDbFields = array('linkid' => null);

			$dbLinks = API::getApi()->select('sysmap_element_url', array(
				'filter' => array('selementid' => zbx_objectValues($links, 'linkid')),
				'output' => array('linkid'),
				'preservekeys' => true
			));
		}
		else {
			$linkDbFields = array(
				'sysmapid' => null,
				'selementid1' => null,
				'selementid2' => null
			);
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
		}

		return true;
	}

	public function checkCircleSelementsLink($sysmapId, $elementId, $elementType) {
		if ($elementType != SYSMAP_ELEMENT_TYPE_MAP) {
			return false;
		}

		if (bccomp($sysmapId, $elementId) == 0) {
			return true;
		}

		$dbElements = DBselect(
			'SELECT se.elementid,se.elementtype'.
			' FROM sysmaps_elements se'.
			' WHERE se.sysmapid='.zbx_dbstr($elementId).
				' AND se.elementtype='.SYSMAP_ELEMENT_TYPE_MAP
		);
		while ($element = DBfetch($dbElements)) {
			if ($this->checkCircleSelementsLink($sysmapId, $element['elementid'], $element['elementtype'])) {
				return true;
			}
		}

		return false;
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

		$selementIds = DB::insert('sysmaps_elements', $selements);

		$insertUrls = array();

		foreach ($selementIds as $key => $selementId) {
			foreach ($selements[$key]['urls'] as $url) {
				$url['selementid'] = $selementId;

				$insertUrls[] = $url;
			}
		}

		DB::insert('sysmap_element_url', $insertUrls);

		return array('selementids' => $selementIds);
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
		$selementIds = array();

		$dbSelements = $this->checkSelementInput($selements, __FUNCTION__);

		$update = array();
		$urlsToDelete = $urlsToUpdate = $urlsToAdd = array();
		foreach ($selements as $selement) {
			$update[] = array(
				'values' => $selement,
				'where' => array('selementid' => $selement['selementid']),
			);
			$selementIds[] = $selement['selementid'];

			if (!isset($selement['urls'])) {
				continue;
			}

			$diffUrls = zbx_array_diff($selement['urls'], $dbSelements[$selement['selementid']]['urls'], 'name');

			// add
			foreach ($diffUrls['first'] as $newUrl) {
				$newUrl['selementid'] = $selement['selementid'];
				$urlsToAdd[] = $newUrl;
			}

			// update url
			foreach ($diffUrls['both'] as $updUrl) {
				$urlsToUpdate[] = array(
					'values' => $updUrl,
					'where' => array(
						'selementid' => $selement['selementid'],
						'name' => $updUrl['name']
					)
				);
			}

			// delete url
			$urlsToDelete = array_merge($urlsToDelete, zbx_objectValues($diffUrls['second'], 'sysmapelementurlid'));
		}

		DB::update('sysmaps_elements', $update);

		if (!empty($urlsToDelete)) {
			DB::delete('sysmap_element_url', array('sysmapelementurlid' => $urlsToDelete));
		}

		if (!empty($urlsToUpdate)) {
			DB::update('sysmap_element_url', $urlsToUpdate);
		}

		if (!empty($urlsToAdd)) {
			DB::insert('sysmap_element_url', $urlsToAdd);
		}

		return array('selementids' => $selementIds);
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

		$this->checkSelementInput($selements, __FUNCTION__);

		DB::delete('sysmaps_elements', array('selementid' => $selementIds));

		return $selementIds;
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

		return array('linkids' => $linkIds);
	}

	protected function updateLinks($links) {
		$links = zbx_toArray($links);

		$this->checkLinkInput($links, __FUNCTION__);

		$udpateLinks = array();
		foreach ($links as $link) {
			$udpateLinks[] = array('values' => $link, 'where' => array('linkid' => $link['linkid']));
		}

		DB::update('sysmaps_links', $udpateLinks);

		return array('linkids' => zbx_objectValues($links, 'linkid'));
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

		DB::delete('sysmaps_links', array('linkid' => $linkIds));

		return array('linkids' => $linkIds);
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

		return array('linktriggerids' => $linkTriggerIds);
	}

	protected function validateCreateLinkTriggers(array $linkTriggers) {
		$linkTriggerDbFields = array(
			'linkid' => null,
			'triggerid' => null
		);

		$colorValidator = new CColorValidator();

		foreach ($linkTriggers as $linkTrigger) {
			if (!check_db_fields($linkTriggerDbFields, $linkTrigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for linktrigger.'));
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

		$updateLinkTriggers = array();
		foreach ($linkTriggers as $linkTrigger) {
			$updateLinkTriggers[] = array(
				'values' => $linkTrigger,
				'where' => array('linktriggerid' => $linkTrigger['linktriggerid'])
			);
		}

		DB::update('sysmaps_link_triggers', $updateLinkTriggers);

		return array('linktriggerids' => $linkTriggerIds);
	}

	protected function validateUpdateLinkTriggers(array $linkTriggers) {
		$linkTriggerDbFields = array('linktriggerid' => null);

		$colorValidator = new CColorValidator();

		foreach ($linkTriggers as $linkTrigger) {
			if (!check_db_fields($linkTriggerDbFields, $linkTrigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for linktrigger update.'));
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

		DB::delete('sysmaps_link_triggers', array('linktriggerid' => $linkTriggerIds));

		return array('linktriggerids' => $linkTriggerIds);
	}

	protected function validateDeleteLinkTriggers(array $linkTriggers) {
		$linktriggerDbFields = array('linktriggerid' => null);

		foreach ($linkTriggers as $linkTrigger) {
			if (!check_db_fields($linktriggerDbFields, $linkTrigger)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Wrong fields for linktrigger delete.'));
			}
		}
	}
}
