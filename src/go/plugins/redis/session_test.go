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

package redis

import (
	"strings"
	"testing"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/tlsconfig"
)

func TestSession_getFieldValues(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name    string
		session session
		want    map[comms.ConfigSetting]string
	}{
		{
			name: "allFields",
			session: session{
				URI:         "redis://localhost:6379",
				Password:    "secret",
				User:        "testuser",
				TLSConnect:  string(tlsconfig.Disabled),
				TLSCAFile:   "/path/to/ca.pem",
				TLSCertFile: "/path/to/cert.pem",
				TLSKeyFile:  "/path/to/key.pem",
			},
			want: map[comms.ConfigSetting]string{
				comms.URI:         "redis://localhost:6379",
				comms.Password:    "secret",
				comms.User:        "testuser",
				comms.TLSConnect:  string(tlsconfig.Disabled),
				comms.TLSCAFile:   "/path/to/ca.pem",
				comms.TLSCertFile: "/path/to/cert.pem",
				comms.TLSKeyFile:  "/path/to/key.pem",
			},
		},
		{
			name:    "emptySession",
			session: session{},
			want: map[comms.ConfigSetting]string{
				comms.URI:         "",
				comms.Password:    "",
				comms.User:        "",
				comms.TLSConnect:  "",
				comms.TLSCAFile:   "",
				comms.TLSCertFile: "",
				comms.TLSKeyFile:  "",
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := tt.session.getFieldValues()
			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Errorf("getFieldValues() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func TestSession_resolveTLSConnect(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name        string
		session     session
		defaults    session
		want        tlsconfig.TLSConnectionType
		wantErr     bool
		errContains string
	}{
		{
			name: "+sessionTLSDisabled",
			session: session{
				TLSConnect: string(tlsconfig.Disabled),
			},
			defaults: session{},
			want:     tlsconfig.Disabled,
			wantErr:  false,
		},
		{
			name: "+sessionTLSRequired",
			session: session{
				TLSConnect: string(tlsconfig.Required),
			},
			defaults: session{},
			want:     tlsconfig.Required,
			wantErr:  false,
		},
		{
			name:    "+defaultsTLSFallback",
			session: session{},
			defaults: session{
				TLSConnect: string(tlsconfig.Required),
			},
			want:    tlsconfig.Required,
			wantErr: false,
		},
		{
			name:     "+defaultDisabled",
			session:  session{},
			defaults: session{},
			want:     tlsconfig.Disabled,
			wantErr:  false,
		},
		{
			name: "-invalidSessionTLSConnect",
			session: session{
				TLSConnect: "invalid",
			},
			defaults:    session{},
			want:        "",
			wantErr:     true,
			errContains: "Session TLS connection type is invalid",
		},
		{
			name:    "-invalidTLSConnect",
			session: session{},
			defaults: session{
				TLSConnect: "invalid",
			},
			want:        "",
			wantErr:     true,
			errContains: "Default TLS connection type is invalid",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := tt.session.resolveTLSConnect(&tt.defaults)

			if tt.wantErr {
				if err == nil {
					t.Errorf("resolveTLSConnect() error = nil, wantErr %v", tt.wantErr)

					return
				}

				if tt.errContains != "" && !contains(err.Error(), tt.errContains) {
					t.Errorf("resolveTLSConnect() error = %v, want error containing %v", err, tt.errContains)
				}

				return
			}

			if err != nil {
				t.Errorf("resolveTLSConnect() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			if got != tt.want {
				t.Errorf("resolveTLSConnect() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_validateRequiredField(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name         string
		fieldName    comms.ConfigSetting
		value        string
		defaultValue string
		wantErr      bool
		errContains  string
	}{
		{
			name:         "+valueProvided",
			fieldName:    comms.URI,
			value:        "redis://localhost:6379",
			defaultValue: "",
			wantErr:      false,
		},
		{
			name:         "+defaultValueProvided",
			fieldName:    comms.URI,
			value:        "",
			defaultValue: "redis://localhost:6379",
			wantErr:      false,
		},
		{
			name:         "+bothValuesProvided",
			fieldName:    comms.URI,
			value:        "redis://localhost:6379",
			defaultValue: "redis://default:6379",
			wantErr:      false,
		},
		{
			name:         "-bothValuesEmpty",
			fieldName:    comms.URI,
			value:        "",
			defaultValue: "",
			wantErr:      true,
			errContains:  "is required",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			err := validateRequiredField(tt.fieldName, tt.value, tt.defaultValue)

			if tt.wantErr {
				if err == nil {
					t.Errorf("validateRequiredField() error = nil, wantErr %v", tt.wantErr)

					return
				}

				if tt.errContains != "" && !contains(err.Error(), tt.errContains) {
					t.Errorf("validateRequiredField() error = %v, want error containing %v", err, tt.errContains)
				}

				return
			}

			if err != nil {
				t.Errorf("validateRequiredField() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func Test_validateForbiddenField(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name        string
		fieldName   comms.ConfigSetting
		value       string
		wantErr     bool
		errContains string
	}{
		{
			name:      "+emptyValueAllowed",
			fieldName: comms.TLSCAFile,
			value:     "",
			wantErr:   false,
		},
		{
			name:        "-nonEmptyValueForbidden",
			fieldName:   comms.TLSCAFile,
			value:       "/path/to/ca.pem",
			wantErr:     true,
			errContains: "is forbidden",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			err := validateForbiddenField(tt.fieldName, tt.value)

			if tt.wantErr {
				if err == nil {
					t.Errorf("validateForbiddenField() error = nil, wantErr %v", tt.wantErr)

					return
				}

				if tt.errContains != "" && !contains(err.Error(), tt.errContains) {
					t.Errorf("validateForbiddenField() error = %v, want error containing %v", err, tt.errContains)
				}

				return
			}

			if err != nil {
				t.Errorf("validateForbiddenField() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}
func TestSession_runSourceConsistencyValidation(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name        string
		session     session
		wantErr     bool
		errContains string
	}{
		{
			name: "+allTLSFields",
			session: session{
				TLSCAFile:   "/path/to/ca.pem",
				TLSCertFile: "/path/to/cert.pem",
				TLSKeyFile:  "/path/to/key.pem",
			},
			wantErr: false,
		},
		{
			name:    "+allTLSFieldsEmpty",
			session: session{},
			wantErr: false,
		},
		{
			name: "-mixedTLSFields",
			session: session{
				TLSCAFile:   "/path/to/ca.pem",
				TLSCertFile: "",
				TLSKeyFile:  "/path/to/key.pem",
			},
			wantErr:     true,
			errContains: "required to be filled in the same source",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			err := tt.session.runSourceConsistencyValidation()

			if tt.wantErr {
				if err == nil {
					t.Errorf("runSourceConsistencyValidation() error = nil, wantErr %v", tt.wantErr)

					return
				}

				if tt.errContains != "" && !contains(err.Error(), tt.errContains) {
					t.Errorf(
						"runSourceConsistencyValidation() error = %v, want error containing %v", err, tt.errContains)
				}

				return
			}

			if err != nil {
				t.Errorf("runSourceConsistencyValidation() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func TestSession_validateSession(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name        string
		session     session
		defaults    session
		wantErr     bool
		errContains string
	}{
		{
			name: "+validSessionWithTLSDisabled",
			session: session{
				URI:        "redis://localhost:6379",
				TLSConnect: string(tlsconfig.Disabled),
				Password:   "abc",
			},
			defaults: session{},
			wantErr:  false,
		},
		{
			name: "+validSessionWithTLSFull",
			session: session{
				URI:         "redis://localhost:6379",
				Password:    "abc",
				TLSConnect:  string(tlsconfig.VerifyFull),
				TLSCAFile:   "/path/to/ca.pem",
				TLSCertFile: "/path/to/cert.pem",
				TLSKeyFile:  "/path/to/key.pem",
			},
			defaults: session{},
			wantErr:  false,
		},
		{
			name: "-invalidConnectType",
			session: session{
				URI:        "redis://localhost:6379",
				Password:   "abc",
				TLSConnect: "invalid",
			},
			defaults:    session{},
			wantErr:     true,
			errContains: "connection type is invalid",
		},
		{
			name: "-inconsistentTLSFields",
			session: session{
				URI:        "redis://localhost:6379",
				Password:   "abc",
				TLSConnect: string(tlsconfig.VerifyFull),
				TLSCAFile:  "/path/to/ca.pem",
				TLSKeyFile: "/path/to/key.pem",
			},
			defaults: session{
				TLSCertFile: "/path/to/cert.pem",
			},
			wantErr:     true,
			errContains: "Source-consistency validation failed",
		},
		{
			name: "-missingCACertificate",
			session: session{
				URI:        "redis://localhost:6379",
				Password:   "abc",
				TLSConnect: string(tlsconfig.VerifyCA),
			},
			defaults:    session{},
			wantErr:     true,
			errContains: "TLSCAFile is required",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			err := tt.session.validateSession(&tt.defaults)

			if tt.wantErr {
				if err == nil {
					t.Errorf("validateSession() error = nil, wantErr %v", tt.wantErr)

					return
				}

				if tt.errContains != "" && !contains(err.Error(), tt.errContains) {
					t.Errorf("validateSession() error = %v, want error containing %v", err, tt.errContains)
				}

				return
			}

			if err != nil {
				t.Errorf("validateSession() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}

func contains(s, substr string) bool {
	return len(s) >= len(substr) && s[:len(substr)] == substr ||
		len(s) > len(substr) && strings.Contains(s, substr)
}
