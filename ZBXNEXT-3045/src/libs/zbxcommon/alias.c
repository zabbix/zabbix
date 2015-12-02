/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "alias.h"
#include "sysinfo.h"
#include "log.h"

static ALIAS	*aliasList = NULL;

void	test_aliases()
{
	ALIAS	*alias;

	for (alias = aliasList; NULL != alias; alias = alias->next)
		test_parameter(alias->name);
}

void	add_alias(const char *name, const char *value)
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
			break;
		}

		/* treat duplicate Alias as error */
		if (0 == strcmp(alias->name, name))
		{
			zabbix_log(LOG_LEVEL_CRIT, "failed to add Alias \"%s\": duplicate name", name);
			exit(EXIT_FAILURE);
		}
	}
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

const char	*zbx_alias_get(const char *orig)
{
	ALIAS	*alias;

	for (alias = aliasList; NULL != alias; alias = alias->next)
	{
		if (0 == strcmp(alias->name, orig))
			return alias->value;
	}

	return orig;
}
