<?php

use App\Site\PlateLookup;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
});

/** Runs the real formatPlate() of resources/js/plate.js with Node and returns what it makes of the typed text. */
function formatted(string $typed): string
{
    $script = "import { formatPlate } from './resources/js/plate.js'; process.stdout.write(formatPlate(process.argv[1]));";
    $result = Process::path(base_path())->run(['node', '--input-type=module', '-e', $script, '--', $typed]);

    return $result->output();
}

it('puts the dashes in a typed license plate, for every Dutch format', function (string $typed, string $expected) {
    if (Process::path(base_path())->run(['node', '-v'])->failed()) {
        $this->markTestSkipped('Node is not installed.');
    }

    expect(formatted($typed))->toBe($expected);
})->with([
    ['70GXL2', '70-GXL-2'],        // 99-XXX-9
    ['AB123C', 'AB-123-C'],        // XX-999-X
    ['12ABCD', '12-AB-CD'],        // 99-XX-XX
    ['AB12CD', 'AB-12-CD'],        // XX-99-XX
    ['ABCD12', 'AB-CD-12'],        // XX-XX-99
    ['1234AB', '12-34-AB'],        // 99-99-XX
    ['AB1234', 'AB-12-34'],        // XX-99-99
    ['1AB234', '1-AB-234'],        // 9-XX-999
    ['123AB4', '123-AB-4'],        // 999-XX-9
    ['1ABC23', '1-ABC-23'],        // 9-XXX-99
    ['A123BC', 'A-123-BC'],        // X-999-XX
    ['ABC12D', 'ABC-12-D'],        // XXX-99-X
    ['A12BCD', 'A-12-BCD'],        // X-99-XXX
    ['70-gxl-2', '70-GXL-2'],      // pasted with dashes, in lower case
    [' 70 gxl 2 ', '70-GXL-2'],    // pasted with spaces
]);

it('shows the first dash as soon as three characters make clear where it goes', function (string $typed, string $expected) {
    if (Process::path(base_path())->run(['node', '-v'])->failed()) {
        $this->markTestSkipped('Node is not installed.');
    }

    expect(formatted($typed))->toBe($expected);
})->with([
    ['AB1', 'AB-1'],         // two letters and a digit: only 99-.. formats start like this, so the dash is after the second character
    ['AB12', 'AB-12'],
    ['70G', '70-G'],         // two digits and a letter
    ['70GXL', '70-GXL'],     // the second dash is still open: 99-XX-XX and 99-XXX-9 both fit
    ['1AB', '1-AB'],         // one digit, then letters: the dash is after the first character
    ['A12', 'A-12'],
    ['A12BC', 'A-12-BC'],    // here both dashes are known
    ['AB12C', 'AB-12-C'],
    ['ABCD', 'AB-CD'],       // four letters: XX-XX-99 or XX-XX-.. only
    ['ABC1', 'ABC-1'],       // three letters and a digit
    ['1234', '12-34'],
    ['123A', '123-A'],
]);

it('leaves the dash out while the formats still disagree, and cuts off what is too long', function (string $typed, string $expected) {
    if (Process::path(base_path())->run(['node', '-v'])->failed()) {
        $this->markTestSkipped('Node is not installed.');
    }

    expect(formatted($typed))->toBe($expected);
})->with([
    ['', ''],
    ['A', 'A'],
    ['AB', 'AB'],
    ['ABC', 'ABC'],          // 99-XXX-9 and XX-XXX-9 start like this, but also XX-XX-..: not clear yet
    ['123', '123'],
    ['A1B', 'A1B'],          // no Dutch format looks like this: left alone
    ['70GXL2X', '70-GXL-2'],
    ['@#!', ''],
]);

it('uses the dash formatting on both plate fields, and the server accepts what comes out of it', function () {
    foreach (['/', '/apk-herinnering'] as $url) {
        $this->get($url)->assertOk()->assertSee('data-plate-input', false);
    }

    expect(PlateLookup::normalize('70-GXL-2'))->toBe('70GXL2');
});

it('does not show the badge "Gebaseerd op RDW open data" in the hero', function () {
    $this->get('/')->assertOk()->assertDontSee('Gebaseerd op RDW open data', false);
});
