/* 
** ZabbixW32 - Win32 agent for Zabbix
** Copyright (C) 2002 Victor Kirhenshtein
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
**
** $module: alias.cpp
**
**/

#include "zabbixw32.h"


//
// Static data
//

static ALIAS *aliasList=NULL;


//
// Add alias to the list
// Returns TRUE on success or FALSE if alias with that name already exist
//

BOOL AddAlias(char *name,char *value)
{
   ALIAS *alias;

   // Find alias in the list
   for(alias=aliasList;alias!=NULL;alias=alias->next)
      if (!strcmp(alias->name,name))
         return FALSE;

   // Create new structure and add it to the list
   alias=(ALIAS *)malloc(sizeof(ALIAS));
   if (alias==NULL)
      return FALSE;
   memset(alias,0,sizeof(ALIAS));
   strncpy(alias->name,name,MAX_ALIAS_NAME-1);
   alias->value=(char *)malloc(strlen(value)+1);
   strcpy(alias->value,value);
   alias->next=aliasList;
   aliasList=alias;

   return TRUE;
}


//
// Checks parameter and expands it if aliased
//

void ExpandAlias(char *orig,char *expanded)
{
   ALIAS *alias;

   for(alias=aliasList;alias!=NULL;alias=alias->next)
      if (!strcmp(alias->name,orig))
      {
         strcpy(expanded,alias->value);
         return;
      }

   strcpy(expanded,orig);
}
