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
	$page["file"]="history.php";
	$page["menu.url"]="latest.php";

	include "include/config.inc.php";
	include "include/forms.inc.php";

/*** Prepare page header - start ***/
	if(is_array($_REQUEST["itemid"]))
	{

		$_REQUEST["itemid"] = array_unique($_REQUEST["itemid"]);

		if(isset($_REQUEST["remove_log"]) && isset($_REQUEST["cmbloglist"]))
		{
			foreach($_REQUEST["itemid"] as $id => $itemid)
				if($itemid == $_REQUEST["cmbloglist"])
					unset($_REQUEST["itemid"][$id]);
		}

		$items_count = count($_REQUEST["itemid"]);
		if($items_count > 1)
		{
			$main_header = count($_REQUEST["itemid"])." log files";
		}
		else
			$_REQUEST["itemid"] = array_pop($_REQUEST["itemid"]);

		$do_not_refresh=1;
	}

	if(!is_array($_REQUEST["itemid"]))
	{
		$result=DBselect("select h.host,i.hostid,i.description,i.key_,i.value_type".
			" from items i,hosts h where i.itemid=".$_REQUEST["itemid"]." and h.hostid=i.hostid");

		$row=DBfetch($result);
		$item_host = $row["host"];
		$item_hostid = $row["hostid"];
		$item_description = item_description($row["description"],$row["key_"]);

		$main_header = $item_host.": ".$item_description;
		$do_not_refresh = $row["value_type"] == ITEM_VALUE_TYPE_LOG?1:null;
	}

	if(isset($do_not_refresh) || isset($_REQUEST["plaintext"]))
	{
		$auto_update = 0;
	}
	else
	{
		$auto_update = 1;
	}
/*** Prepare page header - end ***/

	show_header($main_header,$auto_update,isset($_REQUEST["plaintext"]) ? 1 : 0);

	if(!is_array($_REQUEST["itemid"] && $_REQUEST["action"]=="showgraph"))
		$_REQUEST["period"] = get_request("period",get_profile("web.item[".$_REQUEST["itemid"]."].graph.period", 3600));

	$effectiveperiod=navigation_bar_calc();

	if(!is_array($_REQUEST["itemid"] && $_REQUEST["action"]=="showgraph") && $_REQUEST["period"] >= 3600)
		update_profile("web.item[".$_REQUEST["itemid"]."].graph.period",$_REQUEST["period"]);


	if(!isset($_REQUEST["plaintext"]))
		insert_confirm_javascript();
	else
		echo $main_header.BR;

	if(is_array($_REQUEST["itemid"]))
	{
		foreach($_REQUEST["itemid"] as $itemid)
		{
			$item = get_item_by_itemid($itemid);
			if($item["value_type"] != ITEM_VALUE_TYPE_LOG)
			{
				error("Incorrect URL");
				if(!isset($_REQUEST["plaintext"]))
					show_page_footer();
				exit;
			}
		}
	}
	unset($item);

	if(is_array($_REQUEST["itemid"]))
	{
		$item_type = ITEM_VALUE_TYPE_LOG;
		foreach($_REQUEST["itemid"] as $id => $itemid)
		{
			if(!check_right("Item","R",$itemid))
				unset($_REQUEST["itemid"][$id]);
		}
		if(count($_REQUEST["itemid"])==0)
		{
			show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
			if(!isset($_REQUEST["plaintext"]))
				show_page_footer();
			exit;
		}
	}
	else
	{
		if(!check_right("Item","R",$_REQUEST["itemid"]))
		{
			show_table_header("<font color=\"AA0000\">".S_NO_PERMISSIONS."</font>");
			if(!isset($_REQUEST["plaintext"]))
				show_page_footer();
			exit;
		}
		$item=get_item_by_itemid($_REQUEST["itemid"]);
		$item_type = $item["value_type"];
	}
