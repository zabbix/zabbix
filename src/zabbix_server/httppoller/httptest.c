/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "db.h"
#include "log.h"
#include "dbcache.h"

#include "zbxserver.h"
#include "httpmacro.h"
#include "httptest.h"
#include "zbxregexp.h"

#define PUNYCODE_BASE		36
#define PUNYCODE_BASE_MAX	35
#define PUNYCODE_TMIN		1
#define PUNYCODE_TMAX		26
#define PUNYCODE_SKEW		38
#define PUNYCODE_DAMP		700
#define PUNYCODE_INITIAL_N	128
#define PUNYCODE_INITIAL_BIAS	72
#define PUNYCODE_BIAS_LIMIT	(((PUNYCODE_BASE_MAX) * PUNYCODE_TMAX) / 2)
#define PUNYCODE_MAX_UINT32	((uint32_t)-1)

typedef struct
{
	char	*data;
	size_t	allocated;
	size_t	offset;
}
zbx_httppage_t;

typedef struct
{
	long   	rspcode;
	double 	total_time;
	double 	speed_download;
	double	test_total_time;
	int	test_last_step;
}
zbx_httpstat_t;

extern int	CONFIG_HTTPPOLLER_FORKS;
extern char	*CONFIG_SOURCE_IP;

#ifdef HAVE_LIBCURL

extern char	*CONFIG_SSL_CA_LOCATION;
extern char	*CONFIG_SSL_CERT_LOCATION;
extern char	*CONFIG_SSL_KEY_LOCATION;

#define ZBX_RETRIEVE_MODE_CONTENT	0
#define ZBX_RETRIEVE_MODE_HEADERS	1

static zbx_httppage_t	page;

static size_t	WRITEFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	ZBX_UNUSED(userdata);

	/* first piece of data */
	if (NULL == page.data)
	{
		page.allocated = MAX(8096, r_size);
		page.offset = 0;
		page.data = zbx_malloc(page.data, page.allocated);
	}

	zbx_strncpy_alloc(&page.data, &page.allocated, &page.offset, ptr, r_size);

	return r_size;
}

static size_t	HEADERFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);

	return size * nmemb;
}

#endif	/* HAVE_LIBCURL */

