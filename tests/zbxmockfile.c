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

#define fopen	__real_fopen
#define fclose	__real_fclose
#define fgets	__real_fgets
#define lseek	__real_lseek
#define close	__real_close
#include <unistd.h>
#include <stdio.h>
#undef fopen
#undef fclose
#undef fgets
#undef lseek
#undef close

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"

#include "zbxcommon.h"

#define ZBX_MOCK_MAX_FILES	16

void	*mock_streams[ZBX_MOCK_MAX_FILES];

static zbx_mock_handle_t	fragments;

static FILE	*(*fopen_mock_callback)(const char *, const char *) = NULL;

struct zbx_mock_IO_FILE
{
	const char	*contents;
};

FILE	*__wrap_fopen(const char *path, const char *mode);
int	__wrap_fclose(FILE *fp);
char	*__wrap_fgets(char *s, int size, FILE *stream);
int	__wrap_fstat(int __fildes, struct stat *__stat_buf);
int	__wrap_connect(int socket, void *addr, socklen_t address_len);
int	__wrap_poll(struct pollfd *pds, int nfds, int timeout);
off_t	__wrap_lseek(int fd, off_t offset, int whence);
int	__wrap_close(int fd);
ssize_t	__wrap_read(int fildes, void *buf, size_t nbyte);
int	__wrap_open(const char *path, int oflag, ...);
int	__wrap_stat(const char *path, struct stat *buf);
int	__wrap___xstat(int ver, const char *pathname, struct stat *buf);
#ifdef HAVE_FXSTAT
int	__wrap___fxstat(int __ver, int __fildes, struct stat *__stat_buf);
#endif

int	__real_open(const char *path, int oflag, ...);
int	__real_stat(const char *path, struct stat *buf);
int	__real_fstat(int __fildes, struct stat *__stat_buf);
#ifdef HAVE_FXSTAT
int	__real___fxstat(int __ver, int __fildes, struct stat *__stat_buf);
#endif

static int	is_profiler_path(const char *path)
{
	size_t	len;

	len = strlen(path);

	if ((ZBX_CONST_STRLEN(".gcda") < len && 0 == strcmp(path + len - ZBX_CONST_STRLEN(".gcda"), ".gcda")) ||
			(ZBX_CONST_STRLEN(".gcno") < len &&
					0 == strcmp(path + len - ZBX_CONST_STRLEN(".gcno"), ".gcno")))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	is_mock_stream(FILE *stream)
{
	int	i;

	for (i = 0; i < ZBX_MOCK_MAX_FILES && NULL != mock_streams[i]; i++)
	{
		if (stream == mock_streams[i])
			return SUCCEED;
	}

	return FAIL;
}

FILE	*__wrap_fopen(const char *path, const char *mode)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	file_contents;
	const char		*contents;
	struct zbx_mock_IO_FILE	*file = NULL;

	/* in case a test needs a custom fopen mock, use callback instead */
	if (NULL != fopen_mock_callback)
		return (*fopen_mock_callback)(path, mode);

	if (SUCCEED == is_profiler_path(path))
		return __real_fopen(path, mode);

	if (0 != strcmp(mode, "r"))
	{
		fail_msg("fopen() modes other than \"r\" are not supported.");
	}
	else if (ZBX_MOCK_NO_PARAMETER == (error = zbx_mock_file(path, &file_contents)))
	{
		errno = ENOENT;	/* No such file or directory */
	}
	else if (ZBX_MOCK_SUCCESS != error)
	{
		fail_msg("Error while trying to open path \"%s\" from test case data: %s", path,
				zbx_mock_error_string(error));
	}
	else if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(file_contents, &contents)))
	{
		fail_msg("Error while trying to get contents of file \"%s\" from test case data: %s", path,
				zbx_mock_error_string(error));
	}
	else
	{
		int	i;

		for (i = 0; i < ZBX_MOCK_MAX_FILES && NULL != mock_streams[i]; i++)
			;

		if (i < ZBX_MOCK_MAX_FILES)
		{
			file = zbx_malloc(file, sizeof(struct zbx_mock_IO_FILE));
			file->contents = contents;
			mock_streams[i] = file;
		}
		else
			errno = EMFILE;	/* The per-process limit on the number of open file descriptors has been reached */
	}

	return (FILE *)file;
}

int	__wrap_fclose(FILE *fp)
{
	if (SUCCEED != is_mock_stream(fp))
		return __real_fclose(fp);

	zbx_free(fp);
	return 0;
}

char	*__wrap_fgets(char *s, int size, FILE *stream)
{
	struct zbx_mock_IO_FILE	*file = (struct zbx_mock_IO_FILE *)stream;
	int			length;
	const char		*newline;

	if (SUCCEED != is_mock_stream(stream))
		return __real_fgets(s, size, stream);

	assert_non_null(s);
	assert_true(0 < size);

	if ('\0' == *file->contents)
		return NULL;

	if (size - 1 < (length = strlen(file->contents)))
		length = size - 1;

	if (NULL != (newline = strchr(file->contents, '\n')) && newline - file->contents + 1 < length)
		length = newline - file->contents + 1;

	assert_int_equal(length, zbx_snprintf(s, size, "%.*s", length, file->contents));
	file->contents += length;
	return s;
}

