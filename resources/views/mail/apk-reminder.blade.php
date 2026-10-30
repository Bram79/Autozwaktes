<x-mail::message>
# Je APK verloopt binnenkort

De APK van **{{ $plate }}** verloopt op **{{ $expires }}**@if ($days > 0) (over {{ $days }} {{ $days === 1 ? 'dag' : 'dagen' }})@endif.
Plan op tijd een keuring, zodat je niet zonder geldige APK rijdt.

@if ($partner)
<x-mail::panel>
**{{ $partner['title'] }}**

{{ $partner['text'] }}

[{{ $partner['button'] }}]({{ $partner['url'] ? $partnerUrl : '' }})

*Dit is een affiliate-link: als je hierop klikt en iets afneemt, ontvangen wij een vergoeding.*
</x-mail::panel>
@endif

Benieuwd waar je auto vaak mee naar de garage moet? Bekijk de zwakke punten per model op [Auto Zwaktes]({{ $homeUrl }}).

Je krijgt deze mail omdat je een APK-herinnering hebt aangevraagd. [Stop de herinnering en verwijder mijn gegevens]({{ $unsubscribeUrl }}).
</x-mail::message>
