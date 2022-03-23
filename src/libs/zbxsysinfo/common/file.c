/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "file.h"

#include "common.h"
#include "sysinfo.h"
#include "md5.h"
#include "zbxregexp.h"
#include "log.h"
#include "dir.h"
#include "sha256crypt.h"

#if defined(_WINDOWS) || defined(__MINGW32__)
#include "aclapi.h"
#include "sddl.h"
#endif

#define ZBX_MAX_DB_FILE_SIZE	64 * ZBX_KIBIBYTE	/* files larger than 64 KB cannot be stored in the database */

extern int	CONFIG_TIMEOUT;

int	VFS_FILE_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_stat_t	buf;
	char		*filename, *mode;
	int		ret = SYSINFO_RET_FAIL;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (NULL != mode && 0 == strcmp(mode, "lines"))
	{
		ssize_t		nbytes;
		char		cbuf[MAX_BUFFER_LEN];
		zbx_uint64_t	lines_num = 0;
		int		f;
		double		ts;

		ts = zbx_time();

		if (-1 == (f = zbx_open(filename, O_RDONLY)))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
			goto err;
		}

		while (0 < (nbytes = read(f, cbuf, ARRSIZE(cbuf))))
		{
			char	*p1, *p2;
			size_t	sz = (size_t)nbytes, dif;

			if (CONFIG_TIMEOUT < zbx_time() - ts)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
				close(f);
				goto err;
			}

			p1 = cbuf;

			while (NULL != (p2 = memchr(p1, '\n', sz)))
			{
				lines_num++;
				dif = (size_t)(p2 - p1);
				p1 = p2;

				if (dif < sz)
				{
					sz -= dif + 1;
					p1++;
				}
			}
		}

		close(f);

		if (0 > nbytes)
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot read from file: %s", zbx_strerror(errno)));
			goto err;
		}

		SET_UI64_RESULT(result, lines_num);
	}
	else if (NULL != mode && 0 != strcmp(mode, "bytes"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto err;
	}
	else if (0 != zbx_stat(filename, &buf))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno)));
		goto err;
	}
	else
		SET_UI64_RESULT(result, buf.st_size);

	ret = SYSINFO_RET_OK;
err:
	return ret;
}

int	VFS_FILE_TIME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_file_time_t	file_time;
	char		*filename, *type;
	int		ret = SYSINFO_RET_FAIL;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	type = get_rparam(request, 1);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (SUCCEED != zbx_get_file_time(filename, 0, &file_time))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno)));
		goto err;
	}

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "modify"))	/* default parameter */
		SET_UI64_RESULT(result, file_time.modification_time);
	else if (0 == strcmp(type, "access"))
		SET_UI64_RESULT(result, file_time.access_time);
	else if (0 == strcmp(type, "change"))
		SET_UI64_RESULT(result, file_time.change_time);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto err;
	}

	ret = SYSINFO_RET_OK;
err:
	return ret;
}

#if defined(_WINDOWS) || defined(__MINGW32__)
static int	vfs_file_exists(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*filename;
	int		ret = SYSINFO_RET_FAIL, file_exists = 0, types, types_incl, types_excl;
	DWORD		file_attributes;
	wchar_t		*wpath;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (FAIL == (types_incl = zbx_etypes_to_mask(get_rparam(request, 1), result)) ||
			FAIL == (types_excl = zbx_etypes_to_mask(get_rparam(request, 2), result)))
	{
		goto err;
	}

	if (0 == types_incl)
	{
		if (0 == types_excl)
			types_incl = ZBX_FT_FILE;
		else
			types_incl = ZBX_FT_ALLMASK;
	}

	types = types_incl & (~types_excl) & ZBX_FT_ALLMASK;

	if (NULL == (wpath = zbx_utf8_to_unicode(filename)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot convert file name to UTF-16."));
		goto err;
	}

	file_attributes = GetFileAttributesW(wpath);
	zbx_free(wpath);

	if (INVALID_FILE_ATTRIBUTES == file_attributes)
	{
		DWORD	error;

		switch (error = GetLastError())
		{
			case ERROR_FILE_NOT_FOUND:
				goto exit;
			case ERROR_BAD_NETPATH:	/* special case from GetFileAttributesW() documentation */
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "The specified file is a network share."
						" Use a path to a subfolder on that share."));
				goto err;
			default:
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s",
						strerror_from_system(error)));
				goto err;
		}
	}

	switch (file_attributes & (FILE_ATTRIBUTE_REPARSE_POINT | FILE_ATTRIBUTE_DIRECTORY))
	{
		case FILE_ATTRIBUTE_REPARSE_POINT | FILE_ATTRIBUTE_DIRECTORY:
			if (0 != (types & ZBX_FT_SYM) || 0 != (types & ZBX_FT_DIR))
				file_exists = 1;
			break;
		case FILE_ATTRIBUTE_REPARSE_POINT:
						/* not a symlink directory => symlink regular file*/
						/* counting symlink files as MS explorer */
			if (0 != (types & ZBX_FT_FILE))
				file_exists = 1;
			break;
		case FILE_ATTRIBUTE_DIRECTORY:
			if (0 != (types & ZBX_FT_DIR))
				file_exists = 1;
			break;
		default:	/* not a directory => regular file */
			if (0 != (types & ZBX_FT_FILE))
				file_exists = 1;
	}
exit:
	SET_UI64_RESULT(result, file_exists);
	ret = SYSINFO_RET_OK;
