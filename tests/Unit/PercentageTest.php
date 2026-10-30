<?php

use App\Rdw\StatisticsCalculator;

it('calculates a percentage with two decimals', function () {
    expect(StatisticsCalculator::percentage(1, 3))->toBe(33.33);
    expect(StatisticsCalculator::percentage(50, 200))->toBe(25.0);
});

it('returns 0 without vehicles', function () {
    expect(StatisticsCalculator::percentage(5, 0))->toBe(0.0);
});

it('never goes above 100', function () {
    expect(StatisticsCalculator::percentage(12, 10))->toBe(100.0);
});
