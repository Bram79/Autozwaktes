<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Welkom bij het beheer van Auto Zwaktes</x-slot>
        <x-slot name="description">De cijfers komen automatisch van de RDW. Jij beheert wat erbij hoort: teksten, namen en affiliate-links.</x-slot>

        <ol class="grid gap-4 md:grid-cols-3">
            <li class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                <p class="font-semibold">1. Schrijf je koopadvies</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Open een generatie en vul het koopadvies in. Dit zie je terug op de site onder de gebreken.</p>
                <a href="{{ \App\Filament\Resources\Generations\GenerationResource::getUrl() }}" class="mt-2 inline-block text-sm font-medium text-primary-600 underline">Naar generaties en koopadvies</a>
            </li>
            <li class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                <p class="font-semibold">2. Controleer de modellen</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Klopt de naam? Hoort een RDW-modelnaam bij het juiste model? Pas het aan bij het model, onder aliassen.</p>
                <a href="{{ \App\Filament\Resources\CarModels\CarModelResource::getUrl() }}" class="mt-2 inline-block text-sm font-medium text-primary-600 underline">Naar modellen</a>
            </li>
            <li class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                <p class="font-semibold">3. Zet je affiliate-links aan</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Plak je eigen link in een blok. Zonder link wordt het blok niet getoond.</p>
                <a href="{{ \App\Filament\Pages\AffiliateSettings::getUrl() }}" class="mt-2 inline-block text-sm font-medium text-primary-600 underline">Naar affiliate-instellingen</a>
            </li>
        </ol>

        <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">Wijzigingen zie je meteen op de site. Een nieuw merk of een nieuwe import regel je met een commando; zie de README.</p>
    </x-filament::section>
</x-filament-widgets::widget>
