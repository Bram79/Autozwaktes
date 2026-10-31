<?php

use App\Site\DefectText;

// The RDW cuts every defect description off at 60 characters. These are real descriptions from the data.

it('completes the cut-off words that can only mean one thing', function (string $rdw, string $shown) {
    expect(DefectText::plain($rdw))->toBe($shown);
})->with([
    ['Uitlaatsysteem niet gasdicht / onveilige afvoer uitlaatgasse', 'Uitlaatsysteem niet gasdicht / onveilige afvoer uitlaatgassen'],
    ['Waarschuwingsinrichting airbag- / gordelsysteem geeft defect', 'Waarschuwingsinrichting airbag- / gordelsysteem geeft defect aan'],
    ['Waarschuwingsinrichting controlesysteem bandenspanning geeft', 'Waarschuwingsinrichting controlesysteem bandenspanning geeft defect aan'],
    ['Waarschuwingsinrichting stabiliteitscontrolesysteem geeft de', 'Waarschuwingsinrichting stabiliteitscontrolesysteem geeft defect aan'],
    ['Waarschuwingsinrichting elektronische stuurbekrachtiging gee', 'Waarschuwingsinrichting elektronische stuurbekrachtiging geeft defect aan'],
    ['Drager/bevestigingsmiddel van remvoering raakt trommel/schij', 'Drager/bevestigingsmiddel van remvoering raakt trommel/schijf'],
    ['Inrichting voor aanpassing dimlichtafstelling werkt niet (go', 'Inrichting voor aanpassing dimlichtafstelling werkt niet (goed)'],
]);

it('does not guess the rest of other cut-off descriptions: it stops at the last whole word with an ellipsis', function () {
    $plain = DefectText::plain('Achterlicht glas lichtarmatuur ernstig beschadigd of verwijd');

    expect($plain)->toBe('Achterlicht glas lichtarmatuur ernstig beschadigd of …');
    expect($plain)->not->toContain('verwijd');
});

it('leaves a complete description alone', function () {
    expect(DefectText::plain('Remleiding poreus of beschadigd'))->toBe('Remleiding poreus of beschadigd');
    expect(DefectText::plain('Waarschuwingsinrichting antiblokkeersysteem geeft defect aan'))->toBe('Waarschuwingsinrichting antiblokkeersysteem geeft defect aan');
    expect(DefectText::plain('Roetfilter werkt niet (goed), aantal deeltjes > 1 miljoen en)'))->toEndWith('en)');
});
