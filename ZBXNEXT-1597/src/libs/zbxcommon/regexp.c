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
			if (0 == regexec(&re, string, (size_t)1, &match, 0))	/* matched */
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
 * Function: regexp_sub_replace                                                  *
 *                                                                               *
 * Purpose: Constructs a string from the specified template and regexp match.    *
 *                                                                               *
 * Parameters: text            - [IN] the input string.                          *
 *             output_template - [IN] the output string template. The output     *
 *                                    string is constructed from template by     *
 *                                    replacing \<n> sequences with the captured *
 *                                    regexp group.                              *
 *                                    If the output template is NULL or contains *
 *                                    empty string then a copy of the whole      *
 *                                    input string is returned.                  *
 *             match           - [IN] the captured group data                    *
 *             nmatch          - [IN] the number of items in captured group data *
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
				if (group_index < nmatch && -1 != match[group_index].rm_so)
				{
					zbx_strncpy_alloc(&ptr, &size, &offset, text + match[group_index].rm_so,
							match[group_index].rm_eo - match[group_index].rm_so);
				}
				pstart = pgroup + 1;
				continue;

			case '@':
				/* artificial construct to replace the first captured group or fail */
				/* if the regular expression pattern contains no groups             */
				if (-1 == match[1].rm_so)
				{
					zbx_free(ptr);
					goto out;
				}

				zbx_strncpy_alloc(&ptr, &size, &offset, text + match[1].rm_so,
						match[1].rm_eo - match[1].rm_so);

				pstart = pgroup + 1;
				continue;

			default:
				zbx_strncpy_alloc(&ptr, &size, &offset, pstart, pgroup - pstart);
				pstart = pgroup;
		}
	}

	if ('\0' != *pstart)
		zbx_strcpy_alloc(&ptr, &size, &offset, pstart);
out:
	return ptr;
}


/*********************************************************************************
 *                                                                               *
 * Function: regexp_sub                                                          *
 *                                                                               *
 * Purpose: Test if a string matches the specified regular expression. If yes    *
 *          then create a return value by substituting '\<n>' sequences in       *
 *          output template with the captured groups.                            *
 *                                                                               *
 * Parameters: string          - [IN] the string to parse                        *
 *             pattern         - [IN] the regular expression                     *
 *             output_template - [IN] the output string template. The output     *
 *                                    string is constructed from template by     *
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

	if (0 == regexec(&re, string, ARRSIZE(match), match, 0))
		ptr = regexp_sub_replace(string, output_template, match, ARRSIZE(match));

	regfree(&re);

	return ptr;
}

/*********************************************************************************
 *                                                                               *
 * Function: zbx_regexp_sub                                                      *
 *                                                                               *
 * Purpose: Test if a string matches the specified regular expression. If yes    *
 *          then create a return value by substituting '\<n>' sequences in       *
 *          output template with the captured groups.                            *
 *                                                                               *
 * Parameters: string          - [IN] the string to parse                        *
 *             pattern         - [IN] the regular expression                     *
 *             output_template - [IN] the output string template. The output     *
 *                                    string is constructed from template by     *
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

/*********************************************************************************
 *                                                                               *
 * Function: zbx_mregexp_sub                                                     *
 *                                                                               *
 * Purpose: This function is similar to zbx_regexp_sub() with exception that     *
 *          multiline matches are accepted.                                      *
 *                                                                               *
 * Author: Andris Zeila                                                          *
 *                                                                               *
 *********************************************************************************/
