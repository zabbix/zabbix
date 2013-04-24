// =================================================================
// =================================================================
// == TJH ==  ezSQL Debug Console version 0.2-alpha ===============================
// =================================================================
// =================================================================
// == TJH ==  To provide optional return value as opposed to simple echo 
// == TJH ==  of the $db->vardump  and   $db->debug  functions

// == TJH ==  Helpful for assigning the output to a var for handling in situations like template
// == TJH ==  engines where you want the debugging output rendered in a particular location.

// == TJH ==  This latest version 0.2 alpha includes a modification that allows
// == TJH ==  the original dump and debug behaviours to be maintained by default
// == TJH ==  and hopefully be backward compatible with previous ezSQL versions 

// == TJH ==  n.b.   set   $db->debug_all = true; 			// in your .php file
// == TJH ==          and   $db->debug_echo = false; 	  // in your .php file

// == TJH ==  USAGE:     $ezdump = print_r($db->vardump($result),true);		
// == TJH ==  USAGE:     $ezdebug = print_r($db->console,true);		
// =================================================================
// =================================================================
  
// =================================================================
// ===========   n.b. for TBS template engine users  ==============================
// === TJH ===  This is hacked to enable an ezSQL pop-up debug console	from a TBS template page	
// === TJH ===  The following steps need to be taken:				

// === TJH ===  	(1) Set $db->debug_all = true; // in your .php file
// === TJH ===           and $db->debug_echo = false; // in your .php file

// === TJH ===   	(2) Add the following javascript to top of your html	
/*
<ezdebugconsole>
	[onload_1;block=ezdebugconsole;when [var.db.debug_all]=1]
	<SCRIPT LANGUAGE="JavaScript">
	if(self.name == ''){var title = 'Console';}
	else{var title = 'Console_' + self.name;}
	newWindow = window.open("",title.value,"width=550,height=650,resizable,scrollbars=yes");
	newWindow.document.write("<HTML><HEAD><TITLE>ezSQL Debug [var..script_name;htmlconv=js]</TITLE></HEAD><BODY bgcolor=#e8e8e8>");
//	newWindow.document.write("<b>Debug for [var..script_name;htmlconv=js]</b><BR />");
	newWindow.document.write("<table border=0 width='100%'>");
	newWindow.document.write("[var.ezdebug;htmlconv=js]");
	newWindow.document.write("</body>\n</html>\n");
	</script>
</ezdebugconsole>
*/	

// === TJH ===  	(3) debug data is called with $db->console 		
// === TJH ===  		Use something like 					
// === TJH ===          $ezdebug = print_r($db->console,true);		
// === TJH ===  		to stuff the debug data into a PHP var		
// === TJH ===  													
// === TJH ===  n.b. Don't forget to slurp the slug of javascript 	
// === TJH ===       at the top of the .html template page			
// === TJH ===       you'll need to hack it if you're going to		
// === TJH ===       use it other than with TBS tempalte engine.	
// === TJH ===  													
// === TJH ===  Search this file for "TJH" comments to find changes	
// === TJH ===  You can contact TJH via http://tomhenry.us/			
// =================================================================