?>
<?php
	if(!isset($_REQUEST["plaintext"]))
	{
		$to_save_request = NULL;

		if($item_type == ITEM_VALUE_TYPE_LOG)
		{
			$l_header = new CForm();
			$l_header->SetName("loglist");
			$l_header->AddVar("action",$_REQUEST["action"]);
			$l_header->AddVar("from",$_REQUEST["from"]);
			$l_header->AddVar("period",$_REQUEST["period"]);
			$l_header->AddVar("itemid",$_REQUEST["itemid"]);

			if(isset($_REQUEST["filter_task"]))	$l_header->AddVar("filter_task",$_REQUEST["filter_task"]);
			if(isset($_REQUEST["filter"]))		$l_header->AddVar("filter",$_REQUEST["filter"]);
			if(isset($_REQUEST["mark_color"]))	$l_header->AddVar("mark_color",$_REQUEST["mark_color"]);

			$cmbLogList = new CComboBox("cmbloglist");
			if(is_array($_REQUEST["itemid"]))
			{
				$cmbLogList->AddItem(0, $main_header);
				foreach($_REQUEST["itemid"] as $itemid)
				{
					$item = get_item_by_itemid($itemid);
					$host = get_host_by_hostid($item["hostid"]);
					$cmbLogList->AddItem($itemid,$host["host"].": ".item_description($item["description"],$item["key_"]));
				}
			}
			else
			{
				$cmbLogList->AddItem($_REQUEST["itemid"], $main_header);
			}

			$l_header->AddItem(array(
				"Log files list",SPACE,
				$cmbLogList,SPACE,
				new CButton("add_log","Add","return PopUp('popup.php?".
					"dstfrm=".$l_header->GetName()."&srctbl=logitems','new_win',".
					"'width=600,height=450,resizable=1,scrollbars=1');"),SPACE,
				$cmbLogList->ItemsCount() > 1 ? new CButton("remove_log","Remove selected") : NULL
				));
		}
		else
		{
			$l_header = array(new CLink($item_host,"latest.php?hostid=".$item_hostid),": ",$item_description);
		}

		$form = new CForm();
		$form->AddVar("itemid",$_REQUEST["itemid"]);

		if($_REQUEST["action"]!="showlatest")
		{
			$form->AddVar("from",$_REQUEST["from"]);
			$form->AddVar("period",$_REQUEST["period"]);
			$form->AddVar("itemid",$_REQUEST["itemid"]);
		}

		if(isset($_REQUEST["filter_task"]))	$form->AddVar("filter_task",$_REQUEST["filter_task"]);
		if(isset($_REQUEST["filter"]))		$form->AddVar("filter",$_REQUEST["filter"]);
		if(isset($_REQUEST["mark_color"]))	$form->AddVar("mark_color",$_REQUEST["mark_color"]);
		
		$cmbAction = new CComboBox("action",$_REQUEST["action"],"submit()");

		if(in_array($item_type,array(ITEM_VALUE_TYPE_FLOAT,ITEM_VALUE_TYPE_UINT64)))
		{
			$cmbAction->AddItem("showgraph",S_GRAPH_OF_SPECIFIED_PERIOD);
		}

		$cmbAction->AddItem("showvalues",S_VALUES_OF_SPECIFIED_PERIOD);
		$cmbAction->AddItem("showlatest",S_500_LATEST_VALUES);

		$form->AddItem($cmbAction);

		if($_REQUEST["action"]!="showgraph") 
			$form->AddItem(array(SPACE,new CButton("plaintext",S_AS_PLAIN_TEXT)));

		show_header2($l_header, $form);
	}
