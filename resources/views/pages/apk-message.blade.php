<x-layouts.app :title="$title.' | Auto Zwaktes'" description="Je APK-herinnering bij Auto Zwaktes.">
    <div class="max-w-xl">
        <h1 class="text-3xl sm:text-4xl">{{ $title }}</h1>
        <p class="mt-4 text-lg text-slate-700">{{ $text }}</p>
        <p class="mt-6"><a href="{{ route('home') }}" class="btn">Naar de homepage</a></p>
    </div>
</x-layouts.app>