/******************************************************************************
 *                                                                            *
 * Function: httptest_remove_macros                                           *
 *                                                                            *
 * Purpose: remove all macro variables cached during http test execution      *
 *                                                                            *
 * Parameters: httptest - [IN] the http test data                             *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static void	httptest_remove_macros(zbx_httptest_t *httptest)
{
	int	i;

	for (i = 0; i < httptest->macros.values_num; i++)
	{
		zbx_ptr_pair_t	*pair = &httptest->macros.values[i];

		zbx_free(pair->first);
		zbx_free(pair->second);
	}

	zbx_vector_ptr_pair_clear(&httptest->macros);
}

static void	process_test_data(zbx_uint64_t httptestid, int lastfailedstep, double speed_download,
		const char *err_str, zbx_timespec_t *ts)
{
	const char	*__function_name = "process_test_data";

	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	types[3], states[3];
	DC_ITEM		items[3];
	zbx_uint64_t	itemids[3];
	int		lastclocks[3], errcodes[3];
	size_t		i, num = 0;
	AGENT_RESULT	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select type,itemid from httptestitem where httptestid=" ZBX_FS_UI64, httptestid);

	while (NULL != (row = DBfetch(result)))
	{
		if (3 == num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			break;
		}

		switch (types[num] = (unsigned char)atoi(row[0]))
		{
			case ZBX_HTTPITEM_TYPE_SPEED:
			case ZBX_HTTPITEM_TYPE_LASTSTEP:
				break;
			case ZBX_HTTPITEM_TYPE_LASTERROR:
				if (NULL == err_str)
					continue;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
		}

		ZBX_STR2UINT64(itemids[num], row[1]);
		num++;
	}
	DBfree_result(result);

	DCconfig_get_items_by_itemids(items, itemids, errcodes, num, ZBX_FLAG_ITEM_FIELDS_DEFAULT);

	for (i = 0; i < num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		if (ITEM_STATUS_ACTIVE != items[i].status)
			continue;

		if (HOST_STATUS_MONITORED != items[i].host.status)
			continue;

		if (HOST_MAINTENANCE_STATUS_ON == items[i].host.maintenance_status &&
				MAINTENANCE_TYPE_NODATA == items[i].host.maintenance_type)
		{
			continue;
		}

		init_result(&value);

		switch (types[i])
		{
			case ZBX_HTTPITEM_TYPE_SPEED:
				SET_UI64_RESULT(&value, speed_download);
				break;
			case ZBX_HTTPITEM_TYPE_LASTSTEP:
				SET_UI64_RESULT(&value, lastfailedstep);
				break;
			case ZBX_HTTPITEM_TYPE_LASTERROR:
				SET_STR_RESULT(&value, zbx_strdup(NULL, err_str));
				break;
		}

		items[i].state = ITEM_STATE_NORMAL;
		dc_add_history(items[i].itemid, 0, &value, ts, items[i].state, NULL);

		states[i] = items[i].state;
		lastclocks[i] = ts->sec;

		free_result(&value);
	}

	DCrequeue_items(itemids, states, lastclocks, NULL, NULL, errcodes, num);

	DCconfig_clean_items(items, errcodes, num);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	process_step_data(zbx_uint64_t httpstepid, zbx_httpstat_t *stat, zbx_timespec_t *ts)
{
	const char	*__function_name = "process_step_data";

	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	types[3], states[3];
	DC_ITEM		items[3];
	zbx_uint64_t	itemids[3];
	int		lastclocks[3], errcodes[3];
	size_t		i, num = 0;
	AGENT_RESULT    value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() rspcode:%ld time:" ZBX_FS_DBL " speed:" ZBX_FS_DBL,
			__function_name, stat->rspcode, stat->total_time, stat->speed_download);

	result = DBselect("select type,itemid from httpstepitem where httpstepid=" ZBX_FS_UI64, httpstepid);

	while (NULL != (row = DBfetch(result)))
	{
		if (3 == num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			break;
		}

		if (ZBX_HTTPITEM_TYPE_RSPCODE != (types[num] = (unsigned char)atoi(row[0])) &&
				ZBX_HTTPITEM_TYPE_TIME != types[num] && ZBX_HTTPITEM_TYPE_SPEED != types[num])
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		ZBX_STR2UINT64(itemids[num], row[1]);
		num++;
	}
	DBfree_result(result);

	DCconfig_get_items_by_itemids(items, itemids, errcodes, num, ZBX_FLAG_ITEM_FIELDS_DEFAULT);

	for (i = 0; i < num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		if (ITEM_STATUS_ACTIVE != items[i].status)
			continue;

		if (HOST_STATUS_MONITORED != items[i].host.status)
			continue;

		if (HOST_MAINTENANCE_STATUS_ON == items[i].host.maintenance_status &&
				MAINTENANCE_TYPE_NODATA == items[i].host.maintenance_type)
		{
			continue;
		}

		init_result(&value);

		switch (types[i])
		{
			case ZBX_HTTPITEM_TYPE_RSPCODE:
				SET_UI64_RESULT(&value, stat->rspcode);
				break;
			case ZBX_HTTPITEM_TYPE_TIME:
				SET_DBL_RESULT(&value, stat->total_time);
				break;
			case ZBX_HTTPITEM_TYPE_SPEED:
				SET_DBL_RESULT(&value, stat->speed_download);
				break;
		}

		items[i].state = ITEM_STATE_NORMAL;
		dc_add_history(items[i].itemid, 0, &value, ts, items[i].state, NULL);

		states[i] = items[i].state;
		lastclocks[i] = ts->sec;

		free_result(&value);
	}

	DCrequeue_items(itemids, states, lastclocks, NULL, NULL, errcodes, num);

	DCconfig_clean_items(items, errcodes, num);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#ifdef HAVE_LIBCURL
static void	add_headers(char *headers, struct curl_slist **headers_slist)
{
	char      *p_begin;

	p_begin = headers;

	while ('\0' != *p_begin)
	{
		char    c, *p_end, *line;

		while ('\r' == *p_begin || '\n' == *p_begin)
			p_begin++;

		p_end = p_begin;

		while ('\0' != *p_end && '\r' != *p_end && '\n' != *p_end)
			p_end++;

		if (p_begin == p_end)
			break;

		if ('\0' != (c = *p_end))
			*p_end = '\0';
		line = zbx_strdup(NULL, p_begin);
		if ('\0' != c)
			*p_end = c;

		zbx_lrtrim(line, " \t");
		if ('\0' != *line)
			*headers_slist = curl_slist_append(*headers_slist, line);
		zbx_free(line);

		p_begin = p_end;
	}
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: httpstep_pairs_join                                              *
 *                                                                            *
 * Purpose: performs concatenation of vector of pairs into delimited string   *
 *                                                                            *
 * Parameters: str             - [IN/OUT] result string                       *
 *             alloc_len       - [IN/OUT] allocated memory size               *
 *             offset          - [IN/OUT] offset within string                *
 *             value_delimiter - [IN] delimiter to be used between name and   *
 *                                    value                                   *
 *             pair_delimiter  - [IN] delimiter to be used between pairs      *
 *             pairs           - [IN] vector of pairs                         *
 *                                                                            *
 ******************************************************************************/
