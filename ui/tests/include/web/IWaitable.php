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

/**
 * Interface describing basic set of methods for waitable objects.
 */
interface IWaitable {

	/**
	 * Condition describing state when object is ready.
	 *
	 * @return callable
	 */
	public function getReadyCondition();

	/**
	 * Condition describing state when object is present.
	 *
	 * @return callable
	 */
	public function getPresentCondition();

	/**
	 * Condition describing state when object is not present.
	 *
	 * @return callable
	 */
	public function getNotPresentCondition();

	/**
	 * Condition describing state when object is visible.
	 *
	 * @return callable
	 */
	public function getVisibleCondition();

	/**
	 * Condition describing state when object is not visible.
	 *
	 * @return callable
	 */
	public function getNotVisibleCondition();

	/**
	 * Condition describing state when object is clickable.
	 *
	 * @return callable
	 */
	public function getClickableCondition();

	/**
	 * Condition describing state when object is not clickable.
	 *
	 * @return callable
	 */
	public function getNotClickableCondition();

	/**
	 * Condition describing state when text is present within the object.
	 *
	 * @param string $text    text to be present
	 *
	 * @return callable
	 */
	public function getTextPresentCondition($text);

	/**
	 * Condition describing state when text is not present within the object.
	 *
	 * @param string $text    text to not be present
	 *
	 * @return callable
	 */
	public function getTextNotPresentCondition($text);

	/**
	 * Condition describing state when attribute is present within the object.
	 *
	 * @param array $attributes    attributes to be present
	 *
	 * @return callable
	 */
	public function getAttributesPresentCondition($attributes);

	/**
	 * Condition describing state when text is not present within the object.
	 *
	 * @param array $attributes    attributes to not be present
	 *
	 * @return callable
	 */
	public function getAttributesNotPresentCondition($attributes);

	/**
	 * Condition describing state when object is selected.
	 *
	 * @return callable
	 */
	public function getSelectedCondition();

	/**
	 * Condition describing state when object is not selected.
	 *
	 * @return callable
	 */
	public function getNotSelectedCondition();
}
