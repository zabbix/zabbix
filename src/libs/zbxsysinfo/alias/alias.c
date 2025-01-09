/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxsysinfo.h"
#include "alias.h"

#include "zbxstr.h"
#include "zbxexpr.h"

static ALIAS	*aliasList = NULL;

void	test_aliases(void)
{
	ALIAS	*alias;

	for (alias = aliasList; NULL != alias; alias = alias->next)
		zbx_test_parameter(alias->name);
}

void	zbx_add_alias(const char *name, const char *value)
{
	ALIAS	*alias = NULL;

	for (alias = aliasList; ; alias = alias->next)
	{
		/* add new Alias */
		if (NULL == alias)
		{
			alias = (ALIAS *)zbx_malloc(alias, sizeof(ALIAS));

			alias->name = strdup(name);
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

void	zbx_alias_list_free(void)
{
	ALIAS	*curr, *next;

	next = aliasList;

	while (NULL != next)
	{
		curr = next;
		next = curr->next;
		zbx_free(curr->value);
		zbx_free(curr->name);
		zbx_free(curr);
	}

	aliasList = NULL;
}

const char	*zbx_alias_get(const char *orig)
{
	ALIAS				*alias;
	size_t				len_name, len_value;
	static ZBX_THREAD_LOCAL char	*buffer = NULL;
	static ZBX_THREAD_LOCAL size_t	buffer_alloc = 0;
	size_t				buffer_offset = 0;
	const char			*p = orig;

	if (SUCCEED != zbx_parse_key(&p) || '\0' != *p)
		return orig;

	for (alias = aliasList; NULL != alias; alias = alias->next)
	{
		if (0 == strcmp(alias->name, orig))
			return alias->value;
	}

	for (alias = aliasList; NULL != alias; alias = alias->next)
	{
		len_name = strlen(alias->name);
		if (3 >= len_name || 0 != strcmp(alias->name + len_name - 3, "[*]"))
			continue;

		if (0 != strncmp(alias->name, orig, len_name - 2))
			continue;

		len_value = strlen(alias->value);
		if (3 >= len_value || 0 != strcmp(alias->value + len_value - 3, "[*]"))
			return alias->value;

		zbx_strncpy_alloc(&buffer, &buffer_alloc, &buffer_offset, alias->value, len_value - 3);
		zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, orig + len_name - 3);
		return buffer;
	}

	return orig;
}
