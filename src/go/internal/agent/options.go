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

package agent

import (
	"bytes"
	"errors"
	"fmt"
	"io"
	"net"
	"net/url"
	"os"
	"regexp"
	"slices"
	"strings"
	"unicode"
	"unicode/utf8"

	"golang.zabbix.com/agent2/pkg/tls"
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxnet"
)

var Options AgentOptions

const (
	HostNameLen      = 128
	hostNameListLen  = 2048
	HostMetadataLen  = 65535 // UTF-8 characters, not bytes
	HostInterfaceLen = 255   // UTF-8 characters, not bytes
	Variant          = 2
)

var (
	hostnameRegex *regexp.Regexp = regexp.MustCompile(
		`^([a-zA-Z0-9_]{1}[a-zA-Z0-9_-]{0,62}){1}(\.[a-zA-Z0-9_]{1}[a-zA-Z0-9_-]{0,62})*[\._]?$`)

	errInvalidTLSConnect = errors.New("invalid TLSConnect configuration parameter")
	errInvalidTLSAccept  = errors.New("invalid TLSAccept configuration parameter")
	errCipherCertAndAll  = errors.New(`TLSCipherCert configuration parameter cannot be used when the combined list` +
		` of certificate and PSK ciphersuites are used. Use TLSCipherAll to configure certificate ciphers`)
	errCipherCert13AndAll = errors.New(`TLSCipherCert13 configuration parameter cannot be used when the combined` +
		` list of certificate and PSK ciphersuites are used. Use TLSCipherAll13 to configure certificate ciphers`)
	errCipherAllRedundant = errors.New(`parameter "TLSCipherAll" cannot be applied: the combined list of certificate` +
		` and PSK ciphersuites is not used. Most likely parameters "TLSCipherCert" and/or "TLSCipherPSK"` +
		` are sufficient`)
	errCipherAll13Redundant = errors.New(`parameter "TLSCipherAll13" cannot be applied: the combined list of` +
		` certificate and PSK ciphersuites is not used. Most likely parameters "TLSCipherCert13" and/or` +
		` "TLSCipherPSK13" are sufficient`)
	errMissingTLSPskIdentity    = errors.New("missing TLSPSKIdentity configuration parameter")
	errMissingTLSPskFile        = errors.New("missing TLSPSKFile configuration parameter")
	errTLSPSKIdentityWithoutPsk = errors.New("TLSPSKIdentity configuration parameter set without PSK being used")
	errTLSPSKFileWithoutPsk     = errors.New("TLSPSKFile configuration parameter set without PSK being used")
	errTLSCipherPSKWithoutPsk   = errors.New("TLSCipherPSK configuration parameter set without PSK being used")
	errTLSCipherPSK13WithoutPsk = errors.New("TLSCipherPSK13 configuration parameter set without PSK being used")
	errMissingTLSCAFile         = errors.New("missing TLSCAFile configuration parameter")
	errMissingTLSCertFile       = errors.New("missing TLSCertFile configuration parameter")
	errMissingTLSKeyFile        = errors.New("missing TLSKeyFile configuration parameter")
	errTLSCAFileWithoutCert     = errors.New("TLSCAFile configuration parameter set without certificates being" +
		" used")
	errTLSCertFileWithoutCert = errors.New("TLSCertFile configuration parameter set without certificates" +
		" being used")
	errTLSKeyFileWithoutCert = errors.New("TLSKeyFile configuration parameter set without certificates" +
		" being used")
	errTLSServerCertIssuerWithoutCert = errors.New("TLSServerCertIssuer configuration parameter set without" +
		" certificates being used")
	errTLSServerCertSubjectWithoutCert = errors.New("TLSServerCertSubject configuration parameter set without" +
		" certificates being used")
	errTLSCRLFileWithoutCert   = errors.New("TLSCRLFile configuration parameter set without certificates being used")
	errCipherCertWithoutCert   = errors.New("TLSCipherCert configuration parameter set without certificates being used")
	errCipherCert13WithoutCert = errors.New("TLSCipherCert13 configuration parameter set without certificates being" +
		" used")
	errInvalidTLSPSKFile = errors.New("invalid TLSPSKFile configuration parameter")
)

// PluginSystemOptions collection of system options for all plugins, map key are plugin names.
type PluginSystemOptions map[string]SystemOptions

