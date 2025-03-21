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

package serverlistener

import (
	"fmt"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/pkg/version"
)

func TestFormatError(t *testing.T) {
	t.Parallel()

	const (
		notsupported = "ZBX_NOTSUPPORTED"
		message      = "error message"
	)

	result := formatError(message)

	if string(result[:len(notsupported)]) != notsupported {
		t.Errorf("Expected error message to start with '%s' while got '%s'", notsupported,
			string(result[:len(notsupported)]))

		return
	}

	if result[len(notsupported)] != 0 {
		t.Errorf("Expected terminating zero after ZBX_NOTSUPPORTED error prefix")

		return
	}

	if string(result[len(notsupported)+1:]) != message {
		t.Errorf("Expected error description '%s' while got '%s'", message, string(result[len(notsupported)+1:]))

		return
	}
}

func Test_parsePassiveCheckJSONRequest(t *testing.T) {
	t.Parallel()

	type expectations struct {
		key      string
		timeout  time.Duration
		hasError bool
	}

	tests := []struct {
		name       string
		givenInput string
		expect     expectations
	}{
		{
			name:       "+validTimeout",
			givenInput: `{"request":"passive checks","data":[{"key":"system.cpu.load","timeout":"3"}]}`,
			expect: expectations{
				key:     "system.cpu.load",
				timeout: 3 * time.Second,
			},
		},
		{
			name:       "-invalidTimeout",
			givenInput: `{"request":"passive checks","data":[{"key":"system.cpu.load","timeout":"0"}]}`,
			expect: expectations{
				hasError: true,
			},
		},
		{
			name:       "-invalidJSONFormat",
			givenInput: `{"request": invalid json}`,
			expect: expectations{
				hasError: true,
			},
		},
		{
			name:       "-missingDataArray",
			givenInput: `{"request":"passive checks"}`,
			expect: expectations{
				hasError: true,
			},
		},
		{
			name:       "-emptyDataArray",
			givenInput: `{"request":"passive checks","data":[]}`,
			expect: expectations{
				hasError: true,
			},
		},
		{
			name:       "-unknownRequestType",
			givenInput: `{"request":"unknown","data":[{"key":"system.cpu.load","timeout":"3"}]}`,
			expect: expectations{
				hasError: true,
			},
		},
		{
			name:       "-invalidTimeoutValue",
			givenInput: `{"request":"passive checks","data":[{"key":"system.cpu.load","timeout":"abc"}]}`,
			expect: expectations{
				hasError: true,
			},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			rawInput := []byte(tt.givenInput)

			gotKey, gotTimeout, err := parsePassiveCheckJSONRequest(rawInput)

			if (err != nil) != tt.expect.hasError {
				t.Fatalf("parsePassiveCheckJSONRequest() unexpected error: got %v, want=%v", err, tt.expect.hasError)
			}

			if tt.expect.key != gotKey {
				t.Errorf("parsePassiveCheckJSONRequest() key mismatch: want %q, got %q", tt.expect.key, gotKey)
			}

			if tt.expect.timeout != gotTimeout {
				t.Errorf(
					"parsePassiveCheckJSONRequest() timeout mismatch: want %v, got %v",
					tt.expect.timeout,
					gotTimeout,
				)
			}
		})
	}
}

