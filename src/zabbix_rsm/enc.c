#include <openssl/conf.h>
#include <openssl/evp.h>
#include <openssl/err.h>
#include <string.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>

#include "common.h"
#include "rsm.h"

const char      *progname = NULL;
const char	title_message[] = "Password encrypter";
const char	*help_message[] = {NULL};
const char	usage_message[] = "<passphrase> <secretkey_enc_b64> <secretkey_salt_b64> <password>\n"
		"Parameters:\n"
		"    <passphrase>                a passphrase for secret key decryption\n"
		"    <secretkey_enc_b64>         encrypted secret key for password encryption, base64-encoded\n"
		"    <secretkey_salt_b64>        salt for secret key decryption, base64-encoded\n"
		"    <password>                  a password to encrypt";

int	main(int argc, char *argv[])
{
	const char	*passphrase, *secretkey_enc_b64, *secretkey_salt_b64, *password;
	char		*password_enc_b64 = NULL, *password_salt_b64 = NULL, err[128];

	progname = get_program_name(argv[0]);

	if (argc != 5)
	{
		usage();
		exit(1);
	}

	passphrase = argv[1];
	secretkey_enc_b64 = argv[2];
	secretkey_salt_b64 = argv[3];
	password = argv[4];

	if (SUCCEED != encrypt_cleartext(passphrase, strlen(passphrase), secretkey_enc_b64, strlen(secretkey_enc_b64),
			secretkey_salt_b64, strlen(secretkey_salt_b64), password, strlen(password), &password_enc_b64,
			&password_salt_b64, err, sizeof(err)))
	{
		fprintf(stderr, "cannot encrypt password: %s\n", err);
		goto out;
	}

	printf("%s|%s\n", password_enc_b64, password_salt_b64);
out:
	if (NULL != password_enc_b64)
		zbx_free(password_enc_b64);

	if (NULL != password_salt_b64)
		zbx_free(password_salt_b64);

	return 0;
}
