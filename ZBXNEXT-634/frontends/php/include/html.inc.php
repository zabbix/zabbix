<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	function italic($str){
		if(is_array($str)){
			foreach($str as $key => $val)
				if(is_string($val)){
					$em = new CTag('em','yes');
					$em->addItem($val);
					$str[$key] = $em;
				}
		}
		else if(is_string($str)) {
			$em = new CTag('em','yes','');
			$em->addItem($str);
			$str = $em;
		}
	return $str;
	}

	function bold($str){
		if(is_array($str)){
			foreach($str as $key => $val)
				if(is_string($val)){
					$b = new CTag('strong','yes');
					$b->addItem($val);
					$str[$key] = $b;
				}
		}
		else{
			$b = new CTag('strong','yes','');
			$b->addItem($str);
			$str = $b;
		}
	return $str;
	}

	function make_decoration($haystack, $needle, $class=null){
		$result = $haystack;

		$pos = stripos($haystack,$needle);
		if($pos !== FALSE){
			$start = zbx_substring($haystack, 0, $pos);
//			$middle = substr($haystack, $pos, zbx_strlen($needle));
			$middle = $needle;
			$end = substr($haystack, $pos+zbx_strlen($needle));

			if(is_null($class)){
				$result = array($start, bold($middle), $end);
			}
			else{
				$result = array($start, new CSpan($middle, $class), $end);
			}
		}

	return $result;
	}

	function nbsp($str){
		return str_replace(" ",SPACE,$str);
	}

	function prepare_url(&$var, $varname=null){
		$result = '';

		if(is_array($var)){
			foreach($var as $id => $par)
				$result .= prepare_url($par,isset($varname) ? $varname."[".$id."]": $id);
		}
		else{
			$result = '&'.$varname.'='.urlencode($var);
		}

	return $result;
	}

	function url_param($parameter,$request=true,$name=null){
		$result = '';
		if(!is_array($parameter)){
			if(is_null($name)){
				if(!$request) fatal_error('not request variable require url name [url_param]');

				$name = $parameter;
			}
		}

		if($request){
			$var =& $_REQUEST[$parameter];
		}
		else{
			$var =& $parameter;
		}

		if(isset($var)){
			$result = prepare_url($var,$name);
		}

	return $result;
	}

	function BR(){
		return new CTag('br','no');
	}

	function create_hat($caption,$items,$addicons=null,$id=null,$state=null){
		if(is_null($id)){
			list($usec, $sec) = explode(' ',microtime());
			$id = 'hat_'.((int)($sec % 10)).((int)($usec * 1000));
		}

		$td_l = new CCol(SPACE);
		$td_l->setAttribute('width','100%');

		$icons_row = array($td_l);
		if(!is_null($addicons)){
			if(!is_array($addicons)) $addicons = array($addicons);
			foreach($addicons as $value) $icons_row[] = $value;
		}

		if(!is_null($state)){
			$icon = new CIcon(S_SHOW.'/'.S_HIDE, $state?'arrowup':'arrowdown', "change_hat_state(this,'".$id."');");
			$icon->setAttribute('id',$id.'_icon');
			$icons_row[] = $icon;
		}
		else{
			$state = true;
		}

		$icon_tab = new CTable();
		$icon_tab->setAttribute('width','100%');

		$icon_tab->addRow($icons_row);

		$table = new CTable();
		$table->setAttribute('width','100%');
		$table->setCellPadding(0);
		$table->setCellSpacing(0);
		$table->addRow(get_table_header($caption,$icon_tab));

		$div = new CDiv($items);
		$div->setAttribute('id',$id);
		if(!$state) $div->setAttribute('style','display: none;');

		$table->addRow($div);
	return $table;
	}


