<?php
/*
** ZABBIX
** Copyright (C) 2000-2006 SIA Zabbix
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
	function	calc_exp($fields,$field,$expression)
	{
		global $_REQUEST;

		if(strstr($expression,"{}"))
		{
			if(!isset($_REQUEST[$field]))	return FALSE;
		}
		$expression = str_replace("{}",'$_REQUEST["'.$field.'"]',$expression);
		foreach($fields as $f => $checks)
		{
			// If an unset variable used in expression, return FALSE
			if(strstr($expression,'{'.$f.'}')&&!isset($_REQUEST[$f]))
			{
//				info("Variable is not set. $expression is FALSE");
				return FALSE;
			}
//			echo $f,":",$expression,"<br>";
			$expression = str_replace('{'.$f.'}','$_REQUEST["'.$f.'"]',$expression);
		}
		$expression=rtrim($expression,"&");
		if($expression[strlen($expression)-1]=='&')	$expression[strlen($expression)-1]=0;
		if($expression[strlen($expression)-1]=='&')	$expression[strlen($expression)-1]=0;
		$exec = "return ".$expression.";";
//		info($exec);
		return eval($exec);
	}

	function	unset_all(&$fields)
	{
		foreach($_REQUEST as $key => $val)
		{
			if(!isset($fields[$key]))
			{
//				info("Unset:".$key);
				unset($_REQUEST[$key]);
			}
		}
	}

	function	check_fields(&$fields)
	{
		global	$_REQUEST;

		$ret = TRUE;

		foreach($fields as $field => $checks)
		{
			list($type,$opt,$flags,$validation,$exception)=$checks;

//			info("Field: $field");

			if($exception==NULL)	$except=FALSE;
			else			$except=calc_exp($fields,$field,$exception);

			if($opt == O_MAND &&	$except)	$opt = O_NO;
			else if($opt == O_OPT && $except)	$opt = O_MAND;
			else if($opt == O_NO && $except)	$opt = O_MAND;


			if($opt == O_MAND)
			{
				if(!isset($_REQUEST[$field]))
				{
					info("Field [".$field."] is mandatory");
					$ret = FALSE;
					continue;
				}
			}

			if($opt == O_NO)
			{
				if(isset($_REQUEST[$field]))
				{
					info("Field [".$field."] must be missing");
					$ret = FALSE;
					continue;
				}
				else continue;
			}

			if($opt == O_OPT)
			{
				if(!isset($_REQUEST[$field]))	continue;
			}


			if( ($type == T_ZBX_INT) && !is_numeric($_REQUEST[$field])) {
				info("Field [".$field."] is not integer");
				$ret = FALSE;
				continue;
			}

			if( ($type == T_ZBX_DBL) && !is_numeric($_REQUEST[$field])) {
				info("Field [".$field."] is not double");
				$ret = FALSE;
				continue;
			}

			if(($exception==NULL)||($except==TRUE))
			{
				if(!$validation)	$valid=TRUE;
				else			$valid=calc_exp($fields,$field,$validation);

				if(!$valid)
				{
					info("Incorrect value for [".$field."]");
					$ret = FALSE;
					continue;
				}
			}
		}
		unset_all($fields);
		return $ret;
	}
?>
