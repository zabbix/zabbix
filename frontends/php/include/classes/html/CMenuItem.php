<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


class CMenuItem extends CTag {

	/**
	 * @var string
	 */
	private $action;

	/**
	 * @var array
	 */
	private $aliases = [];

	/**
	 * @var string
	 */
	private $icon_class;

	/**
	 * @var string
	 */
	private $label;

	/**
	 * @var CMenu
	 */
	private $sub_menu;

	/**
	 * @var string
	 */
	private $title;

	/**
	 * @var string
	 */
	private $target;

	/**
	 * @var CUrl
	 */
	private $url;

	/**
	 * @var bool;
	 */
	private $is_selected = false;

	/**
	 * Create menu item class instance.
	 *
	 * @param string $label  Menu item visual label.
	 */
	public function __construct(string $label) {
		parent::__construct('li', true);

		$this->label = $label;
	}

	/**
	 * Getter for action name property.
	 *
	 * @return string|null
	 */
	public function getAction(): ?string {
		return $this->action;
	}

	/**
	 * Set action name property and create CUrl object for the menu item link.
	 *
	 * @param string  Action name.
	 *
	 * @return CMenuItem
	 */
	public function setAction(string $action_name): self {
		return $this->setUrl((new CUrl('zabbix.php'))->setArgument('action', $action_name), $action_name);
	}

	/**
	 * Getter for aliases property.
	 *
	 * @return array
	 */
	public function getAliases(): array {
		return $this->aliases;
	}

	/**
	 * Register action aliases for the this menu item link.
	 *
	 * @param array $aliases
	 *
	 * @return CMenuItem
	 */
	public function setAliases(array $aliases): self {
		$this->aliases = $aliases;

		return $this;
	}

	/**
	 * Set icon CSS class for the this menu item link.
	 *
	 * @param string $icon_class
	 *
	 * @return CMenuItem
	 */
	public function setIcon(string $icon_class): self {
		$this->icon_class = $icon_class;

		return $this;
	}

	/**
	 * Getter for visual label property.
	 *
	 * @return string
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * Return TRUE if menu item marked as selected.
	 *
	 * @return bool
	 */
	public function isSelected(): bool {
		return $this->is_selected;
	}

	/**
	 * Set selected property.
	 *
	 * @param bool $is_selected
	 *
	 * @return CMenuItem
	 */
	public function setSelected(bool $is_selected): self {
		$this->is_selected = $is_selected;

		if ($is_selected) {
			$this->addClass('is-selected');
		}

		return $this;
	}

	/**
	 * Set selected property if this item according passed action name.
	 *
	 * @param string $action_name  Action name to be selected.
	 * @param bool $expand         Add class 'is-expanded' for the opening submenu if is selected.
	 *
	 * @return bool  Returns true, if menu item selected
	 */
	public function setSelectedByAction(string $action_name, bool $expand = true): bool {
		$is_selected = (($this->sub_menu !== null && $this->sub_menu->setSelectedByAction($action_name, $expand))
			|| $this->action === $action_name || in_array($action_name, $this->aliases));

		$this->setSelected($is_selected);

		return $is_selected;
	}

	/**
	 * Get submenu object or create new, if not exists.
	 *
	 * @return CMenu
	 */
	public function getSubMenu(): CMenu {
		if ($this->sub_menu === null) {
			$this->setSubMenu(new CMenu());
		}

		return $this->sub_menu;
	}

	/**
	 * Set submenu object.
	 *
	 * @param CMenu $sub_menu
	 *
	 * @return CMenuItem
	 */
	public function setSubMenu(CMenu $sub_menu): self {
		$this->sub_menu = $sub_menu->addClass('submenu');
		$this->addClass('has-submenu');

		return $this;
	}

	/**
	 * Check, if item has submenu.
	 *
	 * @return bool
	 */
	public function hasSubMenu(): bool {
		return ($this->sub_menu !== null);
	}

	/**
	 * Set attribute target for the menu item link.
	 *
	 * @param string $target
	 *
	 * @return CMenuItem
	 */
	public function setTarget(string $target): self {
		$this->target = $target;

		return $this;
	}

	/**
	 * Set attribute title for the menu item link.
	 *
	 * @param string $title
	 *
	 * @return CMenuItem
	 */
	public function setTitle($title): self {
		$this->title = $title;

		return $this;
	}

	/**
	 * Getter for url property.
	 *
	 * @return CUrl|null
	 */
	public function getUrl(): ?CUrl {
		return $this->url;
	}

	/**
	 * Set url for the menu item link.
	 *
	 * @param CUrl $url
	 * @param string|null $action_name  Action name to be matched by setSelected method.
	 *
	 * @return CMenuItem
	 */
	public function setUrl(CUrl $url, string $action_name = null): self {
		$this->url = $url;
		$this->action = $action_name;

		return $this;
	}

	public function toString($destroy = true)
	{
		if ($this->url !== null || $this->sub_menu !== null) {
			$this->addItem([
				(new CLink($this->label, $this->sub_menu !== null ? '#' : $this->url))
					->addClass($this->icon_class)
					->setTitle($this->title)
					->setTarget($this->target),
				$this->sub_menu
			]);
		}
		else {
			$this->addItem(
				(new CSpan($this->label))
					->addClass($this->icon_class)
					->setTitle($this->title)
			);
		}

		return parent::toString($destroy);
	}
}
