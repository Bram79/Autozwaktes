<x-layouts.app
    title="APK-herinnering: een mail voordat je APK verloopt | Auto Zwaktes"
    description="Vul je kenteken en e-mailadres in en ontvang elk jaar één mail ongeveer 30 dagen voordat de APK van je auto verloopt. Gratis en altijd uit te schrijven."
    :canonical="route('apk')">

    <x-breadcrumbs :items="[['Home', route('home')], ['APK-herinnering', route('apk')]]" />

    <div class="max-w-xl">
        <h1 class="text-3xl sm:text-4xl">Nooit meer je APK vergeten</h1>
        <p class="mt-3 text-slate-700">Vul je kenteken en e-mailadres in. Elk jaar sturen we je één mail ongeveer 30 dagen voordat de APK van je auto verloopt, tot je je uitschrijft. Gratis, en je kunt je altijd direct uitschrijven.</p>

        <ol class="mt-5 grid gap-2 text-sm text-slate-800 sm:grid-cols-3">
            <li class="rounded-xl bg-brand-50 p-3"><span class="font-bold text-brand-700">1.</span> Vul je kenteken en e-mail in.</li>
            <li class="rounded-xl bg-brand-50 p-3"><span class="font-bold text-brand-700">2.</span> Bevestig via de mail die je krijgt.</li>
            <li class="rounded-xl bg-brand-50 p-3"><span class="font-bold text-brand-700">3.</span> Ontvang elk jaar een herinnering.</li>
        </ol>

        <form method="post" action="{{ route('apk.store') }}" class="mt-6 space-y-4 rounded-2xl border border-brand-100 bg-brand-50 p-5">
            @csrf

            @if ($errors->any())
                <div id="fouten" role="alert" tabindex="-1" class="rounded-lg border-2 border-red-800 bg-white p-3 text-sm text-red-800">
                    <p class="font-semibold">Er ging iets mis:</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label for="kenteken" class="block text-sm font-semibold">Kenteken</label>
                <input id="kenteken" name="kenteken" type="text" data-plate-input required maxlength="12" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="AB-123-C" aria-describedby="kenteken-hulp{{ $errors->has('kenteken') ? ' kenteken-fout' : '' }}" @if ($errors->has('kenteken')) aria-invalid="true" @endif
                       class="mt-1 w-full max-w-60 rounded-lg border-2 border-brand-900 bg-accent-400 px-3 py-2 text-lg font-bold uppercase tracking-widest text-brand-900 placeholder:text-brand-900/70 focus:outline-none focus:ring-4 focus:ring-brand-900">
                <p id="kenteken-hulp" class="mt-1 text-xs text-slate-600">Je mag de streepjes weglaten.</p>
                @error('kenteken')<p id="kenteken-fout" class="mt-1 text-sm font-medium text-red-800">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold">E-mailadres</label>
                <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ old('email') }}" @if ($errors->has('email')) aria-invalid="true" aria-describedby="email-fout" @endif
                       class="mt-1 w-full rounded-lg border border-slate-500 bg-white px-3 py-2 focus:border-brand-600">
                @error('email')<p id="email-fout" class="mt-1 text-sm font-medium text-red-800">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="akkoord" value="1" @checked(old('akkoord')) @if ($errors->has('akkoord')) aria-invalid="true" aria-describedby="akkoord-fout" @endif class="mt-1 size-5 rounded border-slate-500 text-brand-700 focus:ring-brand-600">
                    <span>Ja, stuur mij elk jaar een herinnering voor de APK van dit kenteken. Ik weet dat ik daarna een mail krijg om dit te bevestigen, en dat ik me altijd kan uitschrijven.</span>
                </label>
                @error('akkoord')<p id="akkoord-fout" class="mt-1 text-sm font-medium text-red-800">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn">Herinner mij</button>

            <p class="text-xs text-slate-600">We bewaren alleen je e-mailadres, je kenteken en de APK-datum, en alleen om deze herinnering te sturen. Is de APK meer dan een jaar verlopen zonder nieuwe, dan verwijderen we je gegevens vanzelf. Meer in onze <a href="{{ route('privacy') }}" class="text-brand-700 underline underline-offset-2">privacyverklaring</a>.</p>
        </form>
    </div>
</x-layouts.app>
