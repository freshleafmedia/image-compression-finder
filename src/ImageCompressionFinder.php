<?php declare(strict_types=1);

namespace Freshleafmedia\ImageCompressionFinder;

use Freshleafmedia\ImageCompressionFinder\Exceptions\DssimNotFoundException;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ImageCompressionFinder
{
    protected TemporaryDirectory $cacheDirectory;

    public function __construct(
        protected DriverInterface $driver = new ImagickDriver(),
        protected float $maxDifference = 0.001,
        protected int $startingQuality = 60,
    ) {
        $this->cacheDirectory = TemporaryDirectory::make();
    }

    public static function make(): self
    {
        return new self();
    }

    public function driver(DriverInterface $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    public function maxDifference(float $difference): self
    {
        $this->maxDifference = $difference;

        return $this;
    }

    public function startingQuality(int $quality): self
    {
        $this->startingQuality = $quality;

        return $this;
    }

    public function run(string $imagePath): int
    {
        $quality = $this->startingQuality;

        $upperBound = 100;
        $lowerBound = 0;

        while ($upperBound - $lowerBound > 1) {
            $compressedImagePath = $this->generateImage($imagePath, $quality);

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
        $encoding ??= pathinfo($path, PATHINFO_EXTENSION);
        $cachePath = $this->cacheDirectory->path(basename($path) . '@' . $quality . '.' . $encoding);

        if (file_exists($cachePath) === false) {
            (new ImageManager($this->driver))
                ->read($path)
                ->save($cachePath, quality: $quality);
        }

        return $cachePath;
    }

    protected function getDssim(string $imagePathSource, string $imagePathTest): float
    {
        // DSSIM can only compare JPEGs
        if (in_array(pathinfo($imagePathSource, PATHINFO_EXTENSION), ['jpeg', 'jpg'], true) === false) {
            $imagePathSource = $this->generateImage($imagePathSource, 100, 'jpeg');
        }

        if (in_array(pathinfo($imagePathTest, PATHINFO_EXTENSION), ['jpeg', 'jpg'], true) === false) {
            $imagePathTest = $this->generateImage($imagePathTest, 100, 'jpeg');
        }

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
