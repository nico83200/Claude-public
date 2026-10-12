{{-- Tutoriel de première connexion et nouveautés : affichés au chargement de la page d'accueil, tant qu'ils n'ont pas été vus ou passés. --}}
@php
    $pendingVideos = request()->routeIs('dashboard')
        ? app(\App\Services\Videos::class)->pendingFor(auth()->user(), \App\Support\Device::isHandheld(request()))
        : collect();
@endphp
@if ($pendingVideos->isNotEmpty())
    <script type="application/json" id="pending-videos">@json($pendingVideos->map(fn ($v) => \App\Http\Controllers\VideoController::payload($v))->values())</script>
    <div x-data="videoPlayer({ videos: JSON.parse(document.getElementById('pending-videos').textContent), auto: true })"
         x-effect="if ($store.pushGate.show || $store.install.gate) { stop(); }">
        <div x-show="! $store.pushGate.show && ! $store.install.gate">
            @include('videos._player', ['auto' => true])
        </div>
    </div>
@endif
