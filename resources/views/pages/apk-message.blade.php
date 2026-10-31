<x-layouts.app :title="$title.' | '.config('site.name')" :description="'Je APK-herinnering bij '.config('site.name').'.'">
    <div class="max-w-xl">
        <h1 class="text-3xl sm:text-4xl">{{ $title }}</h1>
        <p class="mt-4 text-lg text-ink-soft">{{ $text }}</p>
        <p class="mt-6"><a href="{{ route('home') }}" class="btn">Naar de homepage</a></p>
    </div>
</x-layouts.app>
