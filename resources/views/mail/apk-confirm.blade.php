<x-mail::message>
# Bevestig je APK-herinnering

Je hebt op Auto Zwaktes gevraagd om een herinnering voordat de APK van **{{ $plate }}** verloopt.

<x-mail::button :url="$confirmUrl">
Ja, stuur mij een herinnering
</x-mail::button>

Heb je dit niet aangevraagd? Dan hoef je niets te doen: zonder bevestiging sturen we je niets en verwijderen we je gegevens binnen een week.

Liever niet meer? [Verwijder mijn gegevens]({{ $unsubscribeUrl }}).
</x-mail::message>
