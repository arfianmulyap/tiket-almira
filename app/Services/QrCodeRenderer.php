<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders a scannable QR code as inline SVG.
 *
 * SVG rather than PNG so no image extension is required at runtime and the
 * markup can be embedded directly in the ticket page, which keeps the ticket
 * printable and self-contained with no extra request.
 */
class QrCodeRenderer
{
    /**
     * Edge length in pixels. Large enough to scan reliably from a phone
     * screen and when printed.
     */
    private const SIZE = 320;

    /**
     * Quiet zone in modules. The QR specification requires at least 4;
     * scanners are unreliable without it.
     */
    private const MARGIN = 2;

    /**
     * Render the payload as an SVG document.
     *
     * The caller is responsible for deciding what goes in the payload. Only
     * the opaque ticket token is passed in by this application, never
     * personal data (PRD FR-09).
     */
    public function render(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(self::SIZE, self::MARGIN),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($payload);
    }
}