err:
	return ret;
}
#else /* not _WINDOWS or __MINGW32__ */
static int	vfs_file_exists(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_stat_t	buf;
	const char	*filename;
	int		types = 0, types_incl, types_excl;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	filename = get_rparam(request, 0);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (FAIL == (types_incl = zbx_etypes_to_mask(get_rparam(request, 1), result)) ||
			FAIL == (types_excl = zbx_etypes_to_mask(get_rparam(request, 2), result)))
	{
		return SYSINFO_RET_FAIL;
	}

	if (0 == types_incl)
	{
		if (0 == types_excl)
			types_incl = ZBX_FT_FILE;
		else
			types_incl = ZBX_FT_ALLMASK;
	}

	if (0 != (types_incl & ZBX_FT_SYM) || 0 != (types_excl & ZBX_FT_SYM))
	{
		if (0 == lstat(filename, &buf))
		{
			if (0 != S_ISLNK(buf.st_mode))
				types |= ZBX_FT_SYM;
		}
		else if (ENOENT != errno)
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s",
					zbx_strerror(errno)));
			return SYSINFO_RET_FAIL;
		}
	}

	if (0 == zbx_stat(filename, &buf))
	{
		if (0 != S_ISREG(buf.st_mode))
			types |= ZBX_FT_FILE;
		else if (0 != S_ISDIR(buf.st_mode))
			types |= ZBX_FT_DIR;
		else if (0 != S_ISSOCK(buf.st_mode))
			types |= ZBX_FT_SOCK;
		else if (0 != S_ISBLK(buf.st_mode))
			types |= ZBX_FT_BDEV;
		else if (0 != S_ISCHR(buf.st_mode))
			types |= ZBX_FT_CDEV;
		else if (0 != S_ISFIFO(buf.st_mode))
			types |= ZBX_FT_FIFO;
	}
	else if (ENOENT != errno)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (0 == (types & types_excl) && 0 != (types & types_incl))
		SET_UI64_RESULT(result, 1);
	else
		SET_UI64_RESULT(result, 0);

	return SYSINFO_RET_OK;
}
#endif

int	VFS_FILE_EXISTS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return vfs_file_exists(request, result);
}

int	VFS_FILE_CONTENTS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*filename, *tmp, encoding[32];
	char		read_buf[MAX_BUFFER_LEN], *utf8, *contents = NULL;
	size_t		contents_alloc = 0, contents_offset = 0;
	int		nbytes, flen, f = -1, ret = SYSINFO_RET_FAIL;
	zbx_stat_t	stat_buf;
	double		ts;

	ts = zbx_time();

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	tmp = get_rparam(request, 1);

	if (NULL == tmp)
		*encoding = '\0';
	else
		strscpy(encoding, tmp);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	if (0 != zbx_fstat(f, &stat_buf))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno)));
		goto err;
	}

	if (ZBX_MAX_DB_FILE_SIZE < stat_buf.st_size)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "File is too large for this check."));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	flen = 0;

	while (0 < (nbytes = zbx_read(f, read_buf, sizeof(read_buf), encoding)))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
			zbx_free(contents);
			goto err;
		}

		if (ZBX_MAX_DB_FILE_SIZE < (flen += nbytes))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "File is too large for this check."));
			zbx_free(contents);
			goto err;
		}

		utf8 = convert_to_utf8(read_buf, nbytes, encoding);
		zbx_strcpy_alloc(&contents, &contents_alloc, &contents_offset, utf8);
		zbx_free(utf8);
	}

	if (-1 == nbytes)	/* error occurred */
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));
		zbx_free(contents);
		goto err;
	}

	if (0 != contents_offset)
		contents_offset -= zbx_rtrim(contents, "\r\n");

	if (0 == contents_offset) /* empty file */
	{
		zbx_free(contents);
		contents = zbx_strdup(contents, "");
	}

	SET_TEXT_RESULT(result, contents);

	ret = SYSINFO_RET_OK;
err:
	if (-1 != f)
		close(f);

	return ret;
}

int	VFS_FILE_REGEXP(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*filename, *regexp, encoding[32], *output, *start_line_str, *end_line_str;
	char		buf[MAX_BUFFER_LEN], *utf8, *tmp, *ptr = NULL;
	int		nbytes, f = -1, ret = SYSINFO_RET_FAIL;
	zbx_uint32_t	start_line, end_line, current_line = 0;
	double		ts;

	ts = zbx_time();

	if (6 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	regexp = get_rparam(request, 1);
	tmp = get_rparam(request, 2);
	start_line_str = get_rparam(request, 3);
	end_line_str = get_rparam(request, 4);
	output = get_rparam(request, 5);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (NULL == regexp || '\0' == *regexp)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto err;
	}

	if (NULL == tmp)
		*encoding = '\0';
	else
		strscpy(encoding, tmp);

	if (NULL == start_line_str || '\0' == *start_line_str)
		start_line = 0;
	else if (FAIL == is_uint32(start_line_str, &start_line))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto err;
	}

	if (NULL == end_line_str || '\0' == *end_line_str)
		end_line = 0xffffffff;
	else if (FAIL == is_uint32(end_line_str, &end_line))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		goto err;
	}

	if (start_line > end_line)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Start line parameter must not exceed end line."));
		goto err;
	}

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	while (0 < (nbytes = zbx_read(f, buf, sizeof(buf), encoding)))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
			goto err;
		}

		if (++current_line < start_line)
			continue;

		utf8 = convert_to_utf8(buf, nbytes, encoding);
		zbx_rtrim(utf8, "\r\n");
		zbx_regexp_sub(utf8, regexp, output, &ptr);
		zbx_free(utf8);

		if (NULL != ptr)
		{
			SET_STR_RESULT(result, ptr);
			break;
		}

		if (current_line >= end_line)
		{
			/* force EOF state */
			nbytes = 0;
			break;
		}
	}

	if (-1 == nbytes)	/* error occurred */
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));
		goto err;
	}

	if (0 == nbytes)	/* EOF */
		SET_STR_RESULT(result, zbx_strdup(NULL, ""));

	ret = SYSINFO_RET_OK;
