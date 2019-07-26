/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
#include "zbxregexp.h"
#include "log.h"

struct zbx_regexp
{
	pcre			*pcre_regexp;
	struct pcre_extra	*extra;
};

/* maps to ovector of pcre_exec() */
typedef struct
{
	int rm_so;
	int rm_eo;
}
zbx_regmatch_t;

#define ZBX_REGEXP_GROUPS_MAX	10	/* Max number of supported capture groups in regular expressions. */
					/* Group \0 contains the matching part of string, groups \1 ...\9 */
					/* contain captured groups (substrings).                          */

/******************************************************************************
 *                                                                            *
 * Function: regexp_compile                                                   *
 *                                                                            *
 * Purpose: compiles a regular expression                                     *
 *                                                                            *
 * Parameters:                                                                *
 *     pattern   - [IN] regular expression as a text string. Empty            *
 *                      string ("") is allowed, it will match everything.     *
 *                      NULL is not allowed.                                  *
 *     flags     - [IN] regexp compilation parameters passed to pcre_compile. *
 *                      PCRE_CASELESS, PCRE_NO_AUTO_CAPTURE, PCRE_MULTILINE.  *
 *     regexp    - [OUT] output regexp.                                       *
 *     err_msg_static - [OUT] error message if any. Do not deallocate with    *
 *                            zbx_free().                                     *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	regexp_compile(const char *pattern, int flags, zbx_regexp_t **regexp, const char **err_msg_static)
{
	int			error_offset = -1;
	pcre			*pcre_regexp;
	struct pcre_extra	*extra;

#ifdef PCRE_NO_AUTO_CAPTURE
	/* If PCRE_NO_AUTO_CAPTURE bit is set in 'flags' but regular expression contains references to numbered */
	/* capturing groups then reset PCRE_NO_AUTO_CAPTURE bit. Otherwise the regular expression might not compile. */

	if (0 != (flags & PCRE_NO_AUTO_CAPTURE))
	{
		const char	*pstart = pattern, *offset;

		while (NULL != (offset = strchr(pstart, '\\')))
		{
			offset++;

			if (('1' <= *offset && *offset <= '9') || 'g' == *offset)
			{
				flags ^= PCRE_NO_AUTO_CAPTURE;
				break;
			}

			if (*offset == '\\')
				offset++;

			pstart = offset;
		}
	}
#endif
	if (NULL == (pcre_regexp = pcre_compile(pattern, flags, err_msg_static, &error_offset, NULL)))
		return FAIL;

	if (NULL != regexp)
	{
		if (NULL == (extra = pcre_study(pcre_regexp, 0, err_msg_static)) && NULL != *err_msg_static)
		{
			pcre_free(pcre_regexp);
			return FAIL;
		}

		*regexp = (zbx_regexp_t *)zbx_malloc(NULL, sizeof(zbx_regexp_t));
		(*regexp)->pcre_regexp = pcre_regexp;
		(*regexp)->extra = extra;
	}
	else
		pcre_free(pcre_regexp);

	return SUCCEED;
}

/*******************************************************
 *                                                     *
 * Function: zbx_regexp_compile                        *
 *                                                     *
 * Purpose: public wrapper for regexp_compile          *
 *                                                     *
 *******************************************************/
int	zbx_regexp_compile(const char *pattern, zbx_regexp_t **regexp, const char **err_msg_static)
{
#ifdef PCRE_NO_AUTO_CAPTURE
	return regexp_compile(pattern, PCRE_MULTILINE | PCRE_NO_AUTO_CAPTURE, regexp, err_msg_static);
#else
	return regexp_compile(pattern, PCRE_MULTILINE, regexp, err_msg_static);
#endif
}

/*******************************************************
 *                                                     *
 * Function: zbx_regexp_compile_ext                    *
 *                                                     *
 * Purpose: public wrapper for regexp_compile          *
 *                                                     *
 *******************************************************/
int	zbx_regexp_compile_ext(const char *pattern, zbx_regexp_t **regexp, int flags, const char **err_msg_static)
{
	return regexp_compile(pattern, flags, regexp, err_msg_static);
}

/****************************************************************************************************
 *                                                                                                  *
 * Function: regexp_prepare                                                                         *
 *                                                                                                  *
 * Purpose: wrapper for zbx_regexp_compile. Caches and reuses the last used regexp.                 *
 *                                                                                                  *
 ****************************************************************************************************/
