package tlsconfig

import (
	"crypto/tls"
	"crypto/x509"
	"errors"
	"fmt"
	"io/ioutil"

	"git.zabbix.com/ap/plugin-support/uri"
)

type Details struct {
	SessionName string
	TlsConnect  string
	TlsCaFile   string
	TlsCertFile string
	TlsKeyFile  string
	RawUri      string
}

func CreateConfig(details Details, skipVerify bool) (*tls.Config, error) {
	rootCertPool := x509.NewCertPool()
	pem, err := ioutil.ReadFile(details.TlsCaFile)
	if err != nil {
		return nil, err
	}

	if ok := rootCertPool.AppendCertsFromPEM(pem); !ok {
		return nil, errors.New("Failed to append PEM.")
	}

	clientCerts := make([]tls.Certificate, 0, 1)
	certs, err := tls.LoadX509KeyPair(details.TlsCertFile, details.TlsKeyFile)
	if err != nil {
		return nil, err
	}

	clientCerts = append(clientCerts, certs)

	if skipVerify {
		return &tls.Config{RootCAs: rootCertPool, Certificates: clientCerts, InsecureSkipVerify: skipVerify}, nil
	}

	url, err := uri.New(details.RawUri, nil)
	if err != nil {
		return nil, err
	}

	return &tls.Config{
		RootCAs: rootCertPool, Certificates: clientCerts, InsecureSkipVerify: skipVerify, ServerName: url.Host(),
	}, nil
}

func CreateDetails(session, dbConnect, caFile, certFile, keyFile, uri string) (Details, error) {
	if dbConnect != "" && dbConnect != "required" {
		if err := validateSetTLSFiles(caFile, certFile, keyFile); err != nil {
			return Details{}, fmt.Errorf("%s uri %s, with session %s", err.Error(), uri, session)
		}
	} else {
		if err := validateUnsetTLSFiles(caFile, certFile, keyFile); err != nil {
			return Details{}, fmt.Errorf("%s uri %s, with session %s", err.Error(), uri, session)
		}
	}

	return Details{session, dbConnect, caFile, certFile, keyFile, uri}, nil
}

func validateSetTLSFiles(caFile, certFile, keyFile string) error {
	if caFile == "" {
		return errors.New("missing TLS CA file for database")
	}

	if certFile == "" {
		return errors.New("missing TLS certificate file for database")
	}

	if keyFile == "" {
		return errors.New("missing TLS key file for database")
	}

	return nil
}

func validateUnsetTLSFiles(caFile, certFile, keyFile string) error {
	if caFile != "" {
		return errors.New(
			"TLS CA file configuration parameter set without certificates being used for database")
	}

	if certFile != "" {
		return errors.New(
			"TLS certificate file configuration parameter set without certificates being used for database")
	}

	if keyFile != "" {
		return errors.New(
			"TLS key file configuration parameter set without certificates being used for database")
	}

	return nil
}
