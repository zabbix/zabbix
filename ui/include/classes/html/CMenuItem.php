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
	 * @var bool
	 */
	private $is_selected = false;

	/**
	 * Create menu item.
	 *
	 * @param string $label  Menu item visual label.
	 */
	public function __construct(string $label) {
		parent::__construct('li', true);

		$this->label = $label;
	}

	/**
	 * Get action name.
	 *
	 * @return string|null
	 */
	public function getAction(): ?string {
		return $this->action;
	}

	/**
	 * Set action name and derive a corresponding URL for menu item link.
	 *
	 * @param string $action_name  Action name.
	 *
	 * @return CMenuItem
	 */
	public function setAction(string $action_name): self {
		return $this->setUrl((new CUrl('zabbix.php'))->setArgument('action', $action_name), $action_name);
	}

	/**
	 * Get action name aliases.
	 *
	 * @return array
	 */
	public function getAliases(): array {
		return $this->aliases;
	}

	/**
	 * Set action name aliases.
	 *
	 * @param array $aliases  The aliases of menu item. Is able to specify the alias in following formats:
	 *                        - {action_name} - The alias is applicable to page with specified action name with any GET
	 *                          parameters in URL or without them;
	 *                        - {action_name}?{param}={value} - The alias is applicable to page with specified action
	 *                          when specified GET parameter exists in URL and have the same value;
	 *                        - {action_name}?{param}=* - The alias is applicable to page with specified action
	 *                          when specified GET parameter exists in URL and have any value;
	 *                        - {action_name}?!{param}={value} - The alias is applicable to page with specified action
	 *                          when specified GET parameter not exists in URL or have different value;
	 *                        - {action_name}?!{param}=* - The alias is applicable to page with specified action
	 *                          when specified GET parameter not exists in URL.
	 *
	 * @return CMenuItem
	 */
	public function setAliases(array $aliases): self {
		foreach ($aliases as $alias) {
			['path' => $action_name, 'query' => $query_string] = parse_url($alias) + ['query' => ''];
			parse_str($query_string, $query_params);
			$this->aliases[$action_name][] = $query_params;
		}

		return $this;
	}

	/**
	 * Set icon CSS class for menu item link.
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
	 * Get visual label of menu item.
	 *
	 * @return string
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * Check if menu item is marked as selected.
	 *
	 * @return bool
	 */
	public function isSelected(): bool {
		return $this->is_selected;
	}

	/**
	 * Mark menu item as selected.
	 *
	 * @return CMenuItem
	 */
	public function setSelected(): self {
		$this->is_selected = true;
		$this->addClass('is-selected');

		return $this;
	}

	/**
	 * Deep find menu item (including this one) by action name and mark the whole chain as selected.
	 *
	 * @param string $action_name     Action name to search for.
	 * @param array  $request_params  Parameters of current HTTP request to compare in search process.
	 * @param bool   $expand          Add 'is-expanded' class for selected submenus.
	 *
	 * @return bool  True, if menu item was selected.
	 */
	public function setSelectedByAction(string $action_name, array $request_params, bool $expand = false): bool {
		if (array_key_exists($action_name, $this->aliases)) {
			foreach ($this->aliases[$action_name] as $alias_params) {
				$has_mandatory_params = true;

				foreach ($alias_params as $name => $value) {
					if (!array_key_exists($name, $request_params)
							|| ($value !== '*' && $value !== $request_params[$name])) {
						$has_mandatory_params = false;
						break;
					}
				}

				if ($has_mandatory_params) {
					$this->setSelected();

					return true;
				}
			}
		}

		if ($this->sub_menu !== null && $this->sub_menu->setSelectedByAction($action_name, $request_params, $expand)) {
			$this->setSelected();

			return true;
		}

		return false;
	}

	/**
	 * Get submenu of menu item or create new one, if not exists.
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
	 * Set submenu for menu item.
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
	 * Check if menu item has submenu.
	 *
	 * @return bool
	 */
	public function hasSubMenu(): bool {
		return ($this->sub_menu !== null);
	}

	/**
	 * Set target attribute for the menu item link.
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
	 * Set title attribute for the menu item link.
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
	 * Get url of the menu item link.
	 *
	 * @return CUrl|null
	 */
	public function getUrl(): ?CUrl {
		return $this->url;
	}

	/**
	 * Set url for the menu item link.
	 *
	 * @param CUrl        $url
	 * @param string|null $action_name  Associate action name to be matched by setSelected method.
	 *
	 * @return CMenuItem
	 */
	public function setUrl(CUrl $url, string $action_name = null): self {
		$action = null;

		if ($action_name !== null) {
			$this->setAliases([$action_name]);
			['path' => $action] = parse_url($action_name);
		}

		$this->url = $url;
		$this->action = $action;

		return $this;
	}

	public function toString($destroy = true)
	{
		if ($this->url !== null || $this->sub_menu !== null) {
			$this->addItem([
				(new CLink($this->label, $this->sub_menu !== null ? '#' : $this->url->getUrl()))
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