// SystemOptions holds reserved plugin options.
type SystemOptions struct {
	Path                     *string `conf:"optional"`
	ForceActiveChecksOnStart *int    `conf:"optional,range=0:1"`
	Capacity                 int     `conf:"optional,range=1:1000"`
}

type pluginOptions struct {
	System SystemOptions `conf:"optional"`
}

// RemovePluginSystemOptions removes system configuration from plugin options and returns them as separate
// PluginSystemOptions that is a map where map key is the option name and map value is the corresponding value.
func (a *AgentOptions) RemovePluginSystemOptions() (PluginSystemOptions, error) {
	out := make(PluginSystemOptions)

	for name, p := range a.Plugins {
		var o pluginOptions
		if err := conf.Unmarshal(p, &o); err != nil {
			return nil, errs.Wrapf(err, "failed to unmarshal options for plugin %s ", name)
		}

		a.Plugins[name] = removeSystem(p)
		out[name] = o.System
	}

	return out, nil
}

// CutAfterN returns the whole string s, if it is not longer then n runes (not bytes). Otherwise it returns the
// beginning of the string s, which is cut after the fist n runes.
func CutAfterN(s string, n int) string {
	var i int
	for pos := range s {
		if i >= n {
			return s[:pos]
		}
		i++
	}

	return s
}

func CheckHostnameParameter(s string) error {
	for i := 0; i < len(s); i++ {
		if s[i] == '.' || s[i] == ' ' || s[i] == '_' || s[i] == '-' || s[i] == ',' ||
			(s[i] >= 'A' && s[i] <= 'Z') || (s[i] >= 'a' && s[i] <= 'z') || (s[i] >= '0' && s[i] <= '9') {
			continue
		}

		if unicode.IsPrint(rune(s[i])) {
			return fmt.Errorf("character \"%c\" is not allowed in host name", s[i])
		} else {
			return fmt.Errorf("character 0x%02x is not allowed in host name", s[i])
		}
	}
	return nil
}

func ValidateHostnames(s string) ([]string, error) {
	hostnames := ExtractHostnames(s)
	keys := make(map[string]bool)
	huniq := []string{}
	for _, h := range hostnames {
		if h == "" {
			return nil, fmt.Errorf("host names cannot be empty")
		}
		if len(h) > HostNameLen {
			return nil, fmt.Errorf("host name in list is more than %d symbols", HostNameLen)
		}
		if _, value := keys[h]; !value {
			keys[h] = true
			huniq = append(huniq, h)
		}
	}
	if len(huniq) != len(hostnames) {
		return nil, fmt.Errorf("host names are not unique")
	}

	return hostnames, nil
}

func ExtractHostnames(s string) []string {
	hostnames := strings.Split(s, ",")

	for i := 0; i < len(hostnames); i++ {
		hostnames[i] = strings.Trim(hostnames[i], " \t")
	}

	return hostnames
}

