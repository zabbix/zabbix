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
	require_once("include/classes/ctag.inc.php");

	class CComboItem extends CTag
	{
/* public */
		function CComboItem($value,$caption=NULL,$selected=NULL, $enabled=NULL)
		{
			parent::CTag('option','yes');
			$this->tag_body_start = "";
			$this->options['value'] = $value;

			$this->AddItem($caption);

			$this->SetSelected($selected);
			$this->SetEnabled($enabled);

		}
		function SetValue($value)
		{
			return $this->options['value'] = $value;
		}
		function GetValue()
		{
			return $this->GetOption('value');
		}
		function SetCaption($value=NULL)
		{
			$this->AddItem(nbsp($value));
		}
		function SetSelected($value='yes')
		{
			if((is_string($value) && ($value == 'yes' || $value == 'selected' || $value=='on'))
				|| (is_int($value) && $value<>0))
				return $this->options['selected'] = 'selected';

			$this->DelOption('selected');
		}
	}

	class CComboBox extends CTag
	{
/* private */
		//var $value;

/* public */
		function CComboBox($name='combobox',$value=NULL,$action=NULL)
		{
			parent::CTag('select','yes');
			$this->tag_end = '';

			$this->options['id'] = $name;
			$this->options['name'] = $name;

			$this->options['class'] = 'biginput';
			$this->options['size'] = 1;
			
			$this->value = $value;
			$this->SetAction($action);
		}
		function SetAction($value='submit()', $event='onchange')
		{
			$this->AddOption($event,$value);
		}
		function SetValue($value=NULL)
		{
			$this->value = $value;
		}
		function AddItem($value, $caption='', $selected=NULL, $enabled='yes')
		{
//			if($enabled=='no') return;	/* disable item method 1 */
			if(strtolower(get_class($value))=='ccomboitem') {
				parent::AddItem($value);
			}
			else{
				if(is_null($selected)){
					$selected = 'no';
					if(is_array($this->value)) {
						if(str_in_array($value,$this->value))
							$selected = 'yes';
					}
					else if(strcmp($value,$this->value) == 0)
					{
						$selected = 'yes';
					}
				}
	
				parent::AddItem(new CComboItem($value,$caption,$selected,$enabled));
			}
		}
	}

	class CListBox extends CComboBox{
/* public */
		function CListBox($name='listbox',$value=NULL,$size=5,$action=NULL){
			parent::CComboBox($name,NULL,$action);
			$this->options['multiple'] = 'multiple';
			$this->options['size'] = $size;
			$this->SetValue($value);
		}
		
		function SetSize($value)
		{
			$this->options['size'] = $value;
		}
	}

	class CEditableComboBox extends CComboBox{
		function CEditableComboBox($name='editablecombobox',$value=NULL,$size=0,$action=NULL){
			inseret_javascript_for_editable_combobox();

			parent::CComboBox($name,$value,$action);
			parent::AddAction('onfocus','CEditableComboBoxInit(this);');
			parent::AddAction('onchange','CEditableComboBoxOnChange(this,'.$size.');');
		}

		function AddItem($value, $caption='', $selected=NULL, $enabled='yes'){
			if(is_null($selected)){
				if(is_array($this->value)) {
					if(str_in_array($value,$this->value))
						$this->value_exist = 1;
				}
				else if(strcmp($value,$this->value) == 0){
					$this->value_exist = 1;
				}
			}

			parent::AddItem($value,$caption,$selected,$enabled);
		}

		function ToString($destroy=true){
			if(!isset($this->value_exist) && !empty($this->value)){
				$this->AddItem($this->value, $this->value, 'yes');
			}
			return parent::ToString($destroy);
		}
	}
	
	class CTweenBox{
		function ctweenbox(&$form,$name,$value=null,$size=10){
			$this->form = &$form;
			$this->name = $name.'_tweenbox';
			$this->varname = $name;
			
			$this->value = $value;
			
			$this->id_l = $this->varname.'_left';
			$this->id_r = $this->varname.'_right';
			
			$this->lbox = new ClistBox($this->id_l,null,$size);
			$this->rbox = new ClistBox($this->id_r,null,$size);

			$this->lbox->AddOption('style','width: 280px;');
			$this->rbox->AddOption('style','width: 280px;');
		}
		
		function SetName($name=null){
			if(is_string($name)) $this->name = $name;
		}

		function GetName($name=null){
			return $this->name;
		}
		
		function AddItem($value, $caption, $selected=null, $enabled='yes'){
			if(is_null($selected)){
				if(is_array($this->value)) {
					if(str_in_array($value,$this->value))
						$selected = 1;
				}
				else if(strcmp($value,$this->value) == 0){
					$selected = 1;
				}
			}

			if((is_int($selected) && $selected!=0) || (is_string($selected) && ($selected == 'yes' || $selected == 'selected' || $selected=='on'))){
				$this->lbox->AddItem($value,$caption,null,$enabled);
				$this->form->AddVar($this->varname.'['.$value.']',$value);
			}
			else{
				$this->rbox->AddItem($value,$caption,null,$enabled);
			}
		}
		
		function SetAction($expr, $event='onchange', $value='submit()'){
//			$box = &$this->lbox;
			if($expr){
				$this->lbox->AddOption($event,$value);
			}
			else{
				$this->rbox->AddOption($event,$value);
			}
		}
		
		function Get($caption_l=S_IN,$caption_r=S_OTHER){
			$grp_tab = new CTable();
			$grp_tab->AddOption('name',$this->name);
			$grp_tab->AddOption('id',$this->name);

			$grp_tab->SetCellSpacing(0);
			$grp_tab->SetCellPadding(0);

			if(!is_null($caption_l) || !is_null($caption_r)){
				$grp_tab->AddRow(array($caption_l,SPACE,$caption_r));
			}

			$add_btn = new CButton('add',' « ');//S_ADD);//
			$add_btn->SetType('button');
			$add_btn->SetAction('javascript: moveListBoxSelectedItem("'.$this->form->GetName().'","'.$this->varname.'","'.$this->id_r.'","'.$this->id_l.'","add");');
			
			$rmv_btn = new CButton('remove',' » ');//S_REMOVE);//
			$rmv_btn->SetType('button');
			$rmv_btn->SetAction('javascript: moveListBoxSelectedItem("'.$this->form->GetName().'","'.$this->varname.'","'.$this->id_l.'","'.$this->id_r.'","rmv");');

			$grp_tab->AddRow(array($this->lbox,new CCol(array($add_btn,BR(),$rmv_btn),'top'),$this->rbox));
			
			
		return $grp_tab;
		}
		
		function Show($caption_l=S_IN,$caption_r=S_OTHER){
			$tab = $this->Get($caption_l,$caption_r);
			$tab->Show();
		}
		
		function toString(){
			$tab = $this->Get();
			return $tab->toString();
		}
	}
?>