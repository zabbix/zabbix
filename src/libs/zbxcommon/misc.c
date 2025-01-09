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

#include "zbxcommon.h"

#if defined(_WINDOWS) || defined(__MINGW32__)
#	include "zbxstr.h"
#endif

static ZBX_THREAD_LOCAL volatile sig_atomic_t	zbx_timed_out;	/* 0 - no timeout occurred, 1 - SIGALRM took place */

#if defined(_WINDOWS) || defined(__MINGW32__)

int	__zbx_stat(const char *path, zbx_stat_t *buf)
{
	int	ret, fd;
	wchar_t	*wpath;

	wpath = zbx_utf8_to_unicode(path);

	if (-1 == (ret = _wstat64(wpath, buf)))
		goto out;

	if (0 != S_ISDIR(buf->st_mode) || 0 != buf->st_size)
		goto out;

	/* In the case of symlinks _wstat64 returns zero file size.   */
	/* Try to work around it by opening the file and using fstat. */

	ret = -1;

	if (-1 != (fd = _wopen(wpath, O_RDONLY)))
	{
		ret = _fstat64(fd, buf);
		_close(fd);
	}
out:
	zbx_free(wpath);

	return ret;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: return program name without path                                  *
 *                                                                            *
 * Return value: program name without path                                    *
 *                                                                            *
 ******************************************************************************/
const char	*get_program_name(const char *path)
{
	const char	*filename = NULL;

	for (filename = path; path && *path; path++)
	{
		if ('\\' == *path || '/' == *path)
			filename = path + 1;
	}

	return filename;
}

/******************************************************************************
 *                                                                            *
 * Purpose: allocates nmemb * size bytes of memory and fills it with zeros    *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 ******************************************************************************/
void	*zbx_calloc2(const char *filename, int line, void *old, size_t nmemb, size_t size)
{
	int	max_attempts;
	void	*ptr = NULL;

	/* old pointer must be NULL */
	if (NULL != old)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_calloc: allocating already allocated memory. "
				"Please report this to Zabbix developers.",
				filename, line);
	}

	for (
		max_attempts = 10, nmemb = MAX(nmemb, 1), size = MAX(size, 1);
		0 < max_attempts && NULL == ptr;
		ptr = calloc(nmemb, size), max_attempts--
	);

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_calloc: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)size);

	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: allocates size bytes of memory                                    *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 ******************************************************************************/
void	*zbx_malloc2(const char *filename, int line, void *old, size_t size)
{
	int	max_attempts;
	void	*ptr = NULL;

	/* old pointer must be NULL */
	if (NULL != old)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_malloc: allocating already allocated memory. "
				"Please report this to Zabbix developers.",
				filename, line);
	}

	for (
		max_attempts = 10, size = MAX(size, 1);
		0 < max_attempts && NULL == ptr;
		ptr = malloc(size), max_attempts--
	);

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_malloc: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)size);

	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: changes the size of the memory block pointed to by old            *
 *          to size bytes                                                     *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 ******************************************************************************/
void	*zbx_realloc2(const char *filename, int line, void *old, size_t size)
{
	int	max_attempts;
	void	*ptr = NULL;

	for (
		max_attempts = 10, size = MAX(size, 1);
		0 < max_attempts && NULL == ptr;
		ptr = realloc(old, size), max_attempts--
	);

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_realloc: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)size);

	exit(EXIT_FAILURE);
}

char	*zbx_strdup2(const char *filename, int line, char *old, const char *str)
{
	int	retry;
	char	*ptr = NULL;

	zbx_free(old);

	for (retry = 10; 0 < retry && NULL == ptr; ptr = strdup(str), retry--)
		;

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_strdup: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)(strlen(str) + 1));

	exit(EXIT_FAILURE);
}

/****************************************************************************************
 *                                                                                      *
 * Purpose: For overwriting sensitive data in memory.                                   *
 *          Similar to memset() but should not be optimized out by a compiler.          *
 *                                                                                      *
 * Derived from:                                                                        *
 *   http://www.dwheeler.com/secure-programs/Secure-Programs-HOWTO/protect-secrets.html *
 * See also:                                                                            *
 *   http://www.open-std.org/jtc1/sc22/wg14/www/docs/n1381.pdf on secure_memset()       *
 *                                                                                      *
 ****************************************************************************************/
void	*zbx_guaranteed_memset(void *v, int c, size_t n)
{
	volatile signed char	*p = (volatile signed char *)v;

	while (0 != n--)
		*p++ = (signed char)c;

	return v;
}

static const char	copyright_message[] =
	"Copyright (C) 2025 Zabbix SIA\n"
	"License AGPLv3: GNU Affero General Public License version 3 <https://www.gnu.org/licenses/>.\n"
	"This is free software: you are free to change and redistribute it according to\n"
	"the license. There is NO WARRANTY, to the extent permitted by law.";