/* Function:
 *	hide_form_items()
 *
 * Desc:
 *	Searches items/objects for Form tags like "<input"/Form classes like CForm, and makes it empty
 *
 * Author:
 *	Aly
 */
	function hide_form_items(&$obj){
		if(is_array($obj)){
			foreach($obj as $id => $item){
				hide_form_items($obj[$id]);			// Attention recursion;
			}
		}
		else if(is_object($obj)){
			$formObjects = array('cform','ccheckbox','cselect','cbutton','csubmit','cbuttonqmessage','cbuttondelete','cbuttoncancel');
			if(is_object($obj) && str_in_array(zbx_strtolower(get_class($obj)), $formObjects)){
				$obj=SPACE;
			}

			if(isset($obj->items) && !empty($obj->items)){
				foreach($obj->items as $id => $item){
					hide_form_items($obj->items[$id]); 		// Recursion
				}
			}
		}
		else{
			foreach(array('<form','<input','<select') as $item){
				if(zbx_strpos($obj,$item) !== FALSE) $obj = SPACE;
			}
		}
	}

	function get_table_header($col1, $col2=SPACE){
		if(isset($_REQUEST['print'])){
			hide_form_items($col1);
			hide_form_items($col2);
//if empty header than do not show it
			if(($col1 == SPACE) && ($col2 == SPACE)) return new CJSscript('');
		}

		$td_l = new CCol(SPACE,'header_r');
		$td_l->setAttribute('width','100%');

		$right_row = array($td_l);

		if(!is_null($col2)){
			if(!is_array($col2)) $col2 = array($col2);

			foreach($col2 as $num => $r_item)
				$right_row[] = new CCol($r_item,'header_r');
		}

		$right_tab = new CTable(null,'nowrap');
		$right_tab->setAttribute('width','100%');

		$right_tab->addRow($right_row);

		$table = new CTable(NULL,'header');
//		$table->setAttribute('border',0);
		$table->setCellSpacing(0);
		$table->setCellPadding(1);

		$td_r = new CCol($right_tab,'header_r');
		$td_r->setAttribute('align','right');

		$table->addRow(array(new CCol($col1,'header_l'), $td_r));
	return $table;
	}

	function show_table_header($col1, $col2=SPACE){
		$table = get_table_header($col1, $col2);
		$table->Show();
	}

	function get_icon($name, $params=array()){

		switch($name){
			case 'favourite':
				if(infavorites($params['fav'], $params['elid'], $params['elname'])){
					$icon = new CIcon(
						S_REMOVE_FROM.' '.S_FAVOURITES,
						'iconminus',
						'rm4favorites("'.$params['elname'].'","'.$params['elid'].'", 0);'
					);
				}
				else{
					$icon = new CIcon(
						S_ADD_TO.' '.S_FAVOURITES,
						'iconplus',
						'add2favorites("'.$params['elname'].'","'.$params['elid'].'");'
					);
				}
				$icon->setAttribute('id','addrm_fav');
			break;
			case 'fullscreen':
				$url = new Curl();
				$url->setArgument('fullscreen', $params['fullscreen'] ? '0' : '1');
				$icon = new CIcon(
					$_REQUEST['fullscreen'] ? S_NORMAL.' '.S_VIEW : S_FULLSCREEN,
					'fullscreen',
					"document.location = '".$url->getUrl()."';"
				);
			break;
			case 'menu':
				$icon = new CIcon(S_MENU, 'iconmenu', 'create_page_menu(event, "'.$params['menu'].'");');
			break;
			case 'reset':
				$icon = new CIcon(S_RESET, 'iconreset', 'timeControl.objectReset("'.$params['id'].'");');
			break;
		}

		return $icon;
	}

