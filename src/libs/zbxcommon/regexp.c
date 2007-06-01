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

#include "common.h"

#if defined(_WINDOWS)
#	include "gnuregex.h"
#endif /* _WINDOWS */

char	*zbx_regexp_match(const char *string, const char *pattern, int *len)
{ 
	char	*c = NULL;

	regex_t	re;
	regmatch_t match;

	if(len) *len = 0;

	if( string && string[0] )
	{
		if ( 0 == regcomp(&re, pattern, REG_EXTENDED | /* REG_ICASE | */ REG_NEWLINE) )
		{
			if( 0 == regexec(&re, string, (size_t) 1, &match, 0) )
			{ /* Matched */
				c=(char *)string+match.rm_so;
				if(len) *len = match.rm_eo - match.rm_so;
			
			}

			regfree(&re);
		}
	}
	return	c;
}