/******************************************************************************
 *                                                                            *
 * Purpose: print version and compilation time of application on stdout       *
 *          by application request with parameter '-V'                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_print_version(const char *title_message)
{
	printf("%s (Zabbix) %s\n", title_message, ZABBIX_VERSION);
	printf("Revision %s %s, compilation time: %s %s\n\n", ZABBIX_REVISION, ZABBIX_REVDATE, __DATE__, __TIME__);
	puts(copyright_message);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if string is a valid internet hostname                      *
 *                                                                            *
 * Parameters: hostname - [IN] hostname string to be checked                  *
 *                                                                            *
 * Return value: SUCCEED - could be a valid hostname,                         *
 *               FAIL - definitely not a valid hostname                       *
 * Comments:                                                                  *
 *     Validation is not strict. Restrictions not checked:                    *
 *         - individual label (component) length 1-63,                        *
 *         - hyphens ('-') allowed only as interior characters in labels,     *
 *         - underscores ('_') allowed in domain name, but not in hostname.   *
 *                                                                            *
 ******************************************************************************/
int	zbx_validate_hostname(const char *hostname)
{
	int		component;	/* periods ('.') are only allowed when they serve to delimit components */
	int		len = ZBX_MAX_DNSNAME_LEN;
	const char	*p;

	/* the first character must be an alphanumeric character */
	if (0 == isalnum(*hostname))
		return FAIL;

	/* check only up to the first 'len' characters, the 1st character is already successfully checked */
	for (p = hostname + 1, component = 1; '\0' != *p; p++)
	{
		if (0 == --len)				/* hostname too long */
			return FAIL;

		/* check for allowed characters */
		if (0 != isalnum(*p) || '-' == *p || '_' == *p)
			component = 1;
		else if ('.' == *p && 1 == component)
			component = 0;
		else
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get nearest index position of sorted elements in array            *
 *                                                                            *
 * Parameters: p   - pointer to array of elements                             *
 *             sz  - element size                                             *
 *             num - number of elements                                       *
 *             id  - index to look for                                        *
 *                                                                            *
 * Return value: index at which it would be possible to insert the element so *
 *               that the array is still sorted                               *
 *                                                                            *
 ******************************************************************************/
int	get_nearestindex(const void *p, size_t sz, int num, zbx_uint64_t id)
{
	int		first_index, last_index, index;
	zbx_uint64_t	element_id;

	if (0 == num)
		return 0;

	first_index = 0;
	last_index = num - 1;

	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		if (id == (element_id = *(const zbx_uint64_t *)((const char *)p + index * sz)))
			return index;

		if (last_index == first_index)
		{
			if (element_id < id)
				index++;
			return index;
		}

		if (element_id < id)
			first_index = index + 1;
		else
			last_index = index;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add uint64 value to dynamic array                                 *
 *                                                                            *
 ******************************************************************************/
int	uint64_array_add(zbx_uint64_t **values, int *alloc, int *num, zbx_uint64_t value, int alloc_step)
{
	int	index;

	index = get_nearestindex(*values, sizeof(zbx_uint64_t), *num, value);
	if (index < (*num) && (*values)[index] == value)
		return index;

	if (*alloc == *num)
	{
		if (0 == alloc_step)
		{
			zbx_error("Unable to reallocate buffer");
			zbx_this_should_never_happen_backtrace();
			assert(0);
		}

		*alloc += alloc_step;
		*values = (zbx_uint64_t *)zbx_realloc(*values, *alloc * sizeof(zbx_uint64_t));
	}

	memmove(&(*values)[index + 1], &(*values)[index], sizeof(zbx_uint64_t) * (*num - index));

	(*values)[index] = value;
	(*num)++;

	return index;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove uint64 values from array                                   *
 *                                                                            *
 ******************************************************************************/
void	uint64_array_remove(zbx_uint64_t *values, int *num, const zbx_uint64_t *rm_values, int rm_num)
{
	int	rindex, index;

	for (rindex = 0; rindex < rm_num; rindex++)
	{
		index = get_nearestindex(values, sizeof(zbx_uint64_t), *num, rm_values[rindex]);
		if (index == *num || values[index] != rm_values[rindex])
			continue;

		memmove(&values[index], &values[index + 1], sizeof(zbx_uint64_t) * ((*num) - index - 1));
		(*num)--;
	}
}

void	zbx_alarm_flag_set(void)
{
	zbx_timed_out = 1;
}

void	zbx_alarm_flag_clear(void)
{
	zbx_timed_out = 0;
}

#if !defined(_WINDOWS) && !defined(__MINGW32__)
unsigned int	zbx_alarm_on(unsigned int seconds)
{
	zbx_alarm_flag_clear();

	return alarm(seconds);
}

unsigned int	zbx_alarm_off(void)
{
	unsigned int	ret;

	ret = alarm(0);
	zbx_alarm_flag_clear();
	return ret;
}
#endif

int	zbx_alarm_timed_out(void)
{
	return (0 == zbx_timed_out ? FAIL : SUCCEED);
}

zbx_uint64_t	suffix2factor(char c)
{
	switch (c)
	{
		case 'K':
			return ZBX_KIBIBYTE;
		case 'M':
			return ZBX_MEBIBYTE;
		case 'G':
			return ZBX_GIBIBYTE;
		case 'T':
			return ZBX_TEBIBYTE;
		case 's':
			return 1;
		case 'm':
			return SEC_PER_MIN;
		case 'h':
			return SEC_PER_HOUR;
		case 'd':
			return SEC_PER_DAY;
		case 'w':
			return SEC_PER_WEEK;
		default:
			return 1;
	}
}

