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

#include "zbxregexp.h"

#include "zbxstr.h"
#include "zbxtime.h"

#ifdef HAVE_PCRE_H
#ifdef HAVE_PCRE2_H
#error "cannot use both pcre and pcre2 at the same time!"
#endif
#define ZBX_REGEXP_MULTILINE PCRE_MULTILINE
#ifdef PCRE_NO_AUTO_CAPTURE
#define ZBX_REGEXP_NO_AUTO_CAPTURE PCRE_NO_AUTO_CAPTURE
#endif
#define ZBX_REGEXP_CASELESS PCRE_CASELESS
#endif

#if !defined(HAVE_PCRE_H) && !defined(HAVE_PCRE2_H)
#error "must use pcre or pcre2!"
#endif

#ifdef HAVE_PCRE2_H
#	define ZBX_REGEXP_MULTILINE PCRE2_MULTILINE
#	ifdef PCRE2_NO_AUTO_CAPTURE
#		define ZBX_REGEXP_NO_AUTO_CAPTURE PCRE2_NO_AUTO_CAPTURE
#	endif
#	define ZBX_REGEXP_CASELESS PCRE2_CASELESS
#	ifdef PCRE2_MATCH_INVALID_UTF
#		define ZBX_REGEXP_COMPILE_FLAGS	(PCRE2_MATCH_INVALID_UTF | PCRE2_UTF)
#	else
#		define ZBX_REGEXP_COMPILE_FLAGS	(PCRE2_UTF)
#	endif
#endif