static int	regexp_prepare(const char *pattern, int flags, zbx_regexp_t **regexp, const char **err_msg_static)
{
	ZBX_THREAD_LOCAL static zbx_regexp_t	*curr_regexp = NULL;
	ZBX_THREAD_LOCAL static char		*curr_pattern = NULL;
	ZBX_THREAD_LOCAL static int		curr_flags = 0;
	int					ret = SUCCEED;

	if (NULL == curr_regexp || 0 != strcmp(curr_pattern, pattern) || curr_flags != flags)
	{
		if (NULL != curr_regexp)
		{
			zbx_regexp_free(curr_regexp);
			zbx_free(curr_pattern);
		}

		curr_regexp = NULL;
		curr_pattern = NULL;
		curr_flags = 0;

		if (SUCCEED == regexp_compile(pattern, flags, &curr_regexp, err_msg_static))
		{
			curr_pattern = zbx_strdup(curr_pattern, pattern);
			curr_flags = flags;
		}
		else
			ret = FAIL;
	}

	*regexp = curr_regexp;
	return ret;
}

/***********************************************************************************
 *                                                                                 *
 * Function: regexp_exec                                                           *
 *                                                                                 *
 * Purpose: wrapper for pcre_exec(), searches for a given pattern, specified by    *
 *          regexp, in the string                                                  *
 *                                                                                 *
 * Parameters:                                                                     *
 *     string         - [IN] string to be matched against 'regexp'                 *
 *     regexp         - [IN] precompiled regular expression                        *
 *     flags          - [IN] execution flags for matching                          *
 *     count          - [IN] count of elements in matches array                    *
 *     matches        - [OUT] matches (can be NULL if matching results are         *
 *                      not required)                                              *
 *                                                                                 *
 * Return value: ZBX_REGEXP_MATCH     - successful match                           *
 *               ZBX_REGEXP_NO_MATCH  - no match                                   *
 *               FAIL                 - error occurred                             *
 *                                                                                 *
 ***********************************************************************************/