static void	httpstep_pairs_join(char **str, size_t *alloc_len, size_t *offset, char *value_delimiter,
		char *pair_delimiter, zbx_vector_ptr_pair_t *pairs)
{
	int	p;
	char	*key, *value;

	for (p = 0; p < pairs->values_num; p++)
	{
		key = (char*)pairs->values[p].first;
		value = (char*)pairs->values[p].second;

		if (0 != p)
			zbx_strcpy_alloc(str, alloc_len, offset, pair_delimiter);

		zbx_strcpy_alloc(str, alloc_len, offset, key);
		zbx_strcpy_alloc(str, alloc_len, offset, value_delimiter);
		zbx_strcpy_alloc(str, alloc_len, offset, value);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: httppairs_free                                                   *
 *                                                                            *
 * Purpose: frees memory allocated for vector of pairs                        *
 *                                                                            *
 * Parameters: pairs           - [IN] vector of pairs                         *
 *                                                                            *
 ******************************************************************************/
static void	httppairs_free(zbx_vector_ptr_pair_t *pairs)
{
	int	p;

	for (p = 0; p < pairs->values_num; p++)
	{
		zbx_free(pairs->values[p].first);
		zbx_free(pairs->values[p].second);
	}

	zbx_vector_ptr_pair_destroy(pairs);
}

/******************************************************************************
 *                                                                            *
 * Function: httptest_load_pairs                                              *
 *                                                                            *
 * Purpose: loads http fields of web scenario                                 *
 *                                                                            *
 * Parameters: host            - [IN] host to be used in macro expansion      *
 *             httptest        - [IN/OUT] web scenario                        *
 *                                                                            *
 * Return value: SUCCEED if http fields were loaded and macro expansion was   *
 *               successful. FAIL on error.                                   *
 *                                                                            *
 ******************************************************************************/
static int	httptest_load_pairs(DC_HOST *host, zbx_httptest_t *httptest)
{
	int			type, ret = SUCCEED;
	DB_RESULT		result;
	DB_ROW			row;
	size_t			alloc_len = 0, offset;
	zbx_ptr_pair_t		pair;
	zbx_vector_ptr_pair_t	*vector, headers;
	char			*key, *value;

	zbx_vector_ptr_pair_create(&headers);
	zbx_vector_ptr_pair_create(&httptest->variables);

	httptest->headers = NULL;
	result = DBselect(
			"select name, value, type"
			" from httptest_field"
			" where httptestid=" ZBX_FS_UI64
			" order by httptest_fieldid",
			httptest->httptest.httptestid);

	while (NULL != (row = DBfetch(result)))
	{
		type = atoi(row[2]);
		value = zbx_strdup(NULL, row[1]);

		/* from now on variable values can contain macros so proper URL encoding can be performed */
		if (SUCCEED != (ret = substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL,
				&value, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0)))
		{
			zbx_free(value);
			goto out;
		}

		key = zbx_strdup(NULL, row[0]);

		/* variable names cannot contain macros, and both variable names and variable values cannot contain */
		/* another variables */
		if (ZBX_HTTPFIELD_VARIABLE != type && SUCCEED != (ret = substitute_simple_macros(NULL, NULL, NULL,
				NULL, NULL, host, NULL, NULL, &key, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0)))
		{
			httppairs_free(&httptest->variables);
			zbx_free(key);
			zbx_free(value);
			goto out;
		}

		switch (type)
		{
			case ZBX_HTTPFIELD_HEADER:
				vector = &headers;
				break;
			case ZBX_HTTPFIELD_VARIABLE:
				vector = &httptest->variables;
				break;
			default:
				zbx_free(key);
				zbx_free(value);
				ret = FAIL;
				goto out;
		}

		pair.first = key;
		pair.second = value;

		zbx_vector_ptr_pair_append(vector, pair);
	}

	httpstep_pairs_join(&httptest->headers, &alloc_len, &offset, ":", "\r\n", &headers);
out:
	httppairs_free(&headers);
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: punycode_adapt                                                   *
 *                                                                            *
 * Purpose: after each delta is encoded or decoded, bias should be set for    *
 *          the next delta (should be adapted)                                *
 *                                                                            *
 * Parameters: delta      - [IN] punycode delta (generalized variable-length  *
 *                               integer)                                     *
 *             count      - [IN] is the total number of code points encoded / *
 *                               decoded so far                               *
 *             divisor    - [IN] delta divisor (to avoid overflow)            *
 *                                                                            *
 * Return value: adapted bias                                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	punycode_adapt(zbx_uint32_t delta, int count, int divisor)
{
	zbx_uint32_t	i;

	delta /= divisor;
	delta += delta / count;

	for (i = 0; PUNYCODE_BIAS_LIMIT < delta; i += PUNYCODE_BASE)
		delta /= PUNYCODE_BASE_MAX;

	return ((PUNYCODE_BASE * delta) / (delta + PUNYCODE_SKEW)) + i;
}


/******************************************************************************
 *                                                                            *
 * Function: punycode_encode_digit                                            *
 *                                                                            *
 * Purpose: encodes punycode digit into ansi character [a-z0-9]               *
 *                                                                            *
 * Parameters: digit      - [IN] digit to encode                              *
 *                                                                            *
 * Return value: encoded character                                            *
 *                                                                            *
 ******************************************************************************/
static char	punycode_encode_digit(int digit)
{
	if (0 <= digit && 25 >= digit)
		return digit + 'a';
	else if (25 < digit && PUNYCODE_BASE > digit)
		return digit + 22;

	THIS_SHOULD_NEVER_HAPPEN;
	return '\0';
}


/******************************************************************************
 *                                                                            *
 * Function: punycode_encode_codepoints                                       *
 *                                                                            *
 * Purpose: encodes array of unicode codepoints into into punycode (RFC 3492) *
 *                                                                            *
 * Parameters: codepoints      - [IN] codepoints to encode                    *
 *             count           - [IN] codepoint count                         *
 *             output          - [OUT] encoded result                         *
 *             length          - [IN] length of result buffer                 *
 *                                                                            *
 * Return value: SUCCEED if encoding was successful. FAIL on error.           *
 *                                                                            *
 ******************************************************************************/
static int	punycode_encode_codepoints(zbx_uint32_t *codepoints, size_t count, char *output, size_t length)
{
	int		ret = FAIL;
	zbx_uint32_t	n, delta = 0, bias, max_codepoint, q, k, t;
	size_t		h = 0, out = 0, offset, j;

	n = PUNYCODE_INITIAL_N;
	bias = PUNYCODE_INITIAL_BIAS;

	for (j = 0; j < count; j++)
	{
		if (0x80 > codepoints[j])
		{
			if (2 > length - out)
				goto out;	/* overflow */

			output[out++] = (char)codepoints[j];
		}
	}

	offset = out;
	h = offset;

	if (0 < out)
		output[out++] = '-';

	while (h < count)
	{
		max_codepoint = PUNYCODE_MAX_UINT32;

		for (j = 0; j < count; j++)
		{
			if (codepoints[j] >= n && codepoints[j] < max_codepoint)
				max_codepoint = codepoints[j];
		}

		if (max_codepoint - n > (PUNYCODE_MAX_UINT32 - delta) / (h + 1))
			goto out;	/* overflow */

		delta += (max_codepoint - n) * (h + 1);
		n = max_codepoint;

		for (j = 0; j < count; j++)
		{
			if (codepoints[j] < n && 0 == ++delta)
				goto out;	/* overflow */

			if (codepoints[j] == n)
			{
				q = delta;
				k = PUNYCODE_BASE;

				while (1)
				{
					if (out >= length)
						goto out;	/* out of memory */

					if (k <= bias)
						t = PUNYCODE_TMIN;
					else if (k >= bias + PUNYCODE_TMAX)
						t = PUNYCODE_TMAX;
					else
						t = k - bias;

					if (q < t)
						break;

					output[out++] = punycode_encode_digit(t + (q - t) % (PUNYCODE_BASE - t));
					q = (q - t) / (PUNYCODE_BASE - t);

					k += PUNYCODE_BASE;
				}

				output[out++] = punycode_encode_digit(q);
				bias = punycode_adapt(delta, h + 1, (h == offset) ? PUNYCODE_DAMP : 2);
				delta = 0;
				++h;
			}
		}

		delta++;
		n++;
	}

	if (out >= length)
		goto out;	/* out of memory */

	output[out] = '\0';
	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: punycode_encode_part                                             *
 *                                                                            *
 * Purpose: encodes unicode domain name part into punycode (RFC 3492)         *
 *          domain is being split in parts by punycode_encode by using        *
 *          character '.' as part separator                                   *
 *                                                                            *
 * Parameters: codepoints      - [IN] codepoints to encode                    *
 *             count           - [IN] codepoint count                         *
 *             output          - [IN/OUT] encoded result                      *
 *             size            - [IN/OUT] memory size allocated for result    *
 *             offset          - [IN/OUT] offset within result buffer         *
 *                                                                            *
 * Return value: SUCCEED if encoding was successful. FAIL on error.           *
 *                                                                            *
 ******************************************************************************/
static int	punycode_encode_part(zbx_uint32_t *codepoints, zbx_uint32_t count, char **output, size_t *size,
		size_t *offset)
{
	char		buffer[MAX_STRING_LEN];
	zbx_uint32_t	i, ansi = 1;

	if (0 == count)
		return SUCCEED;

	for (i = 0; i < count; i++)
	{
		if (0x80 <= codepoints[i])
		{
			ansi = 0;
			break;
		}
		else
			buffer[i] = (char)(codepoints[i]);
	}

	if (0 == ansi)
	{
		zbx_strcpy_alloc(output, size, offset, "xn--");
		if (SUCCEED != punycode_encode_codepoints(codepoints, count, buffer, MAX_STRING_LEN))
			return FAIL;
	}
	else
		buffer[count] = '\0';

	zbx_strcpy_alloc(output, size, offset, buffer);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: punycode_encode                                                  *
 *                                                                            *
 * Purpose: encodes unicode domain names into punycode (RFC 3492)             *
 *                                                                            *
 * Parameters: text            - [IN] text to encode                          *
 *             output          - [OUT] encoded text                           *
 *                                                                            *
 * Return value: SUCCEED if encoding was successful. FAIL on error.           *
 *                                                                            *
 ******************************************************************************/
static int	punycode_encode(const char *text, char **output)
{
	int		ret = FAIL;
	size_t		offset = 0, size = 0;
	zbx_uint32_t	n, tmp, count = 0, *codepoints;

	zbx_free(*output);
	codepoints = zbx_malloc(NULL, strlen(text) * sizeof(zbx_uint32_t));

	while ('\0' != *text)
	{
		if (0 == (*text & 0x80))
			n = 0;
		else if (0xc0 == (*text & 0xe0))
			n = 1;
		else if (0xe0 == (*text & 0xf0))
			n = 2;
		else if (0xf0 == (*text & 0xf8))
			n = 3;
		else
			goto out;

		if (0 != n)
		{
			tmp = ((zbx_uint32_t)((*text) & (0x3f >> n))) << 6 * n;
			text++;

			while (0 < n)
			{
				n--;
				if ('\0' == *text || (0x80 != ((*text) & 0xc0)))
					goto out;

				tmp |= ((zbx_uint32_t)((*text) & 0x3f)) << 6 * n;
				text++;
			}

			codepoints[count++] = tmp;
		}
		else
		{
			if ('.' == *text)
			{
				if (SUCCEED != punycode_encode_part(codepoints, count, output, &size, &offset))
					goto out;

				zbx_chrcpy_alloc(output, &size, &offset, *text++);
				count = 0;
			}
			else
				codepoints[count++] = *text++;
		}
	}

	ret = punycode_encode_part(codepoints, count, output, &size, &offset);
out:
	if (SUCCEED != ret)
		zbx_free(*output);

	zbx_free(codepoints);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: httpstep_load_pairs                                              *
 *                                                                            *
 * Purpose: loads http fields of web scenario step                            *
 *                                                                            *
 * Parameters: host            - [IN] host to be used in macro expansion      *
 *             httpstep        - [IN/OUT] web scenario step                   *
 *                                                                            *
 * Return value: SUCCEED if http fields were loaded and macro expansion was   *
 *               successful. FAIL on error.                                   *
 *                                                                            *
 ******************************************************************************/
static int	httpstep_load_pairs(DC_HOST *host, zbx_httpstep_t *httpstep)
{
	int			type, ansi = 1, ret = SUCCEED;
	DB_RESULT		result;
	DB_ROW			row;
	size_t			alloc_len = 0, offset;
	zbx_ptr_pair_t		pair;
	zbx_vector_ptr_pair_t	*vector, headers, query_fields, post_fields;
	char			*key, *value, *url = NULL, query_delimiter = '?', *domain, *tmp;

	httpstep->url = NULL;
	httpstep->posts = NULL;
	httpstep->headers = NULL;

	zbx_vector_ptr_pair_create(&headers);
	zbx_vector_ptr_pair_create(&query_fields);
	zbx_vector_ptr_pair_create(&post_fields);
	zbx_vector_ptr_pair_create(&httpstep->variables);

	result = DBselect(
			"select name, value, type"
			" from httpstep_field"
			" where httpstepid=" ZBX_FS_UI64
			" order by httpstep_fieldid",
			httpstep->httpstep->httpstepid);

	while (NULL != (row = DBfetch(result)))
	{
		type = atoi(row[2]);

		value = zbx_strdup(NULL, row[1]);

		/* from now on variable values can contain macros so proper URL encoding can be performed */
		if (SUCCEED != (ret = substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL,
				&value, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0)))
		{
			zbx_free(value);
			goto out;
		}

		key = zbx_strdup(NULL, row[0]);

		/* variable names cannot contain macros, and both variable names and variable values cannot contain */
		/* another variables */
		if (ZBX_HTTPFIELD_VARIABLE != type && (SUCCEED != (ret = substitute_simple_macros(NULL, NULL, NULL,
				NULL, NULL, host, NULL, NULL, &key, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0)) ||
				SUCCEED != (ret = http_substitute_variables(httpstep->httptest, &key)) ||
				SUCCEED != (ret = http_substitute_variables(httpstep->httptest, &value))))
		{
			httppairs_free(&httpstep->variables);
			zbx_free(key);
			zbx_free(value);
			goto out;
		}

		/* keys and values of query fields / post fields should be encoded */
		if (ZBX_HTTPFIELD_QUERY_FIELD == type || ZBX_HTTPFIELD_POST_FIELD == type)
		{
			http_variable_urlencode(key, &key);
			http_variable_urlencode(value, &value);
		}

		switch (type)
		{
			case ZBX_HTTPFIELD_HEADER:
				vector = &headers;
				break;
			case ZBX_HTTPFIELD_VARIABLE:
				vector = &httpstep->variables;
				break;
			case ZBX_HTTPFIELD_QUERY_FIELD:
				vector = &query_fields;
				break;
			case ZBX_HTTPFIELD_POST_FIELD:
				vector = &post_fields;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				zbx_free(key);
				zbx_free(value);
				ret = FAIL;
				goto out;
		}

		pair.first = key;
		pair.second = value;

		zbx_vector_ptr_pair_append(vector, pair);
	}

	/* URL is created from httpstep->httpstep->url, query_fields and fragment */
	zbx_strcpy_alloc(&url, &alloc_len, &offset, httpstep->httpstep->url);

	value = strchr(url, '#');

	if (NULL != value)
	{
		/* URL contains fragment delimiter, so it must be dropped */

		zabbix_log(LOG_LEVEL_DEBUG, "URL contains fragment delimiter, fragment part is deleted from URL");
		*value = '\0';
		offset = value - url;
	}

	if (0 < query_fields.values_num)
	{
		/* url can contain '?' so proper delimiter should be selected */
		if (NULL != strchr(url, '?'))
			query_delimiter = '&';

		zbx_chrcpy_alloc(&url, &alloc_len, &offset, query_delimiter);
		httpstep_pairs_join(&url, &alloc_len, &offset, "=", "&", &query_fields);
	}

	if (NULL == (domain = strchr(url, '@')))
	{
		if (NULL == (domain = strstr(url, "://")))
			domain = url;
		else
			domain += ZBX_CONST_STRLEN("://");
	}
	else
		domain++;

	tmp = domain;

	while ('\0' != *tmp && ':' != *tmp && '/' != *tmp)
	{
		if (0 != ((*tmp) & 0x80))
			ansi = 0;
		tmp++;
	}

	if (0 == ansi)
	{
		/* non-ansi URL, conversion to the punicode is needed */

		char	*rest = NULL, *encoded_url = NULL, *encoded_domain = NULL, delimiter;

		delimiter = *tmp;

		if ('\0' != delimiter)
		{
			rest = tmp + 1;
			*tmp = '\0';
		}

		if (SUCCEED != (ret = punycode_encode(domain, &encoded_domain)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot encode unicode URL into punycode");
			httppairs_free(&httpstep->variables);
			zbx_free(url);
			goto out;
		}

		/* schema, schema separator and authority part (if any) */
		zbx_strncpy_alloc(&encoded_url, &alloc_len, &offset, url, domain - url);
		/* domain */
		zbx_strcpy_alloc(&encoded_url, &alloc_len, &offset, encoded_domain);
		zbx_free(encoded_domain);

		if ('\0' != delimiter)
		{
			/* rest of the URL (if any) */

			zbx_chrcpy_alloc(&encoded_url, &alloc_len, &offset, delimiter);
			zbx_strcpy_alloc(&encoded_url, &alloc_len, &offset, rest);
		}

		zbx_free(url);
		url = encoded_url;
	}

	httpstep->url = url;

	/* POST data can be saved as raw data or as form data */
	if (ZBX_POSTTYPE_FORM == httpstep->httpstep->post_type)
		httpstep_pairs_join(&httpstep->posts, &alloc_len, &offset, "=", "&", &post_fields);
	else
		httpstep->posts = httpstep->httpstep->posts;	/* post data in raw format */

	httpstep_pairs_join(&httpstep->headers, &alloc_len, &offset, ":", "\r\n", &headers);
out:
	httppairs_free(&headers);
	httppairs_free(&query_fields);
	httppairs_free(&post_fields);
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_httptest                                                 *
 *                                                                            *
 * Purpose: process single scenario of http test                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_httptest(DC_HOST *host, zbx_httptest_t *httptest)
{
	const char	*__function_name = "process_httptest";

	DB_RESULT	result;
	DB_ROW		row;
	DB_HTTPSTEP	dbstep;
	zbx_httpstep_t	httpstep;
	char		*err_str = NULL;
	int		lastfailedstep = 0;
	zbx_timespec_t	ts;
	zbx_httpstat_t	stat;
	double		speed_download = 0;
	int		speed_download_num = 0;
#ifdef HAVE_LIBCURL
	int		err;
	char		*auth = NULL, errbuf[CURL_ERROR_SIZE];
	size_t		auth_alloc = 0, auth_offset;
	CURL		*easyhandle = NULL;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() httptestid:" ZBX_FS_UI64 " name:'%s'",
			__function_name, httptest->httptest.httptestid, httptest->httptest.name);

	result = DBselect(
			"select httpstepid,no,name,url,timeout,posts,required,status_codes,post_type,follow_redirects,"
				"retrieve_mode"
			" from httpstep"
			" where httptestid=" ZBX_FS_UI64
			" order by no",
			httptest->httptest.httptestid);

#ifdef HAVE_LIBCURL
	if (NULL == (easyhandle = curl_easy_init()))
	{
		err_str = zbx_strdup(err_str, "cannot initialize cURL library");
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROXY, httptest->httptest.http_proxy)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERAGENT, httptest->httptest.agent)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_WRITEFUNCTION, WRITEFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HEADERFUNCTION, HEADERFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYPEER,
					0 == httptest->httptest.verify_peer ? 0L : 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYHOST,
					0 == httptest->httptest.verify_host ? 0L : 2L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ERRORBUFFER, errbuf)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		goto clean;
	}

	if (NULL != CONFIG_SOURCE_IP)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_INTERFACE, CONFIG_SOURCE_IP)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto clean;
		}
	}

	if (0 != httptest->httptest.verify_peer && NULL != CONFIG_SSL_CA_LOCATION)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CAPATH, CONFIG_SSL_CA_LOCATION)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto clean;
		}
	}

	if ('\0' != *httptest->httptest.ssl_cert_file)
	{
		char	*file_name;

		file_name = zbx_dsprintf(NULL, "%s/%s", CONFIG_SSL_CERT_LOCATION, httptest->httptest.ssl_cert_file);
		zabbix_log(LOG_LEVEL_DEBUG, "using SSL certificate file: '%s'", file_name);

		err = curl_easy_setopt(easyhandle, CURLOPT_SSLCERT, file_name);
		zbx_free(file_name);

		if (CURLE_OK != err || CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSLCERTTYPE, "PEM")))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto clean;
		}
	}

	if ('\0' != *httptest->httptest.ssl_key_file)
	{
		char	*file_name;

		file_name = zbx_dsprintf(NULL, "%s/%s", CONFIG_SSL_KEY_LOCATION, httptest->httptest.ssl_key_file);
		zabbix_log(LOG_LEVEL_DEBUG, "using SSL private key file: '%s'", file_name);

		err = curl_easy_setopt(easyhandle, CURLOPT_SSLKEY, file_name);
		zbx_free(file_name);

		if (CURLE_OK != err || CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSLKEYTYPE, "PEM")))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto clean;
		}
	}

	if ('\0' != *httptest->httptest.ssl_key_password)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_KEYPASSWD,
				httptest->httptest.ssl_key_password)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto clean;
		}
	}

	httpstep.httptest = httptest;
	httpstep.httpstep = &dbstep;

	while (NULL != (row = DBfetch(result)))
	{
		struct curl_slist	*headers_slist = NULL;

		/* NOTE: do not break or return from this block! */
		/*       process_step_data() call is required! */

		ZBX_STR2UINT64(dbstep.httpstepid, row[0]);
		dbstep.httptestid = httptest->httptest.httptestid;
		dbstep.no = atoi(row[1]);
		dbstep.name = row[2];

		dbstep.url = zbx_strdup(NULL, row[3]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL,
				&dbstep.url, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);
		http_substitute_variables(httptest, &dbstep.url);

		dbstep.post_type = atoi(row[8]);

		if (ZBX_POSTTYPE_RAW == dbstep.post_type)
		{
			dbstep.posts = zbx_strdup(NULL, row[5]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL,
					&dbstep.posts, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);
			http_substitute_variables(httptest, &dbstep.posts);
		}
		else
			dbstep.posts = NULL;

		if (SUCCEED != httpstep_load_pairs(host, &httpstep))
		{
			err_str = zbx_strdup(err_str, "cannot load web scenario step data");
			goto httpstep_error;
		}

		dbstep.timeout = atoi(row[4]);

		dbstep.required = zbx_strdup(NULL, row[6]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL,
				&dbstep.required, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		dbstep.status_codes = zbx_strdup(NULL, row[7]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL,
				&dbstep.status_codes, MACRO_TYPE_COMMON, NULL, 0);

		dbstep.follow_redirects = atoi(row[9]);
		dbstep.retrieve_mode = atoi(row[10]);

		memset(&stat, 0, sizeof(stat));

		zabbix_log(LOG_LEVEL_DEBUG, "%s() use step \"%s\"", __function_name, dbstep.name);

		if (NULL != httpstep.posts && '\0' != *httpstep.posts)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() use post \"%s\"", __function_name, httpstep.posts);

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, httpstep.posts)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
				goto httpstep_error;
			}
		}

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POST, (NULL != httpstep.posts &&
				'\0' != *httpstep.posts) ? 1L : 0L)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_FOLLOWLOCATION,
				0 == dbstep.follow_redirects ? 0L : 1L)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		if (0 != dbstep.follow_redirects)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_MAXREDIRS, ZBX_CURLOPT_MAXREDIRS)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
				goto httpstep_error;
			}
		}

		/* headers defined in a step overwrite headers defined in scenario */
		if (NULL != httpstep.headers && '\0' != *httpstep.headers)
			add_headers(httpstep.headers, &headers_slist);
		else if (NULL != httptest->headers && '\0' != *httptest->headers)
			add_headers(httptest->headers, &headers_slist);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HTTPHEADER, headers_slist)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		/* enable/disable fetching the body */
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_NOBODY,
				ZBX_RETRIEVE_MODE_HEADERS == dbstep.retrieve_mode ? 1L : 0L)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		if (HTTPTEST_AUTH_NONE != httptest->httptest.authentication)
		{
			long	curlauth = 0;

			zabbix_log(LOG_LEVEL_DEBUG, "%s() setting HTTPAUTH [%d]",
					__function_name, httptest->httptest.authentication);
			zabbix_log(LOG_LEVEL_DEBUG, "%s() setting USERPWD for authentication", __function_name);

			switch (httptest->httptest.authentication)
			{
				case HTTPTEST_AUTH_BASIC:
					curlauth = CURLAUTH_BASIC;
					break;
				case HTTPTEST_AUTH_NTLM:
					curlauth = CURLAUTH_NTLM;
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					break;
			}

			auth_offset = 0;
			zbx_snprintf_alloc(&auth, &auth_alloc, &auth_offset, "%s:%s", httptest->httptest.http_user,
					httptest->httptest.http_password);

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HTTPAUTH, curlauth)) ||
					CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERPWD, auth)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
				goto httpstep_error;
			}
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() go to URL \"%s\"", __function_name, httpstep.url);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_TIMEOUT, (long)dbstep.timeout)) ||
				CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, httpstep.url)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		/* try to retrieve page several times depending on number of retries */
		do
		{
			memset(&page, 0, sizeof(page));

			if (CURLE_OK == (err = curl_easy_perform(easyhandle)))
				break;

			zbx_free(page.data);
		}
		while (0 < --httptest->httptest.retries);

		curl_slist_free_all(headers_slist);	/* must be called after curl_easy_perform() */

		if (CURLE_OK == err)
		{
			zabbix_log(LOG_LEVEL_TRACE, "%s() page.data from %s:'%s'", __function_name, httpstep.url, page.data);

			/* first get the data that is needed even if step fails */
			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE, &stat.rspcode)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}
			else if ('\0' != *dbstep.status_codes &&
					FAIL == int_in_list(dbstep.status_codes, stat.rspcode))
			{
				err_str = zbx_dsprintf(err_str, "response code \"%ld\" did not match any of the"
						" required status codes \"%s\"", stat.rspcode, dbstep.status_codes);
			}

			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_TOTAL_TIME, &stat.total_time)) &&
					NULL == err_str)
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}

			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_SPEED_DOWNLOAD,
					&stat.speed_download)) && NULL == err_str)
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}
			else
			{
				speed_download += stat.speed_download;
				speed_download_num++;
			}

			if (ZBX_RETRIEVE_MODE_CONTENT == dbstep.retrieve_mode)
			{
				char	*var_err_str = NULL;

				/* required pattern */
				if (NULL == err_str && '\0' != *dbstep.required && NULL == zbx_regexp_match(page.data,
						dbstep.required, NULL))
				{
					err_str = zbx_dsprintf(err_str, "required pattern \"%s\" was not found on %s",
							dbstep.required, httpstep.url);
				}

				/* variables defined in scenario */
				if (NULL == err_str && FAIL == http_process_variables(httptest,
						&httptest->variables, page.data, &var_err_str))
				{
					char	*variables = NULL;
					size_t	alloc_len = 0, offset;

					httpstep_pairs_join(&variables, &alloc_len, &offset, "=", " ",
							&httptest->variables);

					err_str = zbx_dsprintf(err_str, "error in scenario variables \"%s\": %s",
							variables, var_err_str);

					zbx_free(variables);
				}

				/* variables defined in a step */
				if (NULL == err_str && FAIL == http_process_variables(httptest, &httpstep.variables,
						page.data, &var_err_str))
				{
					char	*variables = NULL;
					size_t	alloc_len = 0, offset;

					httpstep_pairs_join(&variables, &alloc_len, &offset, "=", " ",
							&httpstep.variables);

					err_str = zbx_dsprintf(err_str, "error in step variables \"%s\": %s",
							variables, var_err_str);

					zbx_free(variables);
				}

				zbx_free(var_err_str);
			}

			zbx_free(page.data);
		}
		else
			err_str = zbx_dsprintf(err_str, "%s: %s", curl_easy_strerror(err), errbuf);

