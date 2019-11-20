<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


class CComponentRegistry {
	protected $components = [];

	/**
	 * Register component.
	 *
	 * @param string $name        Component name.
	 * @param object $instance    Component class instance.
	 *
	 * @throws Exception when name is already registered.
	 */
	public function register($name, $instance) {
		if (array_key_exists($name, $this->components)) {
			throw new Exception(_s('Component %s already registered.', $name));
		}

		$this->components[$name] = $instance;
	}

	/**
	 * Return registered component class nstance.
	 *
	 * @return object
	 *
	 * @throws Exception when component with $name is not found.
	 */
	public function get($name) {
		if (!array_key_exists($name, $this->components)) {
			throw new Exception(_s('Component %s is not registered.', $name));
		}

		return $this->components[$name];
	}

	/**
	 * Magic method to allow short syntax:
	 *    App::component()->user->type
	 *    App::component()->{'menu.main'}->add(['New entry' => []])
	 *
	 * @param string $name        Component name.
	 * @param object $instance    Component class instance.
	 *
	 * @throws Exception when component with $name is not found.
	 */
	public function __get($name) {
		return $this->get($name);
	}

	/**
	 * Magic method to allow short syntax:
	 *    App::component()->user = new User
	 *    App::component()->{'menu.main'} = new CMenu
	 *
	 * @param string $name       Requested property name.
	 * @return object
	 *
	 * @throws Exception when name is already registered.
	 */
	public function __set($name, $instance) {
		$this->register($name, $instance);
	}
}
