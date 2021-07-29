/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
package webcertificate

import (
	"crypto/sha1"
	"crypto/sha256"
	"crypto/tls"
	"crypto/x509"
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"time"

	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/uri"
	"zabbix.com/pkg/zbxerr"
)

type Output struct {
	X509              Cert             `json:"x509"`
	Result            ValidationResult `json:"result"`
	Sha1Fingerprint   string           `json:"sha1_fingerprint"`
	Sha256Fingerprint string           `json:"sha256_fingerprint"`
}

type CertTime struct {
	Value     string `json:"value"`
	Timestamp int64  `json:"timestamp"`
}

type Cert struct {
	Version            int      `json:"version"`
	Serial             string   `json:"serial_number"`
	SignatureAlgorithm string   `json:"signature_algorithm"`
	Issuer             string   `json:"issuer"`
	NotBefore          CertTime `json:"not_before"`
	NotAfter           CertTime `json:"not_after"`
	Subject            string   `json:"subject"`
	PublicKeyAlgorithm string   `json:"public_key_algorithm"`
	AlternativeNames   []string `json:"alternative_names"`
}

type ValidationResult struct {
	Value   string `json:"value"`
	Message string `json:"message"`
}

type Options struct {
	Timeout int `conf:"optional,range=1:30"`
}

type Plugin struct {
	plugin.Base
	options Options
}

var impl Plugin

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	p.options.Timeout = global.Timeout
}

func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (interface{}, error) {
	if key != "web.certificate.get" {
		return nil, plugin.UnsupportedMetricError
	}

	hostname, port, dnsName, err := getParameters(params)
	if err != nil {
		return nil, zbxerr.ErrorInvalidParams.Wrap(err)
	}

	cert, err := getCertificatesPEM(fmt.Sprintf("%s:%s", hostname, port), p.options.Timeout)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	var o Output

	cert.KeyUsage = x509.KeyUsageCertSign

	o.X509 = Cert{
		cert.Version, fmt.Sprintf("%x", cert.SerialNumber.Bytes()), cert.SignatureAlgorithm.String(), cert.Issuer.ToRDNSequence().String(),
		CertTime{cert.NotBefore.Format(time.RFC822), cert.NotBefore.Unix()},
		CertTime{cert.NotAfter.Format(time.RFC822), cert.NotAfter.Unix()}, cert.Subject.ToRDNSequence().String(),
		cert.PublicKeyAlgorithm.String(), cert.DNSNames,
	}

	if _, err := cert.Verify(x509.VerifyOptions{DNSName: dnsName}); err != nil {
		if errors.As(err, &x509.UnknownAuthorityError{}) && o.X509.Subject == o.X509.Issuer {
			o.Result = ValidationResult{"valid-but-self-signed",
				"certificate verified successfully, but determined to be self signed"}
		} else {
			o.Result = ValidationResult{"invalid", fmt.Sprintf("failed to verify certificate: %s", err.Error())}
		}
	} else {
		o.Result = ValidationResult{"valid", "certificate verified successfully"}
	}

	o.Sha1Fingerprint = fmt.Sprintf("%x", sha1.Sum(cert.Raw))
	o.Sha256Fingerprint = fmt.Sprintf("%x", sha256.Sum256(cert.Raw))

	b, err := json.Marshal(o)
	if err != nil {
		return nil, err
	}

	return string(b), nil
}

func getParameters(params []string) (hostname, port, dnsName string, err error) {
	switch len(params) {
	case 3:
		if params[2] != "" {
			hostname, port, err = validateURL(params[2], params[1])
			dnsName = params[0]
		} else {
			hostname, port, err = validateURL(params[0], params[1])
			dnsName = hostname
		}
	case 2:
		hostname, port, err = validateURL(params[0], params[1])
		dnsName = hostname
	case 1:
		hostname, port, err = validateURL(params[0], "")
		dnsName = hostname
	case 0:
		err = zbxerr.ErrorTooFewParameters
	default:
		err = zbxerr.ErrorTooManyParameters
	}

	return
}

func validateURL(url, port string) (string, string, error) {
	out, err := uri.New(url, &uri.Defaults{Port: port, Scheme: "https"})
	if err != nil {
		return "", "", err
	}

	if out.Scheme() != "" && out.Scheme() != "https" {
		return "", "", errors.New("scheme must be https")
	}

	if out.Port() != "" && port != "" && out.Port() != port {
		return "", "", errors.New("port set incorrectly")
	}

	if out.Port() == "" && port == "" {
		return out.Host(), "443", nil
	}

	return out.Host(), out.Port(), nil
}

func getCertificatesPEM(address string, timeout int) (*x509.Certificate, error) {
	var dialer net.Dialer
	dialer.Timeout = time.Duration(timeout) * time.Second

	conn, err := tls.DialWithDialer(&dialer, "tcp", address, &tls.Config{InsecureSkipVerify: true})
	if err != nil {
		return nil, err
	}
	defer conn.Close()

	certs := conn.ConnectionState().PeerCertificates
	if len(certs) < 1 {
		return nil, zbxerr.ErrorCannotFetchData
	}

	return certs[0], nil
}

func init() {
	plugin.RegisterMetrics(&impl, "WebCertificate", "web.certificate.get", "Get TLS/SSL website certificate.")
}