char	*zbx_mregexp_sub(const char *string, const char *pattern, const char *output_template)
{
	return regexp_sub(string, pattern, output_template, REG_EXTENDED);
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


/**********************************************************************************
 *                                                                                *
 * Function: regexp_match_ex_regsub                                               *
 *                                                                                *
 * Purpose: Test if the string matches regular expression with the specified      *
 *          case sensitivity option and allocates output variable to store the    *
 *          result if necessary.                                                  *
 *                                                                                *
 * Parameters: string          - [IN] the string to check                         *
 *             pattern         - [IN] the regular expression                      *
 *             cs              - [IN] ZBX_IGNORE_CASE - case insensitive match.   *
 *                                    ZBX_CASE_SENSITIVE - case sensitive match.  *
 *             output_template - [IN] the output string template. The output      *
 *                                    string is constructed from the template by  *
 *                                    replacing \<n> sequences with the captured  *
 *                                    regexp group.                               *
 *                                    If output_template is NULL the the whole    *
 *                                    matched string is returned.                 *
 *             output         - [OUT] a reference to the variable where allocated *
 *                                    memory containing the resulting value       *
 *                                    (substitution) is stored.                   *
 *                                    Specify NULL to skip output value creation. *
 *                                                                                *
 * Return value: SUCCEED - the string matches the specified regular expression    *
 *               FAIL    - the string does not match the specified regular        *
 *                         expression                                             *
 *                                                                                *
 * Author: Andris Zeila                                                           *
 *                                                                                *
 **********************************************************************************/
static int	regexp_match_ex_regsub(const char *string, const char *pattern, zbx_case_sensitive_t cs,
		const char *output_template, char **output)
{
	char	*ptr = NULL;
	int	regexp_flags = REG_EXTENDED | REG_NEWLINE;

	if (ZBX_IGNORE_CASE == cs)
		regexp_flags |= REG_ICASE;

	if (NULL == output)
		ptr = zbx_regexp(string, pattern, NULL, regexp_flags);
	else
		*output = ptr = regexp_sub(string, pattern, output_template, regexp_flags);

	return NULL != ptr ? SUCCEED : FAIL;
}

/**********************************************************************************
 *                                                                                *
 * Function: regexp_match_ex_substring                                            *
 *                                                                                *
 * Purpose: Test if the string contains substring with the specified case         *
 *          sensitivity option.                                                   *
 *                                                                                *
 * Parameters: string          - [IN] the string to check                         *
 *             pattern         - [IN] the substring to search                     *
 *             cs              - [IN] ZBX_IGNORE_CASE - case insensitive search   *
 *                                    ZBX_CASE_SENSITIVE - case sensitive search  *
 *                                                                                *
 * Return value: SUCCEED - string contains the specified substring                *
 *               FAIL    - string does not contain the specified substring        *
 *                                                                                *
 * Author: Andris Zeila                                                           *
 *                                                                                *
 **********************************************************************************/
static int	regexp_match_ex_substring(const char *string, const char *pattern, zbx_case_sensitive_t cs)
{
	char	*ptr = NULL;

	switch (cs)
	{
		case ZBX_CASE_SENSITIVE:
			ptr = strstr(string, pattern);
			break;
		case ZBX_IGNORE_CASE:
			ptr = zbx_strcasestr(string, pattern);
			break;
	}

	return NULL != ptr ? SUCCEED : FAIL;
}

/**********************************************************************************
 *                                                                                *
 * Function: regexp_match_ex_substring_list                                       *
 *                                                                                *
 * Purpose: Test if the string contains a substring from list with the specified  *
 *          delimiter and case sensitivity option.                                *
 *                                                                                *
 * Parameters: string          - [IN] the string to check                         *
 *             pattern         - [IN] the substring list                          *
 *             cs              - [IN] ZBX_IGNORE_CASE - case insensitive search   *
 *                                    ZBX_CASE_SENSITIVE - case sensitive search  *
 *             delimiter       - [IN] the delimiter separating items in the       *
 *                                    substring list                              *
 *                                                                                *
 * Return value: SUCCEED - string contains a substring from the list              *
 *               FAIL    - string contains no substrings from the list            *
 *                                                                                *
 * Author: Andris Zeila                                                           *
 *                                                                                *
 **********************************************************************************/
static int	regexp_match_ex_substring_list(const char *string, char *pattern, zbx_case_sensitive_t cs,
		char delimiter)
{
	int	ret = FAIL;
	char	*s, *c;

	for (s = pattern; '\0' != *s && SUCCEED != ret;)
	{
		if (NULL != (c = strchr(s, delimiter)))
			*c = '\0';

		ret = regexp_match_ex_substring(string, s, cs);

		if (NULL != c)
		{
			*c = delimiter;
			s = ++c;
		}
		else
			break;
	}

	return ret;
}

/**********************************************************************************
 *                                                                                *
 * Function: regexp_sub_ex                                                        *
 *                                                                                *
 * Purpose: Test if the string matches regular expression with the specified      *
 *          case sensitivity option and allocates output variable to store the    *
 *          result if necessary.                                                  *
 *                                                                                *
 * Parameters: regexps         - [IN] the global regular expression array         *
 *             regexps_num     - [IN] the number of global regular expressions    *
 *             string          - [IN] the string to check                         *
 *             pattern         - [IN] the regular expression or global regular    *
 *                                    expression name (@<global regexp name>).    *
 *             cs              - [IN] ZBX_IGNORE_CASE - case insensitive match    *
 *                                    ZBX_CASE_SENSITIVE - case sensitive match   *
 *             output_template - [IN] the output string template. For regular     *
 *                                    expressions (type Result is TRUE) output    *
 *                                    string is constructed from the template by  *
 *                                    replacing '\<n>' sequences with the         *
 *                                    captured regexp group.                      *
 *                                    If output_template is NULL then the whole   *
 *                                    matched string is returned.                 *
 *             output         - [OUT] a reference to the variable where allocated *
 *                                    memory containing the resulting value       *
 *                                    (substitution) is stored.                   *
 *                                    Specify NULL to skip output value creation. *
 *                                                                                *
 * Return value: SUCCEED - the string matches the specified regular expression    *
 *               FAIL    - the string does not match the specified regular        *
 *                         expression                                             *
 *                                                                                *
 * Comments: For regular expressions and global regular expressions with 'Result  *
 *           is TRUE' type the output_template substitution result is stored into *
 *           output variable. For the other global regular expression types the   *
 *           whole string is stored into output variable.                         *
 *                                                                                *
 * Author: Andris Zeila                                                           *
 *                                                                                *
 **********************************************************************************/
int	regexp_sub_ex(ZBX_REGEXP *regexps, int regexps_num, const char *string, const char *pattern,
		zbx_case_sensitive_t cs, const char *output_template, char **output)
{
	int	i, ret = FAIL;

	if (NULL == pattern || '\0' == *pattern)
	{
		/* always match when no pattern is specified */
		ret = SUCCEED;
		goto out;
	}

	if ('@' != *pattern)
	{
		ret = regexp_match_ex_regsub(string, pattern, cs, output_template, output);
		goto out;
	}

	pattern++;

	for (i = 0; i < regexps_num; i++)
	{
		if (0 != strcmp(regexps[i].name, pattern))
			continue;

		ret = FAIL;

		switch (regexps[i].expression_type)
		{
			case EXPRESSION_TYPE_TRUE:
				ret = regexp_match_ex_regsub(string, regexps[i].expression, regexps[i].case_sensitive,
						output_template, output);
				break;
			case EXPRESSION_TYPE_FALSE:
				ret = regexp_match_ex_regsub(string, regexps[i].expression, regexps[i].case_sensitive,
						NULL, NULL);
				/* invert output value */
				ret = (SUCCEED == ret) ? FAIL : SUCCEED;
				break;
			case EXPRESSION_TYPE_INCLUDED:
				ret = regexp_match_ex_substring(string, regexps[i].expression, regexps[i].case_sensitive);
				break;
			case EXPRESSION_TYPE_NOT_INCLUDED:
				ret = regexp_match_ex_substring(string, regexps[i].expression, regexps[i].case_sensitive);
				/* invert output value */
				ret = (SUCCEED == ret) ? FAIL : SUCCEED;
				break;
			case EXPRESSION_TYPE_ANY_INCLUDED:
				ret = regexp_match_ex_substring_list(string, regexps[i].expression, regexps[i].case_sensitive,
						regexps[i].exp_delimiter);
				break;
		}
		break;
	}
out:
	if (SUCCEED == ret && NULL != output && NULL == *output)
	{
		/* Handle output value allocation for global regular expression types   */
		/* that cannot perform output_template substitution (practically        */
		/* all global regular expression types except EXPRESSION_TYPE_TRUE).    */
		size_t	offset = 0, size = 0;

		zbx_strcpy_alloc(output, &size, &offset, string);
	}

	return ret;
}

int	regexp_match_ex(ZBX_REGEXP *regexps, int regexps_num, const char *string, const char *pattern,
		zbx_case_sensitive_t cs)
{
	return regexp_sub_ex(regexps, regexps_num, string, pattern, cs, NULL, NULL);
}
