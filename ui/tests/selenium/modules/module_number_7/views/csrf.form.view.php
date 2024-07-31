<?php

$form = (new CForm())
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get($data['action'])))->removeId())
	->addVar('action', $data['action'])
	->setAttribute('aria-labelledby', CHtmlPage::PAGE_TITLE_ID);

$grid = new CFormGrid();

$grid->addItem([
	new CLabel('Text'),
	new CFormField(
		(new CInput('text', 'text', $data['text']))
	)
]);

$form->addItem((new CTabView())
	->addTab('default', null, $grid)
	->setFooter(makeFormFooter(
		(new CSubmit('preview', 'Submit'))->setAttribute('value', 1)
	))
);

(new CHtmlPage())
	->setTitle('CSRF token test')
	->addItem($form)
	->show();
