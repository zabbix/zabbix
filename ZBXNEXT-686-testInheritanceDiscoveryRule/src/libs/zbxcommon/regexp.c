/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"

#if defined(_WINDOWS)
#	include "gnuregex.h"
#endif /* _WINDOWS */

static char	*zbx_regexp(const char *string, const char *pattern, int *len, int flags)
{
	char		*c = NULL;
	regex_t		re;
	regmatch_t	match;

	if (NULL != len)
		*len = 0;

	if (NULL != string)
	{
		if (0 == regcomp(&re, pattern, flags))
		{
			if (0 == regexec(&re, string, (size_t)1, &match, 0)) /* matched */
			{
				c = (char *)string + match.rm_so;

				if (NULL != len)
					*len = match.rm_eo - match.rm_so;
			}

			regfree(&re);
		}
	}

	return c;
}

char	*zbx_regexp_match(const char *string, const char *pattern, int *len)
{
	return zbx_regexp(string, pattern, len, REG_EXTENDED | REG_NEWLINE);
}

char	*zbx_iregexp_match(const char *string, const char *pattern, int *len)
{
	return zbx_regexp(string, pattern, len, REG_EXTENDED | REG_ICASE | REG_NEWLINE);
}

void	clean_regexps_ex(ZBX_REGEXP *regexps, int *regexps_num)
{
	int	i;

	for (i = 0; i < *regexps_num; i++)
	{
		zbx_free(regexps[i].name);
		zbx_free(regexps[i].expression);
	}

	*regexps_num = 0;
}

void	add_regexp_ex(ZBX_REGEXP **regexps, int *regexps_alloc, int *regexps_num,
		const char *name, const char *expression, int expression_type, char exp_delimiter, int case_sensitive)
{
	if (*regexps_alloc == *regexps_num)
	{
		*regexps_alloc += 16;
		if (NULL == *regexps)
			*regexps = zbx_malloc(*regexps, *regexps_alloc * sizeof(ZBX_REGEXP));
		else
			*regexps = zbx_realloc(*regexps, *regexps_alloc * sizeof(ZBX_REGEXP));
	}

	(*regexps)[*regexps_num].name = strdup(name);
	(*regexps)[*regexps_num].expression = strdup(expression);
	(*regexps)[*regexps_num].expression_type = expression_type;
	(*regexps)[*regexps_num].exp_delimiter = exp_delimiter;
	(*regexps)[*regexps_num].case_sensitive = case_sensitive;

	(*regexps_num)++;
}

int	regexp_match_ex(ZBX_REGEXP *regexps, int regexps_num, const char *string, const char *pattern,
		zbx_case_sensitive_t cs)
{
	int	i, res = FAIL;
	char	*s, *c = NULL;

	if (NULL == pattern || '\0' == *pattern)
		return SUCCEED;

	if ('@' != *pattern)
	{
		switch (cs)
		{
			case ZBX_CASE_SENSITIVE:
				if (NULL != zbx_regexp_match(string, pattern, NULL))
					res = SUCCEED;
				break;
			case ZBX_IGNORE_CASE:
				if (NULL != zbx_iregexp_match(string, pattern, NULL))
					res = SUCCEED;
				break;
		}
		return res;
	}

	pattern++;

	for (i = 0; i < regexps_num; i++)
	{
		if (0 != strcmp(regexps[i].name, pattern))
			continue;

		res = FAIL;

		switch (regexps[i].expression_type)
		{
			case EXPRESSION_TYPE_INCLUDED:
				switch (regexps[i].case_sensitive)
				{
					case ZBX_CASE_SENSITIVE:
						if (NULL != strstr(string, regexps[i].expression))
							res = SUCCEED;
						break;
					case ZBX_IGNORE_CASE:
						if (NULL != zbx_strcasestr(string, regexps[i].expression))
							res = SUCCEED;
						break;
				}
				break;
			case EXPRESSION_TYPE_ANY_INCLUDED:
				for (s = regexps[i].expression; '\0' != *s && SUCCEED != res;)
				{
					if (NULL != (c = strchr(s, regexps[i].exp_delimiter)))
						*c = '\0';

					switch (regexps[i].case_sensitive)
					{
						case ZBX_CASE_SENSITIVE:
							if (NULL != strstr(string, s))
								res = SUCCEED;
							break;
						case ZBX_IGNORE_CASE:
							if (NULL != zbx_strcasestr(string, s))
								res = SUCCEED;
							break;
					}

					if (NULL != c)
					{
						*c = regexps[i].exp_delimiter;
						s = ++c;
						c = NULL;
					}
					else
						break;
				}

				if (NULL != c)
					*c = regexps[i].exp_delimiter;
				break;
			case EXPRESSION_TYPE_NOT_INCLUDED:
				switch (regexps[i].case_sensitive)
				{
					case ZBX_CASE_SENSITIVE:
						if (NULL == strstr(string, regexps[i].expression))
							res = SUCCEED;
						break;
					case ZBX_IGNORE_CASE:
						if (NULL == zbx_strcasestr(string, regexps[i].expression))
							res = SUCCEED;
						break;
				}
				break;
			case EXPRESSION_TYPE_TRUE:
				switch (regexps[i].case_sensitive)
				{
					case ZBX_CASE_SENSITIVE:
						if (NULL != zbx_regexp_match(string, regexps[i].expression, NULL))
							res = SUCCEED;
						break;
					case ZBX_IGNORE_CASE:
						if (NULL != zbx_iregexp_match(string, regexps[i].expression, NULL))
							res = SUCCEED;
						break;
				}
				break;
			case EXPRESSION_TYPE_FALSE:
				switch (regexps[i].case_sensitive)
				{
					case ZBX_CASE_SENSITIVE:
						if (NULL == zbx_regexp_match(string, regexps[i].expression, NULL))
							res = SUCCEED;
						break;
					case ZBX_IGNORE_CASE:
						if (NULL == zbx_iregexp_match(string, regexps[i].expression, NULL))
							res = SUCCEED;
						break;
				}
				break;
		}

		if (FAIL == res)
			break;
	}

	return res;
}
