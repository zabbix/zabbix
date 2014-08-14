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


abstract class CScreenLldGraphBase extends CScreenBase {

	/**
	 * Surrogate screen constructed to contain all graphs created from selected graph prototype.
	 *
	 * @var array
	 */
	protected $surrogateScreen;

	/**
	 * Returns output for LLD graph.
	 *
	 * @return CDiv
	 */
	public function get() {
		if ($this->mode == SCREEN_MODE_EDIT || $this->mustShowPreview()) {
			$output = $this->getOutput($this->getPreview());
		}
		else {
			$this->surrogateScreen = $this->makeSurrogateScreen();

			$this->addSurrogateScreenItems();
			$this->calculateSurrogateScreenSizes();

			$screenBuilder = $this->makeSurrogateScreenBuilder();

			$output = $this->getOutput($screenBuilder->show(), true);
		}

		return $output;
	}

	/**
	 * Makes new surrogate screen from data of this screen item.
	 *
	 * @return array
	 */
	protected function makeSurrogateScreen() {
		return array(
			'screenid' => $this->screenitem['screenitemid'].'_'.$this->screenitem['resourceid'],
			'dynamic' => $this->screenitem['dynamic'],
			'hsize' => 0,
			'vsize' => 0,
			'templateid' => 0,
			'screenitems' => array()
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
		$mode = ($this->mode == SCREEN_MODE_EDIT || $this->mode == SCREEN_MODE_SLIDESHOW)
			? SCREEN_MODE_SLIDESHOW
			: SCREEN_MODE_PREVIEW;

		return new CScreenBuilder(array(
			'isFlickerfree' => $this->isFlickerfree,
			'mode' => $mode,
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
			'screenid' => $this->surrogateScreen['screenid'],
			'resourcetype' => $resourceType,
			'rowspan' => $this->screenitem['rowspan'],
			'colspan' => $this->screenitem['colspan'],
			'height' => $this->screenitem['height'],
			'width' => $this->screenitem['width'],
			'dynamic' => $this->screenitem['dynamic'],
			'halign' => $this->screenitem['halign'],
			'valign' => $this->screenitem['valign']
		);
	}

	/**
	 * Returns host ID that for which simple graphs must be shown - either hosts derived from
	 * item prototype or, if this screen item has dynamic mode enabled, currently selected host ID.
	 *
	 * @return string
	 */
	protected function getCurrentHostId() {
		if ($this->screenitem['dynamic'] == SCREEN_DYNAMIC_ITEM && $this->hostid) {
			$hostId = $this->hostid;
		}
		else {
			$hostId = $this->getHostIdFromScreenItemResource();
		}

		return $hostId;
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
	 * Returns ID of host for which created graphs or items have to be selected.
	 *
	 * @abstract
	 *
	 * @return integer
	 */
	abstract protected function getHostIdFromScreenItemResource();

	/**
	 * Adds items to surrogate screen.
	 *
	 * @abstract
	 *
	 * @return void
	 */
	abstract protected function addSurrogateScreenItems();

	/**
	 * Returns whether a preview should be shown instead of surrogate screen with graphs.
	 *
	 * @abstract
	 *
	 * @return bool
	 */
	abstract protected function mustShowPreview();

	/**
	 * Returns content for preview.
	 *
	 * @abstract
	 *
	 * @return CImg
	 */
	abstract protected function getPreview();
}
