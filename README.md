# Convert PDF to image.

## use

```php
$pdf = new \Pff\Pdf2Image\Pdf(__DIR__ . '/test.pdf');
$pdf->setResolution(192);
$pdf->setCallback(function (Imagick $imagick) {
    $imagick->cropImage(757,1134, 0, 0);
});
$pdf->saveImage(__DIR__ . '/test.jpg');
//$pdf->saveAllPagesAsImages(__DIR__, 'test-');
```
