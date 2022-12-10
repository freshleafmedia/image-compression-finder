<?php

use Freshleafmedia\ImageCompressionFinder\ImageCompressionFinder;

test('Generates quality value', function (string $filePath, int $expectedQuality) {
    $quality = ImageCompressionFinder::make()->run($filePath);

    expect($quality)->toEqual($expectedQuality);
})
->with([
    [__DIR__ . '/fixtures/chris-meads-9FidI-IQxwY-unsplash.jpeg', 80],
    [__DIR__ . '/fixtures/chris-meads-9FidI-IQxwY-unsplash.avif', 71],
    [__DIR__ . '/fixtures/chris-meads-9FidI-IQxwY-unsplash.webp', 85],
]);
