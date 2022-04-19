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
	"strings"
	"time"

	"git.zabbix.com/ap/plugin-support/conf"
	"git.zabbix.com/ap/plugin-support/plugin"
	"git.zabbix.com/ap/plugin-support/uri"
	"git.zabbix.com/ap/plugin-support/zbxerr"
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
	plugin.SystemOptions `conf:"optional,name=System"`
	Timeout              int `conf:"optional,range=1:30"`
}

type Plugin struct {
	plugin.Base
	options Options
}

const (
	dateFormat         = "Jan 02 15:04:05 2006 GMT"
	allParameters      = 3
	noThirdParameter   = 2
	onlyFirstParameter = 1
	emptyParameters    = 0
)

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

	return p.webCertificateGet(params)
}

func (p *Plugin) webCertificateGet(params []string) (interface{}, error) {
	address, port, domain, err := getParameters(params)
	if err != nil {
		return nil, zbxerr.ErrorInvalidParams.Wrap(err)
	}

	certs, err := getCertificatesPEM(fmt.Sprintf("%s:%s", address, port), domain, p.options.Timeout)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if len(certs) < 1 {
		return nil, zbxerr.ErrorCannotFetchData
	}

	leaf := certs[0]
	leaf.KeyUsage = x509.KeyUsageCertSign

	var o Output
	o.X509 = Cert{
		leaf.Version,
		fmt.Sprintf("%x", leaf.SerialNumber.Bytes()),
		leaf.SignatureAlgorithm.String(),
		leaf.Issuer.ToRDNSequence().String(),
		CertTime{leaf.NotBefore.UTC().Format(dateFormat), leaf.NotBefore.Unix()},
		CertTime{leaf.NotAfter.UTC().Format(dateFormat), leaf.NotAfter.Unix()},
		leaf.Subject.ToRDNSequence().String(),
		leaf.PublicKeyAlgorithm.String(),
		leaf.DNSNames,
	}

	o.Result = getValidationResult(
		leaf, createVerificationOptions(domain, certs),
		leaf.Subject.ToRDNSequence().String(), leaf.Issuer.ToRDNSequence().String(),
	)

	o.Sha1Fingerprint = fmt.Sprintf("%x", sha1.Sum(leaf.Raw))
	o.Sha256Fingerprint = fmt.Sprintf("%x", sha256.Sum256(leaf.Raw))

	b, err := json.Marshal(o)
	if err != nil {
		return nil, err
	}

	return string(b), nil
}

func createVerificationOptions(domain string, certs []*x509.Certificate) x509.VerifyOptions {
	opts := x509.VerifyOptions{
		DNSName:       domain,
		Intermediates: x509.NewCertPool(),
	}

	for _, cert := range certs[1:] {
		opts.Intermediates.AddCert(cert)
	}

	return opts
}

func getValidationResult(leaf *x509.Certificate, opts x509.VerifyOptions, subject, issuer string) ValidationResult {
	var out ValidationResult

	if _, err := leaf.Verify(opts); err != nil {
		if errors.As(err, &x509.UnknownAuthorityError{}) && subject == issuer {
			out = ValidationResult{
				"valid-but-self-signed",
				"certificate verified successfully, but determined to be self signed",
			}
		} else {
			out = ValidationResult{"invalid", fmt.Sprintf("failed to verify certificate: %s", err.Error())}
		}
	} else {
		out = ValidationResult{"valid", "certificate verified successfully"}
	}

	return out
}

func getParameters(params []string) (address, port, domain string, err error) {
	switch len(params) {
	case allParameters:
		address, port, domain, err = getParsedParameters(params)
	case noThirdParameter:
		address, port, err = parseURL(params[0], params[1])
		domain = address
	case onlyFirstParameter:
		address, port, err = parseURL(params[0], "")
		domain = address
	case emptyParameters:
		return "", "", "", zbxerr.ErrorTooFewParameters
	default:
		return "", "", "", zbxerr.ErrorTooManyParameters
	}

	return
}

func checkForIPv6(address string) error {
	if isIpV6(net.ParseIP(cutBrackets(address))) {
		return zbxerr.New(fmt.Sprintf("%s can not be ipv6", address))
	}

	return nil
}

func getParsedParameters(params []string) (address, port, domain string, err error) {
	address, port, err = parseURL(params[0], params[1])
	if err != nil {
		return
	}

	domain = address

	if params[2] == "" {
		return
	}

	if ip := net.ParseIP(cutBrackets(params[2])); ip != nil {
		address, port, err = parseURL(params[2], params[1])
		if err != nil {
			return "", "", "", fmt.Errorf("failed to parse ip %s, and port %s, %s", params[2], params[1], err.Error())
		}

		return
	}

	if err = uri.IsHostnameOnly(params[2]); err != nil {
		return "", "", "", fmt.Errorf("failed to parse host %s, %s, required: hostname or IPv4", params[2], err.Error())
	}

	domain = params[2]

	return
}

func cutBrackets(in string) string {
	return strings.TrimSuffix(strings.TrimPrefix(in, "["), "]")
}

func isIpV6(ip net.IP) bool {
	if ip.To4() != nil {
		return false
	}

	if ip.To16() != nil {
		return true
	}

	return false
}

func parseURL(url, port string) (string, string, error) {
	// must be checked before url.New because url.New can not parse `::1` .
	if err := checkForIPv6(url); err != nil {
		return "", "", err
	}

	u, err := uri.New(url, &uri.Defaults{Port: port, Scheme: "https"})
	if err != nil {
		return "", "", err
	}

	// must be checked after the url is created to rule out full IPv6 with scheme.
	if err := checkForIPv6(u.Host()); err != nil {
		return "", "", err
	}

	if u.Scheme() != "" && u.Scheme() != "https" {
		return "", "", errors.New("scheme must be https")
	}

	return getHostAndPort(u, port)
}

func getHostAndPort(uri *uri.URI, port string) (string, string, error) {
	if uri.Port() != "" && port != "" && uri.Port() != port {
		return "", "", errors.New("port set incorrectly")
	}

	if uri.Port() == "" && port == "" {
		return uri.Host(), "443", nil
	}

	return uri.Host(), uri.Port(), nil
}

func getCertificatesPEM(address, domain string, timeout int) ([]*x509.Certificate, error) {
	var dialer net.Dialer
	dialer.Timeout = time.Duration(timeout) * time.Second

	conn, err := tls.DialWithDialer(&dialer, "tcp", address, &tls.Config{InsecureSkipVerify: true, ServerName: domain})
	if err != nil {
		return nil, err
	}
	defer conn.Close()

	return conn.ConnectionState().PeerCertificates, nil
}

func init() {
	plugin.RegisterMetrics(&impl, "WebCertificate", "web.certificate.get", "Get TLS/SSL website certificate.")
}
