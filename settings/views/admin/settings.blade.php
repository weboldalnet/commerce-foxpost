@extends('admin.layouts.layout')
@section('title', 'FoxPost beállítások')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="header-box my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-0">FoxPost beállítások</h1>
                        <p class="text-muted small mb-0">FoxWeb API integráció konfigurálása</p>
                    </div>
                    <div>
                        <button type="button" id="foxpost-test-connection-btn" class="btn btn-warning font-weight-bold">
                            <i class="fa fa-plug mr-1"></i> Kapcsolat tesztelése
                        </button>
                        <div id="foxpost-test-connection-result" class="mt-2 mb-0 d-none"></div>
                    </div>
                </div>
            </div>

            @include('admin.webshop.partials.alerts')

            @php
                $fpEnabled = filter_var($fpSettings['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $fpIsProd = ($fpSettings['environment'] ?? 'test') === 'prod';
                $fpHasKeys = !empty($fpSettings['username']) && !empty($fpSettings['password']) && !empty($fpSettings['api_key']);
                $fpHasRate = isset($fpSettings['rate']) && $fpSettings['rate'] !== '' && is_numeric($fpSettings['rate']);
            @endphp

            <form action="{{ route('admin.webshop.foxpost.settings.update') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Modul állapota</div>
                        <div class="content-box bordered mb-3">
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" id="enabled" name="enabled"
                                       @if($fpEnabled) checked @endif>
                                <label class="custom-control-label fw-600" for="enabled">FoxPost modul engedélyezve</label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="home_delivery_enabled" name="home_delivery_enabled"
                                       @if(filter_var($fpSettings['home_delivery_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) checked @endif>
                                <label class="custom-control-label" for="home_delivery_enabled">Házhoz szállítás is</label>
                            </div>

                            @if($fpEnabled && !$fpHasKeys)
                                <div class="alert alert-warning mb-0 py-2 px-3 small">
                                    <i class="fa fa-exclamation-triangle mr-1"></i>
                                    A modul be van kapcsolva, de hiányoznak a hozzáférési adatok –
                                    a csomagfeladás hibára fut.
                                </div>
                            @else
                                <div class="alert alert-info mb-0 py-2 px-3 small">
                                    <i class="fa fa-info-circle mr-1"></i>
                                    A bekapcsoláshoz mindhárom hozzáférési adat kell: felhasználónév, jelszó és api-key.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="header-box product-info mb-1">Környezet</div>
                        <div class="content-box bordered mb-3">
                            <div class="form-group mb-3">
                                <label class="fw-600">API környezet</label>
                                <select name="environment" id="environment" class="form-control">
                                    <option value="test" @if(!$fpIsProd) selected @endif>Teszt (sandbox)</option>
                                    <option value="prod" @if($fpIsProd) selected @endif>Éles</option>
                                </select>
                            </div>

                            @if($fpIsProd)
                                <div class="alert alert-danger mb-2 py-2 px-3 small">
                                    <i class="fa fa-exclamation-triangle mr-1"></i>
                                    <strong>Éles környezet.</strong> A feladott csomagok valódiak.
                                </div>
                            @else
                                <div class="alert alert-secondary mb-2 py-2 px-3 small">
                                    <i class="fa fa-flask mr-1"></i>
                                    Teszt módban csak a hu1000 alatti azonosítójú automaták használhatók –
                                    a választó is csak ezeket kínálja fel.
                                </div>
                            @endif

                            <div class="d-flex align-items-center justify-content-between">
                                <span class="small text-muted">
                                    Betöltött automaták: <strong>{{ $fpApmCount }}</strong>
                                </span>
                                <button type="submit" class="btn btn-sm btn-secondary"
                                        formaction="{{ route('admin.webshop.foxpost.refresh-apms') }}">
                                    <i class="fa fa-sync-alt mr-1"></i> Lista frissítése
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="header-box product-info mb-1">Hitelesítés (FoxWeb API)</div>
                <div class="content-box bordered mb-3">
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="form-group mb-0">
                                <label class="fw-600">Felhasználónév</label>
                                <input type="text" name="username" class="form-control"
                                       value="{{ $fpSettings['username'] ?? '' }}" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group mb-0">
                                <label class="fw-600">Jelszó</label>
                                <input type="password" name="password" class="form-control"
                                       value="{{ $fpSettings['password'] ?? '' }}" autocomplete="new-password">
                                <span class="text-muted fs-14">Titkosítva tárolódik. Üresen hagyva a korábbi marad.</span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group mb-0">
                                <label class="fw-600">API kulcs</label>
                                <input type="password" name="api_key" class="form-control"
                                       value="{{ $fpSettings['api_key'] ?? '' }}" autocomplete="new-password">
                                <span class="text-muted fs-14">A Basic hitelesítés mellett ez is kötelező.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="header-box product-info mb-1">Szállítási díjak</div>
                <div class="content-box bordered mb-3">
                    @if($fpEnabled && !$fpHasRate)
                        <div class="alert alert-warning py-2 px-3 small">
                            <i class="fa fa-exclamation-triangle mr-1"></i>
                            A FoxPost modul be van kapcsolva, de nincs megadva szállítási díj –
                            a pénztár jelenleg <strong>0 Ft</strong> díjjal számol.
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="fw-600">Csomagautomata díja</label>
                                <input type="text" name="rate" class="form-control"
                                       value="{{ $fpSettings['rate'] ?? '' }}" placeholder="pl. 990">
                                <span class="text-muted fs-14">Üresen hagyva 0 díjjal számol.</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="fw-600">Házhoz szállítás díja</label>
                                <input type="text" name="home_delivery_rate" class="form-control"
                                       value="{{ $fpSettings['home_delivery_rate'] ?? '' }}" placeholder="pl. 1490">
                                <span class="text-muted fs-14">Üresen az automatás díj érvényes.</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="fw-600">Ingyenes szállítás felett</label>
                                <input type="text" name="free_above" class="form-control"
                                       value="{{ $fpSettings['free_above'] ?? '' }}" placeholder="pl. 25000">
                                <span class="text-muted fs-14">Üresen nincs ilyen határ.</span>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="fw-600">Pénznem</label>
                                <select name="currency" class="form-control">
                                    @foreach(['HUF' => 'HUF – forint', 'EUR' => 'EUR – euró'] as $v => $l)
                                        <option value="{{ $v }}" @if(($fpSettings['currency'] ?? 'HUF') === $v) selected @endif>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mb-0 py-2 px-3 small">
                        <i class="fa fa-info-circle mr-1"></i>
                        A FoxPost API nem ad díjkalkulációt – a díjszabás szerződésfüggő –, ezért
                        a webshop az itt megadott fix díjakkal számol.
                    </div>
                </div>

                <div class="header-box product-info mb-1">Csomag beállítások</div>
                <div class="content-box bordered mb-3">
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="form-group mb-0">
                                <label class="fw-600">Csomagméret</label>
                                <select name="size" class="form-control">
                                    @foreach(['xs' => 'XS', 's' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL'] as $v => $l)
                                        <option value="{{ $v }}" @if(strtolower($fpSettings['size'] ?? 'm') === $v) selected @endif>{{ $l }}</option>
                                    @endforeach
                                </select>
                                <span class="text-muted fs-14">
                                    A FoxPost méretkategóriát kér, nem súlyt. Minden csomag ezt kapja.
                                </span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="form-group mb-0">
                                <label class="fw-600">Címkeméret</label>
                                <select name="label_size" class="form-control">
                                    @foreach(['A6' => 'A6', 'A7' => 'A7', '_85X85' => '85×85 mm'] as $v => $l)
                                        <option value="{{ $v }}" @if(($fpSettings['label_size'] ?? 'A7') === $v) selected @endif>{{ $l }}</option>
                                    @endforeach
                                </select>
                                <span class="text-muted fs-14">Az A5 méretet a FoxPost már nem fogadja el.</span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="custom-control custom-checkbox mt-4">
                                <input type="checkbox" class="custom-control-input" id="cod_enabled" name="cod_enabled"
                                       @if(filter_var($fpSettings['cod_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) checked @endif>
                                <label class="custom-control-label" for="cod_enabled">Utánvét FoxPoston keresztül</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3 mb-5">
                    <button type="submit" class="btn btn-primary fs-18 font-weight-bold px-5">
                        <i class="fa fa-save mr-1"></i> Beállítások mentése
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('foxpost-test-connection-btn');
        var out = document.getElementById('foxpost-test-connection-result');

        // Beágyazott visszajelzés natív alert() helyett: az blokkolja a lapot.
        function show(isSuccess, message) {
            out.className = 'mt-2 mb-0 alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
            out.textContent = message;
        }

        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Tesztelés...';
            out.className = 'mt-2 mb-0 d-none';
            out.textContent = '';

            fetch('{{ route("admin.webshop.foxpost.test-connection") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                show(!!data.success, data.message || '');
            })
            .catch(function () {
                show(false, 'Váratlan hiba történt a tesztelés során.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-plug mr-1"></i> Kapcsolat tesztelése';
            });
        });

        var envSelect = document.getElementById('environment');
        if (envSelect) {
            var savedEnv = envSelect.value;
            envSelect.addEventListener('change', function () {
                if (envSelect.value !== savedEnv) {
                    show(false, 'A környezetváltás csak mentés után lép érvénybe – a kapcsolat tesztelése addig a korábbi beállítással fut.');
                }
            });
        }
    });
</script>
@endsection
