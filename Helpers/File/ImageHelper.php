<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * ImageHelper provides a convenient and efficient way to manipulate images.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\File;

use Exception;
use Intervention\Image\ImageManager;
use Intervention\Image\ImageManagerStatic;

class ImageHelper
{
    private $config;

    private $image;

    private int $width = 0;

    private int $height = 0;

    private $path;

    public function __construct()
    {
        $this->config = config('image');
    }

    public function config(string $option): array
    {
        return $this->config[$option];
    }

    /*
    The image to be manipulated
     */
    public function image(string $path): self
    {
        if (! file_exists($path)) {
            throw new Exception('The file ' . $path . ' does not exist');
        }

        $manager = new ImageManager(['driver' => $this->config['driver']]);
        $this->image = $manager->make($path);

        return $this;
    }

    public function imageWidth(): int
    {
        return $this->image->width();
    }

    public function imageHeight(): int
    {
        return $this->image->height();
    }

    /*
    Sets the width of the image, in pixels.
     */
    public function width(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    /*
    Sets the height of the image, in pixels.
     */
    public function height(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    /*
    'auto', '0', '90', '180', '270'
     */
    public function orientation(string $orientation = 'auto'): self
    {
        if (! in_array($orientation, ['auto', '0', '90', '180', '270'])) {
            $orientation = 'auto';
        }

        if ($orientation === 'auto') {
            $this->image->orientate();
        } else {
            $this->image->rotate($orientation);
        }

        return $this;
    }

    /*
    -270 to 270.
     */
    public function rotate(string $rotate): self
    {
        $this->image->rotate($rotate);

        return $this;
    }

    /*
    v, h, both
     */
    public function flip(string $flip): self
    {
        if (in_array($flip, ['v', 'h', 'both'])) {
            if ($flip === 'both') {
                $this->image->flip('h')->flip('v');
            } else {
                $this->image->flip($flip);
            }
        }

        return $this;
    }

    public function crop(string $crop = 'fit'): self
    {
        $position = function ($position) {
            $x = $y = 0;
            $origWidth = $this->imageWidth();
            $origHeight = $this->imageHeight();
            $width = $this->width;
            $height = $this->height;

            switch ($position) {
                case 'top-left':
                    $x = 0;
                    $y = 0;
                    break;
                case 'top':
                    $x = floor(($origWidth - $width) / 2);
                    $y = 0;
                    break;
                case 'top-right':
                    $x = $origWidth - $width;
                    $y = 0;
                    break;
                case 'left':
                    $x = 0;
                    $y = floor(($origHeight - $height) / 2);
                    break;
                case 'center':
                    $x = floor(($origWidth - $width) / 2);
                    $y = floor(($origHeight - $height) / 2);
                    break;
                case 'right':
                    $x = ($origWidth - $width);
                    $y = floor(($origHeight - $height) / 2);
                    break;
                case 'bottom-left':
                    $x = 0;
                    $y = $origHeight - $height;
                    break;
                case 'bottom':
                    $x = floor(($origWidth - $width) / 2);
                    $y = $origHeight - $height;
                    break;
                case 'bottom-right':
                    $x = ($origWidth - $width);
                    $y = $origHeight - $height;
                    break;
            }

            return [$x, $y];
        };

        $x = $position($crop)[0];
        $y = $position($crop)[1];

        $this->image->crop($this->width, $this->height, $x, $y);

        return $this;
    }

    public function fit(): self
    {
        $this->image->fit($this->width, $this->height, function (object $constraint) {
            $constraint->upsize();
        });

        return $this;
    }

    /**
     * Perform resize image manipulation
     * based on the width and height provided
     */
    public function resize(?string $scale = null): self
    {
        $config = $this->config['presets'];
        $width = !empty($scale) ? $config[$scale]['width'] : $this->width;
        $height = !empty($scale) ? $config[$scale]['height'] : $this->height;

        // The logic below ensures the image isn't "stretched"
        $this->image->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        return $this;
    }

    /*
    Adjusts the image opacity. Use values between 1 and 100.
     */
    public function opacity(int $opacity): self
    {
        $this->image->opacity($opacity);

        return $this;
    }

    /*
    Adjusts the image brightness. Use values between -100 and +100, where 0 represents no change.
     */
    public function brightness(string $brightness): self
    {
        $this->image->brightness($brightness);

        return $this;
    }

    /*
    Adjusts the image contrast. Use values between -100 and +100, where 0 represents no change.
     */
    public function contrast(string $contrast): self
    {
        $this->image->contrast($contrast);

        return $this;
    }

    /*
    Adjusts the image gamma. Use values between 0.1 and 9.99.
     */
    public function gamma(float $gamma): self
    {
        $this->image->gamma($gamma);

        return $this;
    }

    /*
    Sharpen the image. Use values between 0 and 100.
     */
    public function sharpen(int $sharpen): self
    {
        $this->image->sharpen($sharpen);

        return $this;
    }

    /*
    Reverses all colors of the current image.
     */
    public function invert(): self
    {
        $this->image->invert();

        return $this;
    }

    /*
    Adds a blur effect to the image. Use values between 0 and 100.
     */
    public function blur(int $blur = 0): self
    {
        $this->image->blur($blur);

        return $this;
    }

    /*
    Applies a pixelation effect to the image. Use values between 0 and 1000.
     */
    public function pixelate(string $pixel): self
    {
        $this->image->pixelate($pixel);

        return $this;
    }

    /*
    Applies a filter effect to the image. Accepts greyscale or sepia.
     */
    public function filter(string $filter): self
    {
        if ($filter == 'greyscale') {
            $this->image->greyscale();
        } elseif ($filter == 'sepia') {
            $this->image->greyscale();
            $this->image->brightness(-10);
            $this->image->contrast(10);
            $this->image->colorize(38, 27, 12);
            $this->image->brightness(-10);
            $this->image->contrast(10);
        }

        return $this;
    }

    public function watermark(array $config = []): self
    {
        $config = empty($config) ? $this->config : $config;

        $x = $config['watermark']['setting']['padding']['x'];
        $y = $config['watermark']['setting']['padding']['y'];
        $position = $config['watermark']['setting']['position'];

        $watermark = function ($config) {
            $file = $config['watermark']['path'];
            $width = (int) $config['watermark']['setting']['width'];
            $height = (int) $config['watermark']['setting']['height'];
            $opacity = (int) $config['watermark']['setting']['opacity'];

            if (file_exists($file)) {
                return ImageManagerStatic::make($file)->fit($width, $height)->opacity((100 - $opacity));
            } else {
                throw new Exception('Watermark file ' . $file . ' does not exists');
            }
        };

        $this->image->insert($watermark($config), $position, $x, $y);

        return $this;
    }

    public function encode(string $format, int $quality = 90): self
    {
        $this->image->encode($format, $quality);

        return $this;
    }

    public function save(string $save_to, string $save_as, int $quality = 90): bool
    {
        $path = rtrim($save_to, '/') . '/' . $save_as;
        $this->image->save($path, $quality);

        return true;
    }
}
