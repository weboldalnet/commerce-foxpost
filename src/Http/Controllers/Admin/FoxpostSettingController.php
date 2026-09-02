<?php

namespace Weboldalnet\CommerceFoxpost\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Weboldalnet\CommerceFoxpost\Services\FoxpostApmService;
use Weboldalnet\CommerceFoxpost\Services\FoxpostService;
use Weboldalnet\CommerceFoxpost\Services\FoxpostSettingsService;

class FoxpostSettingController extends Controller
{
    public function index()
    {
        // FIGYELEM: a változó neve nem lehet $settings – a platform admin layoutja
        // egy globálisan megosztott $settings modellt használ, azt felülírnánk.
        $fpSettings = FoxpostSettingsService::all();

        foreach (FoxpostSettingsService::viewKeys() as $key) {
            if (!array_key_exists($key, $fpSettings)) {
                $fpSettings[$key] = FoxpostSettingsService::get($key);
            }
        }

        foreach (FoxpostSettingsService::encryptedKeys() as $key) {
            if (!empty($fpSettings[$key])) {
                $fpSettings[$key] = '********';
            }
        }

        return view('commerce-foxpost::admin.settings', [
            'fpSettings' => $fpSettings,
            'fpApmCount' => count(FoxpostApmService::all()),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->all();
        $booleanKeys = FoxpostSettingsService::booleanKeys();
        $encryptedKeys = FoxpostSettingsService::encryptedKeys();

        foreach ($data as $key => $value) {
            if ($key === '_token') {
                continue;
            }

            $type = 'string';

            if (in_array($key, $booleanKeys, true)) {
                $type = 'boolean';
                $value = ($value === 'on' || $value === '1' || $value === true);
            } elseif (in_array($key, $encryptedKeys, true)) {
                $type = 'encrypted';
                // A maszkolt értéket nem mentjük vissza
                if ($value === '********') {
                    continue;
                }
            }

            FoxpostSettingsService::save($key, $value, $type);
        }

        foreach ($booleanKeys as $key) {
            if (!isset($data[$key])) {
                FoxpostSettingsService::save($key, false, 'boolean');
            }
        }

        // A környezetváltás más automata-listát jelent, ezért ürítjük a gyorsítótárat.
        FoxpostApmService::clearCache();

        return redirect()->back()->with('success', 'FoxPost beállítások sikeresen mentve.');
    }

    /**
     * Kapcsolat tesztelése a FoxPost API felé.
     */
    public function testConnection(FoxpostService $service)
    {
        try {
            return response()->json($service->testConnection());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Hiba a kapcsolódáskor: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Az automata-lista gyorsítótárának frissítése.
     */
    public function refreshApms()
    {
        FoxpostApmService::clearCache();
        $count = count(FoxpostApmService::all());

        return redirect()->back()->with('success', 'Automata-lista frissítve: ' . $count . ' automata.');
    }
}