err:
	if (-1 != f)
		close(f);

	return ret;
}

int	VFS_FILE_REGMATCH(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*filename, *regexp, *tmp, encoding[32];
	char		buf[MAX_BUFFER_LEN], *utf8, *start_line_str, *end_line_str;
	int		nbytes, res, f = -1, ret = SYSINFO_RET_FAIL;
	zbx_uint32_t	start_line, end_line, current_line = 0;
	double		ts;

	ts = zbx_time();

	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	regexp = get_rparam(request, 1);
	tmp = get_rparam(request, 2);
	start_line_str = get_rparam(request, 3);
	end_line_str = get_rparam(request, 4);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (NULL == regexp || '\0' == *regexp)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto err;
	}

	if (NULL == tmp)
		*encoding = '\0';
	else
		strscpy(encoding, tmp);

	if (NULL == start_line_str || '\0' == *start_line_str)
		start_line = 0;
	else if (FAIL == is_uint32(start_line_str, &start_line))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto err;
	}

	if (NULL == end_line_str || '\0' == *end_line_str)
		end_line = 0xffffffff;
	else if (FAIL == is_uint32(end_line_str, &end_line))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		goto err;
	}

	if (start_line > end_line)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Start line must not exceed end line."));
		goto err;
	}

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	res = 0;

	while (0 == res && 0 < (nbytes = zbx_read(f, buf, sizeof(buf), encoding)))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
			goto err;
		}

		if (++current_line < start_line)
			continue;

		utf8 = convert_to_utf8(buf, nbytes, encoding);
		zbx_rtrim(utf8, "\r\n");
		if (NULL != zbx_regexp_match(utf8, regexp, NULL))
			res = 1;
		zbx_free(utf8);

		if (current_line >= end_line)
			break;
	}

	if (-1 == nbytes)	/* error occurred */
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));
		goto err;
	}

	SET_UI64_RESULT(result, res);

	ret = SYSINFO_RET_OK;
err:
	if (-1 != f)
		close(f);

	return ret;
}

static int	vfs_file_cksum_md5(char *filename, AGENT_RESULT *result)
{
	int		i, nbytes, f, ret = SYSINFO_RET_FAIL;
	md5_state_t	state;
	u_char		buf[16 * ZBX_KIBIBYTE];
	char		*hash_text = NULL;
	size_t		sz;
	md5_byte_t	hash[MD5_DIGEST_SIZE];
	double		ts;

	ts = zbx_time();

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	zbx_md5_init(&state);

	while (0 < (nbytes = (int)read(f, buf, sizeof(buf))))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
			goto err;
		}

		zbx_md5_append(&state, (const md5_byte_t *)buf, nbytes);
	}

	zbx_md5_finish(&state, hash);

	if (0 > nbytes)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));
		goto err;
	}

	/* convert MD5 hash to text form */

	sz = MD5_DIGEST_SIZE * 2 + 1;
	hash_text = (char *)zbx_malloc(hash_text, sz);

	for (i = 0; i < MD5_DIGEST_SIZE; i++)
		zbx_snprintf(&hash_text[i << 1], sz - (i << 1), "%02x", hash[i]);

	SET_STR_RESULT(result, hash_text);

	ret = SYSINFO_RET_OK;
err:
	if (-1 != f)
		close(f);

	return ret;
}

int	VFS_FILE_MD5SUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*filename;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	filename = get_rparam(request, 0);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	return vfs_file_cksum_md5(filename, result);
}

