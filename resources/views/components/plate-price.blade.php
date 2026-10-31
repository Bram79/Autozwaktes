@props(['price' => null])

{{-- The new price (catalogue price) of the car that was just looked up by plate. Without a price from the RDW nothing is shown. --}}
@php $price ??= \App\Site\PlateVisit::price(); @endphp
@if ($price)
    <aside class="mt-6 rounded-2xl border border-brand-100 bg-brand-50 p-5" aria-label="Nieuwprijs van jouw auto">
        <p class="text-sm font-semibold text-slate-700">Nieuwprijs van jouw auto</p>
        <p class="mt-1 text-3xl font-extrabold text-brand-900">€ {{ number_format($price, 0, ',', '.') }}</p>
        <p class="mt-1 text-sm text-slate-700">De catalogusprijs van dit kenteken volgens de RDW. Dat is de prijs van de auto toen hij nieuw was, niet wat hij nu waard is.</p>
    </aside>
@endif