int	__wrap_connect(int socket, void *addr, socklen_t address_len)
{
	zbx_mock_error_t	error;

	ZBX_UNUSED(socket);
	ZBX_UNUSED(addr);
	ZBX_UNUSED(address_len);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("fragments", &fragments)))
		fail_msg("Cannot get fragments handle: %s", zbx_mock_error_string(error));

	return 0;
}

int	__wrap_poll(struct pollfd *pds, int nfds, int timeout)
{
	ZBX_UNUSED(timeout);

	for (int i = 0; i < nfds; i++)
		pds[i].revents = (POLLIN | POLLOUT);

	return nfds;
}

static const char	*frag_data = NULL;
static const char	*frag_pos = NULL;
static size_t		frag_sz = 0;

static int	next_fragment(void)
{
	zbx_mock_handle_t	fragment;
	zbx_mock_error_t	error;

	if (ZBX_MOCK_SUCCESS != zbx_mock_vector_element(fragments, &fragment))
		return 0;	/* no more data */

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_binary(fragment, &frag_data, &frag_sz)))
		fail_msg("Cannot read data '%s'", zbx_mock_error_string(error));
	frag_pos = frag_data;

	return 1;
}

int	__wrap_open(const char *path, int oflag, ...)
{
	if (SUCCEED == is_profiler_path(path))
	{
		va_list	args;
		int	fd;

		va_start(args, oflag);
		fd = __real_open(path, oflag, va_arg(args, int));
		va_end(args);
		return fd;
	}

	fragments = zbx_mock_get_parameter_handle("in.fragments");
	next_fragment();

	return INT_MAX;
}

/******************************************************************************
 *                                                                            *
 * Comments: Note that simply wrapping read function will break any compiled  *
 *           in tool, that would attempt to use read() function. In this case *
 *           some safeguards must be added to implement pass-through          *
 *           functionality like it's done with open/fxstat etc functions for  *
 *           coverage builds.                                                 *
 *                                                                            *
 ******************************************************************************/
ssize_t	__wrap_read(int fildes, void *buf, size_t nbyte)
{
	size_t	mv_len;

	ZBX_UNUSED(fildes);

	if (frag_pos >= frag_data + frag_sz)
	{
		if (1 != next_fragment())
			return 0;
	}

	if ((ssize_t)nbyte < (frag_data + frag_sz) - frag_pos)
		mv_len = nbyte;
	else
		mv_len = (frag_data + frag_sz) - frag_pos;

	memcpy(buf, frag_pos, mv_len);

	frag_pos += mv_len;

	return mv_len;
}

off_t	__wrap_lseek(int fd, off_t offset, int whence)
{
	const char	*new_pos;

	if (fd != INT_MAX)
		return __real_lseek(fd, offset, whence);

	switch(whence)
	{
		case SEEK_END:
			new_pos = (frag_data + frag_sz) + offset;
			break;
		case SEEK_CUR:
			new_pos = frag_pos + offset;
			break;
		case SEEK_SET:
			new_pos = frag_data + offset;
			break;
		default:
			errno = EINVAL;
			goto error;
	}

	if (new_pos < frag_data || new_pos > frag_data + frag_sz)
	{
		errno = EOVERFLOW;
		goto error;
	}

	frag_pos = new_pos;

	return frag_pos - frag_data;
error:
	return (off_t)-1;
}

int	__wrap_close(int fd)
{
	if (fd != INT_MAX) return __real_close(fd);

	frag_data = frag_pos = NULL;
	frag_sz = 0;

	return 0;
}

int	__wrap_stat(const char *path, struct stat *buf)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	handle;

	if (SUCCEED == is_profiler_path(path))
		return __real_stat(path, buf);

	if (ZBX_MOCK_SUCCESS == (error = zbx_mock_file(path, &handle)))
	{
		buf->st_mode = S_IFMT & S_IFREG;
		return 0;
	}

	if (ZBX_MOCK_NO_PARAMETER != error)
		fail_msg("Error during path \"%s\" lookup among files: %s", path, zbx_mock_error_string(error));

	if (0)	/* directory lookup is not implemented */
	{
		buf->st_mode = S_IFMT & S_IFDIR;
		return 0;
	}

	errno = ENOENT;	/* No such file or directory */
	return -1;
}

int	__wrap_fstat(int __fildes, struct stat *__stat_buf)
{
	if (__fildes != INT_MAX)
		return __real_fstat(__fildes, __stat_buf);

	__stat_buf->st_size = zbx_mock_get_parameter_uint64("in.file_len");
	return 0;
}

int	__wrap___xstat(int ver, const char *pathname, struct stat *buf)
{
	ZBX_UNUSED(ver);

	if (SUCCEED == is_profiler_path(pathname))
		return __real_stat(pathname, buf);

	return __wrap_stat(pathname, buf);
}

#ifdef HAVE_FXSTAT
int	__wrap___fxstat(int __ver, int __fildes, struct stat *__stat_buf)
{
	if (__fildes != INT_MAX)
		return __real___fxstat(__ver, __fildes, __stat_buf);

	__stat_buf->st_size = zbx_mock_get_parameter_uint64("in.file_len");

	return 0;
}
#endif

void	zbx_set_fopen_mock_callback(FILE *(*fopen_callback)(const char *, const char *))
{
	fopen_mock_callback = fopen_callback;
}
