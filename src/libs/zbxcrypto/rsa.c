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

#if defined(HAVE_OPENSSL)
#include "zbxcrypto.h"
#include "zbxstr.h"

#include <openssl/evp.h>
#include <openssl/rsa.h>
#include <openssl/err.h>
#include <openssl/bio.h>
#include <openssl/pem.h>
#elif defined(HAVE_GNUTLS)
#include "zbxcrypto.h"
#include "zbxstr.h"

#include <gnutls/gnutls.h>
#include <gnutls/crypto.h>
#include <gnutls/x509.h>
#include <gnutls/abstract.h>
#endif

#if defined(HAVE_OPENSSL) || defined(HAVE_GNUTLS)
static void	pem_replace_spaces(char *s)
{
	while ('\0' != *s && '-' != *s)
	{
		if (' ' == *s)
			*s = '\n';

		s++;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Receives PEM container with arbitrary newlines, validate if       *
 *          contains necessary newlines after header and before footer,       *
 *          insert them if they are absent.                                   *
 *                                                                            *
 * Parameters: key     - [IN/OUT] key in PEM container                        *
 *             key_len - [IN/OUT] key length                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_normalize_pem(char **key, size_t *key_len)
{
#define PEM_BEGIN		"-----BEGIN"
#define PEM_BEGIN_HEADER_END	"-----"
#define PEM_FOOTER_END		"-----END"
	char	*ptr;
	size_t	offset;

	if (0 == strncmp(*key, PEM_BEGIN, ZBX_CONST_STRLEN(PEM_BEGIN)))
	{
		if (NULL == (ptr = strstr(*key + ZBX_CONST_STRLEN(PEM_BEGIN), PEM_BEGIN_HEADER_END)))
			return;

		ptr += ZBX_CONST_STRLEN(PEM_BEGIN_HEADER_END);

		pem_replace_spaces(ptr);

		if ('\n' != *ptr)
		{
			offset = ptr - *key - 1;
			zbx_replace_string(key, offset, &offset, "-\n");
			*key_len = *key_len + 1;
		}

		if (NULL == (ptr = strstr(*key, PEM_FOOTER_END)))
			return;

		if ('\n' != *(ptr = ptr - 1))
		{
			offset = ptr - *key + 1;
			zbx_replace_string(key, offset, &offset, "\n-");
			*key_len = *key_len + 1;
		}
	}
#undef PEM_BEGIN
#undef PEM_BEGIN_HEADER_END
#undef PEM_FOOTER_END
}
#endif

#if defined(HAVE_OPENSSL)
/******************************************************************************
 *                                                                            *
 * Purpose: creates RS256 signature for given data                            *
 *                                                                            *
 * Parameters:                                                                *
 *     key         - [IN] private key in PEM container (PKCS#1 or PKCS#8)     *
 *     data        - [IN] data to sign                                        *
 *     data_len    - [IN] length of data to sign                              *
 *     output      - [OUT] dynamically allocated memory with signature        *
 *     output_len  - [OUT] length of signature (bytes)                        *
 *     error       - [OUT] dynamically allocated memory with error message    *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - signature was generated successfully                         *
 *     FAIL    - error occurred                                               *
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

	bio = BIO_new_mem_buf(key, (int)key_len);

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

	if (1 != EVP_DigestSignUpdate(mdctx, (const unsigned char *)data, data_len))
	{
		*error = zbx_strdup(NULL, "EVP_DigestSignUpdate failed");
		ret = FAIL;
		goto out;
	}

	if (1 != EVP_DigestSignFinal(mdctx, NULL, &sign_len))
	{
		*error = zbx_strdup(NULL, "failed to retrieve length of a signature");
		ret = FAIL;
		goto out;
	}

	sig = OPENSSL_malloc(sign_len);

	if (1 != EVP_DigestSignFinal(mdctx, sig, &sign_len))
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
