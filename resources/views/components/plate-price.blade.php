@props(['price' => null])

{{-- The new price (catalogue price) of the car that was just looked up by plate. Without a price from the RDW nothing is shown. --}}
@php $price ??= \App\Site\PlateVisit::price(); @endphp
@if ($price)
    <aside class="mt-6 panel" aria-label="Nieuwprijs van jouw auto">
        <p class="text-sm font-semibold text-ink-soft">Nieuwprijs van jouw auto</p>
        <p class="mt-1 text-3xl font-semibold tabular-nums text-brand-900"><span data-count="{{ $price }}">€ {{ number_format($price, 0, ',', '.') }}</span></p>
    </aside>
@endif
