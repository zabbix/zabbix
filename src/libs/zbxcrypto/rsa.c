/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxcrypto.h"
#include "zbxstr.h"

#if defined(HAVE_OPENSSL)
#include <openssl/evp.h>
#include <openssl/rsa.h>
#include <openssl/err.h>
#include <openssl/bio.h>
#include <openssl/pem.h>
#elif defined(HAVE_GNUTLS)
#include <gnutls/gnutls.h>
#include <gnutls/crypto.h>
#include <gnutls/x509.h>
#include <gnutls/abstract.h>
#endif

#if defined(HAVE_OPENSSL) || defined(HAVE_GNUTLS)
/******************************************************************************
 *                                                                            *
 * Purpose: receive private key in PEM container with arbitrary newlines,     *
 *          validate if its header corresponds to PKCS#1 or PKCS#8 format,    *
 *          the if required - add newlines to header and footer (so OpenSSL   *
 *          or GnuTLS would be able to parse it)                              *
 *                                                                            *
 * Parameters:                                                                *
 *     key         - [OUT/IN] the private key in ASCII format                 *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - key was successfully formatted                               *
 *     FAIL    - an error occurred                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_format_pem_pkey(char **key)
{
#define PEM_PKEY_BEGIN			"-----BEGIN"
#define PEM_PKEY_BEGIN_HEADER_END	"KEY-----"
#define PEM_PKEY_FOOTER_END		"-----END"

	char	*begin_ptr, *end_ptr, *newline_begin, *newline_end;
	size_t	offset;

	if (0 == strncmp(*key, PEM_PKEY_BEGIN, ZBX_CONST_STRLEN(PEM_PKEY_BEGIN)))
	{
		if (NULL == (begin_ptr = strstr(*key, PEM_PKEY_BEGIN_HEADER_END)))
			return FAIL;

		if ('\n' != *(newline_begin = begin_ptr + ZBX_CONST_STRLEN(PEM_PKEY_BEGIN_HEADER_END)))
		{
			offset = newline_begin - *key - 1;
			zbx_replace_string(key, offset, &offset, "-\n");
		}

		if (NULL == (end_ptr = strstr(*key, PEM_PKEY_FOOTER_END)))
			return FAIL;

		if ('\n' != *(newline_end = end_ptr - 1))
		{
			offset = newline_end - *key + 1;
			zbx_replace_string(key, offset, &offset, "\n-");
		}

		return SUCCEED;
	}

	return FAIL;

#undef PEM_PKEY_BEGIN
#undef PEM_PKEY_BEGIN_HEADER_END
#undef PEM_PKEY_FOOTER_END
}
#endif

#if defined(HAVE_OPENSSL)
/******************************************************************************
 *                                                                            *
 * Purpose: create RS256 signature for given data                             *
 *                                                                            *
 * Parameters:                                                                *
 *     key         - [IN] private key in a PEM container (PKCS#1 or PKCS#8)   *
 *     data        - [IN] data to sign                                        *
 *     data        - [IN] length of data to sign                              *
 *     output      - [OUT] dynamically allocated memory with signature        *
 *     output_len  - [OUT] length of a signature (bytes)                      *
 *     error       - [OUT] dynamically allocated memory with error message    *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - signature was generated successfully                         *
 *     FAIL    - an error occurred                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_rs256_sign(char *key, size_t key_len, char *data, size_t data_len, unsigned char **output,
		size_t *output_len, char **error)
{
	int		ret = SUCCEED;
	BIO		*bio = NULL;
	EVP_PKEY	*pkey = NULL;
	EVP_MD_CTX	*mdctx = NULL;
	unsigned char	*sig = NULL;
	size_t		sign_len;

	ZBX_UNUSED(key_len);

	bio = BIO_new_mem_buf(key, -1);

	if (NULL == (pkey = PEM_read_bio_PrivateKey(bio, NULL, NULL, NULL)) ||
			NULL == (mdctx = EVP_MD_CTX_create()))
	{
		*error = zbx_strdup(NULL, "failed to import private key");
		ret = FAIL;
		goto out;
	}

	if (1 != EVP_DigestSignInit(mdctx, NULL, EVP_sha256(), NULL, pkey))
	{
		*error = zbx_strdup(NULL, "EVP_DigestSignInit failed");
		ret = FAIL;
		goto out;
	}

	if (0 >= EVP_DigestSign(mdctx, NULL, &sign_len, NULL, 0))
	{
		*error = zbx_strdup(NULL, "failed to retrieve length of a signature");
		ret = FAIL;
		goto out;
	}

	sig = OPENSSL_malloc(sign_len);

	if (0 >= EVP_DigestSign(mdctx, sig, &sign_len, (const unsigned char *)data, data_len))
	{
		*error = zbx_strdup(NULL, "signing failed");
		ret = FAIL;
	}
	else
	{
		*output = (unsigned char *)zbx_malloc(NULL, sign_len);
		*output_len = (size_t)sign_len;
		memcpy(*output, sig, sign_len);
	}
out:
	if (NULL != bio)
		BIO_free(bio);

	if (NULL != pkey)
		EVP_PKEY_free(pkey);

	if (NULL != mdctx)
		EVP_MD_CTX_destroy(mdctx);

	if (NULL != sig)
		OPENSSL_free(sig);

	return ret;
}
#elif defined(HAVE_GNUTLS)
int	zbx_rs256_sign(char *key, size_t key_len, char *data, size_t data_len, unsigned char **output,
		size_t *output_len, char **error)
{
	gnutls_x509_privkey_t	x509key;
	gnutls_privkey_t	privkey;
	gnutls_datum_t		keyd, bodyd, sigd;
	int			ret = SUCCEED;

	keyd.data = (unsigned char *)key;
	keyd.size = key_len;
	bodyd.data = (unsigned char *)data;
	bodyd.size = data_len;

	if (GNUTLS_E_SUCCESS != gnutls_x509_privkey_init(&x509key))
	{
		*error = zbx_strdup(NULL, "failed to initialize storage for private key");
		return FAIL;
	}

	if (GNUTLS_E_SUCCESS != gnutls_x509_privkey_import(x509key, &keyd, GNUTLS_X509_FMT_PEM)
			|| GNUTLS_E_SUCCESS != gnutls_privkey_init(&privkey))
	{
		*error = zbx_strdup(NULL, "failed to import private key");
		ret = FAIL;
		goto out2;
	}

	if (GNUTLS_E_SUCCESS != gnutls_privkey_import_x509(privkey, x509key, 0))
	{
		*error = zbx_strdup(NULL, "failed to import private key");
		ret = FAIL;
		goto out1;
	}

	if (GNUTLS_E_SUCCESS != gnutls_privkey_sign_data(privkey, GNUTLS_DIG_SHA256, 0, &bodyd, &sigd))
	{
		*error = zbx_strdup(NULL, "signing failed");
		ret = FAIL;
		goto out1;
	}
	else
	{
		*output = (unsigned char *)zbx_malloc(NULL, sigd.size);
		*output_len = (size_t)sigd.size;
		memcpy(*output, sigd.data, sigd.size);
		gnutls_free(sigd.data);
	}
out1:
	gnutls_privkey_deinit(privkey);
out2:
	gnutls_x509_privkey_deinit(x509key);

	return ret;
}
#endif
