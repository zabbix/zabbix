/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
** documentation files (the "Software"), to deal in the Software without restriction, including without limitation the
** rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
** permit persons to whom the Software is furnished to do so, subject to the following conditions:
**
** The above copyright notice and this permission notice shall be included in all copies or substantial portions
** of the Software.
**
** THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
** WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
** COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
** TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
** SOFTWARE.
**/

package redis

import (
	"golang.zabbix.com/sdk/plugin/comms"
)

const (
	forbidden fieldRequirement = iota
	required
	optional
)

type fieldRequirement int

// gives a map which describes what field combinations are allowed depending on TLSConnect value.
func getValidationRules(tlsConnect comms.TLSConnectionType) map[comms.ConfigSetting]fieldRequirement {
	// common rules.
	rules := map[comms.ConfigSetting]fieldRequirement{
		comms.URI:      optional,
		comms.User:     optional,
		comms.Password: optional,

		comms.TLSCertFile: optional,
		comms.TLSKeyFile:  optional,
	}

	switch tlsConnect {
	case comms.Disabled:
		rules[comms.TLSCAFile] = forbidden

		rules[comms.TLSKeyFile] = forbidden
		rules[comms.TLSCertFile] = forbidden

	case comms.Required:
		rules[comms.TLSCAFile] = forbidden

	case comms.VerifyCA, comms.VerifyFull:
		rules[comms.TLSCAFile] = required

	}

	return rules
}

// Field groups that must come from the same source (session or defaults).
func getSourceConsistencyRules() [][]comms.ConfigSetting {
	return [][]comms.ConfigSetting{
		{comms.TLSCertFile, comms.TLSKeyFile}, // Cert and key must come from the same source
	}
}