httpstep_error:
		zbx_free(dbstep.status_codes);
		zbx_free(dbstep.required);
		zbx_free(dbstep.posts);
		zbx_free(dbstep.url);

		httppairs_free(&httpstep.variables);

		if (ZBX_POSTTYPE_FORM == httpstep.httpstep->post_type)
			zbx_free(httpstep.posts);

		zbx_free(httpstep.url);
		zbx_free(httpstep.headers);

		zbx_timespec(&ts);
		process_step_data(dbstep.httpstepid, &stat, &ts);

		if (NULL != err_str)
		{
			lastfailedstep = dbstep.no;
			break;
		}
	}

	zbx_free(auth);
clean:
	curl_easy_cleanup(easyhandle);
#else
	err_str = zbx_strdup(err_str, "cURL library is required for Web monitoring support");
#endif	/* HAVE_LIBCURL */

	zbx_timespec(&ts);

	if (NULL != err_str)
	{
		if (0 == lastfailedstep)
		{
			/* we are here either because cURL initialization failed */
			/* or we have been compiled without cURL library */

			lastfailedstep = 1;

			/* we don't have name of the step, try to fetch it */

			if (NULL != (row = DBfetch(result)))
			{
				ZBX_STR2UINT64(dbstep.httpstepid, row[0]);
				dbstep.name = row[2];

				memset(&stat, 0, sizeof(stat));

				process_step_data(dbstep.httpstepid, &stat, &ts);
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot process web scenario \"%s\" on host \"%s\": %s",
						httptest->httptest.name, host->name, err_str);
				dbstep.name = NULL;
				THIS_SHOULD_NEVER_HAPPEN;
			}
		}

		if (NULL != dbstep.name)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot process step \"%s\" of web scenario \"%s\" on host \"%s\""
					": %s", dbstep.name, httptest->httptest.name, host->name, err_str);
		}
	}
	DBfree_result(result);

	DBexecute("update httptest set nextcheck=%d+delay where httptestid=" ZBX_FS_UI64,
			ts.sec, httptest->httptest.httptestid);

	if (0 != speed_download_num)
		speed_download /= speed_download_num;

	process_test_data(httptest->httptest.httptestid, lastfailedstep, speed_download, err_str, &ts);

	zbx_free(err_str);

	dc_flush_history();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_httptests                                                *
 *                                                                            *
 * Purpose: process httptests                                                 *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: number of processed httptests                                *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: always SUCCEED                                                   *
 *                                                                            *
 ******************************************************************************/
