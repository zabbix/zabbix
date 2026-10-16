/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package smart

import (
	"embed"
	"encoding/json"
	"slices"
	"sync"
	"testing"
)

//go:embed testdata/controller
var controllerFixtures embed.FS

type controllerResponse struct {
	args   []string
	output []byte
	err    error
}

type fixtureController struct {
	t         *testing.T
	responses []controllerResponse
	call      int
	mu        sync.Mutex
}

type controllerSmartInfoScans map[string]json.RawMessage

type controllerEnvironment struct {
	Version           json.RawMessage          `json:"version"`
	AllDevicesScan    json.RawMessage          `json:"all_devices_scan"`
	RaidDevicesScan   json.RawMessage          `json:"raid_devices_scan"`
	AllSmartInfoScans controllerSmartInfoScans `json:"all_smart_info_scans"`
}

func readControllerFixture(t *testing.T, fixture string) []byte {
	t.Helper()

	data, err := controllerFixtures.ReadFile("testdata/controller/" + fixture)
	if err != nil {
		t.Fatalf("failed to read fixture %q: %v", fixture, err)
	}

	return data
}

func readControllerEnvironment(t *testing.T, name string) controllerEnvironment {
	t.Helper()

	data := readControllerFixture(t, "discovery/environments.json")
	environments := make(map[string]controllerEnvironment)

	err := json.Unmarshal(data, &environments)
	if err != nil {
		t.Fatalf("failed to parse controller environments: %v", err)
	}

	environment, ok := environments[name]
	if !ok {
		t.Fatalf("controller environment %q not found", name)
	}

	return environment
}

func (scans controllerSmartInfoScans) get(t *testing.T, args string) json.RawMessage {
	t.Helper()

	scan, ok := scans[args]
	if !ok {
		t.Fatalf("SMART info scan %q not found", args)
	}

	return scan
}

func newFixtureController(t *testing.T, responses ...controllerResponse) *fixtureController {
	t.Helper()

	controller := &fixtureController{
		t:         t,
		responses: responses,
	}

	t.Cleanup(func() {
		controller.mu.Lock()
		defer controller.mu.Unlock()

		if controller.call != len(controller.responses) {
			t.Fatalf(
				"SmartController.Execute() calls = %d, want %d",
				controller.call,
				len(controller.responses),
			)
		}
	})

	return controller
}

func (c *fixtureController) Execute(args ...string) ([]byte, error) {
	c.mu.Lock()
	defer c.mu.Unlock()

	if c.call >= len(c.responses) {
		c.t.Fatalf("unexpected SmartController.Execute() call with args %v", args)

		return nil, nil
	}

	response := c.responses[c.call]
	c.call++

	if response.args != nil && !slices.Equal(response.args, args) {
		c.t.Fatalf(
			"SmartController.Execute() args = %v, want %v",
			args,
			response.args,
		)

		return nil, nil
	}

	return response.output, response.err
}
