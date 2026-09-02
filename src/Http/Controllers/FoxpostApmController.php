<?php

namespace Weboldalnet\CommerceFoxpost\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Weboldalnet\CommerceFoxpost\Services\FoxpostApmService;

/**
 * Csomagautomaták a pénztár választójához.
 *
 * A FoxPost nem ad beágyazható választó-komponenst, ezért a saját felületünk
 * innen kéri le az automatákat – keresésre szűrve, hogy ne kelljen a teljes
 * (több ezer elemű) listát a böngészőbe tölteni.
 */
class FoxpostApmController extends Controller
{
    public function index(Request $request)
    {
        $term = (string) $request->input('q', '');
        $apms = FoxpostApmService::search($term, 60);

        return response()->json([
            'success' => true,
            'count' => count($apms),
            'items' => $apms,
        ]);
    }
}
