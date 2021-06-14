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
		$menu = APP::Component()->get('menu.main');

		$menu->remove('Reports');

		$menu
			->find('Monitoring')
			->getSubMenu()
			->remove('Maps');
	}
}
