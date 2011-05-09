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

int	add_alias(const char *name, const char *value)
{
	ALIAS	*alias = NULL;

	assert(name);
	assert(value);

	for (alias = aliasList; ; alias = alias->next)
	{
		/* add new Alias */
		if (NULL == alias)
		{
			alias = (ALIAS *)zbx_malloc(alias, sizeof(ALIAS));
			memset(alias, 0, sizeof(ALIAS));

			zbx_strlcpy(alias->name, name, MAX_ALIAS_NAME - 1);

			alias->value = strdup(value);
			alias->next = aliasList;
			aliasList = alias;

			zabbix_log(LOG_LEVEL_DEBUG, "Alias added: \"%s\" -> \"%s\"", name, value);
			return SUCCEED;
		}

		/* treat duplicate Alias as error */
		if (0 == strcmp(alias->name, name))
		{
			zabbix_log(LOG_LEVEL_CRIT, "failed to add Alias \"%s\": duplicate name", name);
			exit(FAIL);
		}
	}

	zabbix_log(LOG_LEVEL_WARNING, "Alias handling FAILED: \"%s\" -> \"%s\"", name, value);

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
