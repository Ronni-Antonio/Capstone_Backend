<?php

namespace App\Http\Controllers;

use App\Models\IotDeviceConfig;
use Illuminate\Http\Request;

class IotDeviceConfigController extends Controller
{
    /** Admin UI: never return the stored Wi-Fi password. */
    public function index()
    {
        return response()->json(
            IotDeviceConfig::orderBy('device_config_id')->get()->map(fn ($device) => $this->adminPayload($device))
        );
    }

    /** Admin UI: update desired Wi-Fi settings for one controller. */
    public function update(Request $request, string $controllerCode)
    {
        $device = IotDeviceConfig::where('controller_code', $controllerCode)->firstOrFail();

        $validated = $request->validate([
            'device_name' => 'sometimes|string|max:255',
            'wifi_ssid' => 'required|string|max:32',
            'wifi_password' => 'nullable|string|min:8|max:63',
        ]);

        $changes = [
            'wifi_ssid' => $validated['wifi_ssid'],
            'config_version' => $device->config_version + 1,
        ];

        if (array_key_exists('device_name', $validated)) {
            $changes['device_name'] = $validated['device_name'];
        }

        // Blank password means "keep the current password" from the admin UI.
        if (!empty($validated['wifi_password'])) {
            $changes['wifi_password'] = $validated['wifi_password'];
        }

        $device->update($changes);

        return response()->json([
            'success' => true,
            'message' => 'Wi-Fi configuration queued. The controller will apply it on its next config poll.',
            'device' => $this->adminPayload($device->fresh()),
        ]);
    }

    /** ESP32: poll desired configuration. Requires X-Device-Key. */
    public function showForDevice(Request $request, string $controllerCode)
    {
        $device = IotDeviceConfig::where('controller_code', $controllerCode)->firstOrFail();
        $this->authorizeDevice($request, $controllerCode);

        $device->update(['last_seen_at' => now()]);

        return response()->json([
            'controller_code' => $device->controller_code,
            'wifi_ssid' => $device->wifi_ssid,
            'wifi_password' => $device->wifi_password,
            'config_version' => (int) $device->config_version,
            'applied_version' => (int) $device->applied_version,
            'restart_required' => $device->config_version > $device->applied_version,
        ]);
    }

    /** ESP32: acknowledge that a config version was stored/applied. */
    public function acknowledge(Request $request, string $controllerCode)
    {
        $device = IotDeviceConfig::where('controller_code', $controllerCode)->firstOrFail();
        $this->authorizeDevice($request, $controllerCode);

        $validated = $request->validate([
            'config_version' => 'required|integer|min:1',
        ]);

        $device->update([
            'applied_version' => min((int) $validated['config_version'], (int) $device->config_version),
            'last_seen_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    private function adminPayload(IotDeviceConfig $device): array
    {
        return [
            'controller_code' => $device->controller_code,
            'device_name' => $device->device_name,
            'wifi_ssid' => $device->wifi_ssid ?? '',
            'has_wifi_password' => !empty($device->wifi_password),
            'config_version' => (int) $device->config_version,
            'applied_version' => (int) $device->applied_version,
            'pending' => $device->config_version > $device->applied_version,
            'last_seen_at' => optional($device->last_seen_at)->toISOString(),
        ];
    }

    private function authorizeDevice(Request $request, string $controllerCode): void
    {
        $expected = match ($controllerCode) {
            'controller-1' => (string) config('services.iot.controller_1_key'),
            'controller-2' => (string) config('services.iot.controller_2_key'),
            default => '',
        };

        $provided = (string) $request->header('X-Device-Key', '');

        abort_if($expected === '' || $provided === '' || !hash_equals($expected, $provided), 401, 'Invalid device key.');
    }
}