int	process_httptests(int httppoller_num, int now)
{
	const char	*__function_name = "process_httptests";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_httptest_t	httptest;
	DC_HOST		host;
	int		httptests_count = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* create macro cache to use in http tests */
	zbx_vector_ptr_pair_create(&httptest.macros);

	result = DBselect(
			"select h.hostid,h.host,h.name,t.httptestid,t.name,t.agent,"
				"t.authentication,t.http_user,t.http_password,t.http_proxy,t.retries,t.ssl_cert_file,"
				"t.ssl_key_file,t.ssl_key_password,t.verify_peer,t.verify_host"
			" from httptest t,hosts h"
			" where t.hostid=h.hostid"
				" and t.nextcheck<=%d"
				" and " ZBX_SQL_MOD(t.httptestid,%d) "=%d"
				" and t.status=%d"
				" and h.proxy_hostid is null"
				" and h.status=%d"
				" and (h.maintenance_status=%d or h.maintenance_type=%d)",
			now,
			CONFIG_HTTPPOLLER_FORKS, httppoller_num - 1,
			HTTPTEST_STATUS_MONITORED,
			HOST_STATUS_MONITORED,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(host.hostid, row[0]);
		strscpy(host.host, row[1]);
		strscpy(host.name, row[2]);

		ZBX_STR2UINT64(httptest.httptest.httptestid, row[3]);
		httptest.httptest.name = row[4];

		if (SUCCEED != httptest_load_pairs(&host, &httptest))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot process web scenario \"%s\" on host \"%s\": "
					"cannot load web scenario data", httptest.httptest.name, host.name);
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		httptest.httptest.agent = zbx_strdup(NULL, row[5]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
				&httptest.httptest.agent, MACRO_TYPE_COMMON, NULL, 0);

		if (HTTPTEST_AUTH_NONE != (httptest.httptest.authentication = atoi(row[6])))
		{
			httptest.httptest.http_user = zbx_strdup(NULL, row[7]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
					&httptest.httptest.http_user, MACRO_TYPE_COMMON, NULL, 0);

			httptest.httptest.http_password = zbx_strdup(NULL, row[8]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
					&httptest.httptest.http_password, MACRO_TYPE_COMMON, NULL, 0);
		}

		if ('\0' != *row[9])
		{
			httptest.httptest.http_proxy = zbx_strdup(NULL, row[9]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
					&httptest.httptest.http_proxy, MACRO_TYPE_COMMON, NULL, 0);
		}
		else
			httptest.httptest.http_proxy = NULL;

		httptest.httptest.retries = atoi(row[10]);

		httptest.httptest.ssl_cert_file = zbx_strdup(NULL, row[11]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, NULL,
				&httptest.httptest.ssl_cert_file, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httptest.httptest.ssl_key_file = zbx_strdup(NULL, row[12]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, NULL,
				&httptest.httptest.ssl_key_file, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httptest.httptest.ssl_key_password = zbx_strdup(NULL, row[13]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
				&httptest.httptest.ssl_key_password, MACRO_TYPE_COMMON, NULL, 0);

		httptest.httptest.verify_peer = atoi(row[14]);
		httptest.httptest.verify_host = atoi(row[15]);

		/* add httptest variables to the current test macro cache */
		http_process_variables(&httptest, &httptest.variables, NULL, NULL);

		process_httptest(&host, &httptest);

		zbx_free(httptest.httptest.ssl_key_password);
		zbx_free(httptest.httptest.ssl_key_file);
		zbx_free(httptest.httptest.ssl_cert_file);
		zbx_free(httptest.httptest.http_proxy);

		if (HTTPTEST_AUTH_NONE != httptest.httptest.authentication)
		{
			zbx_free(httptest.httptest.http_password);
			zbx_free(httptest.httptest.http_user);
		}
		zbx_free(httptest.httptest.agent);
		zbx_free(httptest.headers);
		httppairs_free(&httptest.variables);

		/* clear the macro cache used in this http test */
		httptest_remove_macros(&httptest);

		httptests_count++;	/* performance metric */
	}
	/* destroy the macro cache used in http tests */
	zbx_vector_ptr_pair_destroy(&httptest.macros);

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return httptests_count;
}
