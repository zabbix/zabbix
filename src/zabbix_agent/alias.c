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
#include "alias.h"

//
// Static data
//

static ALIAS *aliasList=NULL;


//
// Add alias to the list
// Returns 1 on success or 0 if alias with that name already exist
//

int AddAlias(char *name,char *value)
{
	ALIAS *alias;
	int ret = 0;

	for(alias=aliasList; ; alias=alias->next)
	{
		/* Add new parameters */
		if(alias == NULL)
		{
			alias=(ALIAS *)malloc(sizeof(ALIAS));
			if (alias!=NULL)
			{
				memset(alias,0,sizeof(ALIAS));
				strncpy(alias->name, name, MAX_ALIAS_NAME-1);
				alias->value = (char *)malloc(strlen(value)+1);
				strcpy(alias->value,value);
				alias->next=aliasList;
				aliasList=alias;

				ret = 1;
			}
			break;
		}

		/* Replace existing parameters */
		if (strcmp(alias->name, name) == 0)
		{
			if(alias->value)
				free(alias->value);

			memset(alias, 0, sizeof(ALIAS));
			
			strncpy(alias->name, name, MAX_ALIAS_NAME-1);
			
			alias->value = (char *)malloc(strlen(value)+1);
			strcpy(alias->value, value);

			alias->next = aliasList;
			aliasList = alias;

			ret = 1;
			break;
		}
	}
	return ret;
}

void	FreeAliasList(void)
{
	ALIAS	*curr;
	ALIAS	*next;
		
	next = aliasList;
	while(next!=NULL)
	{
		curr = next;
		next = curr->next;
		free(curr->value);
		free(curr);
	}
}

//
// Checks parameter and expands it if aliased
//

void ExpandAlias(char *orig,char *expanded)
{
   ALIAS *alias;
   int ret = 1;

   for(alias=aliasList;alias!=NULL;alias=alias->next)
      if (!strcmp(alias->name,orig))
      {
         strcpy(expanded,alias->value);
		 ret = 0;
         break;
      }

	if(ret == 1)
	{
		strcpy(expanded,orig);
	}

}
