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
	class	Ctable
	{
		var $rows=array();
		var $header=array();
		var $msg_empty;
		var $after_header_html;
		var $before_footer_html;
		
		function Ctable($msg_empty="...")
		{
			$this->msg_empty=$msg_empty;
			$this->after_header_html="";
			$this->before_footer_html="";
		}

		function setAfterHeader($html)
		{
			$this->after_header_html = $html;
		}

		function setBeforeFooter($html)
		{
			$this->before_footer_html = $html;
		}

		function addRow($row)
		{
			$this->rows = array_merge($this->rows, array($row));
		}

// Private
		function setHeader($header)
		{
			$this->header = $header;
		}

// Private
		function showHeader($class="tableinfo")
		{
			echo "<table class=\"$class\" border=0 width=\"100%\" bgcolor='#AAAAAA' cellspacing=1 cellpadding=3>";
			echo "\n";
			echo "<tr bgcolor='#CCCCCC'>";
			while(list($num,$element)=each($this->header))
			{
				echo "<td><b>".$element."</b></td>";
			}
			echo "</tr>";
			echo "\n";
		}

// Private
		function showFooter()
		{
			echo "</table>";
			echo "\n";
		}

// Private	
		function showRow($elements, $rownum)
		{
			if($rownum%2 == 1)	{ echo "<TR BGCOLOR=\"#DDDDDD\">"; }
			else			{ echo "<TR BGCOLOR=\"#EEEEEE\">"; }

			while(list($num,$element)=each($elements))
			{
				if(is_array($element)&&isset($element["hide"])&&($element["hide"]==1))	continue;
				if(is_array($element))
				{
					if(isset($element["class"]))
						echo "<td class=\"".$element["class"]."\">".$element["value"]."</td>";
					else
						echo "<td>".$element["value"]."</td>";
				}
				else
				{
					echo "<td>".$element."</td>";
				}
			}
			echo "</tr>";
			echo "\n";
		}

		function show()
		{
			$this->showHeader();
			echo $this->after_header_html."\n";
			while (list($num,$row) = each($this->rows))
			{
				$this->showRow($row,$num);
			}
			if(count($this->rows) == 0)
			{
				echo "<tr bgcolor=#eeeeee><td colspan=".count($this->header)." align=center>".$this->msg_empty."</td></tr>\n";
			}
			echo $this->before_footer_html."\n";
			$this->showFooter();
		}
	}
?>
