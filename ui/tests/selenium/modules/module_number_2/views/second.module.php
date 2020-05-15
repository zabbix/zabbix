<?php

(new CWidget())
	->addItem(
		(new CTag('h1', true, '2nd module is also working'))
			->addStyle('font-size: 64px;text-align: center;color: green;padding: 150px 0;')
	)->show();
