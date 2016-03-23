#include <openssl/err.h>
#include <string.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>

#include "common.h"
#include "base64.h"
#include "rsm.h"

const char      *progname = NULL;
const char	title_message[] = "Key generator";
const char	*help_message[] = {NULL};
const char	usage_message[] = "\n"
		"Generate secret key encrypted by a passphrase and salt. The result key and salt are base64-encoded.";

int	main(int argc, char *argv[])
{
	const EVP_CIPHER	*cipher;
	const EVP_MD		*digest;
	unsigned char		passphrase[128], salt[8], secret_key[64], key[EVP_MAX_KEY_LENGTH], iv[EVP_MAX_IV_LENGTH],
				*secret_key_enc = NULL;
	char			*secret_key_enc_b64 = NULL, *salt_b64 = NULL, err[128];
	int			secret_key_enc_len, block_size, nrounds = 500;

	progname = get_program_name(argv[0]);

	if (argc != 1)
	{
		usage();
		exit(1);
	}

	if (SUCCEED != zbx_read_stdin("Enter EPP passphrase: ", (char *)passphrase, sizeof(passphrase),
			err, sizeof(err)))
	{
		fprintf(stderr, "cannot get EPP passphrase: %s\n", err);
		goto out;
	}

	/* initialize the library */
	if (SUCCEED != rsm_ssl_init())
	{
		fprintf(stderr, "cannot initialize SSL library\n");
		goto out;
	}

	cipher = EVP_aes_128_cbc();
	digest = EVP_sha256();

	block_size = EVP_CIPHER_block_size(cipher);

	/* generate salt */
	if (SUCCEED != get_random(salt, sizeof(salt)))
	{
		fprintf(stderr, "cannot get random salt\n");
		goto out;
	}

	/* generate secret key */
	if (SUCCEED != get_random(secret_key, sizeof(secret_key)))
	{
		fprintf(stderr, "cannot get random secret key\n");
		goto out;
	}

	/* generate key and IV for encryption */
	if (0 == EVP_BytesToKey(cipher, digest, salt, passphrase, strlen((const char *)passphrase), nrounds, key, iv))
	{
		fprintf(stderr, "cannot generate encryption key and initialization vector using passphrase\n");
		goto out;
	}

	/* according to docs encrypted text can take at most: secret_key_len + block_size - 1 */
	secret_key_enc = zbx_malloc(secret_key_enc, sizeof(secret_key) + block_size);

	/* encrypt the secret key */
	if (SUCCEED != encrypt(cipher, secret_key, sizeof(secret_key), key, iv, secret_key_enc, &secret_key_enc_len,
			err, sizeof(err)))
	{
		fprintf(stderr, "cannot encrypt secret key\n");
		goto out;
	}

	str_base64_encode_dyn((char *)secret_key_enc, &secret_key_enc_b64, secret_key_enc_len);
	str_base64_encode_dyn((char *)salt, &salt_b64, sizeof(salt));

	printf("%s|%s\n", secret_key_enc_b64, salt_b64);
out:
	if (NULL != salt_b64)
		zbx_free(salt_b64);

	if (NULL != secret_key_enc_b64)
		zbx_free(secret_key_enc_b64);

	if (NULL != secret_key_enc)
		zbx_free(secret_key_enc);

	EVP_cleanup();
	ERR_free_strings();

	return 0;
}
