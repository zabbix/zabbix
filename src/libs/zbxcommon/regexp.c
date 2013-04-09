/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


/*********************************************************************************
 *                                                                               *
 * Function: zbx_regexp_sub_replace                                              *
 *                                                                               *
 * Purpose: Constructs string from the specified template and regexp match.      *
 *          If the template is NULL or empty a copy of the parsed string is      *
 *          returned.                                                            *
 *                                                                               *
 * Parameters: text            - [IN] the input string.                          *
 *             output_template - [IN] the output string template. The output     *
 *                                    string is construed from template by       *
 *                                    replacing \<n> sequences with the captured *
 *                                    regexp group.                              *
 *                                    If output template is NULL or contains     *
 *                                    empty string then the whole input string   *
 *                                    is used as output value.                   *
 *             match           - [IN] the captured group data                    *
 *             nsmatch         - [IN] the number of items in captured group data *
 *                                                                               *
 * Return value: Allocated string containing output value                        *
 *                                                                               *
 * Author: Andris Zeila                                                          *
 *                                                                               *
 *********************************************************************************/
static char	*regexp_sub_replace(const char *text, const char *output_template, regmatch_t *match, size_t nmatch)
{
	char		*ptr = NULL;
	const char	*pstart = output_template, *pgroup;
	size_t		size = 0, offset = 0, group_index;

	if (NULL == output_template || '\0' == *output_template)
		return zbx_strdup(NULL, text);

	while (NULL != (pgroup = strchr(pstart, '\\')))
	{
		switch (*(++pgroup))
		{
			case '\\':
				zbx_strncpy_alloc(&ptr, &size, &offset, pstart, pgroup - pstart);
				pstart = pgroup + 1;
				continue;

			case '0':
			case '1':
			case '2':
			case '3':
			case '4':
			case '5':
			case '6':
			case '7':
			case '8':
			case '9':
				zbx_strncpy_alloc(&ptr, &size, &offset, pstart, pgroup - pstart - 1);
				group_index = *pgroup - '0';
				if (group_index < nmatch - 1 && -1 != match[group_index].rm_so)
				{
					zbx_strncpy_alloc(&ptr, &size, &offset, text + match[group_index].rm_so,
							match[group_index].rm_eo - match[group_index].rm_so);
				}
				pstart = pgroup + 1;
				continue;

			case '*':
				/* artificial construct to handle exception that all data must be */
				/* returned if regular expression pattern contains no groups      */
				if (-1 == match[1].rm_so)
					zbx_strcpy_alloc(&ptr, &size, &offset, text);

				pstart = pgroup + 1;
				continue;

			default:
				zbx_strncpy_alloc(&ptr, &size, &offset, pstart, pgroup - pstart);
				pstart = pgroup;
		}
	}
	if ('\0' != *pstart)
		zbx_strcpy_alloc(&ptr, &size, &offset, pstart);

	return ptr;
}


/*********************************************************************************
 *                                                                               *
 * Function: regexp_sub                                                          *
 *                                                                               *
 * Purpose: Test if the string matches the specified regular expression and      *
 *          creates return value by substituting '\<n>' sequences in output      *
 *          template with the captured groups in output in the case of success.  *
 *                                                                               *
 * Parameters: string          - [IN] the string to parse                        *
 *             pattern         - [IN] the regular expression.                    *
 *             output_template - [IN] the output string template. The output     *
 *                                    string is construed from template by       *
 *                                    replacing \<n> sequences with the captured *
 *                                    regexp group.                              *
 *                                    If output template is NULL or contains     *
 *                                    empty string then the whole input string   *
 *                                    is used as output value.                   *
 *            flags            - [IN] the regcomp() function flags.              *
 *                                    See regcomp() manual.                      *
 *                                                                               *
 * Return value: Allocated string containing output value if the input           *
 *               string matches the specified regular expression or NULL         *
 *               otherwise.                                                      *
 *                                                                               *
 * Author: Andris Zeila                                                          *
 *                                                                               *
 *********************************************************************************/
static char	*regexp_sub(const char *string, const char *pattern, const char *output_template, int flags)
{
	regex_t		re;
	regmatch_t	match[10];
	char		*ptr = NULL;

	if (NULL == string)
		return NULL;

	if (NULL == output_template || '\0' == *output_template)
		flags |= REG_NOSUB;

	if (0 != regcomp(&re, pattern, flags))
		return NULL;

	if (0 == regexec(&re, string, sizeof(match) / sizeof(match[0]), match, 0))
		ptr = regexp_sub_replace(string, output_template, match, sizeof(match) / sizeof(match[0]));

	regfree(&re);

	return ptr;
}

/*********************************************************************************
 *                                                                               *
 * Function: zbx_regexp_sub                                                      *
 *                                                                               *
 * Purpose: Test if the string matches specified regular expression and creates  *
 *          return value by substituting \<n> in output template with captured   *
 *          groups in output in the case of success.                             *
 *                                                                               *
 * Parameters: string          - [IN] the string to parse                        *
 *             pattern         - [IN] the regular expression.                    *
 *             output_template - [IN] the output string template. The output     *
 *                                    string is construed from template by       *
 *                                    replacing \<n> sequences with the captured *
 *                                    regexp group.                              *
 *                                                                               *
 * Return value: Allocated string containing resulting value or NULL if          *
 *               the input string does not match the specified regular           *
 *               expression.                                                     *
 *                                                                               *
 * Comments: This function performs case sensitive match                         *
 *                                                                               *
 * Author: Andris Zeila                                                          *
 *                                                                               *
 *********************************************************************************/
char	*zbx_regexp_sub(const char *string, const char *pattern, const char *output_template)
{
	return regexp_sub(string, pattern, output_template, REG_EXTENDED | REG_NEWLINE);
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