static u_long	crctab[] =
{
	0x0,
	0x04c11db7, 0x09823b6e, 0x0d4326d9, 0x130476dc, 0x17c56b6b,
	0x1a864db2, 0x1e475005, 0x2608edb8, 0x22c9f00f, 0x2f8ad6d6,
	0x2b4bcb61, 0x350c9b64, 0x31cd86d3, 0x3c8ea00a, 0x384fbdbd,
	0x4c11db70, 0x48d0c6c7, 0x4593e01e, 0x4152fda9, 0x5f15adac,
	0x5bd4b01b, 0x569796c2, 0x52568b75, 0x6a1936c8, 0x6ed82b7f,
	0x639b0da6, 0x675a1011, 0x791d4014, 0x7ddc5da3, 0x709f7b7a,
	0x745e66cd, 0x9823b6e0, 0x9ce2ab57, 0x91a18d8e, 0x95609039,
	0x8b27c03c, 0x8fe6dd8b, 0x82a5fb52, 0x8664e6e5, 0xbe2b5b58,
	0xbaea46ef, 0xb7a96036, 0xb3687d81, 0xad2f2d84, 0xa9ee3033,
	0xa4ad16ea, 0xa06c0b5d, 0xd4326d90, 0xd0f37027, 0xddb056fe,
	0xd9714b49, 0xc7361b4c, 0xc3f706fb, 0xceb42022, 0xca753d95,
	0xf23a8028, 0xf6fb9d9f, 0xfbb8bb46, 0xff79a6f1, 0xe13ef6f4,
	0xe5ffeb43, 0xe8bccd9a, 0xec7dd02d, 0x34867077, 0x30476dc0,
	0x3d044b19, 0x39c556ae, 0x278206ab, 0x23431b1c, 0x2e003dc5,
	0x2ac12072, 0x128e9dcf, 0x164f8078, 0x1b0ca6a1, 0x1fcdbb16,
	0x018aeb13, 0x054bf6a4, 0x0808d07d, 0x0cc9cdca, 0x7897ab07,
	0x7c56b6b0, 0x71159069, 0x75d48dde, 0x6b93dddb, 0x6f52c06c,
	0x6211e6b5, 0x66d0fb02, 0x5e9f46bf, 0x5a5e5b08, 0x571d7dd1,
	0x53dc6066, 0x4d9b3063, 0x495a2dd4, 0x44190b0d, 0x40d816ba,
	0xaca5c697, 0xa864db20, 0xa527fdf9, 0xa1e6e04e, 0xbfa1b04b,
	0xbb60adfc, 0xb6238b25, 0xb2e29692, 0x8aad2b2f, 0x8e6c3698,
	0x832f1041, 0x87ee0df6, 0x99a95df3, 0x9d684044, 0x902b669d,
	0x94ea7b2a, 0xe0b41de7, 0xe4750050, 0xe9362689, 0xedf73b3e,
	0xf3b06b3b, 0xf771768c, 0xfa325055, 0xfef34de2, 0xc6bcf05f,
	0xc27dede8, 0xcf3ecb31, 0xcbffd686, 0xd5b88683, 0xd1799b34,
	0xdc3abded, 0xd8fba05a, 0x690ce0ee, 0x6dcdfd59, 0x608edb80,
	0x644fc637, 0x7a089632, 0x7ec98b85, 0x738aad5c, 0x774bb0eb,
	0x4f040d56, 0x4bc510e1, 0x46863638, 0x42472b8f, 0x5c007b8a,
	0x58c1663d, 0x558240e4, 0x51435d53, 0x251d3b9e, 0x21dc2629,
	0x2c9f00f0, 0x285e1d47, 0x36194d42, 0x32d850f5, 0x3f9b762c,
	0x3b5a6b9b, 0x0315d626, 0x07d4cb91, 0x0a97ed48, 0x0e56f0ff,
	0x1011a0fa, 0x14d0bd4d, 0x19939b94, 0x1d528623, 0xf12f560e,
	0xf5ee4bb9, 0xf8ad6d60, 0xfc6c70d7, 0xe22b20d2, 0xe6ea3d65,
	0xeba91bbc, 0xef68060b, 0xd727bbb6, 0xd3e6a601, 0xdea580d8,
	0xda649d6f, 0xc423cd6a, 0xc0e2d0dd, 0xcda1f604, 0xc960ebb3,
	0xbd3e8d7e, 0xb9ff90c9, 0xb4bcb610, 0xb07daba7, 0xae3afba2,
	0xaafbe615, 0xa7b8c0cc, 0xa379dd7b, 0x9b3660c6, 0x9ff77d71,
	0x92b45ba8, 0x9675461f, 0x8832161a, 0x8cf30bad, 0x81b02d74,
	0x857130c3, 0x5d8a9099, 0x594b8d2e, 0x5408abf7, 0x50c9b640,
	0x4e8ee645, 0x4a4ffbf2, 0x470cdd2b, 0x43cdc09c, 0x7b827d21,
	0x7f436096, 0x7200464f, 0x76c15bf8, 0x68860bfd, 0x6c47164a,
	0x61043093, 0x65c52d24, 0x119b4be9, 0x155a565e, 0x18197087,
	0x1cd86d30, 0x029f3d35, 0x065e2082, 0x0b1d065b, 0x0fdc1bec,
	0x3793a651, 0x3352bbe6, 0x3e119d3f, 0x3ad08088, 0x2497d08d,
	0x2056cd3a, 0x2d15ebe3, 0x29d4f654, 0xc5a92679, 0xc1683bce,
	0xcc2b1d17, 0xc8ea00a0, 0xd6ad50a5, 0xd26c4d12, 0xdf2f6bcb,
	0xdbee767c, 0xe3a1cbc1, 0xe760d676, 0xea23f0af, 0xeee2ed18,
	0xf0a5bd1d, 0xf464a0aa, 0xf9278673, 0xfde69bc4, 0x89b8fd09,
	0x8d79e0be, 0x803ac667, 0x84fbdbd0, 0x9abc8bd5, 0x9e7d9662,
	0x933eb0bb, 0x97ffad0c, 0xafb010b1, 0xab710d06, 0xa6322bdf,
	0xa2f33668, 0xbcb4666d, 0xb8757bda, 0xb5365d03, 0xb1f740b4
};

static int	vfs_file_cksum_crc32(char *filename, AGENT_RESULT *result)
{
	int		i, nr, f, ret = SYSINFO_RET_FAIL;
	zbx_uint32_t	crc, flen;
	u_char		buf[16 * ZBX_KIBIBYTE];
	u_long		cval;
	double		ts;

	ts = zbx_time();

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	crc = flen = 0;

	while (0 < (nr = (int)read(f, buf, sizeof(buf))))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
			goto err;
		}

		flen += nr;

		for (i = 0; i < nr; i++)
			crc = (crc << 8) ^ crctab[((crc >> 24) ^ buf[i]) & 0xff];
	}

	if (0 > nr)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));
		goto err;
	}

	/* include the length of the file */
	for (; 0 != flen; flen >>= 8)
		crc = (crc << 8) ^ crctab[((crc >> 24) ^ flen) & 0xff];

	cval = ~crc;

	SET_UI64_RESULT(result, cval);

	ret = SYSINFO_RET_OK;
err:
	if (-1 != f)
		close(f);

	return ret;
}