static int	regexp_exec(const char *string, const zbx_regexp_t *regexp, int flags, int count,
		zbx_regmatch_t *matches)
{
#define MATCHES_BUFF_SIZE	(ZBX_REGEXP_GROUPS_MAX * 3)		/* see pcre_exec() in "man pcreapi" why 3 */

	int				result, r;
	ZBX_THREAD_LOCAL static int	matches_buff[MATCHES_BUFF_SIZE];
	int				*ovector = NULL;
	int				ovecsize = 3 * count;		/* see pcre_exec() in "man pcreapi" why 3 */
	struct pcre_extra		extra, *pextra;
#if defined(PCRE_EXTRA_MATCH_LIMIT) && defined(PCRE_EXTRA_MATCH_LIMIT_RECURSION) && !defined(_WINDOWS)
	static unsigned long int	recursion_limit = 0;

	if (0 == recursion_limit)
	{
		struct rlimit	rlim;

		/* calculate recursion limit, PCRE man page suggests to reckon on about 500 bytes per recursion */
		/* but to be on the safe side - reckon on 800 bytes and do not set limit higher than 100000 */
		if (0 == getrlimit(RLIMIT_STACK, &rlim))
			recursion_limit = rlim.rlim_cur < 80000000 ? rlim.rlim_cur / 800 : 100000;
		else
			recursion_limit = 10000;	/* if stack size cannot be retrieved then assume ~8 MB */
	}
#endif

	if (ZBX_REGEXP_GROUPS_MAX < count)
		ovector = (int *)zbx_malloc(NULL, (size_t)ovecsize * sizeof(int));
	else
		ovector = matches_buff;

	if (NULL == regexp->extra)
	{
		pextra = &extra;
		pextra->flags = 0;
	}
	else
		pextra = regexp->extra;
#if defined(PCRE_EXTRA_MATCH_LIMIT) && defined(PCRE_EXTRA_MATCH_LIMIT_RECURSION)
	pextra->flags |= PCRE_EXTRA_MATCH_LIMIT | PCRE_EXTRA_MATCH_LIMIT_RECURSION;
	pextra->match_limit = 1000000;
#ifdef _WINDOWS
	pextra->match_limit_recursion = ZBX_PCRE_RECURSION_LIMIT;
#else
	pextra->match_limit_recursion = recursion_limit;
#endif
#endif
	/* see "man pcreapi" about pcre_exec() return value and 'ovector' size and layout */
	if (0 <= (r = pcre_exec(regexp->pcre_regexp, pextra, string, strlen(string), flags, 0, ovector, ovecsize)))
	{
		if (NULL != matches)
			memcpy(matches, ovector, (size_t)((0 < r) ? MIN(r, count) : count) * sizeof(zbx_regmatch_t));

		result = ZBX_REGEXP_MATCH;
	}
	else if (PCRE_ERROR_NOMATCH == r)
	{
		result = ZBX_REGEXP_NO_MATCH;
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() failed with error %d", __func__, r);
		result = FAIL;
	}

	if (ZBX_REGEXP_GROUPS_MAX < count)
		zbx_free(ovector);

	return result;
#undef MATCHES_BUFF_SIZE
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_regexp_free                                                  *
 *                                                                            *
 * Purpose: wrapper for pcre_free                                             *
 *                                                                            *
 * Parameters: regexp - [IN] compiled regular expression                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_regexp_free(zbx_regexp_t *regexp)
{
	/* pcre_free_study() was added to the API for release 8.20 while extra was available before */
#ifdef PCRE_CONFIG_JIT
	pcre_free_study(regexp->extra);
#else
	pcre_free(regexp->extra);
#endif
	pcre_free(regexp->pcre_regexp);
	zbx_free(regexp);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_regexp_match_precompiled                                     *
 *                                                                            *
 * Purpose: checks if string matches a precompiled regular expression without *
 *          returning matching groups                                         *
 *                                                                            *
 * Parameters: string - [IN] string to be matched                             *
 *             regex  - [IN] precompiled regular expression                   *
 *                                                                            *
 * Return value: 0 - successful match                                         *
 *               nonzero - no match                                           *
 *                                                                            *
 * Comments: use this function for better performance if many strings need to *
 *           be matched against the same regular expression                   *
 *                                                                            *
 ******************************************************************************/
int     zbx_regexp_match_precompiled(const char *string, const zbx_regexp_t *regexp)
{
	return (ZBX_REGEXP_MATCH == regexp_exec(string, regexp, 0, 0, NULL)) ? 0 : -1;
}

/****************************************************************************************************
 *                                                                                                  *
 * Function: zbx_regexp                                                                             *
 *                                                                                                  *
 * Purpose: compiles and executes a regexp                                                          *
 *                                                                                                  *
 * Parameters:                                                                                      *
 *     string     - [IN] string to be matched against 'regexp'                                      *
 *     pattern    - [IN] regular expression pattern                                                 *
 *     flags      - [IN] execution flags for matching                                               *
 *     len        - [OUT] length of matched string,                                                 *
 *                      0 in case of no match or                                                    *
 *                      FAIL if an error occurred.                                                  *
 *                                                                                                  *
 * Return value: pointer to the matched substring or null                                           *
 *                                                                                                  *
 ****************************************************************************************************/
static char	*zbx_regexp(const char *string, const char *pattern, int flags, int *len)
{
	char		*c = NULL;
	zbx_regmatch_t	match;
	zbx_regexp_t	*regexp = NULL;
	const char*	error = NULL;

	if (NULL != len)
		*len = FAIL;

	if (SUCCEED != regexp_prepare(pattern, flags, &regexp, &error))
		return NULL;

	if (NULL != string)
	{
		int	r;

		if (ZBX_REGEXP_MATCH == (r = regexp_exec(string, regexp, 0, 1, &match)))
		{
			c = (char *)string + match.rm_so;

			if (NULL != len)
				*len = match.rm_eo - match.rm_so;
		}
		else if (ZBX_REGEXP_NO_MATCH == r && NULL != len)
			*len = 0;
	}

	return c;
}

char	*zbx_regexp_match(const char *string, const char *pattern, int *len)
{
	return zbx_regexp(string, pattern, PCRE_MULTILINE, len);
}

/******************************************************************************
 *                                                                            *
 * Function: strncpy_alloc                                                    *
 *                                                                            *
 * Purpose: zbx_strncpy_alloc with maximum allocated memory limit.            *
 *                                                                            *
 * Parameters: str       - [IN/OUT] destination buffer pointer                *
 *             alloc_len - [IN/OUT] already allocated memory                  *
 *             offset    - [IN/OUT] offset for writing                        *
 *             src       - [IN] copied string                                 *
 *             n         - [IN] maximum number of bytes to copy               *
 *             limit     - [IN] maximum number of bytes to be allocated       *
 *                                                                            *
 ******************************************************************************/
static void	strncpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src, size_t n, size_t limit)
{
	if (0 != limit && *offset + n > limit)
		n = (limit > *offset) ? (limit - *offset) : 0;

	zbx_strncpy_alloc(str, alloc_len, offset, src, n);
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
 *             limit           - [IN] size limit for memory allocation           *
 *                                    0 means no limit                           *
 *                                                                               *
 * Return value: Allocated string containing output value                        *
 *                                                                               *
 *********************************************************************************/
static char	*regexp_sub_replace(const char *text, const char *output_template, zbx_regmatch_t *match, int nmatch,
		size_t limit)
{
	char		*ptr = NULL;
	const char	*pstart = output_template, *pgroup;
	size_t		size = 0, offset = 0;
	int		group_index;

	if (NULL == output_template || '\0' == *output_template)
		return zbx_strdup(NULL, text);

	while (NULL != (pgroup = strchr(pstart, '\\')))
	{
		switch (*(++pgroup))
		{
			case '\\':
				strncpy_alloc(&ptr, &size, &offset, pstart, pgroup - pstart, limit);
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
				strncpy_alloc(&ptr, &size, &offset, pstart, pgroup - pstart - 1, limit);
				group_index = *pgroup - '0';
				if (group_index < nmatch && -1 != match[group_index].rm_so)
				{
					strncpy_alloc(&ptr, &size, &offset, text + match[group_index].rm_so,
							match[group_index].rm_eo - match[group_index].rm_so, limit);
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

				strncpy_alloc(&ptr, &size, &offset, text + match[1].rm_so,
						match[1].rm_eo - match[1].rm_so, limit);

				pstart = pgroup + 1;
				continue;

			default:
				strncpy_alloc(&ptr, &size, &offset, pstart, pgroup - pstart, limit);
				pstart = pgroup;
		}

		if (0 != limit && offset >= limit)
			break;
	}

	if ('\0' != *pstart)
		strncpy_alloc(&ptr, &size, &offset, pstart, strlen(pstart), limit);
out:
	if (NULL != ptr)
	{
		if (0 != limit && offset >= limit)
		{
			size = offset;
			offset--;

			/* ensure that the string is not cut in the middle of UTF-8 sequence */
			if (0x80 <= (0xc0 & ptr[offset]))
			{
				while (0x80 == (0xc0 & ptr[offset]) && 0 < offset)
					offset--;

				if (zbx_utf8_char_len(&ptr[offset]) != size - offset)
					ptr[offset] = '\0';
			}
		}

		/* Some regexp and output template combinations can produce invalid UTF-8 sequences. */
		/* For example, regexp "(.)(.)" and output template "\1 \2" produce a valid UTF-8 sequence */
		/* for single-byte UTF-8 characters and invalid sequence for multi-byte characters. */
		/* Using (*UTF) modifier (e.g. "(*UTF)(.)(.)") solves the problem for multi-byte characters */
		/* but it is up to user to add the modifier. To prevent producing invalid UTF-8 sequences do */
		/* output sanitization. */

		zbx_replace_invalid_utf8(ptr);
	}

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
 *            flags            - [IN] the pcre_compile() function flags.         *
 *                                    See pcre_compile() manual.                 *
 *            out              - [OUT] the output value if the input string      *
 *                                     matches the specified regular expression  *
 *                                     or NULL otherwise                         *
 *                                                                               *
 * Return value: SUCCEED - the regular expression match was done                 *
 *               FAIL    - failed to compile regexp                              *
 *                                                                               *
 *********************************************************************************/
static int	regexp_sub(const char *string, const char *pattern, const char *output_template, int flags, char **out)
{
	const char	*error = NULL;
	zbx_regexp_t	*regexp = NULL;
	zbx_regmatch_t	match[ZBX_REGEXP_GROUPS_MAX];
	unsigned int	i;

	if (NULL == string)
	{
		zbx_free(*out);
		return SUCCEED;
	}

#ifdef PCRE_NO_AUTO_CAPTURE
	/* no subpatterns without an output template */
	if (NULL == output_template || '\0' == *output_template)
		flags |= PCRE_NO_AUTO_CAPTURE;
#endif

	if (FAIL == regexp_prepare(pattern, flags, &regexp, &error))
		return FAIL;

	zbx_free(*out);

	/* -1 is special pcre value for unused patterns */
	for (i = 0; i < ARRSIZE(match); i++)
		match[i].rm_so = match[i].rm_eo = -1;

	if (ZBX_REGEXP_MATCH == regexp_exec(string, regexp, 0, ZBX_REGEXP_GROUPS_MAX, match))
		*out = regexp_sub_replace(string, output_template, match, ZBX_REGEXP_GROUPS_MAX, 0);

	return SUCCEED;
#undef MATCH_SIZE
}

/*********************************************************************************
 *                                                                               *
 * Function: zbx_mregexp_sub_precompiled                                         *
 *                                                                               *
 * Purpose: Test if a string matches precompiled regular expression. If yes      *
 *          then create a return value by substituting '\<n>' sequences in       *
 *          output template with the captured groups.                            *
 *                                                                               *
 * Parameters: string          - [IN] the string to parse                        *
 *             regexp          - [IN] the precompiled regular expression         *
 *             output_template - [IN] the output string template. The output     *
 *                                    string is constructed from template by     *
 *                                    replacing \<n> sequences with the captured *
 *                                    regexp group.                              *
 *                                    If output template is NULL or contains     *
 *                                    empty string then the whole input string   *
 *                                    is used as output value.                   *
 *             limit           - [IN] size limit for memory allocation           *
 *                                    0 means no limit                           *
 *             out             - [OUT] the output value if the input string      *
 *                                     matches the specified regular expression  *
 *                                     or NULL otherwise                         *
 *                                                                               *
 * Return value: SUCCEED - the regular expression match was done                 *
 *               FAIL    - failed to match                                       *
 *                                                                               *
 * Comments: Multiline match is performed                                        *
 *                                                                               *
 *********************************************************************************/
int	zbx_mregexp_sub_precompiled(const char *string, const zbx_regexp_t *regexp, const char *output_template,
		size_t limit, char **out)
{
	zbx_regmatch_t	match[ZBX_REGEXP_GROUPS_MAX];
	unsigned int	i;

	zbx_free(*out);

	/* -1 is special pcre value for unused patterns */
	for (i = 0; i < ARRSIZE(match); i++)
		match[i].rm_so = match[i].rm_eo = -1;

	if (ZBX_REGEXP_MATCH == regexp_exec(string, regexp, 0, ZBX_REGEXP_GROUPS_MAX, match))
	{
		*out = regexp_sub_replace(string, output_template, match, ZBX_REGEXP_GROUPS_MAX, limit);
		return SUCCEED;
	}

	return FAIL;
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
 *            out              - [OUT] the output value if the input string      *
 *                                     matches the specified regular expression  *
 *                                     or NULL otherwise                         *
 *                                                                               *
 * Return value: SUCCEED - the regular expression match was done                 *
 *               FAIL    - failed to compile regexp                              *
 *                                                                               *
 * Comments: This function performs case sensitive match                         *
 *                                                                               *
 *********************************************************************************/
int	zbx_regexp_sub(const char *string, const char *pattern, const char *output_template, char **out)
{
	return regexp_sub(string, pattern, output_template, PCRE_MULTILINE, out);
}

/*********************************************************************************
 *                                                                               *
 * Function: zbx_mregexp_sub                                                     *
 *                                                                               *
 * Purpose: This function is similar to zbx_regexp_sub() with exception that     *
 *          multiline matches are accepted.                                      *
 *                                                                               *
 *********************************************************************************/
int	zbx_mregexp_sub(const char *string, const char *pattern, const char *output_template, char **out)
{
	return regexp_sub(string, pattern, output_template, 0, out);
}

/*********************************************************************************
 *                                                                               *
 * Function: zbx_iregexp_sub                                                     *
 *                                                                               *
 * Purpose: This function is similar to zbx_regexp_sub() with exception that     *
 *          case insensitive matches are accepted.                               *
 *                                                                               *
 *********************************************************************************/
int	zbx_iregexp_sub(const char *string, const char *pattern, const char *output_template, char **out)
{
	return regexp_sub(string, pattern, output_template, PCRE_CASELESS, out);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_regexp_clean_expressions                                     *
 *                                                                            *
 * Purpose: frees expression data retrieved by DCget_expressions function or  *
 *          prepared with add_regexp_ex() function calls                      *
 *                                                                            *
 * Parameters: expressions  - [IN] a vector of expression data pointers       *
 *                                                                            *
 ******************************************************************************/
void	zbx_regexp_clean_expressions(zbx_vector_ptr_t *expressions)
{
	int	i;

	for (i = 0; i < expressions->values_num; i++)
	{
		zbx_expression_t	*regexp = expressions->values[i];

		zbx_free(regexp->name);
		zbx_free(regexp->expression);
		zbx_free(regexp);
	}

	zbx_vector_ptr_clear(expressions);
}

void	add_regexp_ex(zbx_vector_ptr_t *regexps, const char *name, const char *expression, int expression_type,
		char exp_delimiter, int case_sensitive)
{
	zbx_expression_t	*regexp;

	regexp = zbx_malloc(NULL, sizeof(zbx_expression_t));

	regexp->name = zbx_strdup(NULL, name);
	regexp->expression = zbx_strdup(NULL, expression);

	regexp->expression_type = expression_type;
	regexp->exp_delimiter = exp_delimiter;
	regexp->case_sensitive = case_sensitive;

	zbx_vector_ptr_append(regexps, regexp);
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
 *             case_sensitive  - [IN] ZBX_IGNORE_CASE - case insensitive match.   *
 *                                    ZBX_CASE_SENSITIVE - case sensitive match.  *
 *             output_template - [IN] the output string template. The output      *
 *                                    string is constructed from the template by  *
 *                                    replacing \<n> sequences with the captured  *
 *                                    regexp group.                               *
 *                                    If output_template is NULL the whole        *
 *                                    matched string is returned.                 *
 *             output         - [OUT] a reference to the variable where allocated *
 *                                    memory containing the resulting value       *
 *                                    (substitution) is stored.                   *
 *                                    Specify NULL to skip output value creation. *
 *                                                                                *
 * Return value: ZBX_REGEXP_MATCH    - the string matches the specified regular   *
 *                                     expression                                 *
 *               ZBX_REGEXP_NO_MATCH - the string does not match the regular      *
 *                                     expression                                 *
 *               FAIL                - the string is NULL or the specified        *
 *                                     regular expression is invalid              *
 *                                                                                *
 **********************************************************************************/
static int	regexp_match_ex_regsub(const char *string, const char *pattern, int case_sensitive,
		const char *output_template, char **output)
{
	int	regexp_flags = PCRE_MULTILINE, ret = FAIL;

	if (ZBX_IGNORE_CASE == case_sensitive)
		regexp_flags |= PCRE_CASELESS;

	if (NULL == output)
	{
		if (NULL == zbx_regexp(string, pattern, regexp_flags, &ret))
		{
			if (FAIL != ret)
				ret = ZBX_REGEXP_NO_MATCH;
		}
		else
			ret = ZBX_REGEXP_MATCH;
	}
	else
	{
		if (SUCCEED == regexp_sub(string, pattern, output_template, regexp_flags, output))
		{
			ret = (NULL != *output ? ZBX_REGEXP_MATCH : ZBX_REGEXP_NO_MATCH);
		}
		else
			ret = FAIL;
	}

	return ret;
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
 *             case_sensitive  - [IN] ZBX_IGNORE_CASE - case insensitive search   *
 *                                    ZBX_CASE_SENSITIVE - case sensitive search  *
 *                                                                                *
 * Return value: ZBX_REGEXP_MATCH    - string contains the specified substring    *
 *               ZBX_REGEXP_NO_MATCH - string does not contain the substring      *
 *                                                                                *
 **********************************************************************************/
static int	regexp_match_ex_substring(const char *string, const char *pattern, int case_sensitive)
{
	char	*ptr = NULL;

	switch (case_sensitive)
	{
		case ZBX_CASE_SENSITIVE:
			ptr = strstr(string, pattern);
			break;
		case ZBX_IGNORE_CASE:
			ptr = zbx_strcasestr(string, pattern);
			break;
	}

	return (NULL != ptr ? ZBX_REGEXP_MATCH : ZBX_REGEXP_NO_MATCH);
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
 *             case_sensitive  - [IN] ZBX_IGNORE_CASE - case insensitive search   *
 *                                    ZBX_CASE_SENSITIVE - case sensitive search  *
 *             delimiter       - [IN] the delimiter separating items in the       *
 *                                    substring list                              *
 *                                                                                *
 * Return value: ZBX_REGEXP_MATCH    - string contains a substring from the list  *
 *               ZBX_REGEXP_NO_MATCH - string does not contain any substrings     *
 *                                     from the list                              *
 *                                                                                *
 **********************************************************************************/
static int	regexp_match_ex_substring_list(const char *string, char *pattern, int case_sensitive, char delimiter)
{
	int	ret = ZBX_REGEXP_NO_MATCH;
	char	*s, *c;

	for (s = pattern; '\0' != *s && ZBX_REGEXP_MATCH != ret;)
	{
		if (NULL != (c = strchr(s, delimiter)))
			*c = '\0';

		ret = regexp_match_ex_substring(string, s, case_sensitive);

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
 *             string          - [IN] the string to check                         *
 *             pattern         - [IN] the regular expression or global regular    *
 *                                    expression name (@<global regexp name>).    *
 *             case_sensitive  - [IN] ZBX_IGNORE_CASE - case insensitive match    *
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
 * Return value: ZBX_REGEXP_MATCH    - the string matches the specified regular   *
 *                                     expression                                 *
 *               ZBX_REGEXP_NO_MATCH - the string does not match the specified    *
 *                                     regular expression                         *
 *               FAIL                - invalid regular expression                 *
 *                                                                                *
 * Comments: For regular expressions and global regular expressions with 'Result  *
 *           is TRUE' type the 'output_template' substitution result is stored    *
 *           into 'output' variable. For other global regular expression types    *
 *           the whole string is stored into 'output' variable.                   *
 *                                                                                *
 **********************************************************************************/
int	regexp_sub_ex(const zbx_vector_ptr_t *regexps, const char *string, const char *pattern,
		int case_sensitive, const char *output_template, char **output)
{
	int	i, ret = FAIL;
	char	*output_accu;	/* accumulator for 'output' when looping over global regexp subexpressions */

	if (NULL == pattern || '\0' == *pattern)
	{
		/* always match when no pattern is specified */
		ret = ZBX_REGEXP_MATCH;
		goto out;
	}

	if ('@' != *pattern)				/* not a global regexp */
	{
		ret = regexp_match_ex_regsub(string, pattern, case_sensitive, output_template, output);
		goto out;
	}

	pattern++;
	output_accu = NULL;

	for (i = 0; i < regexps->values_num; i++)	/* loop over global regexp subexpressions */
	{
		const zbx_expression_t	*regexp = regexps->values[i];

		if (0 != strcmp(regexp->name, pattern))
			continue;

		switch (regexp->expression_type)
		{
			case EXPRESSION_TYPE_TRUE:
				if (NULL != output)
				{
					char	*output_tmp = NULL;

					if (ZBX_REGEXP_MATCH == (ret = regexp_match_ex_regsub(string,
							regexp->expression, regexp->case_sensitive, output_template,
							&output_tmp)))
					{
						zbx_free(output_accu);
						output_accu = output_tmp;
					}
				}
				else
				{
					ret = regexp_match_ex_regsub(string, regexp->expression, regexp->case_sensitive,
							NULL, NULL);
				}
				break;
			case EXPRESSION_TYPE_FALSE:
				ret = regexp_match_ex_regsub(string, regexp->expression, regexp->case_sensitive,
						NULL, NULL);
				if (FAIL != ret)	/* invert output value */
					ret = (ZBX_REGEXP_MATCH == ret ? ZBX_REGEXP_NO_MATCH : ZBX_REGEXP_MATCH);
				break;
			case EXPRESSION_TYPE_INCLUDED:
				ret = regexp_match_ex_substring(string, regexp->expression, regexp->case_sensitive);
				break;
			case EXPRESSION_TYPE_NOT_INCLUDED:
				ret = regexp_match_ex_substring(string, regexp->expression, regexp->case_sensitive);
				/* invert output value */
				ret = (ZBX_REGEXP_MATCH == ret ? ZBX_REGEXP_NO_MATCH : ZBX_REGEXP_MATCH);
				break;
			case EXPRESSION_TYPE_ANY_INCLUDED:
				ret = regexp_match_ex_substring_list(string, regexp->expression, regexp->case_sensitive,
						regexp->exp_delimiter);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				ret = FAIL;
		}

		if (FAIL == ret || ZBX_REGEXP_NO_MATCH == ret)
		{
			zbx_free(output_accu);
			break;
		}
	}

	if (ZBX_REGEXP_MATCH == ret && NULL != output_accu)
	{
		*output = output_accu;
		return ZBX_REGEXP_MATCH;
	}
out:
	if (ZBX_REGEXP_MATCH == ret && NULL != output && NULL == *output)
	{
		/* Handle output value allocation for global regular expression types   */
		/* that cannot perform output_template substitution (practically        */
		/* all global regular expression types except EXPRESSION_TYPE_TRUE).    */
		size_t	offset = 0, size = 0;

		zbx_strcpy_alloc(output, &size, &offset, string);
	}

	return ret;
}

int	regexp_match_ex(const zbx_vector_ptr_t *regexps, const char *string, const char *pattern, int case_sensitive)
{
	return regexp_sub_ex(regexps, string, pattern, case_sensitive, NULL, NULL);
}

int	zbx_global_regexp_exists(const char *name, const zbx_vector_ptr_t *regexps)
{
	int	i;

	for (i = 0; i < regexps->values_num; i++)
	{
		const zbx_expression_t	*regexp = (const zbx_expression_t *)regexps->values[i];

		if (0 == strcmp(regexp->name, name))
			return SUCCEED;
	}

	return FAIL;
}

/**********************************************************************************
 *                                                                                *
 * Function: zbx_regexp_escape_stringsize                                         *
 *                                                                                *
 * Purpose: calculate a string size after symbols escaping                        *
 *                                                                                *
 * Parameters: string - [IN] the string to check                                  *
 *                                                                                *
 * Return value: new size of the string                                           *
 *                                                                                *
 **********************************************************************************/
static size_t	zbx_regexp_escape_stringsize(const char *string)
{
	size_t		len = 0;
	const char	*sptr;

	if (NULL == string)
		return 0;

	for (sptr = string; '\0' != *sptr; sptr++)
	{
		switch (*sptr)
		{
			case '.':
			case '\\':
			case '+':
			case '*':
			case '?':
			case '[':
			case '^':
			case ']':
			case '$':
			case '(':
			case ')':
			case '{':
			case '}':
			case '=':
			case '!':
			case '>':
			case '<':
			case '|':
			case ':':
			case '-':
			case '#':
				len += 2;
				break;
			default:
				len++;
		}
	}

	return len;
}

/**********************************************************************************
 *                                                                                *
 * Function: zbx_regexp_escape_string                                             *
 *                                                                                *
 * Purpose: replace . \ + * ? [ ^ ] $ ( ) { } = ! < > | : - symbols in string     *
 *          with combination of \ and escaped symbol                              *
 *                                                                                *
 * Parameters: p      - [IN/OUT] buffer for new string after update               *
 *             string - [IN] the string to update                                 *
 *                                                                                *
 **********************************************************************************/
static void	zbx_regexp_escape_string(char *p, const char *string)
{
	const char	*sptr;

	for (sptr = string; '\0' != *sptr; sptr++)
	{
		switch (*sptr)
		{
			case '.':
			case '\\':
			case '+':
			case '*':
			case '?':
			case '[':
			case '^':
			case ']':
			case '$':
			case '(':
			case ')':
			case '{':
			case '}':
			case '=':
			case '!':
			case '>':
			case '<':
			case '|':
			case ':':
			case '-':
			case '#':
				*p++ = '\\';
				*p++ = *sptr;
				break;
			default:
				*p++ = *sptr;
		}
	}

	return;
}

/**********************************************************************************
 *                                                                                *
 * Function: zbx_regexp_escape                                                    *
 *                                                                                *
 * Purpose: escaping of symbols for using in regexp expression                    *
 *                                                                                *
 * Parameters: string - [IN/OUT] the string to update                             *
 *                                                                                *
 **********************************************************************************/
void	zbx_regexp_escape(char **string)
{
	size_t	size;
	char	*buffer;

	if (0 == (size = zbx_regexp_escape_stringsize(*string)))
		return;

	buffer = zbx_malloc(NULL, size + 1);
	buffer[size] = '\0';
	zbx_regexp_escape_string(buffer, *string);
	zbx_free(*string);
	*string = buffer;
}