func Test_formatCheckDataPayload(t *testing.T) {
	t.Parallel()

	type args struct {
		checkInput string
		isJSON     bool
	}

	type expectations struct {
		expectedOutput []byte
		expectError    bool
	}

	expectedJSON := func(value string) []byte {
		return []byte(fmt.Sprintf(
			`{"version":"%s","variant":%d,"data":[{"value":"%s"}]}`,
			version.Long(),
			agent.Variant,
			value,
		))
	}

	tests := []struct {
		name   string
		args   args
		expect expectations
	}{
		{
			name: "+validPlainText",
			args: args{
				checkInput: "cpu_load: 1.5",
				isJSON:     false,
			},
			expect: expectations{
				expectedOutput: []byte("cpu_load: 1.5"),
			},
		},
		{
			name: "+validJSONResponse",
			args: args{
				checkInput: "cpu_load: 1.5",
				isJSON:     true,
			},
			expect: expectations{
				expectedOutput: expectedJSON("cpu_load: 1.5"),
			},
		},
		{
			name: "+emptyPlainText",
			args: args{
				checkInput: "",
				isJSON:     false,
			},
			expect: expectations{
				expectedOutput: []byte(""),
			},
		},
		{
			name: "+emptyJSONResponse",
			args: args{
				checkInput: "",
				isJSON:     true,
			},
			expect: expectations{
				expectedOutput: expectedJSON(""),
			},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			output, err := formatCheckDataPayload(tt.args.checkInput, tt.args.isJSON)

			if (err != nil) != tt.expect.expectError {
				t.Fatalf(
					"formatCheckDataPayload(): unexpected error state: got err=%v, want error=%v",
					err,
					tt.expect.expectError,
				)
			}

			if diff := cmp.Diff(tt.expect.expectedOutput, output); diff != "" {
				t.Errorf("formatCheckDataPayload(): output mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Test_formatCheckErrorPayload(t *testing.T) {
	t.Parallel()

	type args struct {
		errText string
		isJSON  bool
	}

	type expectations struct {
		expectedOutput []byte
		expectError    bool
	}

	expectedJSON := func(errorMsg string) []byte {
		return []byte(fmt.Sprintf(
			`{"version":"%s","variant":%d,"data":[{"error":"%s"}]}`,
			version.Long(),
			agent.Variant,
			errorMsg,
		))
	}

	tests := []struct {
		name   string
		args   args
		expect expectations
	}{
		{
			name: "+validPlainTextError",
			args: args{
				errText: "Timeout occurred",
				isJSON:  false,
			},
			expect: expectations{
				expectedOutput: formatError("Timeout occurred"),
			},
		},
		{
			name: "+validJSONError",
			args: args{
				errText: "Timeout occurred",
				isJSON:  true,
			},
			expect: expectations{
				expectedOutput: expectedJSON("Timeout occurred"),
			},
		},
		{
			name: "+emptyPlainTextError",
			args: args{
				errText: "",
				isJSON:  false,
			},
			expect: expectations{
				expectedOutput: formatError(""),
			},
		},
		{
			name: "+emptyJSONError",
			args: args{
				errText: "",
				isJSON:  true,
			},
			expect: expectations{
				expectedOutput: expectedJSON(""),
			},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			output, err := formatCheckErrorPayload(tt.args.errText, tt.args.isJSON)

			if (err != nil) != tt.expect.expectError {
				t.Fatalf("formatCheckErrorPayload(): unexpected error state: got err=%v, want error=%v",
					err,
					tt.expect.expectError,
				)
			}

			if diff := cmp.Diff(tt.expect.expectedOutput, output); diff != "" {
				t.Errorf("formatCheckErrorPayload(): output mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

func Test_formatJSONParsingError(t *testing.T) {
	t.Parallel()

	type args struct {
		errText string
	}

	type expectations struct {
		expectedOutput []byte
		expectError    bool
	}

	expectedJSON := func(errorMsg string) []byte {
		return []byte(fmt.Sprintf(
			`{"version":"%s","variant":%d,"error":"%s"}`,
			version.Long(),
			agent.Variant,
			errorMsg,
		))
	}

	tests := []struct {
		name   string
		args   args
		expect expectations
	}{
		{
			name: "+validJSONParsingError",
			args: args{
				errText: "Invalid JSON format",
			},
			expect: expectations{
				expectedOutput: expectedJSON("Invalid JSON format"),
			},
		},
		{
			name: "+emptyJSONParsingError",
			args: args{
				errText: "",
			},
			expect: expectations{
				expectedOutput: expectedJSON(""),
			},
		},
	}

	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			output, err := formatJSONParsingError(tt.args.errText)

			if (err != nil) != tt.expect.expectError {
				t.Fatalf(
					"formatJSONParsingError(): unexpected error state: got err=%v, want error=%v",
					err,
					tt.expect.expectError,
				)
			}

			if diff := cmp.Diff(tt.expect.expectedOutput, output); diff != "" {
				t.Errorf("formatJSONParsingError(): output mismatch (-want +got):\n%s", diff)
			}
		})
	}
}
