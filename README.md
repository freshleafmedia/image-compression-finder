# Image Compression Finder

Automatic detection of maximum compression without visually changing the image.


## Overview

This is a library which attempts to determine the maximum amount of compression which can be applied 
to an image while not introducing any visible changes.

The output is an integer between 0 and 100.


## Installation

```
composer require freshleafmedia/images-compression-finder
```

### DSSIM

A working copy of [kornelski/dssim](https://github.com/kornelski/dssim) must be installed.

To install the latest, at time of writing, on Ubuntu the following one-liner can be used:

```
curl -sSL https://github.com/kornelski/dssim/releases/download/3.2.3/dssim_3.2.3_amd64.deb > /tmp/dssim.deb && apt install /tmp/dssim.deb
```



## Usage

```php
use FreshleafMedia\ImageCompressionFinder\ImageCompressionFinder;

$quality = ImageCompressionFinder::make()->run('/path/to/image');

$quality; // 66 (0-100)
```


## How it works

The algorithm uses a binary search to repeatedly compress and test an image until the ideal quality is found

1. Compress the image with a quality setting of 60
2. Analyse the new image using DSSIM
3. If the change is within tolerance the quality is halved, if not it is increased by 50% (aka a binary search)
4. Compress the image again with the new quality
5. GOTO 2


## Options

The `ImageCompressionFinder` constructor takes a number of options:

```php
new ImageCompressionFinder(
    driver: 'imagick', // The driver to use to compress images
    maxDifference: 0.001, // The highest acceptable visual change. 0-∞ where 0 is no change at all
    startingQuality: 60, // Where to start the search
);
```


## Tests

Unit tests can be run via `composer test`



## License

See [LICENSE](LICENSE)
