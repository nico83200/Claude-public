{{-- Tutoriel de première connexion et nouveautés : affichés une fois par session tant qu'ils n'ont pas été vus. --}}
@php
    $pendingVideos = collect();
    if (! session('videos_offered') && ! request()->routeIs('videos.*', 'settings.privacy', 'admin.*')) {
        $pendingVideos = app(\App\Services\Videos::class)->pendingFor(auth()->user(), \App\Support\Device::isHandheld(request()));
        if ($pendingVideos->isNotEmpty()) {
            session(['videos_offered' => true]);
        }
    }
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
