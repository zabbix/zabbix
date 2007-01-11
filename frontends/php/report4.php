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
        include "include/config.inc.php";
        $page["title"] = "S_IT_NOTIFICATIONS";
        $page["file"] = "report4.php";
        show_header($page["title"],0,0);
?>

<?php
	if(!check_right("User","R",0))
	{
	      show_table_header("<font color=\"AA0000\">No permissions !</font>");
	      show_page_footer();
	      exit;
	}
?>

<?php
        if(!isset($_REQUEST["year"]))
        {
                  $_REQUEST["year"]=2006;
 //               show_table_header("<font color=\"AA0000\">Undefined serviceid !</font>");
 //               show_page_footer();
 //               exit;
        }
?>

<?php
        if(!isset($_REQUEST["period"]))
        {
                $_REQUEST["period"]="weekly";
        }

        if(!isset($_REQUEST["media_type"]))
        {
                $_REQUEST["media_type"]="0";
        }

        $h1=S_NOTIFICATIONS_BIG;

#       $h2=S_GROUP.SPACE;
        $h2=S_YEAR.SPACE;
        $h2=$h2."<select class=\"biginput\" name=\"year\" onChange=\"submit()\">";
        $result=DBselect("select h.hostid,h.host from hosts h,items i where h.status=".HOST_STATUS_MONITORED." and h.hostid=i.hostid group by h.hostid,h.host order by h.host");

        $year=date("Y");
        for($year=date("Y")-2;$year<=date("Y");$year++)
        {
                $h2=$h2.form_select("year",$year,$year);
        }
        $h2=$h2."</select>";

        $h2=$h2.SPACE.S_PERIOD.SPACE;
        $h2=$h2."<select class=\"biginput\" name=\"period\" onChange=\"submit()\">";
        $h2=$h2.form_select("period","daily",S_DAILY);
        $h2=$h2.form_select("period","weekly",S_WEEKLY);
        $h2=$h2.form_select("period","monthly",S_MONTHLY);
        $h2=$h2.form_select("period","yearly",S_YEARLY);
        $h2=$h2."</select>";
        $h2=$h2.SPACE.S_MEDIA_TYPE.SPACE;
        $h2=$h2."<select class=\"biginput\" name=\"media_type\" onChange=\"submit()\">";
 //     $h2=$h2.form_select("media_type","0",S_ALL_SMALL);
        $result=DBselect("select * from media_type order by description");
        $type_count=0;
        while($row=DBfetch($result))
             {
               $type_count++;
               $descarray[$type_count]=$row["description"];
               $id=$row["mediatypeid"];
               $idarray[$type_count]=$id;
             }
        $descarray[0]="all";
        $i=-1;
        while($i<$type_count)
             {
               $i++;
               global $_REQUEST;
               $selected = "";
               if(!is_null("media_type"))
               {
                   if(isset($_REQUEST["media_type"])&&$_REQUEST["media_type"]==$i)
                            $selected = "selected";
               }
               $form_select1="<option value=$i $selected>$descarray[$i]";
               $h2=$h2.$form_select1;
//             $h2=$h2.form_select("media_type","$descarray[$i]",S_EMAIL);
            }
        $h2=$h2."</select>";
 

        show_header2($h1,$h2,"<form name=\"selection\" method=\"get\" action=\"report4.php\">", "</form>");
?>

