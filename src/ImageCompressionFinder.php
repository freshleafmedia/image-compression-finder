<?php declare(strict_types=1);

namespace Freshleafmedia\ImageCompressionFinder;

use Freshleafmedia\ImageCompressionFinder\Exceptions\DssimNotFoundException;
use Intervention\Image\ImageManager;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ImageCompressionFinder
{
    protected TemporaryDirectory $cacheDirectory;

    public function __construct(
        protected string $driver = 'imagick',
        protected float $maxDifference = 0.001,
        protected int $startingQuality = 60,
    ) {
        $this->cacheDirectory = TemporaryDirectory::make();
    }

    public static function make(): self
    {
        return new self();
    }

    public function run(string $imagePath): int
    {
        $quality = $this->startingQuality;

        $upperBound = 100;
        $lowerBound = 0;

        while ($upperBound - $lowerBound > 1) {
            $compressedImagePath = $this->generateImage($imagePath, $quality);

            if (in_array(pathinfo($imagePath, PATHINFO_EXTENSION), ['jpeg', 'jpg'], true) === false) {
                // DSSIM can only compare JPEGs
                $compressedImagePath = $this->generateImage($compressedImagePath, 100, 'jpg');
            }

            $difference = $this->getDssim($imagePath, $compressedImagePath);

            if ($difference > $this->maxDifference) {
                $lowerBound = $quality;
            } else {
                $upperBound = $quality;
            }

            $nextQuality = $lowerBound + (($upperBound - $lowerBound) / 2);

            $quality = intval(round($nextQuality));
        }

        return $quality;
    }

    protected function generateImage(string $path, int $quality, ?string $encoding = null): string
    {
        [$filename, $extension] = explode('.', basename($path));
        $encoding ??= $extension;
        $cachePath = $this->cacheDirectory->path($filename . '@' . $quality . '.' . $encoding);

        if (file_exists($cachePath) === false) {
            (new ImageManager(['driver' => $this->driver]))
                ->make($path)
                ->save($cachePath, $quality, $encoding);
        }

        return $cachePath;
    }

    protected function getDssim(string $imagePathSource, string $imagePathTest): float
    {
        $executable = (new ExecutableFinder())->find('dssim');

        if ($executable === null) {
            throw new DssimNotFoundException();
        }

        $process = new Process([$executable, $imagePathSource, $imagePathTest]);

        $process->mustRun();

        preg_match('/^([0-9\.]+).+/', $process->getOutput(), $matches);

        return (float)$matches[1];
    }

    public function __destruct()
    {
        $this->cacheDirectory->delete();
    }
}
