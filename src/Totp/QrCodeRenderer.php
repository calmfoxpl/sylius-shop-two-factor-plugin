<?php

declare(strict_types=1);

namespace Calmfox\SyliusShopTwoFactorPlugin\Totp;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class QrCodeRenderer
{
    /** Returns a data: URI with an SVG QR code, ready for an <img src>. */
    public function dataUri(string $content, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd()));

        return 'data:image/svg+xml;base64,' . base64_encode($writer->writeString($content));
    }
}