struct zbx_regexp
{
#ifdef HAVE_PCRE_H
	pcre			*pcre_regexp;
	struct pcre_extra	*extra;
#endif
#ifdef HAVE_PCRE2_H
	pcre2_code		*pcre2_regexp;
	pcre2_match_context	*match_ctx;
#endif
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


ZBX_PTR_VECTOR_IMPL(expression, zbx_expression_t *)

typedef struct
{
	zbx_regmatch_t groups[ZBX_REGEXP_GROUPS_MAX];
}
zbx_match_t;

ZBX_PTR_VECTOR_DECL(match, zbx_match_t *)
ZBX_PTR_VECTOR_IMPL(match, zbx_match_t *)

#ifdef HAVE_PCRE2_H
static void	zbx_match_free(zbx_match_t *match)
{
	zbx_free(match);
}
#endif

#if defined(HAVE_PCRE2_H)
static char	*decode_pcre2_compile_error(int error_code, PCRE2_SIZE error_offset, int flags)
{
	/* 120 code units buffer is recommended in "man pcre2api" */
#define BUF_SIZE	(120 * PCRE2_CODE_UNIT_WIDTH / 8)
	int		ret;
	char		buf[BUF_SIZE];

	if (0 > (ret = pcre2_get_error_message(error_code, (PCRE2_UCHAR *)buf, sizeof(buf))))
		return zbx_dsprintf(NULL, "pcre2_get_error_message(%d, ...) failed with error %d", error_code, ret);

	return zbx_dsprintf(NULL, "%s, position %zu, flags:0x%x", buf, (size_t)error_offset, (unsigned int)flags);
#undef BUF_SIZE
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: compiles a regular expression                                     *
 *                                                                            *
 * Parameters:                                                                *
 *     pattern   - [IN] regular expression as a text string. Empty            *
 *                      string ("") is allowed, it will match everything.     *
 *                      NULL is not allowed.                                  *
 *     flags     - [IN] regexp compilation parameters passed to pcre_compile  *
 *                      or pcre2_compile.                                     *
 *                      ZBX_REGEXP_CASELESS, ZBX_REGEXP_NO_AUTO_CAPTURE,      *
 *                      ZBX_REGEXP_MULTILINE.                                 *
 *     regexp    - [OUT] compiled regexp. Can be NULL if only regexp          *
 *                       compilation is checked, Cleanup in caller.           *
 *     err_msg   - [OUT] dynamically allocated error message. Can be NULL to  *
 *                       discard the error message.                           *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	regexp_compile(const char *pattern, int flags, zbx_regexp_t **regexp, char **err_msg)
{
#ifdef HAVE_PCRE_H
	const char	*err_msg_static = NULL;
	int		error_offset = -1;
	pcre		*pcre_regexp;
#endif
#ifdef HAVE_PCRE2_H
	pcre2_code	*pcre2_regexp;
	int		error = 0;
	PCRE2_SIZE 	error_offset = 0;
#endif
#ifdef ZBX_REGEXP_NO_AUTO_CAPTURE
	/* If ZBX_REGEXP_NO_AUTO_CAPTURE bit is set in 'flags' but regular expression contains references to numbered */
	/* capturing groups then reset ZBX_REGEXP_NO_AUTO_CAPTURE bit. */
	/* Otherwise the regular expression might not compile. */

	if (0 != (flags & ZBX_REGEXP_NO_AUTO_CAPTURE))
	{
		const char	*pstart = pattern, *offset;

		while (NULL != (offset = strchr(pstart, '\\')))
		{
			offset++;

			if (('1' <= *offset && *offset <= '9') || 'g' == *offset)
			{
				flags ^= ZBX_REGEXP_NO_AUTO_CAPTURE;
				break;
			}

			if (*offset == '\\')
				offset++;

			pstart = offset;
		}
	}
#endif
#ifdef HAVE_PCRE_H
	if (NULL == (pcre_regexp = pcre_compile(pattern, flags, &err_msg_static, &error_offset, NULL)))
	{
		if (NULL != err_msg)
		{
			*err_msg = zbx_dsprintf(*err_msg, "%s, position %d, flags:0x%x", err_msg_static, error_offset,
					(unsigned int)flags);
		}

		return FAIL;
	}

	if (NULL != regexp)
	{
		struct pcre_extra	*extra;

		if (NULL == (extra = pcre_study(pcre_regexp, 0, &err_msg_static)) && NULL != err_msg_static)
		{
			if (NULL != err_msg)
			{
				*err_msg = zbx_dsprintf(*err_msg, "pcre_study() error: %s, flags:0x%x", err_msg_static,
						(unsigned int)flags);
			}

			pcre_free(pcre_regexp);
			return FAIL;
		}

		*regexp = (zbx_regexp_t *)zbx_malloc(NULL, sizeof(zbx_regexp_t));
		(*regexp)->pcre_regexp = pcre_regexp;
		(*regexp)->extra = extra;
	}
	else
		pcre_free(pcre_regexp);
#endif
#ifdef HAVE_PCRE2_H
	*err_msg = NULL;

	if (NULL == (pcre2_regexp = pcre2_compile((PCRE2_SPTR)pattern, PCRE2_ZERO_TERMINATED,
			ZBX_REGEXP_COMPILE_FLAGS | flags, &error, &error_offset, NULL)))
	{
		*err_msg = decode_pcre2_compile_error(error, error_offset, flags);
		return FAIL;
	}

	if (NULL != regexp)
	{
		pcre2_match_context	*match_ctx;

		if (NULL == (match_ctx = pcre2_match_context_create(NULL)))
		{
			pcre2_code_free(pcre2_regexp);
			*err_msg = zbx_strdup(*err_msg, "cannot create pcre2 match context");
			return FAIL;
		}

		*regexp = (zbx_regexp_t *)zbx_malloc(NULL, sizeof(zbx_regexp_t));
		(*regexp)->pcre2_regexp = pcre2_regexp;
		(*regexp)->match_ctx = match_ctx;
	}
	else
		pcre2_code_free(pcre2_regexp);
#endif
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Compile a regular expression with default options. Capture groups *
 *          are disabled by default (if PCRE_NO_AUTO_CAPTURE is supported).   *
 *          If you need to compile a regular expression that contains capture *
 *          groups use function zbx_regexp_compile_ext() instead.             *
 *                                                                            *
 * Parameters:                                                                *
 *     pattern   - [IN] regular expression as a text string. Empty            *
 *                      string ("") is allowed, it will match everything.     *
 *                      NULL is not allowed.                                  *
 *     regexp    - [OUT] compiled regular expression.                         *
 *     err_msg   - [OUT] error message if any.                                *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_regexp_compile(const char *pattern, zbx_regexp_t **regexp, char **err_msg)
{
#ifdef ZBX_REGEXP_NO_AUTO_CAPTURE
	return regexp_compile(pattern, ZBX_REGEXP_MULTILINE | ZBX_REGEXP_NO_AUTO_CAPTURE, regexp, err_msg);
#else
	return regexp_compile(pattern, ZBX_REGEXP_MULTILINE, regexp, err_msg);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: Compile a regular expression with no or specified regular         *
 *          expression compilation parameters.                                *
 *                                                                            *
 * Parameters:                                                                *
 *     pattern   - [IN] regular expression as a text string. Empty            *
 *                      string ("") is allowed, it will match everything.     *
 *                      NULL is not allowed.                                  *
 *     regexp    - [OUT] compiled regular expression.                         *
 *     flags     - [IN] regexp compilation parameters passed to pcre_compile. *
 *                      ZBX_REGEXP_CASELESS, ZBX_REGEXP_NO_AUTO_CAPTURE,      *
 *                      ZBX_REGEXP_MULTILINE.                                 *
 *     err_msg   - [OUT] error message if any.                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_regexp_compile_ext(const char *pattern, zbx_regexp_t **regexp, int flags, char **err_msg)
{
	return regexp_compile(pattern, flags, regexp, err_msg);
}

/****************************************************************************************************
 *                                                                                                  *
 * Purpose: wrapper for zbx_regexp_compile. Caches and reuses the last used regexp.                 *
 *                                                                                                  *
 ****************************************************************************************************/
static int	regexp_prepare(const char *pattern, int flags, zbx_regexp_t **regexp, char **err_msg)
{
	static ZBX_THREAD_LOCAL zbx_regexp_t	*curr_regexp = NULL;
	static ZBX_THREAD_LOCAL char		*curr_pattern = NULL;
	static ZBX_THREAD_LOCAL int		curr_flags = 0;
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

		if (SUCCEED == regexp_compile(pattern, flags, &curr_regexp, err_msg))
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

/* calculate recursion limit, PCRE man page suggests to reckon on about 500 bytes per recursion */
/* but to be on the safe side - reckon on 800 bytes and do not set limit higher than 100000 */
#define REGEXP_RECURSION_STEP	800
#define REGEXP_RECURSION_LIMIT	100000

static ZBX_THREAD_LOCAL unsigned long	rxp_stacklimit = 0;

/****************************************************************************************************
 *                                                                                                  *
 * Purpose: initialize regular expression execution environment                                     *
 *                                                                                                  *
 ****************************************************************************************************/
void	zbx_init_regexp_env(void)
{
#ifdef HAVE_STACKSIZE
	/* get stack size if configured, otherwise it will use default process stack size */
	if (REGEXP_RECURSION_LIMIT * REGEXP_RECURSION_STEP < (rxp_stacklimit = HAVE_STACKSIZE * ZBX_KIBIBYTE))
		rxp_stacklimit = REGEXP_RECURSION_LIMIT * REGEXP_RECURSION_STEP;
#endif
}

static unsigned long int	compute_recursion_limit(void)
{
	if (0 == rxp_stacklimit)
	{
#if !defined(_WINDOWS) && !defined(__MINGW32__)
#	define REGEXP_RECURSION_DEFAULT	10000	/* if stack size cannot be retrieved then assume ~8 MB */
		struct rlimit	rlim;

		if (0 == getrlimit(RLIMIT_STACK, &rlim))
		{
			if (REGEXP_RECURSION_LIMIT * REGEXP_RECURSION_STEP < (rxp_stacklimit = rlim.rlim_cur))
				rxp_stacklimit = REGEXP_RECURSION_LIMIT * REGEXP_RECURSION_STEP;
		}

		if (0 == rxp_stacklimit)
#else
#	define REGEXP_RECURSION_DEFAULT	2000	/* assume ~1 MB stack and ~500 bytes per recursion */
#endif
			rxp_stacklimit = REGEXP_RECURSION_DEFAULT * REGEXP_RECURSION_STEP;
	}

	return rxp_stacklimit / REGEXP_RECURSION_STEP;
#undef REGEXP_RECURSION_DEFAULT
}

#undef REGEXP_RECURSION_LIMIT
#undef REGEXP_RECURSION_STEP

#if defined(HAVE_PCRE2_H)
static char	*decode_pcre2_match_error(int error_code)
{
	/* 120 code units buffer is recommended in "man pcre2api" */
	const size_t	err_msg_size = 120 * PCRE2_CODE_UNIT_WIDTH / 8;

	char	*err_msg = (char *)zbx_malloc(NULL, err_msg_size);
	int	ret;

	if (0 > (ret = pcre2_get_error_message(error_code, (PCRE2_UCHAR *)err_msg, err_msg_size)))
	{
		zbx_snprintf(err_msg, err_msg_size, "pcre2_get_error_message(%d, ...) failed with error %d",
				error_code, ret);
	}

	return err_msg;
}
#endif

/***********************************************************************************
 *                                                                                 *
 * Purpose: wrapper for pcre_exec() and pcre2_match(), searches for a given        *
 *          pattern, specified by regexp, in the string                            *
 *                                                                                 *
 * Parameters:                                                                     *
 *     string         - [IN] string to be matched against 'regexp'                 *
 *     regexp         - [IN] precompiled regular expression                        *
 *     flags          - [IN] execution flags for matching                          *
 *     count          - [IN] count of elements in matches array                    *
 *     matches        - [OUT] matches (can be NULL if matching results are         *
 *                      not required)                                              *
 *     err_msg        - [OUT] dynamically allocated error message (can be NULL).   *
 *     offset         - [IN] offset in the string at which to start matching       *
 *                                                                                 *
 * Return value: ZBX_REGEXP_MATCH     - successful match                           *
 *               ZBX_REGEXP_NO_MATCH  - no match                                   *
 *               FAIL                 - error occurred                             *
 *                                                                                 *
 ***********************************************************************************/
static int	regexp_exec(const char *string, const zbx_regexp_t *regexp, int flags, int count,
		zbx_regmatch_t *matches, char **err_msg, int offset)
{
#ifdef HAVE_PCRE_H
#define MATCHES_BUFF_SIZE	(ZBX_REGEXP_GROUPS_MAX * 3)		/* see pcre_exec() in "man pcreapi" why 3 */

	int				result, r;
	static ZBX_THREAD_LOCAL int	matches_buff[MATCHES_BUFF_SIZE];
	int				*ovector = NULL;
	int				ovecsize = 3 * count;		/* see pcre_exec() in "man pcreapi" why 3 */
	struct pcre_extra		extra, *pextra;

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
	pextra->match_limit_recursion = compute_recursion_limit();
#endif
	/* see "man pcreapi" about pcre_exec() return value and 'ovector' size and layout */
	if (0 <= (r = pcre_exec(regexp->pcre_regexp, pextra, string, (int)strlen(string), flags,
		offset, ovector, ovecsize)))
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
		if (NULL != err_msg)
		{
			*err_msg = zbx_dsprintf(NULL, "pcre_exec() returned %d. See PCRE library documentation or"
			" \"man pcreapi\", section \"Error return values from pcre_exec()\" for explanation"
			" or /usr/include/pcre.h", r);
		}

		result = FAIL;
	}

	if (ZBX_REGEXP_GROUPS_MAX < count)
		zbx_free(ovector);

	return result;
#undef MATCHES_BUFF_SIZE
#endif
#ifdef HAVE_PCRE2_H
	int			result, r, i;
	pcre2_match_data	*match_data = NULL;
	PCRE2_SIZE		*ovector = NULL;

	pcre2_set_match_limit(regexp->match_ctx, 1000000);

	pcre2_set_recursion_limit(regexp->match_ctx, (uint32_t)compute_recursion_limit());
	match_data = pcre2_match_data_create((uint32_t)count, NULL);

	if (NULL == match_data)
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() cannot create pcre2 match data of size %d", __func__, count);
		result = FAIL;
	}
	else
	{
#ifdef PCRE2_MATCH_INVALID_UTF
		flags |= PCRE2_NO_UTF_CHECK;
#endif

		if (0 <= (r = pcre2_match(regexp->pcre2_regexp, (PCRE2_SPTR)string, PCRE2_ZERO_TERMINATED, offset,
			flags, match_data, regexp->match_ctx)))
		{
			if (NULL != matches)
			{
				ovector = pcre2_get_ovector_pointer(match_data);

				/* have to copy this way because pcre2 ovector uses 8 byte integers,  *
				 * but we want to keep it compatible with existing matches structure, *
				 * which uses 4 byte integers                                         */
				for (i = 0; i < ((0 < r) ? MIN(r, count) : count); i++)
				{
					matches[i].rm_so = (int)ovector[i*2];
					matches[i].rm_eo = (int)ovector[i*2+1];
				}
			}

			result = ZBX_REGEXP_MATCH;
		}
		else if (PCRE2_ERROR_NOMATCH == r)
		{
			result = ZBX_REGEXP_NO_MATCH;
		}
		else
		{
			if (NULL != err_msg)
				*err_msg = decode_pcre2_match_error(r);

			result = FAIL;
		}

		pcre2_match_data_free(match_data);
	}

	return result;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: wrapper for pcre_free                                             *
 *                                                                            *
 * Parameters: regexp - [IN] compiled regular expression                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_regexp_free(zbx_regexp_t *regexp)
{
#ifdef HAVE_PCRE_H
	/* pcre_free_study() was added to the API for release 8.20 while extra was available before */
#ifdef PCRE_CONFIG_JIT
	pcre_free_study(regexp->extra);
#else
	pcre_free(regexp->extra);
#endif
	pcre_free(regexp->pcre_regexp);
#endif
#ifdef HAVE_PCRE2_H
	pcre2_code_free(regexp->pcre2_regexp);
	pcre2_match_context_free(regexp->match_ctx);
#endif
	zbx_free(regexp);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if string matches a precompiled regular expression without *
 *          returning matching groups                                         *
 *                                                                            *
 * Parameters: string - [IN] string to be matched                             *
 *             regexp - [IN] precompiled regular expression                   *
 *                                                                            *
 * Return value: 0 - successful match                                         *
 *               nonzero - no match                                           *
 *                                                                            *
 * Comments: use this function for better performance if many strings need to *
 *           be matched against the same regular expression                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_regexp_match_precompiled(const char *string, const zbx_regexp_t *regexp)
{
	return (ZBX_REGEXP_MATCH == regexp_exec(string, regexp, 0, 0, NULL, NULL, 0)) ? 0 : -1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if string matches a precompiled regular expression without *
 *          returning matching groups                                         *
 *                                                                            *
 * Parameters: string - [IN] string to be matched                             *
 *             regexp - [IN] precompiled regular expression                   *
 *            err_msg - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: ZBX_REGEXP_MATCH     - successful match                      *
 *               ZBX_REGEXP_NO_MATCH  - no match                              *
 *               FAIL                 - error occurred                        *
 *                                                                            *
 * Comments: use this function for better performance if many strings need to *
 *           be matched against the same regular expression                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_regexp_match_precompiled2(const char *string, const zbx_regexp_t *regexp, char **err_msg)
{
	return regexp_exec(string, regexp, 0, 0, NULL, err_msg, 0);
}

/****************************************************************************************************
 *                                                                                                  *
 * Purpose: compiles and executes a regex pattern                                                   *
 *                                                                                                  *
 * Parameters:                                                                                      *
 *     string     - [IN] string to be matched against 'pattern'                                     *
 *     pattern    - [IN] regular expression pattern                                                 *
 *     flags      - [IN] execution flags for matching                                               *
 *     len        - [OUT] length of matched string,                                                 *
 *                      0 in case of no match or                                                    *
 *                      FAIL if an error occurred.                                                  *
 *                                                                                                  *
 * Return value: pointer to the matched substring or null                                           *
 *                                                                                                  *
 * Comments:     Note, that although the input 'string' was const, the return is not, as the caller *
 *               owns it and can modify it. This is similar to strstr() and strcasestr() functions. *
 *               We may need to find a way how to silence the resulting '-Wcast-qual' warning.      *
 *                                                                                                  *
 ****************************************************************************************************/
static char	*zbx_regexp(const char *string, const char *pattern, int flags, int *len)
{
	char		*error = NULL, *c = NULL;
	zbx_regmatch_t	match;
	zbx_regexp_t	*regexp = NULL;

	if (NULL != len)
		*len = FAIL;

	if (SUCCEED != regexp_prepare(pattern, flags, &regexp, &error))
	{
		zbx_free(error);
		return NULL;
	}

	if (NULL != string)
	{
		int	r;

		if (ZBX_REGEXP_MATCH == (r = regexp_exec(string, regexp, 0, 1, &match, NULL, 0)))
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

/****************************************************************************************************
 *                                                                                                  *
 * Purpose: compiles and executes a regex pattern                                                   *
 *                                                                                                  *
 * Parameters:                                                                                      *
 *     string      - [IN] string to be matched against 'pattern'                                    *
 *     pattern     - [IN] regular expression pattern                                                *
 *     flags       - [IN] execution flags for matching                                              *
 *     matched_pos - [OUT] pointer to the matched substring, can be NULL                            *
 *     len         - [OUT] pointer to length of matched string, can be NULL                         *
 *     err_msg     - [OUT] error message. Deallocate in caller.                                     *
 *                                                                                                  *
 * Return value: if success:                                                                        *
 *                   ZBX_REGEXP_MATCH or                                                            *
 *                   ZBX_REGEXP_NO_MATCH                                                            *
 *               if errors:                                                                         *
 *                   ZBX_REGEXP_COMPILE_FAIL or                                                     *
 *                   ZBX_REGEXP_RUNTIME_FAIL with error message in 'err_msg'                        *
 *                                                                                                  *
 ****************************************************************************************************/
static int	zbx_regexp2(const char *string, const char *pattern, int flags, char **matched_pos, int *len,
		char **err_msg)
{
	zbx_regmatch_t	match;
	zbx_regexp_t	*regexp = NULL;
	int		r;

	if (SUCCEED != regexp_prepare(pattern, flags, &regexp, err_msg))
		return ZBX_REGEXP_COMPILE_FAIL;

	/* 'regexp' ownership was taken by regexp_prepare(), do not cleanup */

	if (ZBX_REGEXP_MATCH == (r = regexp_exec(string, regexp, 0, 1, &match, err_msg, 0)))
	{
		if (NULL != matched_pos)
			*matched_pos = (char *)(uintptr_t)string + match.rm_so;

		if (NULL != len)
			*len = match.rm_eo - match.rm_so;

		return ZBX_REGEXP_MATCH;
	}

	if (ZBX_REGEXP_NO_MATCH == r)
	{
		if (NULL != len)
			*len = 0;

		return ZBX_REGEXP_NO_MATCH;
	}

	return ZBX_REGEXP_RUNTIME_FAIL;
}

/*************************************************************************************************
 *                                                                                               *
 *  Comments: Note, that although the input 'string' was const, the return is not, as the caller *
 *            owns it and can modify it. This is similar to strstr() and strcasestr() functions. *
 *            We may need to find a way how to silence the resulting '-Wcast-qual' warning.      *
 *                                                                                               *
 *************************************************************************************************/
char	*zbx_regexp_match(const char *string, const char *pattern, int *len)
{
	return zbx_regexp(string, pattern, ZBX_REGEXP_MULTILINE, len);
}

/******************************************************************************
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
 *             group_check     - [IN] check if pattern matches but does not      *
 *                                    contain group to capture and return NULL   *
 *                                                                               *
 * Return value: Allocated string containing output value                        *
 *                                                                               *
 *********************************************************************************/
static char	*regexp_sub_replace(const char *text, const char *output_template, zbx_regmatch_t *match, int nmatch,
		size_t limit, zbx_regexp_group_check_t group_check)
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
				strncpy_alloc(&ptr, &size, &offset, pstart, (size_t)(pgroup - pstart), limit);
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
				strncpy_alloc(&ptr, &size, &offset, pstart, (size_t)(pgroup - pstart - 1), limit);
				group_index = *pgroup - '0';
				if (group_index < nmatch && -1 != match[group_index].rm_so)
				{
					strncpy_alloc(&ptr, &size, &offset, text + match[group_index].rm_so,
							(size_t)(match[group_index].rm_eo - match[group_index].rm_so),
							limit);
				}
				else if (ZBX_REGEXP_GROUP_CHECK_ENABLE == group_check)
				{
					zbx_free(ptr);
					goto out;
				}
				pstart = pgroup + 1;
				continue;

			default:
				strncpy_alloc(&ptr, &size, &offset, pstart, (size_t)(pgroup - pstart), limit);
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
 *            group_check      - [IN] check if pattern matches but does not      *
 *                                    contain group to capture                   *
 *            out              - [OUT] the output value if the input string      *
 *                                     matches the specified regular expression  *
 *                                     or NULL otherwise                         *
 *                                                                               *
 * Return value: SUCCEED - the regular expression match was done                 *
 *               FAIL    - failed to compile regexp                              *
 *                                                                               *
 *********************************************************************************/
static int	regexp_sub(const char *string, const char *pattern, const char *output_template, int flags,
		zbx_regexp_group_check_t group_check, char **out)
{
	char		*error = NULL;
	zbx_regexp_t	*regexp = NULL;
	zbx_regmatch_t	match[ZBX_REGEXP_GROUPS_MAX];
	unsigned int	i;

	if (NULL == string)
	{
		zbx_free(*out);
		return SUCCEED;
	}

#ifdef ZBX_REGEXP_NO_AUTO_CAPTURE
	/* no subpatterns without an output template */
	if (NULL == output_template || '\0' == *output_template)
		flags |= ZBX_REGEXP_NO_AUTO_CAPTURE;
#endif

	if (FAIL == regexp_prepare(pattern, flags, &regexp, &error))
	{
		zbx_free(error);
		return FAIL;
	}

	zbx_free(*out);

	/* -1 is special pcre value for unused patterns */
	for (i = 0; i < ARRSIZE(match); i++)
		match[i].rm_so = match[i].rm_eo = -1;

	if (ZBX_REGEXP_MATCH == regexp_exec(string, regexp, 0, ZBX_REGEXP_GROUPS_MAX, match, NULL, 0))
		*out = regexp_sub_replace(string, output_template, match, ZBX_REGEXP_GROUPS_MAX, 0, group_check);

	return SUCCEED;
}

/*********************************************************************************
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
 *            err_msg          - [OUT] error message. Deallocate in caller.      *
 *                                                                               *
 * Return value: if success:                                                     *
 *                   ZBX_REGEXP_MATCH or                                         *
 *                   ZBX_REGEXP_NO_MATCH                                         *
 *               if errors:                                                      *
 *                   ZBX_REGEXP_COMPILE_FAIL or                                  *
 *                   ZBX_REGEXP_RUNTIME_FAIL with error message in 'err_msg'     *
 *                                                                               *
 *********************************************************************************/
static int	regexp_sub2(const char *string, const char *pattern, const char *output_template, int flags, char **out,
		char **err_msg)
{
	zbx_regexp_t	*regexp = NULL;
	zbx_regmatch_t	match[ZBX_REGEXP_GROUPS_MAX];
	unsigned int	i;
	int		ret;

#ifdef ZBX_REGEXP_NO_AUTO_CAPTURE
	/* no subpatterns without an output template */
	if (NULL == output_template || '\0' == *output_template)
		flags |= ZBX_REGEXP_NO_AUTO_CAPTURE;
#endif

	if (SUCCEED != regexp_prepare(pattern, flags, &regexp, err_msg))
		return ZBX_REGEXP_COMPILE_FAIL;

	zbx_free(*out);

	/* -1 is special pcre value for unused patterns */
	for (i = 0; i < ARRSIZE(match); i++)
		match[i].rm_so = match[i].rm_eo = -1;

	/* 'regexp' ownership was taken by regexp_prepare(), do not cleanup */

	if (ZBX_REGEXP_MATCH == (ret = regexp_exec(string, regexp, 0, ZBX_REGEXP_GROUPS_MAX, match, err_msg, 0)))
	{
		*out = regexp_sub_replace(string, output_template, match, ZBX_REGEXP_GROUPS_MAX, 0,
				ZBX_REGEXP_GROUP_CHECK_DISABLE);
	}

	if (FAIL == ret)
		ret = ZBX_REGEXP_RUNTIME_FAIL;

	return ret;	/* ZBX_REGEXP_MATCH, ZBX_REGEXP_NO_MATCH or ZBX_REGEXP_RUNTIME_FAIL */
}

/*********************************************************************************
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

	if (ZBX_REGEXP_MATCH == regexp_exec(string, regexp, 0, ZBX_REGEXP_GROUPS_MAX, match, NULL, 0) &&
			NULL != (*out = regexp_sub_replace(string, output_template, match, ZBX_REGEXP_GROUPS_MAX,
			limit, ZBX_REGEXP_GROUP_CHECK_DISABLE)))
	{
		return SUCCEED;
	}

	return FAIL;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: Test if a string matches the specified regular expression. If yes    *
 *          then all matches in incoming string are replaced with values based   *
 *          on repleacement template. For each match replacement value created   *
 *          by substituting '\<n>' sequences in output template with the mtach   *
 *          captured groups.                                                     *
 *                                                                               *
 * Parameters: string          - [IN] the string to replace                      *
 *             pattern         - [IN] the regular expression                     *
 *             repl_template   - [IN] the repleacement template used to          *
 *                                    construct replacement string for each      *
 *                                    match. If output template is NULL then     *
 *                                    empty string is used as template.          *
 *             out             - [OUT] the output string with replaced matches   *
 *                                                                               *
 * Return value: SUCCEED - the regular expression match was done                 *
 *               FAIL    - failed to compile regexp                              *
 *                                                                               *
 * Comments: This function performs case sensitive match                         *
 *                                                                               *
 *********************************************************************************/
int	zbx_regexp_repl(const char *string, const char *pattern, const char *output_template, char **out)
{
#ifdef HAVE_PCRE2_H
#define ZBX_REGEX_REPL_TIMEOUT	3	/* Regex matches processing timeout in seconds */
	zbx_regexp_t		*regexp = NULL;
	int			mi, shift = 0, ret = FAIL, len = strlen(string);
	char			*out_str, *error = NULL;
	zbx_vector_match_t	matches;
	size_t			i;
	double			starttime = zbx_time();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() len:%d", __func__, len);

	if ('\0' == *pattern)
	{
		*out = zbx_strdup(*out, string);
		return SUCCEED;
	}

	if (FAIL == regexp_prepare(pattern, ZBX_REGEXP_MULTILINE, &regexp, &error))
	{
		zbx_free(error);
		return FAIL;
	}

	zbx_vector_match_create(&matches);

	/* collect all matches */
	for (;;)
	{
		zbx_match_t	*match;

		match = zbx_malloc(NULL, sizeof(zbx_match_t));
		/* -1 is special pcre value for unused patterns */
		for (i = 0; i < ARRSIZE(match->groups); i++)
			match->groups[i].rm_so = match->groups[i].rm_eo = -1;

		if (ZBX_REGEXP_MATCH != regexp_exec(string, regexp, 0, ZBX_REGEXP_GROUPS_MAX, match->groups, NULL,
				shift))
		{
			zbx_free(match);
			break;
		}

		shift = match->groups[0].rm_eo;

		zbx_vector_match_append(&matches, match);
		if (shift >= len)
			break;

		if (shift == match->groups[0].rm_so)
			shift++;

		if (ZBX_REGEX_REPL_TIMEOUT < zbx_time() - starttime)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "timeout after %d matches %s()",
					matches.values_num, __func__);
			goto out;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "replacing:%d matches %s()", matches.values_num, __func__);
	out_str = zbx_strdup(NULL, string);
	/* create pattern based string for each match and relplace matched string with this string */
	for (mi = matches.values_num - 1; 0 <= mi; mi--)
	{
		zbx_regmatch_t	*groups = matches.values[mi]->groups;
		char		*replace, *ptr;

		if ('\0' == *output_template)
		{
			replace = zbx_strdup(NULL, output_template);
		}
		else
		{
			replace = regexp_sub_replace(string, output_template, groups, ZBX_REGEXP_GROUPS_MAX,
					MAX_EXECUTE_OUTPUT_LEN, ZBX_REGEXP_GROUP_CHECK_DISABLE);
		}

		if (NULL != replace)
		{
			size_t	replen = strlen(replace), outlen = strlen(out_str), length = outlen + replen + 1,
				eo = (size_t)groups[0].rm_eo;

			if (MAX_EXECUTE_OUTPUT_LEN <= length)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "macro function output exceeded limit of %d Kb",
						MAX_EXECUTE_OUTPUT_LEN / ZBX_KIBIBYTE);
				zbx_free(out_str);
				zbx_free(replace);
				goto out;
			}
			ptr = (char *)zbx_malloc(NULL, length);
			if (0 != (size_t)groups[0].rm_so)
				memcpy(ptr, out_str, (size_t)groups[0].rm_so);
			if (0 != replen)
				memcpy(ptr + groups[0].rm_so, replace, replen);
			memcpy(ptr + groups[0].rm_so + replen, out_str + eo, outlen - eo + 1);

			zbx_free(out_str);
			out_str = ptr;
			zbx_free(replace);
		}
		else
		{
			zbx_free(out_str);
			goto out;
		}
	}
	ret = SUCCEED;
	zbx_free(*out);
	*out = out_str;

out:
	zbx_vector_match_clear_ext(&matches, zbx_match_free);
	zbx_vector_match_destroy(&matches);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#undef ZBX_REGEX_REPL_TIMEOUT
#else
	ZBX_UNUSED(string);
	ZBX_UNUSED(pattern);
	ZBX_UNUSED(output_template);
	ZBX_UNUSED(out);

	return FAIL;
#endif
}

/*********************************************************************************
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
 *             out             - [OUT] the output value if the input string      *
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
	return regexp_sub(string, pattern, output_template, ZBX_REGEXP_MULTILINE, ZBX_REGEXP_GROUP_CHECK_DISABLE, out);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: This function is similar to zbx_regexp_sub() with exception that     *
 *          multiline matches are accepted.                                      *
 *                                                                               *
 *********************************************************************************/
int	zbx_mregexp_sub(const char *string, const char *pattern, const char *output_template,
		zbx_regexp_group_check_t group_check, char **out)
{
	return regexp_sub(string, pattern, output_template, 0, group_check, out);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: This function is similar to zbx_regexp_sub() with exception that     *
 *          case insensitive matches are accepted.                               *
 *                                                                               *
 *********************************************************************************/
int	zbx_iregexp_sub(const char *string, const char *pattern, const char *output_template, char **out)
{
	return regexp_sub(string, pattern, output_template, ZBX_REGEXP_CASELESS, ZBX_REGEXP_GROUP_CHECK_DISABLE, out);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees expression data retrieved by DCget_expressions function or  *
 *          prepared with zbx_add_regexp_ex() function calls                  *
 *                                                                            *
 * Parameters: expressions  - [IN] a vector of expression data pointers       *
 *                                                                            *
 ******************************************************************************/
void	zbx_regexp_clean_expressions(zbx_vector_expression_t *expressions)
{
	int	i;

	for (i = 0; i < expressions->values_num; i++)
	{
		zbx_expression_t	*regexp = expressions->values[i];

		zbx_free(regexp->name);
		zbx_free(regexp->expression);
		zbx_free(regexp);
	}

	zbx_vector_expression_clear(expressions);
}

void	zbx_add_regexp_ex(zbx_vector_expression_t *regexps, const char *name, const char *expression,
		int expression_type, char exp_delimiter, int case_sensitive)
{
	zbx_expression_t	*regexp;

	regexp = zbx_malloc(NULL, sizeof(zbx_expression_t));

	regexp->name = zbx_strdup(NULL, name);
	regexp->expression = zbx_strdup(NULL, expression);

	regexp->expression_type = expression_type;
	regexp->exp_delimiter = exp_delimiter;
	regexp->case_sensitive = case_sensitive;

	zbx_vector_expression_append(regexps, regexp);
}

/**********************************************************************************
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
	int	regexp_flags = ZBX_REGEXP_MULTILINE, ret = FAIL;

	if (ZBX_IGNORE_CASE == case_sensitive)
		regexp_flags |= ZBX_REGEXP_CASELESS;

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
		if (SUCCEED == regexp_sub(string, pattern, output_template, regexp_flags,
				ZBX_REGEXP_GROUP_CHECK_DISABLE, output))
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
 *             err_msg        - [OUT] dynamically allocated error message         *
 *                                                                                *
 * Return value: ZBX_REGEXP_MATCH    - the string matches the specified regular   *
 *                                     expression                                 *
 *               ZBX_REGEXP_NO_MATCH - the string does not match the regular      *
 *                                     expression                                 *
 *               If errors:                                                       *
 *               ZBX_REGEXP_COMPILE_FAIL or                                       *
 *               ZBX_REGEXP_RUNTIME_FAIL with error message in 'err_msg'          *
 *                                                                                *
 **********************************************************************************/
static int	regexp_match_ex_regsub2(const char *string, const char *pattern, int case_sensitive,
		const char *output_template, char **output, char **err_msg)
{
	int	regexp_flags = ZBX_REGEXP_MULTILINE, ret;
	char	*err_msg_local = NULL;

	if (ZBX_IGNORE_CASE == case_sensitive)
		regexp_flags |= ZBX_REGEXP_CASELESS;

	if (NULL == output)
		ret = zbx_regexp2(string, pattern, regexp_flags, NULL, NULL, &err_msg_local);
	else
		ret = regexp_sub2(string, pattern, output_template, regexp_flags, output, &err_msg_local);

	if (ZBX_REGEXP_MATCH == ret || ZBX_REGEXP_NO_MATCH == ret)
		return ret;

	if (NULL != err_msg)
	{
		*err_msg = zbx_dsprintf(*err_msg, "%s regular expression: %s", (ZBX_REGEXP_COMPILE_FAIL == ret) ?
				"Invalid" : "Error occurred while matching", err_msg_local);
	}

	zbx_free(err_msg_local);

	return ret;
}

/**********************************************************************************
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
	const char	*ptr = NULL;

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
/* regular expressions */
#define EXPRESSION_TYPE_INCLUDED	0
#define EXPRESSION_TYPE_ANY_INCLUDED	1
#define EXPRESSION_TYPE_NOT_INCLUDED	2
#define EXPRESSION_TYPE_TRUE		3
#define EXPRESSION_TYPE_FALSE		4
int	zbx_regexp_sub_ex(const zbx_vector_expression_t *regexps, const char *string, const char *pattern,
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

/**********************************************************************************
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
 *             err_msg        - [OUT] dynamically allocated error message         *
 *                                                                                *
 * Return value: ZBX_REGEXP_MATCH    - the string matches the specified regular   *
 *                                     expression                                 *
 *               ZBX_REGEXP_NO_MATCH - the string does not match the specified    *
 *                                     regular expression                         *
 *               If errors:                                                       *
 *               ZBX_REGEXP_COMPILE_FAIL or                                       *
 *               ZBX_REGEXP_RUNTIME_FAIL with error message in 'err_msg'          *
 *                                                                                *
 * Comments: For regular expressions and global regular expressions with 'Result  *
 *           is TRUE' type the 'output_template' substitution result is stored    *
 *           into 'output' variable. For other global regular expression types    *
 *           the whole string is stored into 'output' variable.                   *
 *                                                                                *
 **********************************************************************************/
int	zbx_regexp_sub_ex2(const zbx_vector_expression_t *regexps, const char *string, const char *pattern,
		int case_sensitive, const char *output_template, char **output, char **err_msg)
{
	int	i, ret = ZBX_REGEXP_NO_MATCH;
	char	*output_accu = NULL;	/* accumulator for 'output' when looping over global regexp subexpressions */

	if (NULL == pattern || '\0' == *pattern)
	{
		/* always match when no pattern is specified */
		ret = ZBX_REGEXP_MATCH;
		goto out;
	}

	if ('@' != *pattern)				/* not a global regexp */
	{
		ret = regexp_match_ex_regsub2(string, pattern, case_sensitive, output_template, output, err_msg);
		goto out;
	}

	pattern++;

	for (i = 0; i < regexps->values_num; i++)	/* loop over global regexp subexpressions */
	{
		const zbx_expression_t	*regexp = (const zbx_expression_t *)regexps->values[i];

		if (0 != strcmp(regexp->name, pattern))
			continue;

		switch (regexp->expression_type)
		{
			case EXPRESSION_TYPE_TRUE:
				if (NULL != output)
				{
					char	*output_tmp = NULL;

					if (ZBX_REGEXP_MATCH == (ret = regexp_match_ex_regsub2(string,
							regexp->expression, regexp->case_sensitive, output_template,
							&output_tmp, err_msg)))
					{
						zbx_free(output_accu);
						output_accu = output_tmp;
					}
				}
				else
				{
					ret = regexp_match_ex_regsub2(string, regexp->expression,
							regexp->case_sensitive, NULL, NULL, err_msg);
				}

				if (ZBX_REGEXP_COMPILE_FAIL == ret || ZBX_REGEXP_RUNTIME_FAIL == ret)
				{
					zbx_free(output_accu);
					return ret;
				}

				break;
			case EXPRESSION_TYPE_FALSE:
				ret = regexp_match_ex_regsub2(string, regexp->expression, regexp->case_sensitive,
						NULL, NULL, err_msg);

				if (ZBX_REGEXP_MATCH == ret)	/* invert output value */
				{
					ret = ZBX_REGEXP_NO_MATCH;
				}
				else if (ZBX_REGEXP_NO_MATCH == ret)
				{
					ret = ZBX_REGEXP_MATCH;
				}
				else if (ZBX_REGEXP_COMPILE_FAIL == ret || ZBX_REGEXP_RUNTIME_FAIL == ret)
				{
					zbx_free(output_accu);
					return ret;
				}

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
				zabbix_log(LOG_LEVEL_WARNING, "%s() Invalid regular expression_type: %d, name:'%s',"
						" expression:'%s'", __func__, regexp->expression_type, regexp->name,
						regexp->expression);

				if (NULL != err_msg)
				{
					*err_msg = zbx_dsprintf(*err_msg, "Invalid regular expression type: %d",
							regexp->expression_type);
				}

				zbx_free(output_accu);
				THIS_SHOULD_NEVER_HAPPEN;

				return ZBX_REGEXP_COMPILE_FAIL;	/* to make it NOTSUPPORTED */
		}

		if (ZBX_REGEXP_NO_MATCH == ret)
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
#undef EXPRESSION_TYPE_INCLUDED
#undef EXPRESSION_TYPE_ANY_INCLUDED
#undef EXPRESSION_TYPE_NOT_INCLUDED
#undef EXPRESSION_TYPE_TRUE
#undef EXPRESSION_TYPE_FALSE

int	zbx_regexp_match_ex(const zbx_vector_expression_t *regexps, const char *string, const char *pattern,
		int case_sensitive)
{
	return zbx_regexp_sub_ex(regexps, string, pattern, case_sensitive, NULL, NULL);
}

int	zbx_global_regexp_exists(const char *name, const zbx_vector_expression_t *regexps)
{
	int	i;

	for (i = 0; i < regexps->values_num; i++)
	{
		const zbx_expression_t	*regexp = regexps->values[i];

		if (0 == strcmp(regexp->name, name))
			return SUCCEED;
	}

	return FAIL;
}

/**********************************************************************************
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
 * Purpose: escaping of symbols for using in regular expression                   *
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

/**********************************************************************************
 *                                                                                *
 * Purpose: remove repeated wildcard characters from the expression               *
 *                                                                                *
 * Parameters: str - [IN/OUT] the string to update                                *
 *                                                                                *
 **********************************************************************************/
void	zbx_wildcard_minimize(char *str)
{
	char	*p1, *p2;
	int	w = 0;

	for (p1 = p2 = str; '\0' != *p2; p2++)
	{
		if ('*' == *p2)
		{
			if (0 != w)
				continue;

			w = 1;
		}
		else
			w = 0;

		*p1 = *p2;
		p1++;
	}

	*p1 = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: Matches string value to specified wildcard.                       *
 *          Asterisk (*) characters match to any characters of any length.    *
 *                                                                            *
 * Parameters: value    - [IN] string to match                                *
 *             wildcard - [IN] wildcard string expression                     *
 *                                                                            *
 * Return value: 1 - value match the wildcard                                 *
 *               0 - otherwise                                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_wildcard_match(const char *value, const char *wildcard)
{
	const char *s_pivot = value, *w_pivot = wildcard;

	while ('\0' != *value && '*' != *wildcard)
	{
		if (*value++ != *wildcard++)
			return 0;
	}

	while ('\0' != *value)
	{
		if ('*' == *wildcard)
		{
			wildcard++;

			if ('\0' == *wildcard)
				return 1;

			w_pivot = wildcard;
			s_pivot = value + 1;
		}
		else if (*value == *wildcard)
		{
			value++;
			wildcard++;
		}
		else
		{
			wildcard = w_pivot;
			value = s_pivot++;
		}
	}

	while ('*' == *wildcard)
		wildcard++;

	return '\0' == *wildcard;
}
