<?php

use App\Site\DefectText;

it('writes the CNG/LNG defects of the RDW in plain Dutch', function (string $rdw, string $plain) {
    expect(DefectText::plain($rdw))->toBe($plain);
})->with([
    ['CNG-/LNG- tank heeft deuk(en)', 'Aardgastank heeft deuk(en)'],
    ['CNG-/LNG- tank niet permanent aangebracht', 'Aardgastank niet permanent aangebracht'],
    ['Gasvoerende slangen CNG/LNG zichtbaar beschadigd', 'Gasvoerende slangen voor aardgas zichtbaar beschadigd'],
    ['Leidingen of gasvoerende slangen CNG/LNG geknikt', 'Leidingen of gasvoerende slangen voor aardgas geknikt'],
    ['Overmatige corrosie/ernstige beschadigingen CNG-/ LNG- onder', 'Overmatige corrosie/ernstige beschadigingen aan aardgasonderdelen'],
    ['Vulaansluiting CNG/LNG niet voorzien van stofkap', 'Vulaansluiting voor aardgas niet voorzien van stofkap'],
    ['Vulaansluiting CNG/LNG onjuist geplaatst', 'Vulaansluiting voor aardgas onjuist geplaatst'],
    ['Gasdichte behuizing CNG/LNG mondt niet uit in buitenlucht', 'Gasdichte behuizing voor aardgas mondt niet uit in buitenlucht'],
    ['Gasdichte behuizing CNG/LNG ontbreekt of ondeugdelijk', 'Gasdichte behuizing voor aardgas ontbreekt of ondeugdelijk'],
]);

it('replaces the abbreviation by a word in any other CNG/LNG text', function () {
    expect(DefectText::plain('Iets met CNG/LNG-installatie'))->toBe('Iets met aardgasinstallatie');
    expect(DefectText::plain('CNG-/LNG-installatie lekt'))->not->toContain('CNG')->not->toContain('LNG');
});

it('leaves other defects alone, also LPG that most drivers know', function () {
    foreach (['Remleiding poreus of beschadigd', 'LPG-tank heeft deuk(en)', 'Vulaansluiting LPG onjuist geplaatst', 'Bandenprofiel te laag'] as $text) {
        expect(DefectText::plain($text))->toBe($text);
    }
});
