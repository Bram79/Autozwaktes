<x-layouts.app :title="$title.' | Auto Zwaktes'" description="Je APK-herinnering bij Auto Zwaktes.">
    <div class="max-w-xl">
        <h1 class="text-3xl sm:text-4xl">{{ $title }}</h1>
        <p class="mt-4 text-lg text-slate-700">{{ $text }}</p>
        <form method="post" action="{{ $action }}" class="mt-6">
            @csrf
            <button type="submit" class="btn">{{ $button }}</button>
        </form>
    </div>
</x-layouts.app>
