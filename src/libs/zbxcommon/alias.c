/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
#include "log.h"

/* Static data */

static ALIAS *aliasList=NULL;


/*
 * Add alias to the list
 * Returns 1 on success or 0 if alias with that name already exists
*/
int	add_alias_from_config(char *line)
{
	char	*name = NULL,
		*value = NULL;

	name = line;
	value = strchr(line,':');
	if(NULL == value)
		return FAIL;

	*value = '\0';
	value++;

	return add_alias(name, value);
}

int	add_alias(const char *name, const char *value)
{
	ALIAS *alias = NULL;

	assert(name);
	assert(value);

	for(alias = aliasList; ; alias=alias->next)
	{
		/* Add new parameters */
		if ( NULL == alias )
		{
			alias = (ALIAS *)zbx_malloc(alias, sizeof(ALIAS));
			memset(alias,0,sizeof(ALIAS));

			zbx_strlcpy(alias->name, name, MAX_ALIAS_NAME-1);

			alias->value = strdup(value);

			alias->next=aliasList;

			aliasList=alias;

			zabbix_log( LOG_LEVEL_DEBUG, "Alias added. [%s] -> [%s]", name, value);
			return SUCCEED;
		}

		/* Replace existing parameters */
		if (strcmp(alias->name, name) == 0)
		{
			zbx_free(alias->value);

			memset(alias, 0, sizeof(ALIAS));

			zbx_strlcpy(alias->name, name, MAX_ALIAS_NAME-1);

			alias->value = strdup(value);

			alias->next = aliasList;

			aliasList = alias;

			zabbix_log( LOG_LEVEL_DEBUG, "Alias replaced. [%s] -> [%s]", name, value);
			return SUCCEED;
		}
	}
	zabbix_log( LOG_LEVEL_WARNING, "Alias FAILED. [%s] -> [%s]", name, value);
	return FAIL;
}

void	alias_list_free(void)
{
	ALIAS	*curr;
	ALIAS	*next;

	next = aliasList;
	while(next!=NULL)
	{
		curr = next;
		next = curr->next;
		zbx_free(curr->value);
		zbx_free(curr);
	}
	aliasList = NULL;
}

/*
 * Checks parameter and expands it if aliased
 */

void	alias_expand(const char *orig, char *expanded, int exp_buf_len)
{
	ALIAS *alias;

	for(alias = aliasList; alias!=NULL; alias = alias->next)
	{
		if (!strcmp(alias->name,orig))
		{
			zbx_strlcpy(expanded, alias->value, exp_buf_len);
			return;
		}
	}
	zbx_strlcpy(expanded, orig, exp_buf_len);
}
