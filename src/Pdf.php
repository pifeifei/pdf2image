<?php

declare(strict_types=1);

namespace Pff\Pdf2Image;

use Imagick;
use ImagickException;
use Pff\Pdf2Image\Exceptions\InvalidFormatException;
use Pff\Pdf2Image\Exceptions\PdfException;
use Pff\Pdf2Image\Exceptions\PdfNotFoundException;

class Pdf
{
    /** @var Imagick */
    protected $imagick;

    /** @var string */
    protected $pdfFile;

    /** @var int */
    protected $resolution = 144;

    /** @var string */
    protected $format = 'jpg';

    /** @var int */
    protected $page = 1;

    /** @var string[] */
    protected $validFormats = ['jpg', 'jpeg', 'png'];

    /** @var null|callable */
    protected $callback;

    /**
     * @param string $pdfFile the path to the pdf file
     *
     * @throws PdfNotFoundException
     * @throws PdfException
     */
    public function __construct(string $pdfFile)
    {
        if (!file_exists($pdfFile)) {
            throw new PdfNotFoundException();
        }

        $this->pdfFile = $pdfFile;
        $this->imagick = new Imagick();

        try {
            $this->imagick->setResolution($this->resolution, $this->resolution);
        } catch (ImagickException $e) {
            throw new PdfException($e->getMessage(), 1, $e);
        }
    }

    public function imagick(): Imagick
    {
        return $this->imagick;
    }

    /**
     * Set the raster resolution.
     *
     * @return $this
     */
    public function setResolution(int $resolution): self
    {
        $this->resolution = $resolution;

        return $this;
    }

    /**
     * Set the output format.
     *
     * @throws InvalidFormatException
     *
     * @return $this
     */
    public function setFormat(string $format): self
    {
        if (!$this->isValidFormat($format)) {
            throw new InvalidFormatException('Format ' . $format . ' is not supported');
        }

        $this->format = $format;

        return $this;
    }

    /**
     * Determine if the given format is a valid output format.
     *
     * @param mixed $format
     */
    public function isValidFormat($format): bool
    {
        return \in_array($format, $this->validFormats, true);
    }

    /**
     * Set the page number that should be rendered.
     *
     * @throws PdfException
     *
     * @return $this
     */
    public function setPage(int $page): self
    {
        if ($page > $this->getNumberOfPages()) {
            throw new PdfException('Page ' . $page . ' does not exist');
        }

        $this->page = $page;

        return $this;
    }

    /**
     * Get the number of pages in the pdf file.
     *
     * @throws PdfException
     */
    public function getNumberOfPages(): int
    {
        try {
            return (new Imagick($this->pdfFile))->getNumberImages();
        } catch (ImagickException $e) {
            throw new PdfException($e->getMessage(), 1, $e);
        }
    }

    /**
     * set callback.
     *
     * @return $this
     */
    public function setCallback(callable $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Save the image to the given path.
     *
     * @throws PdfException
     */
    public function saveImage(string $pathToImage): bool
    {
        $imageData = $this->getImageData($pathToImage);

        if (\is_callable($this->callback)) {
            ($this->callback)($imageData);
        }

        return !(false === file_put_contents($pathToImage, $imageData));
    }

    /**
     * Save the file as images to the given directory.
     *
     * @throws PdfException
     *
     * @return array<string> $files the paths to the created images
     */
    public function saveAllPagesAsImages(string $directory, string $prefix = ''): array
    {
        $totalPage = $this->getNumberOfPages();
        $pageWidth = \strlen((string) $totalPage);

        if (0 === $totalPage) {
            return [];
        }

        return array_map(function ($pageNumber) use ($directory, $prefix, $pageWidth) {
            $this->setPage($pageNumber);
            $end = substr('000000' . $pageNumber, -$pageWidth);
            $destination = sprintf('%s/%s%s.%s', $directory, $prefix, $end, $this->format);
            $this->saveImage($destination);

            return $destination;
        }, range(1, $totalPage));
    }

    /**
     * Return raw image data.
     *
     * @throws PdfException
     */
    public function getImageData(string $pathToImage): Imagick
    {
        $imagick = $this->imagick;

        try {
            $imagick->setResolution($this->resolution, $this->resolution);
            $imagick->readImage(sprintf('%s[%s]', $this->pdfFile, $this->page - 1));
            $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $imagick->setFormat($this->determineFormat($pathToImage));

            return $imagick;
        } catch (ImagickException $e) {
            throw new PdfException($e->getMessage(), 1, $e);
        }
    }

    /**
     * Determine in which format the image must be rendered.
     */
    protected function determineFormat(string $pathToImage): string
    {
        $format = pathinfo($pathToImage, PATHINFO_EXTENSION);

        if ('' !== $format) {
            $format = $this->format;
        }

        $format = strtolower($format);

        if (!$this->isValidFormat($format)) {
            $format = 'jpg';
        }

        return $format;
    }
}