static int	vfs_file_cksum_sha256(char *filename, AGENT_RESULT *result)
{
	int		i, f, ret = SYSINFO_RET_FAIL;
	char		buf[16 * ZBX_KIBIBYTE];
	char		hash_res[ZBX_SHA256_DIGEST_SIZE], hash_res_stringhexes[ZBX_SHA256_DIGEST_SIZE * 2 + 1];
	double		ts;
	ssize_t		nr;
	sha256_ctx	ctx;

	ts = zbx_time();

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open file: %s", zbx_strerror(errno)));
		goto err;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
		goto err;
	}

	zbx_sha256_init(&ctx);

	while (0 < (nr = read(f, buf, sizeof(buf))))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while processing item."));
			goto err;
		}

		zbx_sha256_process_bytes(buf, (size_t)nr, &ctx);
	}

	if (0 > nr)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read from file."));
		goto err;
	}

	zbx_sha256_finish(&ctx, hash_res);

	for (i = 0 ; i < ZBX_SHA256_DIGEST_SIZE; i++)
	{
		char z[3];

		zbx_snprintf(z, 3, "%02x", (unsigned char)hash_res[i]);
		hash_res_stringhexes[i * 2] = z[0];
		hash_res_stringhexes[i * 2 + 1] = z[1];
	}

	hash_res_stringhexes[ZBX_SHA256_DIGEST_SIZE * 2] = '\0';

	SET_STR_RESULT(result, zbx_strdup(NULL, hash_res_stringhexes));

	ret = SYSINFO_RET_OK;
err:
	if (-1 != f)
		close(f);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Comments: computes POSIX 1003.2 checksum                                   *
 *                                                                            *
 ******************************************************************************/
int	VFS_FILE_CKSUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*filename, *method;
	int	ret = SYSINFO_RET_FAIL;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	method = get_rparam(request, 1);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (NULL == method || '\0' == *method || 0 == strcmp(method, "crc32"))
		ret = vfs_file_cksum_crc32(filename, result);
	else if (0 == strcmp(method, "md5"))
		ret = vfs_file_cksum_md5(filename, result);
	else if (0 == strcmp(method, "sha256"))
		ret = vfs_file_cksum_sha256(filename, result);
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
err:
	return ret;
}

#if defined(_WINDOWS) || defined(__MINGW32__)
int	VFS_FILE_OWNER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*filename, *ownertype, *resulttype;
	int			ret = SYSINFO_RET_FAIL;
	wchar_t			*wpath;
	PSECURITY_DESCRIPTOR	sec = NULL;
	PSID			sid = NULL;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	ownertype = get_rparam(request, 1);
	resulttype = get_rparam(request, 2);

	if (NULL == filename || '\0' == *filename || NULL == (wpath = zbx_utf8_to_unicode(filename)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (NULL != ownertype && '\0' != *ownertype && 0 != strcmp(ownertype, "user"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto err;
	}

	if (NULL != resulttype && '\0' != *resulttype &&
			(0 != strcmp(resulttype, "id") && 0 != strcmp(resulttype, "name")))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto err;
	}

	if (ERROR_SUCCESS != GetNamedSecurityInfo(wpath, SE_FILE_OBJECT, OWNER_SECURITY_INFORMATION, &sid, NULL, NULL,
			NULL, &sec) || NULL == sid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain security information."));
		goto err;
	}

	if (NULL != resulttype && 0 == strcmp(resulttype, "id"))
	{
		wchar_t	*sid_string = NULL;

		if (TRUE != ConvertSidToStringSid(sid, &sid_string))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain SID."));
			goto err;
		}

		SET_STR_RESULT(result, zbx_unicode_to_utf8(sid_string));
		LocalFree(sid_string);
	}
	else
	{
		DWORD		acc_sz = 0, dmn_sz = 0;
		wchar_t		*acc_name = NULL, *dmn_name = NULL;
		SID_NAME_USE	acc_type = SidTypeUnknown;
		char		*acc_utf8, *dmn_ut8;

		LookupAccountSid(NULL, sid, acc_name, (LPDWORD)&acc_sz, dmn_name, (LPDWORD)&dmn_sz, &acc_type);

		acc_name = (wchar_t *)zbx_malloc(acc_name, acc_sz * sizeof(wchar_t));
		dmn_name = (wchar_t *)zbx_malloc(dmn_name, dmn_sz * sizeof(wchar_t));

		if (TRUE != LookupAccountSid(NULL, sid, acc_name, (LPDWORD)&acc_sz, dmn_name, (LPDWORD)&dmn_sz,
				&acc_type))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain user name."));
			zbx_free(acc_name);
			zbx_free(dmn_name);
			goto err;
		}

		acc_utf8 = (zbx_unicode_to_utf8(acc_name));
		dmn_ut8 = (zbx_unicode_to_utf8(dmn_name));

		if (0 == strlen(dmn_ut8))
			SET_STR_RESULT(result, zbx_dsprintf(NULL, "%s", acc_utf8));
		else
			SET_STR_RESULT(result, zbx_dsprintf(NULL, "%s\\%s", dmn_ut8, acc_utf8));

		zbx_free(acc_name);
		zbx_free(dmn_name);
		zbx_free(acc_utf8);
		zbx_free(dmn_ut8);
	}

	ret = SYSINFO_RET_OK;
err:
	if (NULL != sec)
		LocalFree(sec);

	return ret;
}
#else
int	VFS_FILE_OWNER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*filename, *ownertype, *resulttype;
	int		ret = SYSINFO_RET_FAIL, type;
	zbx_stat_t	st;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);
	ownertype = get_rparam(request, 1);
	resulttype = get_rparam(request, 2);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (0 != lstat(filename, &st))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno)));
		goto err;
	}

	if (NULL != ownertype && 0 == strcmp(ownertype, "group"))
	{
		type = 1;
	}
	else if (NULL == ownertype || '\0' == *ownertype || 0 == strcmp(ownertype, "user"))
	{
		type = 0;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto err;
	}

	if (NULL != resulttype && 0 == strcmp(resulttype, "id"))
	{
		if (1 == type)
			SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, (zbx_uint64_t)st.st_gid));
		else
			SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, (zbx_uint64_t)st.st_uid));
	}
	else if (NULL == resulttype || '\0' == *resulttype || 0 == strcmp(resulttype, "name"))
	{
		if (1 == type)
		{
			struct group	*grp;

			if (NULL == (grp = getgrgid(st.st_gid)))
				SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, (zbx_uint64_t)st.st_gid));
			else
				SET_STR_RESULT(result, zbx_strdup(NULL, grp->gr_name));
		}
		else
		{
			struct passwd	*pwd;

			if (NULL == (pwd = getpwuid(st.st_uid)))
				SET_STR_RESULT(result, zbx_dsprintf(NULL, ZBX_FS_UI64, (zbx_uint64_t)st.st_uid));
			else
				SET_STR_RESULT(result, zbx_strdup(NULL, pwd->pw_name));
		}
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto err;
	}

	ret = SYSINFO_RET_OK;