func GetTLSConfig(options *AgentOptions) (cfg *tls.Config, err error) {
	if !tls.Supported() {
		if options.TLSAccept != "" ||
			options.TLSConnect != "" ||
			options.TLSPSKFile != "" ||
			options.TLSKeyFile != "" ||
			options.TLSCertFile != "" ||
			options.TLSPSKIdentity != "" {
			return nil, errors.New(tls.SupportedErrMsg())
		}

		return
	}

	c := &tls.Config{}

	switch options.TLSConnect {
	case "", "unencrypted":
		c.Connect = tls.ConnUnencrypted
	case "psk":
		c.Connect = tls.ConnPSK
	case "cert":
		c.Connect = tls.ConnCert
	default:
		return nil, errInvalidTLSConnect
	}

	if options.TLSAccept != "" {
		opts := strings.Split(options.TLSAccept, ",")
		for _, o := range opts {
			switch strings.Trim(o, " \t") {
			case "unencrypted":
				c.Accept |= tls.ConnUnencrypted
			case "psk":
				c.Accept |= tls.ConnPSK
			case "cert":
				c.Accept |= tls.ConnCert
			default:
				return nil, errInvalidTLSAccept
			}
		}
	} else {
		c.Accept = tls.ConnUnencrypted
	}

	if c.Accept&(tls.ConnPSK|tls.ConnCert) == tls.ConnPSK|tls.ConnCert {
		err = requireNoCipherCert(options)
		if err != nil {
			return nil, err
		}

		c.CipherAll = options.TLSCipherAll
		c.CipherAll13 = options.TLSCipherAll13
	} else {
		err = requireNoCipherAll(options)
		if err != nil {
			return nil, err
		}

		c.CipherAll = options.TLSCipherCert
		c.CipherAll13 = options.TLSCipherCert13
	}

	c.CipherPSK = options.TLSCipherPSK
	c.CipherPSK13 = options.TLSCipherPSK13

	if (c.Accept|c.Connect)&tls.ConnPSK != 0 {
		if options.TLSPSKIdentity == "" {
			return nil, errMissingTLSPskIdentity
		}

		c.PSKIdentity = options.TLSPSKIdentity

		if options.TLSPSKFile == "" {
			return nil, errMissingTLSPskFile
		}

		var file *os.File

		if file, err = os.Open(options.TLSPSKFile); err != nil {
			return nil, invalidTLSPSKFileError(err)
		}

		defer func() {
			closeErr := file.Close()
			if closeErr != nil {
				log.Debugf("error closing file: %s\n", err)
			}
		}()

		var b []byte

		if b, err = io.ReadAll(file); err != nil {
			return nil, invalidTLSPSKFileError(err)
		}

		c.PSKKey = string(bytes.TrimRight(b, "\r\n \t"))
	} else {
		if options.TLSPSKIdentity != "" {
			return nil, errTLSPSKIdentityWithoutPsk
		}

		if options.TLSPSKFile != "" {
			return nil, errTLSPSKFileWithoutPsk
		}

		if options.TLSCipherPSK != "" {
			return nil, errTLSCipherPSKWithoutPsk
		}

		if options.TLSCipherPSK13 != "" {
			return nil, errTLSCipherPSK13WithoutPsk
		}
	}

	if (c.Accept|c.Connect)&tls.ConnCert != 0 {
		if options.TLSCAFile == "" {
			return nil, errMissingTLSCAFile
		}

		c.CAFile = options.TLSCAFile

		if options.TLSCertFile == "" {
			return nil, errMissingTLSCertFile
		}

		c.CertFile = options.TLSCertFile

		if options.TLSKeyFile == "" {
			return nil, errMissingTLSKeyFile
		}

		c.KeyFile = options.TLSKeyFile
		c.ServerCertIssuer = options.TLSServerCertIssuer
		c.ServerCertSubject = options.TLSServerCertSubject
		c.CRLFile = options.TLSCRLFile
	} else {
		if options.TLSCAFile != "" {
			return nil, errTLSCAFileWithoutCert
		}

		if options.TLSCertFile != "" {
			return nil, errTLSCertFileWithoutCert
		}

		if options.TLSKeyFile != "" {
			return nil, errTLSKeyFileWithoutCert
		}

		if options.TLSServerCertIssuer != "" {
			return nil, errTLSServerCertIssuerWithoutCert
		}

		if options.TLSServerCertSubject != "" {
			return nil, errTLSServerCertSubjectWithoutCert
		}

		if options.TLSCRLFile != "" {
			return nil, errTLSCRLFileWithoutCert
		}

		if options.TLSCipherCert != "" {
			return nil, errCipherCertWithoutCert
		}

		if options.TLSCipherCert13 != "" {
			return nil, errCipherCert13WithoutCert
		}
	}

	return c, nil
}

func GlobalOptions(all *AgentOptions) (options *plugin.GlobalOptions) {
	options = &plugin.GlobalOptions{
		Timeout:  Options.Timeout,
		SourceIP: Options.SourceIP,
	}
	return
}

func isValidHostname(hostname string) bool {
	isValid := hostnameRegex.MatchString(hostname)

	return isValid
}

func validateHost(host string) error {
	host = strings.TrimSpace(host)

	if net.ParseIP(host) != nil || isValidHostname(host) {
		return nil
	}

	return errs.Errorf("failed to validate host: %q", host)
}

func elementsUnique(array [][]string) (bool, string) {
	seen := make(map[string]struct{})

	for _, row := range array {
		for _, element := range row {
			_, exists := seen[element]
			if exists {
				return false, element
			}

			seen[element] = struct{}{}
		}
	}

	return true, ""
}