?>
<?php
/*
	if($_REQUEST["action"]=="showfreehist")
	{
		echo BR;
		insert_freehist_form($_REQUEST["itemid"],$effectiveperiod);
	}
	elseif($_REQUEST["action"]=="showplaintxt")
	{
		echo BR;
		insert_plaintxt_form($_REQUEST["itemid"],$effectiveperiod);
   
	}
*/
	if($_REQUEST["action"]=="showgraph" && $item_type != ITEM_VALUE_TYPE_LOG)
	{
		show_history($_REQUEST["itemid"],$_REQUEST["from"],$effectiveperiod);
	}
	elseif($_REQUEST["action"]=="showvalues" || $_REQUEST["action"]=="showlatest")
	{
		if($_REQUEST["action"]=="showvalues")
		{		
			$time = time(NULL) - $effectiveperiod - $_REQUEST["from"] * 3600;
			$till = time(NULL) - $_REQUEST["from"] * 3600;
			$hours=$effectiveperiod / 3600;

			$l_header = "Showing history of ".$effectiveperiod." seconds($hours h)".BR.
				"[from: ".date("Y.M.d H:i:s",$time)."] [till: ".date("Y.M.d H:i:s",$till)."]";
		}
		else
		{
			$l_header = NULL;
		}

		if(!isset($_REQUEST["plaintext"]))
		{
			if($item_type==ITEM_VALUE_TYPE_LOG)
			{
				$to_save_request = array("filter_task", "filter", "mark_color");

				$filter_task = get_request("filter_task",0);
				$filter = get_request("filter","");
				$mark_color = get_request("mark_color",0);

				$r_header = new CForm();
				$r_header->AddVar("action",$_REQUEST["action"]);
				$r_header->AddVar("from",$_REQUEST["from"]);
				$r_header->AddVar("period",$_REQUEST["period"]);
				$r_header->AddVar("itemid",$_REQUEST["itemid"]);

				$cmbFTask = new CComboBox("filter_task",$filter_task,"submit()");
				$cmbFTask->AddItem(FILTER_TAST_SHOW,S_SHOW_SELECTED);
				$cmbFTask->AddItem(FILTER_TAST_HIDE,S_HIDE_SELECTED);
				$cmbFTask->AddItem(FILTER_TAST_MARK,S_MARK_SELECTED);
				$cmbFTask->AddItem(FILTER_TAST_INVERT_MARK,S_MARK_OTHERS);

				$r_header->AddItem(array(
					"Select rows with value like",SPACE,
					new CTextBox("filter",$filter,25),
					$cmbFTask,SPACE));

				if(in_array($filter_task,array(FILTER_TAST_MARK,FILTER_TAST_INVERT_MARK)))
				{
					$cmbColor = new CComboBox("mark_color",$mark_color);
					$cmbColor->AddItem(MARK_COLOR_RED,S_AS_RED);
					$cmbColor->AddItem(MARK_COLOR_GREEN,S_AS_GREEN);
					$cmbColor->AddItem(MARK_COLOR_BLUE,S_AS_BLUE);
					$r_header->AddItem(array($cmbColor,SPACE));
				}
				$r_header->AddItem(new CButton("select","Select"));
			}
			else
			{
				$r_header = NULL;
			}

				if($l_header || $r_header)
					show_table_header($l_header,$r_header);
		}
		else
		{
			echo $l_header."\n";
		}

		$cond_clock = "";
		$limit = "NO";
		if($_REQUEST["action"]=="showlatest"){
			$limit = 500;
		} elseif($_REQUEST["action"]=="showvalues"){
			$cond_clock = " and h.clock>$time and h.clock<$till";
		}

		if($item_type==ITEM_VALUE_TYPE_LOG)
		{
			$itemid_lst = "";
			if(is_array($_REQUEST["itemid"]))
			{
				foreach($_REQUEST["itemid"] as $itemid)
					$itemid_lst .= $itemid.",";

				$itemid_lst = trim($itemid_lst,",");
				$item_cout = count($_REQUEST["itemid"]);
			}
			else
			{
				$itemid_lst = $_REQUEST["itemid"];
				$item_cout = 1;
			}

			$sql_filter = "";
			if(isset($_REQUEST["filter"]) && $_REQUEST["filter"]!="")
			{
				if($_REQUEST["filter_task"] == FILTER_TAST_SHOW)
					$sql_filter = " and h.value like ".zbx_dbstr("%".$_REQUEST["filter"]."%")."";
				elseif($_REQUEST["filter_task"] == FILTER_TAST_HIDE)
					$sql_filter = " and h.value not like ".zbx_dbstr("%".$_REQUEST["filter"]."%")."";
			}


			$sql = "select hst.host,i.itemid,i.key_,i.description,h.clock,h.value,i.valuemapid,h.timestamp,h.source,h.severity".
				" from history_log h, items i, hosts hst".
				" where hst.hostid=i.hostid and h.itemid=i.itemid".$sql_filter." and i.itemid in (".$itemid_lst.")".$cond_clock.
				" order by h.clock desc, h.id desc";

			$result=DBselect($sql,$limit);

			if(!isset($_REQUEST["plaintext"]))
			{
				$table = new CTableInfo('...','log_history_table');
				$table->SetHeader(array(S_TIMESTAMP,
					$item_cout > 1 ? S_ITEM : NULL,
					S_LOCAL_TIME,S_SOURCE,S_SEVERITY,S_VALUE),"header");

				$table->ShowStart(); // to solve memory leak we call 'Show' method by steps
			}
			else
			{
				echo "<PRE>\n";
			}

			while($row=DBfetch($result))
			{
				$color_style = NULL;

				if(isset($_REQUEST["filter"]) && $_REQUEST["filter"]!="")
				{
					$contain = stristr($row["value"],$_REQUEST["filter"]) ? TRUE : FALSE;

					if(!isset($_REQUEST["mark_color"])) $_REQUEST["mark_color"] = MARK_COLOR_RED;

					if(($contain) && ($_REQUEST["filter_task"] == FILTER_TAST_MARK))
						$color_style = $_REQUEST["mark_color"];
					if((!$contain) && ($_REQUEST["filter_task"] == FILTER_TAST_INVERT_MARK))
						$color_style = $_REQUEST["mark_color"];

					switch($color_style)
					{
						case MARK_COLOR_RED:	$color_style="mark_as_red"; break;
						case MARK_COLOR_GREEN:	$color_style="mark_as_green"; break;
						case MARK_COLOR_BLUE:	$color_style="mark_as_blue"; break;
					}
				}

//				if(is_null($color_style) && is_array($_REQUEST["itemid"]))
//					$color_style = "item_".(array_search($row["itemid"],$_REQUEST["itemid"])%6);

				$new_row = array(nbsp(date("[Y.M.d H:i:s]",$row["clock"])));

				if($item_cout > 1)
					array_push($new_row,$row["host"].":".item_description($row["description"],$row["key_"]));

				if($row["timestamp"] == 0)
				{
					array_push($new_row,new CCol("-","center"));
				}
				else
				{
					array_push($new_row,date("Y.M.d H:i:s",$row["timestamp"]));
				}
				
				if($row["source"] == "")
				{
					array_push($new_row,new CCol("-","center"));
				}
				else
				{
					array_push($new_row,$row["source"]);
				}

				$severity = $row["severity"];

				if($severity==0)         $severity = S_NOT_CLASSIFIED;
				elseif($severity==1)     $severity = new CCol(S_INFORMATION,"information");
				elseif($severity==2)     $severity = new CCol(S_WARNING,"warrning");
				elseif($severity==3)     $severity = new CCol(S_AVERAGE,"average");
				elseif($severity==4)     $severity = new CCol(S_HIGH,"high");
				elseif($severity==5)     $severity = new CCol(S_DISASTER,"disaster");
				elseif($severity==6)     $severity = S_AUDIT_SUCCESS;
				elseif($severity==7)     $severity = S_AUDIT_FAILURE;
				else                     $severity = $row["priority"];

				array_push($new_row,$severity);
				$row["value"] = trim($row["value"],"\r\n");
				array_push($new_row,htmlspecialchars($row["value"]));

				if(!isset($_REQUEST["plaintext"]))
				{

					$crow = new CRow($new_row); 

					if(is_null($color_style) && is_array($_REQUEST["itemid"]))
					{
						$min_color = 0x98;
						$max_color = 0xF8;
						$int_color = ($max_color - $min_color) / count($_REQUEST["itemid"]);
						$int_color *= array_search($row["itemid"],$_REQUEST["itemid"]);
						$int_color += $min_color;
						$crow->AddOption("style","background-color: ".sprintf("#%X%X%X",$int_color,$int_color,$int_color));
					} else {
						$crow->SetClass($color_style);
					}

					$table->ShowRow($crow);	// to solve memory leak we call 'Show' method for each element
				}
				else
				{
					echo date("Y-m-d H:i:s",$row["clock"]);
					echo "\t".$row["clock"]."\t".$row["value"]."\n";
				}
			}
			if(!isset($_REQUEST["plaintext"]))
				$table->ShowEnd();	// to solve memory leak we call 'Show' method by steps
			else
				echo "</PRE>";
		}
		else
		{
			switch($item_type)
			{
				case ITEM_VALUE_TYPE_FLOAT:	$h_table = "history";		break;
				case ITEM_VALUE_TYPE_UINT64:	$h_table = "history_uint";	break;
				case ITEM_VALUE_TYPE_TEXT:	$h_table = "history_text";	break;
				default:			$h_table = "history_str";
			}

			$sql = "select h.clock,h.value,i.valuemapid from $h_table h, items i".
				" where h.itemid=i.itemid and i.itemid=".$_REQUEST["itemid"].
				$cond_clock." order by clock desc";
			$result=DBselect($sql, $limit);
			if(!isset($_REQUEST["plaintext"]))
			{
				$table = new CTableInfo();
				$table->SetHeader(array(S_TIMESTAMP, S_VALUE));

				$table->ShowStart(); // to solve memory leak we call 'Show' method by steps
			}
			else
			{
				echo "<PRE>\n";
			}

COpt::profiling_start("history");
			while($row=DBfetch($result))
			{
				
				if($DB_TYPE == "ORACLE" && $item_type == ITEM_VALUE_TYPE_TEXT)
				{
					if(isset($row["value"]))
						$row["value"] = $row["value"]->load();
					else
						$row["value"] = "";
				}

				if($row["valuemapid"] > 0)
					$value = replace_value_by_map($row["value"], $row["valuemapid"]);
				else
					$value = $row["value"]; 

				$new_row = array(date("Y.M.d H:i:s",$row["clock"]));
				if(in_array($item_type,array(ITEM_VALUE_TYPE_FLOAT,ITEM_VALUE_TYPE_UINT64)))
				{
					array_push($new_row,$value);
				}
				else
				{
					array_push($new_row,array("<pre>",htmlspecialchars($value),"</pre>"));
				}
				if(!isset($_REQUEST["plaintext"]))
				{
					$table->ShowRow($new_row);
				}
				else
				{
					echo date("Y-m-d H:i:s",$row["clock"]);
					echo "\t".$row["clock"]."\t".$row["value"]."\n";
				}
			}
			if(!isset($_REQUEST["plaintext"]))
				$table->ShowEnd();	// to solve memory leak we call 'Show' method by steps
			else
				echo "</PRE>";
COpt::profiling_stop("history");
		}
	}

	if(!isset($_REQUEST["plaintext"]))
	{
		if(in_array($_REQUEST["action"],array("showvalues","showgraph")))
		{
			navigation_bar("history.php",$to_save_request);
		}

		show_page_footer();
	}
?>
