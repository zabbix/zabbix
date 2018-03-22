/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "log.h"

#ifdef HAVE_ZLIB
#include "zlib.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_zlib_strerror                                                *
 *                                                                            *
 * Purpose: converts zlib error code into error message                       *
 *                                                                            *
 * Parameters: in      - [IN] the error code                                  *
 *             message - [OUT] the output message                             *
 *                                                                            *
 ******************************************************************************/
static void	zbx_zlib_strerror(int err, char **message)
{
	switch (err)
	{
		case Z_ERRNO:
			*message = zbx_strdup(*message, zbx_strerror(errno));
			break;
		case Z_MEM_ERROR:
			*message = zbx_strdup(*message, "not enough memory");
			break;
		case Z_BUF_ERROR:
			*message = zbx_strdup(*message, "not enough space in output buffer");
			break;
		case Z_DATA_ERROR:
			*message = zbx_strdup(*message, "corrupted input data");
			break;
		default:
			*message = zbx_dsprintf(*message, "unknown error (%d)", err);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_compress                                                     *
 *                                                                            *
 * Purpose: compress data                                                     *
 *                                                                            *
 * Parameters: in       - [IN] the data to compress                           *
 *             size_in  - [IN] the input data size                            *
 *             out      - [OUT] the compressed data                           *
 *             size_out - [OUT] the compressed data size                      *
 *                                                                            *
 * Return value: SUCCEED - the data was compressed successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: In the case of success the output buffer must be freed by the    *
 *           caller.                                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_compress(const char *in, size_t size_in, char **out, size_t *size_out)
{
	int	ret_zlib;

	*size_out = compressBound(size_in);
	*out = (char *)zbx_malloc(NULL, *size_out);

	if (Z_OK != (ret_zlib = compress((unsigned char *)*out, size_out, (unsigned char *)in, size_in)))
	{
		char	*errmsg;

		zbx_free(*out);
		zbx_zlib_strerror(ret_zlib, &errmsg);
		zabbix_log(LOG_LEVEL_ERR, "Cannot compress data, returned error: %s", ret_zlib, errmsg);
		zbx_free(errmsg);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_uncompress                                                   *
 *                                                                            *
 * Purpose: uncompress data                                                   *
 *                                                                            *
 * Parameters: in       - [IN] the data to uncompress                         *
 *             size_in  - [IN] the input data size                            *
 *             out      - [OUT] the uncompressed data                         *
 *             size_out - [IN] the uncompressed data size                     *
 *                                                                            *
 * Return value: SUCCEED - the data was uncompressed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_uncompress(const char *in, size_t size_in, char *out, size_t size_out)
{
	int	ret_zlib;
	size_t	len = size_out;

	if (Z_OK != (ret_zlib = uncompress((unsigned char *)out, &len, (unsigned char *)in, size_in)))
	{
		char	*errmsg;

		zbx_zlib_strerror(ret_zlib, &errmsg);
		zabbix_log(LOG_LEVEL_ERR, "Cannot uncompress data, returned error: %s", ret_zlib, errmsg);
		zbx_free(errmsg);
		return FAIL;
	}

	return SUCCEED;
}

#else

int zbx_compress(const char *in, size_t size_in, char **out, size_t *size_out)
{
	return FAIL;
}

int zbx_uncompress(const char *in, size_t size_in, char *out, size_t size_out)
{
	return FAIL;
}

#endif
