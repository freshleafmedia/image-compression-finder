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

    public function run(string $imagePath, ?string $convertEncodingTo = null): int
    {
        $previousQuality = $this->startingQuality;
        $newQuality = intval($this->startingQuality / 2);

        while ($newQuality !== $previousQuality) {
            var_dump($newQuality, $previousQuality);
            $compressedImagePath = $this->generateImage($imagePath, $newQuality, $convertEncodingTo);
            $compressedJpegImagePath = $this->generateImage($compressedImagePath, 100, 'jpg');

            $difference = $this->getDssim($imagePath, $compressedJpegImagePath);

            if ($difference > $this->maxDifference) {
                $nextQuality = round($newQuality + (($previousQuality - $newQuality) / 2));
            } else {
                $nextQuality = round($newQuality / 2);
            }

            $previousQuality = $newQuality;
            $newQuality = intval($nextQuality);
        }

        return $newQuality;
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
