<?php

(new CWidget())
	->addItem(
		(new CTag('h1', true, 'You should not see this message'))
	)->show();