err:
	return ret;
}
#endif

#if defined(_WINDOWS) || defined(__MINGW32__)
int	VFS_FILE_PERMISSIONS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(request);
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Item is not supported on Windows."));

	return SYSINFO_RET_FAIL;
}
#else
static char	*get_file_permissions(zbx_stat_t *st)
{
	return zbx_dsprintf(NULL, "%x%x%x%x", ((S_ISUID | S_ISGID | S_ISVTX) & st->st_mode) >> 9,
				(S_IRWXU & st->st_mode) >> 6, (S_IRWXG & st->st_mode) >> 3, S_IRWXO & st->st_mode);
}

int	VFS_FILE_PERMISSIONS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*filename;
	int		ret = SYSINFO_RET_FAIL;
	zbx_stat_t	st;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	filename = get_rparam(request, 0);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (SUCCEED != zbx_stat(filename, &st))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno)));
		goto err;
	}

	SET_STR_RESULT(result, get_file_permissions(&st));

	ret = SYSINFO_RET_OK;
err:
	return ret;
}
#endif

static char	*get_print_time(time_t st_raw)
{
#define MAX_TIME_STR_LEN	26
	struct tm	st;
	char		*st_str;

	st_str = zbx_malloc(NULL, MAX_TIME_STR_LEN);
	localtime_r(&st_raw, &st);
	strftime(st_str, MAX_TIME_STR_LEN, "%Y-%m-%dT%T%z", &st);

	return st_str;
#undef MAX_TIME_STR_LEN
}

#define VFS_FILE_ADD_TIME(time, tag)						\
	do									\
	{									\
		char	*tmp;							\
										\
		if (0 < time)							\
		{								\
			tmp = get_print_time((time_t)time);			\
			zbx_json_addstring(j, tag, tmp, ZBX_JSON_TYPE_STRING);	\
			zbx_free(tmp);						\
		}								\
		else								\
			zbx_json_addstring(j, tag, NULL, ZBX_JSON_TYPE_STRING);	\
	} while (0)

#define VFS_FILE_ADD_TS(time, tag)						\
	do									\
	{									\
		if (0 < time)							\
			zbx_json_adduint64(j, tag, (zbx_uint64_t)time);		\
		else								\
			zbx_json_addstring(j, tag, NULL, ZBX_JSON_TYPE_STRING);	\
	} while (0)

#if defined(_WINDOWS) || defined(__MINGW32__)
#	define ZBX_DIR_DELIMITER	"\\"
#else
#	define ZBX_DIR_DELIMITER	"/"
#endif

static int	get_dir_names(const char *filename, char **basename, char **dirname, char **pathname)
{
	char	*ptr1, *ptr2;
	size_t	len;

#if defined(_WINDOWS) || defined(__MINGW32__)
	if (NULL == (*pathname = _fullpath(NULL, filename, 0)))
		return FAIL;
#elif defined(__hpux)
	char resolved_path[PATH_MAX + 1];

	if (NULL == (*pathname = realpath(filename, resolved_path)))
		return FAIL;

	*pathname = zbx_strdup(NULL, *pathname);
#else
	if (NULL == (*pathname = realpath(filename, NULL)))
		return FAIL;
#endif

	ptr1 = *pathname;
	ptr2 = strstr(ptr1, ZBX_DIR_DELIMITER);

	while (NULL != ptr2)
	{
		len = strlen(ptr2) + 1;
		ptr1 = &ptr1[strlen(ptr1) - len + 2];
		ptr2 = strstr(ptr1, ZBX_DIR_DELIMITER);
	}

	if (0 == strlen(ptr1))
		*basename = zbx_strdup(NULL, *pathname);
	else
		*basename = zbx_strdup(NULL, ptr1);

	ptr2 = zbx_strdup(NULL, *pathname);
	len = strlen(*pathname) - strlen(ptr1);
#if defined(_WINDOWS) || defined(__MINGW32__)
	if (3 == len)
#else
	if (1 == len)
#endif
		len++;
	ptr2[len - 1] = '\0';
	*dirname = zbx_dsprintf(NULL, "%s", ptr2);
	zbx_free(ptr2);

	return SUCCEED;
}

