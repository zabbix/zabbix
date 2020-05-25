<?php declare(strict_types = 1);

namespace Modules\Example_F;

use Core\CModule,
	APP,
	CMenu;

class Module extends CModule {

	/**
	 * Initialize module.
	 */
	public function init(): void {

		/** @var CMenu $menu */
		$menu = APP::Component()->get('menu.main');

		$menu->remove(_('Reports'));

		$menu
			->find(_('Administration'))
			->getSubMenu()
			->remove(_('Scripts'));
	}
}
