@props(['name'])

{{-- An ad spot: stays hidden, and empty, until the visitor has accepted advertisements. --}}
@if (config('site.ads.enabled'))
    <div data-ad-slot="{{ $name }}" hidden class="min-h-[250px] rounded-2xl border border-dashed border-brand-100 p-3 text-center">
        <p class="text-xs text-slate-500">Advertentie</p>
    </div>
@endif