#if defined(_WINDOWS) || defined(__MINGW32__)
int	zbx_vfs_file_info(const char *filename, struct zbx_json *j, int array, char **error)
{
	int			ret = FAIL;
	DWORD			file_attributes, acc_sz = 0, dmn_sz = 0;
	wchar_t			*wpath = NULL, *sid_string = NULL, *acc_name = NULL, *dmn_name = NULL;
	char			*type = NULL, *basename = NULL, *dirname = NULL,
				*pathname = NULL, *user = NULL, *sidbuf = NULL;
	PSID			sid = NULL;
	PSECURITY_DESCRIPTOR	sec = NULL;
	SID_NAME_USE		acc_type = SidTypeUnknown;
	zbx_stat_t		buf;
	zbx_file_time_t		file_time;
	zbx_uint64_t		size;

	if (NULL == (wpath = zbx_utf8_to_unicode(filename)))
	{
		*error = zbx_strdup(NULL, "Cannot convert file name to UTF-16.");
		goto err;
	}

	file_attributes = GetFileAttributesW(wpath);

	if (INVALID_FILE_ATTRIBUTES == file_attributes)
	{
		DWORD	last_error;

		switch (last_error = GetLastError())
		{
			case ERROR_FILE_NOT_FOUND:
				goto err;
			case ERROR_BAD_NETPATH:	/* special case from GetFileAttributesW() documentation */
				*error = zbx_dsprintf(NULL, "The specified file is a network share."
						" Use a path to a subfolder on that share.");
				goto err;
			default:
				*error = zbx_dsprintf(NULL, "Cannot obtain file information: %s",
						strerror_from_system(last_error));
				goto err;
		}
	}

	if (0 != (file_attributes & FILE_ATTRIBUTE_REPARSE_POINT))
		type = zbx_strdup(NULL, "sym");
	else if (0 != (file_attributes & FILE_ATTRIBUTE_DIRECTORY))
		type = zbx_strdup(NULL, "dir");
	else
		type = zbx_strdup(NULL, "file");

	if (SUCCEED != get_dir_names(filename, &basename, &dirname, &pathname))
	{
		*error = zbx_strdup(NULL, "Cannot obtain file or directory name.");
		goto err;
	}

	if (ERROR_SUCCESS != GetNamedSecurityInfo(wpath, SE_FILE_OBJECT, OWNER_SECURITY_INFORMATION, &sid, NULL, NULL,
			NULL, &sec))
	{
		*error = zbx_strdup(NULL, "Cannot obtain security information.");
		goto err;
	}

	LookupAccountSid(NULL, sid, acc_name, (LPDWORD)&acc_sz, dmn_name, (LPDWORD)&dmn_sz, &acc_type);

	acc_name = (wchar_t *)zbx_malloc(acc_name, acc_sz * sizeof(wchar_t));
	dmn_name = (wchar_t *)zbx_malloc(dmn_name, dmn_sz * sizeof(wchar_t));

	if (TRUE == LookupAccountSid(NULL, sid, acc_name, (LPDWORD)&acc_sz, dmn_name, (LPDWORD)&dmn_sz,
			&acc_type))
	{
		char	*acc_ut8, *dmn_ut8;

		acc_ut8 = zbx_unicode_to_utf8(acc_name);
		dmn_ut8 = zbx_unicode_to_utf8(dmn_name);

		if (0 == strlen(dmn_ut8))
			user = zbx_dsprintf(NULL, "%s", acc_ut8);
		else
			user = zbx_dsprintf(NULL, "%s\\%s", dmn_ut8, acc_ut8);

		zbx_free(acc_ut8);
		zbx_free(dmn_ut8);
	}

	zbx_free(acc_name);
	zbx_free(dmn_name);

	if (TRUE != ConvertSidToStringSid(sid, &sid_string))
	{
		*error = zbx_strdup(NULL, "Cannot obtain SID.");
		goto err;
	}

	sidbuf = zbx_unicode_to_utf8(sid_string);
	LocalFree(sid_string);

	if (0 != (file_attributes & (FILE_ATTRIBUTE_REPARSE_POINT | FILE_ATTRIBUTE_DIRECTORY)))
	{
		size = 0;
	}
	else if (0 != zbx_stat(filename, &buf))
	{
		*error = zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno));
		goto err;
	}
	else
		size = (zbx_uint64_t)buf.st_size;

	/* name */

	if (0 != array)
		zbx_json_addobject(j, NULL);

	zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_BASENAME, basename, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_PATHNAME, pathname, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_DIRNAME, dirname, ZBX_JSON_TYPE_STRING);

	/* type */
	zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_TYPE, type, ZBX_JSON_TYPE_STRING);

	/* User name */
	zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_USER, user, ZBX_JSON_TYPE_STRING);

	/* SID */
	zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_SID, sidbuf, ZBX_JSON_TYPE_STRING);

	/* size */
	zbx_json_adduint64(j, ZBX_SYSINFO_FILE_TAG_SIZE, size);

	/* time */

	if (SUCCEED != zbx_get_file_time(filename, 1, &file_time))
	{
		memset(&file_time, 0 ,sizeof(file_time));
	}

	zbx_json_addobject(j, ZBX_SYSINFO_FILE_TAG_TIME);
	VFS_FILE_ADD_TIME(file_time.access_time, ZBX_SYSINFO_FILE_TAG_TIME_ACCESS);
	VFS_FILE_ADD_TIME(file_time.modification_time, ZBX_SYSINFO_FILE_TAG_TIME_MODIFY);
	VFS_FILE_ADD_TIME(file_time.change_time, ZBX_SYSINFO_FILE_TAG_TIME_CHANGE);
	zbx_json_close(j);

	zbx_json_addobject(j, ZBX_SYSINFO_FILE_TAG_TIMESTAMP);
	VFS_FILE_ADD_TS(file_time.access_time, ZBX_SYSINFO_FILE_TAG_TIME_ACCESS);
	VFS_FILE_ADD_TS(file_time.modification_time, ZBX_SYSINFO_FILE_TAG_TIME_MODIFY);
	VFS_FILE_ADD_TS(file_time.change_time, ZBX_SYSINFO_FILE_TAG_TIME_CHANGE);
	zbx_json_close(j);

	/* close object*/
	zbx_json_close(j);

	ret =  SUCCEED;
