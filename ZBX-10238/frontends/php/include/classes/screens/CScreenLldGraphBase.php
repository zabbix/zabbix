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


/**
 * Base class for screen elements containing surrogate screen with graphs from sources generated from prototypes.
 */
abstract class CScreenLldGraphBase extends CScreenBase {

	/**
	 * Surrogate screen constructed to contain all graphs created from selected graph prototype.
	 *
	 * @var array
	 */
	protected $surrogateScreen;

	/**
	 * Screen of this screen item.
	 *
	 * @var array
	 */
	protected $screen = null;

	/**
	 * Returns output of screen element or null if there are no graphs/items for surrogate screen.
	 *
	 * @return CDiv|null
	 */
	public function get() {
		if ($this->mode == SCREEN_MODE_EDIT) {
			$output = $this->getOutput($this->getPreviewOutput());
		}
		else {
			$screenItems = $this->getSurrogateScreenItems();

			if ($screenItems) {
				$this->createSurrogateScreen($screenItems);

				$this->calculateSurrogateScreenSizes();

				$screenBuilder = $this->makeSurrogateScreenBuilder();

				$output = $this->getOutput($screenBuilder->show(), true);
			}
			else {
				$output = null;
			}

		}

		return $output;
	}

	/**
	 * Creates surrogate screen from data of this screen item.
	 *
	 * @param array $screenItems
	 */
	protected function createSurrogateScreen(array $screenItems = array()) {
		$screenId = $this->screenitem['screenitemid'].'_'.$this->screenitem['resourceid'];

		foreach ($screenItems as &$screenItem) {
			$screenItem['screenid'] = $screenId;
		}
		unset($screenItem);

		$this->surrogateScreen = array(
			'screenid' => $screenId,
			'dynamic' => $this->screenitem['dynamic'],
			'hsize' => 0,
			'vsize' => 0,
			'templateid' => 0,
			'screenitems' => $screenItems
		);
	}

	/**
	 * Calculates "hsize", "vsize" for surrogate screen and "x" and "y" for surrogate screen items.
	 */
	protected function calculateSurrogateScreenSizes() {
		$screenItemCount = count($this->surrogateScreen['screenitems']);
		$maxColumns = $this->screenitem['max_columns'];

		$this->surrogateScreen['hsize'] = ($screenItemCount >= $maxColumns) ? $maxColumns : $screenItemCount;
		$this->surrogateScreen['vsize'] = floor($screenItemCount / $maxColumns) + 1;

		foreach ($this->surrogateScreen['screenitems'] as $key => &$screenItem) {
			$screenItem['x'] = $key % $maxColumns;
			$screenItem['y'] = floor($key / $maxColumns);
		}
		unset($screenItem);
	}

	/**
	 * Returns screen builder used to generate output from surrogate screen.
	 *
	 * @return CScreenBuilder
	 */
	protected function makeSurrogateScreenBuilder() {
		return new CScreenBuilder(array(
			'isFlickerfree' => $this->isFlickerfree,
			'mode' => $this->mode,
			'timestamp' => $this->timestamp,
			'screen' => $this->surrogateScreen,
			'period' => $this->timeline['period'],
			'stime' => $this->timeline['stimeNow'],
			'profileIdx' => $this->profileIdx,
			'hostid' => $this->hostid,
			'updateProfile' => false
		));
	}

	/**
	 * Returns template for screen item with specified type.
	 *
	 * @param integer $resourceType    Resource type, one of SCREEN_RESOURCE_* constants.
	 *
	 * @return array
	 */
	protected function getScreenItemTemplate($resourceType) {
		return array(
			'resourcetype' => $resourceType,
			'rowspan' => 1,
			'colspan' => 1,
			'height' => $this->screenitem['height'],
			'width' => $this->screenitem['width'],
			'dynamic' => $this->screenitem['dynamic'],
			'halign' => $this->screenitem['halign'],
			'valign' => $this->screenitem['valign'],
			'url' => ''
		);
	}

	/**
	 * @param array $screenItems
	 */
	protected function addItemsToSurrogateScreen(array $screenItems) {
		foreach ($screenItems as $screenItem) {
			$this->surrogateScreen['screenitems'][] = $screenItem;
		}
	}

	/**
	 * Gathers and returns items for surrogate screen.
	 *
	 * @abstract
	 *
	 * @return array
	 */
	abstract protected function getSurrogateScreenItems();

	/**
	 * Returns content for preview of graph.
	 *
	 * @abstract
	 *
	 * @return CTag
	 */
	abstract protected function getPreviewOutput();
}