<?php
        $year=date("Y");
        $table = new CTableInfo();
        if($_REQUEST["period"]=="yearly")
        {
                $header=array(new CCol(S_YEAR,"center"));
                $uindex=1;
                $result=DBselect("select * from users".
                        " order by alias");
                while($row=DBfetch($result))
                {
                        $header=array_merge($header,array(new CImg("vtext.php?text=".$row["alias"])));
                        $userarray[$uindex]=$row["userid"];
                        $uindex++;
                }
                $table->setHeader($header,"vertical_header");



                for($year=date("Y")-5;$year<=date("Y");$year++)
                {       
                        $start=mktime(0,0,0,1,1,$year);
                        $end=mktime(0,0,0,1,1,$year+1);
                        $table_row = array(nbsp($year));
                        $style = NULL;
                        $counter=1;
                        while ($counter<$uindex) 
                              {
                               $result=DBselect("select count(*) from alerts where userid='$userarray[$counter]' and clock>$start and clock<$end");
                               while($row=DBfetch($result))
                                    {
                                    $count_all=$row[0];
                                    }
                               $i=0;
                               while ($i<$type_count)
                                    {
                                       $i++;  
                                       $result=DBselect("select count(*) from alerts where userid='$userarray[$counter]' and clock>$start and clock<$end and mediatypeid=$idarray[$i]");
                                       while($row=DBfetch($result))
                                            {
                                               $count_by_type[$i]=$row[0];
                                            }
                                    }
                               if ($_REQUEST["media_type"]==0)
                                    {
                                      $total_count=$count_all;
                                      $total_count.=" (";
                                      $i=0;
                                      while ($i<$type_count)
                                            {
                                               $i++;
                                               if($i>1) { $total_count.="/"; }
                                               $total_count.=$count_by_type[$i];
                                            }
                                      $total_count.=")";
                                    }
                               $i=0;
                               while($i<=$type_count)
                                    {
                                       $i++;
                                       if ($_REQUEST["media_type"]==$i)
                                          $total_count=$count_by_type[$i];
                                    }
                               array_push($table_row,new CCol($total_count,$style));
                               $counter++;
                              }
                        $table->AddRow($table_row);
                }

        }
        else if($_REQUEST["period"]=="monthly")
                {
                $header=array(new CCol(SPACE.S_MONTH,"center"));
                $uindex=1;
                $result=DBselect("select * from users order by alias");
                while($row=DBfetch($result))
                {
                        $header=array_merge($header,array(new CImg("vtext.php?text=".$row["alias"])));
                        $userarray[$uindex]=$row["userid"];
                        $uindex++;
                }
                $table->setHeader($header,"vertical_header");

                for($month=1;$month<=12;$month++)
                {
                        $start=mktime(0,0,0,$month,1,$_REQUEST["year"]);
                        $end=mktime(0,0,0,$month+1,1,$_REQUEST["year"]);
                        if($start>time()) break;
                        $table_row = array(nbsp(date("M Y",$start)));
                        $style = NULL;
                        $counter=1;
                        while ($counter<$uindex)
                              {
                               $result=DBselect("select count(*) from alerts where userid='$userarray[$counter]' and clock>$start and clock<$end");
                               while($row=DBfetch($result))
                                    {
                                    $count_all=$row[0];
                                    }
                               $i=0;
                               while ($i<$type_count)
                                    {
                                       $i++;
                                       $result=DBselect("select count(*) from alerts where userid='$userarray[$counter]' and clock>$start and clock<$end and mediatypeid=$idarray[$i]");
                                       while($row=DBfetch($result))
                                            {
                                               $count_by_type[$i]=$row[0];
                                            }
                                    }
                               if ($_REQUEST["media_type"]==0)
                                    {
                                      $total_count=$count_all;
                                      $total_count.=" (";
                                      $i=0;
                                      while ($i<$type_count)
                                            {
                                               $i++;
                                               if($i>1) { $total_count.="/"; }
                                               $total_count.=$count_by_type[$i];
                                            }
                                      $total_count.=")";
                                    }
                               $i=0;
                               while($i<=$type_count)
                                    {
                                       $i++;
                                       if ($_REQUEST["media_type"]==$i)
                                          $total_count=$count_by_type[$i];
                                    }
                               array_push($table_row,new CCol($total_count,$style));
                               $counter++;
                              }

                        $table->AddRow($table_row);
                }
        }
        else if($_REQUEST["period"]=="daily")
        {
                $header=array(new CCol(SPACE.S_DAY,"center"));
                $uindex=1;
                $result=DBselect("select * from users order by alias");
                while($row=DBfetch($result))
                {
                        $header=array_merge($header,array(new CImg("vtext.php?text=".$row["alias"])));
                        $userarray[$uindex]=$row["userid"];
                        $uindex++;
                }
                $table->setHeader($header,"vertical_header");

                $s=mktime(0,0,0,1,1,$_REQUEST["year"]);
                $e=mktime(0,0,0,1,1,$_REQUEST["year"]+1);
                for($day=$s;$day<$e;$day+=24*3600)
                {
                        $start=$day;
                        $end=$day+24*3600;

                        if($start>time())       break;
                
                        $table_row = array(nbsp(date("d M Y",$start)));
                        $style = NULL;
                        $counter=1;
                        while ($counter<$uindex)
                              {
                               $result=DBselect("select count(*) from alerts where userid='$userarray[$counter]' and clock>$start and clock<$end");
                               while($row=DBfetch($result))
                                    {
                                    $count_all=$row[0];
                                    }
                               $i=0;
                               while ($i<$type_count)
                                    {
                                       $i++;
                                       $result=DBselect("select count(*) from alerts where userid='$userarray[$counter]' and clock>$start and clock<$end and mediatypeid=$idarray[$i]");
                                       while($row=DBfetch($result))
                                            {
                                               $count_by_type[$i]=$row[0];
                                            }
                                    }
                               if ($_REQUEST["media_type"]==0)
                                    {
                                      $total_count=$count_all;
                                      $total_count.=" (";
                                      $i=0;
                                      while ($i<$type_count)
                                            {
                                               $i++;
                                               if($i>1) { $total_count.="/"; }
                                               $total_count.=$count_by_type[$i];
                                            }
                                      $total_count.=")";
                                    }
                               $i=0;
                               while($i<=$type_count)
                                    {
                                       $i++;
                                       if ($_REQUEST["media_type"]==$i)
                                          $total_count=$count_by_type[$i];
                                    }
                               array_push($table_row,new CCol($total_count,$style));
                               $counter++;
                              }


                        $table->AddRow($table_row);
                }               
        }
        else
        {
        //-------Weekly-------------
                $year=date("Y");
                $header=array(new CCol(SPACE.S_FROM,"center"),new CCol(SPACE.S_TILL,"center"));
                $uindex=1;
                $result=DBselect("select * from users order by alias");
                while($row=DBfetch($result))
                {
                        $header=array_merge($header,array(new CImg("vtext.php?text=".$row["alias"])));
                        $userarray[$uindex]=$row["userid"];
                        $uindex++;
                }
                $table->setHeader($header,"vertical_header");
                for($year=date("Y")-2;$year<=date("Y");$year++)
        {
                if( isset($_REQUEST["year"]) && ($_REQUEST["year"] != $year) )
                {
                        continue;
                }
                $start=mktime(0,0,0,1,1,$year);

                $wday=date("w",$start);
                if($wday==0) $wday=7;
                $start=$start-($wday-1)*24*3600;
                $i=0;
                for($i=0;$i<53;$i++)
                {
                        $period_start=$start+7*24*3600*$i;
                        $period_end=$start+7*24*3600*($i+1);
                        if($period_start>time())
                        {
                                break;
                        } 
                        $from=date(S_DATE_FORMAT_YMD,$period_start);
                        $till=date(S_DATE_FORMAT_YMD,$period_end);
                        $table_row = array($from,$till);
                        $style = NULL;
                        $counter=1;
                        while ($counter<$uindex)
                              {
                               $result=DBselect("select count(*) from alerts where userid='$userarray[$counter]' and clock>$period_start and clock<$period_end");
                               while($row=DBfetch($result))
                                    {
                                    $count_all=$row[0];
                                    }
                               $k=0;
                               while ($k<$type_count)
                                    {
                                       $k++;
                                       $result=DBselect("select count(*) from alerts where userid='$userarray[$counter]' and clock>$period_start and clock<$period_end and mediatypeid=$idarray[$k]");
                                       while($row=DBfetch($result))
                                            {
                                               $count_by_type[$k]=$row[0];
                                            }
                                    }
                               if ($_REQUEST["media_type"]==0)
                                    {
                                      $total_count=$count_all;
                                      $total_count.=" (";
                                      $l=0;
                                      while ($l<$type_count)
                                            {
                                               $l++;
                                               if($l>1) { $total_count.="/"; }
                                               $total_count.=$count_by_type[$l];
                                            }
                                      $total_count.=")";
                                    }
                               $m=0;
                               while($m<=$type_count)
                                    {
                                       $m++;
                                       if ($_REQUEST["media_type"]==$m)
                                          $total_count=$count_by_type[$m];
                                    }
                               array_push($table_row,new CCol($total_count,$style));

                              $counter++;
                              }

                        $table->AddRow($table_row);
                 } }
        //--------Weekly-------------
        }
        $table->show();
        if ($_REQUEST["media_type"]=="0")
           {
             $style = "off";
             $table = new CTableInfo();
             $types="all (";
             $i=0;
             while($i<$type_count)
                  {
                     $i++;
                     if($i>1) {$types.="/";}
                     $types.=$descarray[$i];
                  }
             $types.=")";
             $table->AddRow(new CSpan(SPACE.SPACE.SPACE.SPACE.SPACE.SPACE.$types,$style));
             $table->Show();
           }
        show_page_footer();
?>