err:
	if (NULL != sec)
		LocalFree(sec);

	zbx_free(wpath);

	zbx_free(type);
	zbx_free(basename);
	zbx_free(dirname);
	zbx_free(pathname);
	zbx_free(user);
	zbx_free(sidbuf);

	return ret;
}
#else /* not _WINDOWS or __MINGW32__ */
int	zbx_vfs_file_info(const char *filename, struct zbx_json *j, int array, char **error)
{
	int		ret = FAIL;
	char		*permissions, *type = NULL, *basename = NULL, *dirname = NULL, *pathname = NULL;
	zbx_file_time_t	file_time;
	zbx_stat_t	buf;
	struct group	*grp;
	struct passwd	*pwd;

	if (0 != lstat(filename, &buf))
	{
		*error = zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno));
		return FAIL;
	}

	if (0 != S_ISLNK(buf.st_mode))
		type = zbx_strdup(NULL, "sym");
	else if (0 != S_ISREG(buf.st_mode))
		type = zbx_strdup(NULL, "file");
	else if (0 != S_ISDIR(buf.st_mode))
		type = zbx_strdup(NULL, "dir");
	else if (0 != S_ISSOCK(buf.st_mode))
		type = zbx_strdup(NULL, "sock");
	else if (0 != S_ISBLK(buf.st_mode))
		type = zbx_strdup(NULL, "bdev");
	else if (0 != S_ISCHR(buf.st_mode))
		type = zbx_strdup(NULL, "cdev");
	else if (0 != S_ISFIFO(buf.st_mode))
		type = zbx_strdup(NULL, "fifo");

	if (SUCCEED != get_dir_names(filename, &basename, &dirname, &pathname))
	{
		*error = zbx_strdup(NULL, "Cannot obtain file or directory name.");
		goto err;
	}

	if (SUCCEED != zbx_get_file_time(filename, 1, &file_time))
	{
		*error = zbx_dsprintf(NULL, "Cannot obtain file information: %s", zbx_strerror(errno));
		goto err;
	}

	/* name */

	if (0 != array)
		zbx_json_addobject(j, NULL);

	zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_BASENAME, basename, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_PATHNAME, pathname, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_DIRNAME, dirname, ZBX_JSON_TYPE_STRING);

	/* type */
	zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_TYPE, type, ZBX_JSON_TYPE_STRING);

	/* user */
	if (NULL == (pwd = getpwuid(buf.st_uid)))
	{
		char	buffer[MAX_ID_LEN];

		zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, (zbx_uint64_t)buf.st_uid);
		zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_USER, buffer, ZBX_JSON_TYPE_STRING);
	}
	else
		zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_USER, pwd->pw_name, ZBX_JSON_TYPE_STRING);

	/* group */
	if (NULL == (grp = getgrgid(buf.st_gid)))
	{
		char	buffer[MAX_ID_LEN];

		zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, (zbx_uint64_t)buf.st_gid);
		zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_GROUP, buffer, ZBX_JSON_TYPE_STRING);
	}
	else
		zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_GROUP, grp->gr_name, ZBX_JSON_TYPE_STRING);

	/* permissions */
	permissions = get_file_permissions(&buf);
	zbx_json_addstring(j, ZBX_SYSINFO_FILE_TAG_PERMISSIONS, permissions, ZBX_JSON_TYPE_STRING);
	zbx_free(permissions);

	/* uid */
	zbx_json_adduint64(j, ZBX_SYSINFO_FILE_TAG_UID, (zbx_uint64_t)buf.st_uid);

	/* gid */
	zbx_json_adduint64(j, ZBX_SYSINFO_FILE_TAG_GID, (zbx_uint64_t)buf.st_gid);

	/* size */
	zbx_json_adduint64(j, ZBX_SYSINFO_FILE_TAG_SIZE, (zbx_uint64_t)buf.st_size);

	/* time */

	zbx_json_addobject(j, ZBX_SYSINFO_FILE_TAG_TIME);
	VFS_FILE_ADD_TIME(file_time.access_time, ZBX_SYSINFO_FILE_TAG_TIME_ACCESS);
	VFS_FILE_ADD_TIME(file_time.modification_time, ZBX_SYSINFO_FILE_TAG_TIME_MODIFY);
	VFS_FILE_ADD_TIME(file_time.change_time, ZBX_SYSINFO_FILE_TAG_TIME_CHANGE);
	zbx_json_close(j);

	zbx_json_addobject(j, ZBX_SYSINFO_FILE_TAG_TIMESTAMP);
	VFS_FILE_ADD_TS(file_time.access_time, ZBX_SYSINFO_FILE_TAG_TIME_ACCESS);
	VFS_FILE_ADD_TS(file_time.modification_time, ZBX_SYSINFO_FILE_TAG_TIME_MODIFY);
	VFS_FILE_ADD_TS(file_time.change_time, ZBX_SYSINFO_FILE_TAG_TIME_CHANGE);
	zbx_json_close(j);

	zbx_json_close(j);

	ret =  SUCCEED;
err:
	zbx_free(type);
	zbx_free(basename);
	zbx_free(dirname);
	zbx_free(pathname);

	return ret;
}
#endif

static int	vfs_file_get(const char *filename, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char		*error = NULL;
	struct zbx_json	j;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	if (SUCCEED == zbx_vfs_file_info(filename, &j, 0, &error))
	{
		SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

		ret =  SYSINFO_RET_OK;
	}
	else
		SET_MSG_RESULT(result, error);

	zbx_json_close(&j);

	zbx_json_free(&j);

	return ret;
}

#undef VFS_FILE_ADD_TIME
#undef VFS_FILE_ADD_TS

int	VFS_FILE_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*filename;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	filename = get_rparam(request, 0);

	if (NULL == filename || '\0' == *filename)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	return vfs_file_get(filename, result);
}