func normalizeAddress(address string) (string, error) {
	address = strings.TrimSpace(address)
	u := url.URL{Host: address}
	ip := net.ParseIP(address)

	if ip == nil && strings.TrimSpace(u.Hostname()) == "" {
		return "", errs.Errorf("failed to parse address %q: empty value", address)
	}

	var checkAddr string

	switch {
	case ip != nil:
		checkAddr = net.JoinHostPort(address, "10051")
	case u.Port() == "":
		checkAddr = net.JoinHostPort(u.Hostname(), "10051")
	default:
		checkAddr = address
	}

	h, p, err := net.SplitHostPort(checkAddr)
	if err != nil {
		return "", errs.Wrapf(err, "failed to parse address %q", address)
	}

	err = validateHost(h)
	if err != nil {
		return "", err
	}

	return net.JoinHostPort(strings.TrimSpace(h), strings.TrimSpace(p)), nil
}

func clusterToAddresses(cluster string) ([]string, error) {
	rawAddresses := strings.Split(cluster, ";")

	parsedAddresses := make([]string, len(rawAddresses)) //nolint:makezero

	for i, rawAddress := range rawAddresses {
		address, err := normalizeAddress(rawAddress)

		if err != nil {
			return nil, err
		}

		parsedAddresses[i] = address
	}

	return parsedAddresses, nil
}

// ParseServerActive validates address list of zabbix Server or Proxy for ActiveCheck.
func ParseServerActive(optionServerActive string) ([][]string, error) {
	if strings.TrimSpace(optionServerActive) == "" {
		return [][]string{}, nil
	}

	clusters := strings.Split(optionServerActive, ",")

	resultAddresses := make([][]string, len(clusters)) //nolint:makezero

	for i, cluster := range clusters {
		clusterAddresses, err := clusterToAddresses(cluster)
		if err != nil {
			return nil, err
		}

		resultAddresses[i] = clusterAddresses
	}

	elementsUniqueRes, duplicateElement := elementsUnique(resultAddresses)

	if !elementsUniqueRes {
		return nil, errs.Errorf("address %q specified more than once", duplicateElement)
	}

	return resultAddresses, nil
}

func ValidateOptions(options *AgentOptions) error {
	var err error
	var maxLen int

	hosts := ExtractHostnames(options.Hostname)
	options.Hostname = strings.Join(hosts, ",")

	if len(hosts) > 1 {
		maxLen = hostNameListLen
	} else {
		maxLen = HostNameLen
	}

	if len(options.Hostname) > maxLen {
		return fmt.Errorf("the value of \"Hostname\" configuration parameter cannot be longer than %d"+
			" characters", maxLen)
	}
	if err = CheckHostnameParameter(options.Hostname); err != nil {
		return fmt.Errorf("invalid \"Hostname\" configuration parameter: %s", err.Error())
	}
	if utf8.RuneCountInString(options.HostInterface) > HostInterfaceLen {
		return fmt.Errorf("the value of \"HostInterface\" configuration parameter cannot be longer than %d"+
			" characters", HostInterfaceLen)
	}

	_, err = zbxnet.GetAllowedPeers(options.Server)
	if err != nil {
		return errs.Wrap(err, `failed to validate "Server" configuration parameter`)
	}

	_, err = ParseServerActive(options.ServerActive)
	if err != nil {
		return errs.Wrap(err, `failed to validate "ServerActive" configuration parameter`)
	}

	return nil
}

func invalidTLSPSKFileError(e error) error {
	return fmt.Errorf("%w: %w", errInvalidTLSPSKFile, e)
}

func requireNoCipherCert(options *AgentOptions) error {
	if options.TLSCipherCert != "" {
		return errCipherCertAndAll
	}

	if options.TLSCipherCert13 != "" {
		return errCipherCert13AndAll
	}

	return nil
}

func requireNoCipherAll(options *AgentOptions) error {
	if options.TLSCipherAll != "" {
		return errCipherAllRedundant
	}

	if options.TLSCipherAll13 != "" {
		return errCipherAll13Redundant
	}

	return nil
}

func removeSystem(privateOptions any) any {
	root, ok := privateOptions.(*conf.Node)
	if !ok {
		return privateOptions
	}

	for i, v := range root.Nodes {
		node, ok := v.(*conf.Node)
		if !ok {
			continue
		}

		if node.Name == "System" {
			root.Nodes = slices.Delete(root.Nodes, i, i+1)

			return root
		}
	}

	return privateOptions
}
