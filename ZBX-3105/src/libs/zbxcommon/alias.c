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
#include "log.h"

static ALIAS	*aliasList = NULL;

/******************************************************************************
 *                                                                            *
 * Function: add_aliases_from_config                                          *
 *                                                                            *
 * Purpose: initialize aliases from configuration                             *
 *                                                                            *
 * Parameters: lines - aliase entries from configuration file                 *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 * Comments: calls add_alias() for each entry                                 *
 *                                                                            *
 ******************************************************************************/
void	add_aliases_from_config(char **lines)
{
	char	*name, *value, **pline; /* a pointer to line */

	pline = lines;
	while (NULL != *pline)
	{
		name = *pline;
		value = strchr(name, ':');
		if (NULL == value)
		{
			zabbix_log(LOG_LEVEL_WARNING, "ignoring Alias \"%s\": not colon-separated", name);
			pline++;
			continue;
		}

		*value = '\0';
		value++;

		add_alias(name, value);

		pline++;
	}
}

int	add_alias(const char *name, const char *value)
{
	ALIAS	*alias = NULL;

	assert(name);
	assert(value);

	for (alias = aliasList; ; alias = alias->next)
	{
		/* add new parameters */
		if (NULL == alias)
		{
			alias = (ALIAS *)zbx_malloc(alias, sizeof(ALIAS));
			memset(alias, 0, sizeof(ALIAS));

			zbx_strlcpy(alias->name, name, MAX_ALIAS_NAME - 1);

			alias->value = strdup(value);
			alias->next = aliasList;
			aliasList = alias;

			zabbix_log(LOG_LEVEL_DEBUG, "alias added: [%s] -> [%s]", name, value);
			return SUCCEED;
		}

		/* replace existing parameters */
		if (0 == strcmp(alias->name, name))
		{
			zbx_free(alias->value);
			alias->value = strdup(value);

			zabbix_log(LOG_LEVEL_DEBUG, "alias replaced: [%s] -> [%s]", name, value);
			return SUCCEED;
		}
	}

	zabbix_log(LOG_LEVEL_WARNING, "alias handling FAILED: [%s] -> [%s]", name, value);

	return FAIL;
}

void	alias_list_free()
{
	ALIAS	*curr, *next;

	next = aliasList;

	while (NULL != next)
	{
		curr = next;
		next = curr->next;
		zbx_free(curr->value);
		zbx_free(curr);
	}

	aliasList = NULL;
}

void	alias_expand(const char *orig, char *expanded, int exp_buf_len)
{
	ALIAS	*alias;

	for (alias = aliasList; NULL != alias; alias = alias->next)
	{
		if (0 == strcmp(alias->name, orig))
		{
			zbx_strlcpy(expanded, alias->value, exp_buf_len);
			return;
		}
	}

	zbx_strlcpy(expanded, orig, exp_buf_len);
}