/**
* returns Ctable object with host header
*
* {@source}
* @access public
* @static
* @version 1
*
* @param string $hostid
* @param array $elemnts [items, triggers, graphs, applications]
* @return object
*/
	function get_header_host_table($hostid, $current=null){
		$elements = array(
			'items' => 'items',
			'triggers' => 'triggers',
			'graphs' => 'graphs',
			'applications' => 'applications',
			'screens' => 'screens',
			'discoveries' => 'discoveries'
		);

		if(!is_null($current))
			unset($elements[$current]);

		$header_host_opt = array(
			'hostids' => $hostid,
			'output' => API_OUTPUT_EXTEND,
			'templated_hosts' => 1,
		);
		if(isset($elements['items'])) $header_host_opt['selectItems'] = API_OUTPUT_COUNT;
		if(isset($elements['triggers'])) $header_host_opt['select_triggers'] = API_OUTPUT_COUNT;
		if(isset($elements['graphs'])) $header_host_opt['select_graphs'] = API_OUTPUT_COUNT;
		if(isset($elements['applications'])) $header_host_opt['select_applications'] = API_OUTPUT_COUNT;
		if(isset($elements['screens'])) $header_host_opt['selectScreens'] = API_OUTPUT_COUNT;
		if(isset($elements['discoveries'])) $header_host_opt['selectDiscoveries'] = API_OUTPUT_COUNT;

		$header_hosts = CHost::get($header_host_opt);

		if(!$header_host = reset($header_hosts)){
			$header_host = array(
				'hostid' => 0,
				'host' => ($current == 'host') ? S_NEW_HOST : _('New template'),
				'status' => ($current == 'host') ? HOST_STATUS_NOT_MONITORED : HOST_STATUS_TEMPLATE,
				'available' => HOST_AVAILABLE_UNKNOWN,
				'screens' => 0,
				'items' => 0,
				'graphs' => 0,
				'triggers' => 0,
				'applications' => 0,
				'discoveries' => 0,
				'proxy_hostid' => 0
			);
		}


		$description = array();
		if($header_host['proxy_hostid']){
			$proxy = get_host_by_hostid($header_host['proxy_hostid']);
			$description[] = $proxy['host'].':';
		}
		$description[] = $header_host['host'];

		$list = new CList(null, 'objectlist');

		if($header_host['status'] == HOST_STATUS_TEMPLATE)
			$list->addItem(array('&laquo; ', new CLink(S_TEMPLATE_LIST, 'templates.php?templateid='.$header_host['hostid'].url_param('groupid'))));
		else
			$list->addItem(array('&laquo; ', new CLink(S_HOST_LIST, 'hosts.php?hostid='.$header_host['hostid'].url_param('groupid'))));


		$tbl_header_host = new CDiv(null, 'objectgroup ui-widget-content ui-corner-all');
		if($header_host['status'] == HOST_STATUS_TEMPLATE){
			$list->addItem(array(bold(S_TEMPLATE.': '), new CLink($description, 'templates.php?form=update&templateid='.$header_host['hostid'])));
		}
		else{
			switch($header_host['status']){
				case HOST_STATUS_MONITORED:
					$status = new CSpan(S_MONITORED, 'off');
					break;
				case HOST_STATUS_NOT_MONITORED:
					$status = new CSpan(S_NOT_MONITORED, 'on');
					break;
				default:
					$status = S_UNKNOWN;
			}

			if($header_host['available'] == HOST_AVAILABLE_TRUE)
				$available = new CSpan(S_AVAILABLE, 'off');
			else if($header_host['available'] == HOST_AVAILABLE_FALSE)
				$available = new CSpan(S_NOT_AVAILABLE, 'on');
			else if($header_host['available'] == HOST_AVAILABLE_UNKNOWN)
				$available = new CSpan(S_UNKNOWN, 'unknown');

			$list->addItem(array(bold(S_HOST.': '), new CLink($description, 'hosts.php?form=update&hostid='.$header_host['hostid'])));
			$list->addItem($status);
			$list->addItem(array(S_AVAILABILITY.': ', $available));
		}

		if(isset($elements['items'])){
			$list->addItem(array(new CLink(S_ITEMS, 'items.php?hostid='.$header_host['hostid']),' ('.$header_host['items'].')'));
		}
		if(isset($elements['triggers'])){
			$list->addItem(array(new CLink(S_TRIGGERS, 'triggers.php?hostid='.$header_host['hostid']),' ('.$header_host['triggers'].')'));
		}
		if(isset($elements['graphs'])){
			$list->addItem(array(new CLink(S_GRAPHS, 'graphs.php?hostid='.$header_host['hostid']),' ('.$header_host['graphs'].')'));
		}
		if(isset($elements['applications'])){
			$list->addItem(array(new CLink(S_APPLICATIONS, 'applications.php?hostid='.$header_host['hostid']),' ('.$header_host['applications'].')'));
		}
		if(isset($elements['discoveries'])){
			$list->addItem(array(new CLink(S_DISCOVERY, 'host_discovery.php?hostid='.$header_host['hostid']),' ('.$header_host['discoveries'].')'));
		}
		if($header_host['status'] == HOST_STATUS_TEMPLATE){
			if(isset($elements['screens'])){
				$list->addItem(array(new CLink(S_SCREENS, 'screenconf.php?templateid='.$header_host['hostid']), ' ('.$header_host['screens'].')'));
			}
		}

		$tbl_header_host->addItem($list);
		return $tbl_header_host;
	}


	function makeFormFooter($main, $other){
		$mainBttns = new CDiv();
		foreach($main as $bttn){
			$bttn->addClass('main');
			$bttn->useJQueryStyle();
			$mainBttns->addItem($bttn);
		}
		$space = new CDiv($mainBttns, 'dt right');

		$otherBttns = new CDiv($other);
		$otherBttns->useJQueryStyle();
		$buttons = new CDiv(array($otherBttns), 'dd');

		$footer = new CDiv(array($space, $buttons), 'footer');

		$footer = new CDiv($footer);
		$footer->addClass('min-width objectgroup ui-widget-content ui-corner-all');
		$footer->addStyle('padding: 2px;');

		return $footer;
	}
?>
