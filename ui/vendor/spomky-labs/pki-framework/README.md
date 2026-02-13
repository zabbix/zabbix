# Public Key Infrastructure

> **Note**
> The code in this framework is the same as the one available in https://github.com/sop,
> but modified to fulfil with the Spomky-Labs requirements.
> All credits go to the original developer

A PHP Framework

* X.509 public key certificates, attribute certificates,
* X.690 Abstract Syntax Notation One (ASN.1) Distinguished Encoding Rules (DER) encoding and decoding
* X.501 ASN.1 types, X.520 attributes and DN parsing.
* [RFC 7468](https://tools.ietf.org/html/rfc7468) textual encodings of cryptographic structures _(PEM)_.
* Various ASN.1 types for cryptographic applications.
* Cryptography support for various PKCS applications.

## Requirements

- PHP >=8.1
- `mbstring`

The extension `gmp` or `bcmath` is highly recommended

## Installation

This library is available on [Github](https://github.com/Spomky-Labs/pki-framework).

```sh
composer require spomky-labs/pki-framework
```

## License

This project is licensed under the MIT License.
