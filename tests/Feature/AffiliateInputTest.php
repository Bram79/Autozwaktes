<?php

use App\Models\AffiliateClick;

beforeEach(function () {
    $this->withoutVite();
    config(['site.affiliates.vehicle_report.url' => 'https://partner.example/rapport?auto={merk}+{model}&jaar={jaar}']);
});

it('does not crash when a query value is an array instead of text', function (string $query) {
    $this->get('/go/vehicle_report?'.$query)->assertStatus(302);
})->with([
    'brand as array' => 'merk[]=audi',
    'model as array' => 'model[a]=a4',
    'year as array' => 'jaar[]=2010',
    'page as array' => 'p[]=/audi',
    'position as array' => 'plek[]=blok',
]);

it('ignores an array for the parts category instead of failing', function () {
    config(['site.affiliates.parts.url' => 'https://parts.example/?q={merk}']);

    $this->get('/go/parts?cat[]=1')->assertStatus(302);
});

it('still counts a normal click', function () {
    $this->get('/go/vehicle_report?merk=audi&model=a4&jaar=2010&p=/audi/a4&plek=blok')->assertRedirect();

    expect(AffiliateClick::count())->toBe(1);
});
