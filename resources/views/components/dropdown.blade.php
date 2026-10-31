@props(['title', 'id'])

{{-- A dropdown that works without JavaScript (<details>), closed until the visitor opens it. --}}
<details class="group mt-8 rounded-lg border border-brand-100 bg-brand-50">
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-lg p-5 [&::-webkit-details-marker]:hidden">
        <h2 id="{{ $id }}" class="text-xl font-semibold">{{ $title }}</h2>
        <svg class="size-5 shrink-0 text-brand-700 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
        </svg>
    </summary>

    <div class="border-t border-brand-100 px-5 pb-5">
        {{ $slot }}
    </div>
</details>
