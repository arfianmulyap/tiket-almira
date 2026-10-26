<?php

namespace App\Services;

use App\Models\Ticket;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Renders the e-ticket as a one-page PDF (Phase 13).
 *
 * No PDF library is installed and the project rule is not to add a dependency
 * when the feature can be built with what is already here. It can:
 *
 *   - the QR symbol comes from bacon/bacon-qr-code, which is already a
 *     dependency for the on-screen ticket. Its module matrix is drawn as
 *     filled rectangles, so the code stays vector-crisp and scans reliably at
 *     any print size;
 *   - the text uses the base-14 Helvetica faces that every PDF reader ships
 *     with. Nothing has to be embedded, so the file stays a few kilobytes and
 *     has no font licensing question.
 *
 * The output is plain PDF 1.4 assembled by hand: catalog, one page, a content
 * stream, two font dictionaries, and an xref table built from the byte offsets
 * of those objects.
 *
 * Text is converted to Windows-1252 because base-14 fonts only address that
 * character set. Indonesian text is unaffected; characters outside it are
 * transliterated or dropped rather than producing mojibake, so the ticket still
 * prints correctly for an event name with an accent.
 *
 * The QR encodes `tickets.qr_token` — the same value the scanner validates
 * against the database — and nothing else. No personal data is put in the code
 * (PRD FR-09).
 */
class TicketPdfRenderer
{
    /**
     * A4 portrait in PostScript points, the size the e-ticket is designed for.
     */
    private const PAGE_WIDTH = 595.28;

    private const PAGE_HEIGHT = 841.89;

    private const MARGIN = 48.0;

    /**
     * Default text colour: near-black rather than pure black, which looks less
     * harsh on paper.
     */
    private const INK = 0.1;

    /**
     * Colour of the small uppercase captions.
     */
    private const MUTED = 0.45;

    /**
     * QR edge length. Large enough to scan from a printed A4 page and from a
     * phone screen showing the PDF.
     */
    private const QR_SIZE = 170.0;

    /**
     * Helvetica advance widths in 1/1000 em, for ASCII 32..126.
     *
     * Used only to decide where a line breaks. A value being slightly off costs
     * a little raggedness, never correctness, because each line is measured
     * against a deliberately conservative content width.
     *
     * @var array<int, int>
     */
    private const WIDTHS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /**
     * Width in 1/1000 em used for characters outside the measured ASCII range.
     */
    private const FALLBACK_WIDTH = 556;

    /**
     * Render the ticket for an order whose payment has been approved.
     */
    public function render(Ticket $ticket): string
    {
        $ticket->loadMissing('order.event');

        $event = $ticket->order->event;

        $left = self::MARGIN;
        $contentWidth = self::PAGE_WIDTH - 2 * self::MARGIN;
        $detailsWidth = $contentWidth - self::QR_SIZE - 32.0;
        $qrLeft = self::PAGE_WIDTH - self::MARGIN - self::QR_SIZE;

        $ops = '';
        $y = self::PAGE_HEIGHT - self::MARGIN;

        // Masthead.
        $ops .= $this->text($left, $y, 8.5, true, strtoupper($this->sanitize((string) config('app.name'))), self::MUTED);
        $ops .= $this->textRight($left + $contentWidth, $y, 8.5, false, 'E-TICKET', self::MUTED);
        $y -= 10;
        $ops .= $this->rule($left, $y, $contentWidth, 0.75);
        $y -= 30;

        // Event name: the largest element, because it is what a door steward
        // checks first.
        [$block, $y] = $this->block($left, $y, 20, true, $detailsWidth, (string) $event->name, self::INK, 1.3);
        $ops .= $block;
        $y -= 8;

        // QR sits on the right, aligned with the detail block below.
        $qrBottom = $y - self::QR_SIZE;
        $ops .= $this->qr($qrLeft, $qrBottom, self::QR_SIZE, (string) $ticket->qr_token);
        $ops .= $this->text($qrLeft, $qrBottom - 14, 8, false, 'QR check-in tiket', self::MUTED);

        foreach ($this->fields($ticket) as [$label, $value]) {
            $ops .= $this->text($left, $y, 7.5, true, strtoupper($label), self::MUTED);
            $y -= 14;
            [$block, $y] = $this->block($left, $y, 11, false, $detailsWidth, $value, self::INK, 1.35);
            $ops .= $block;
            $y -= 10;
        }

        // Footer band, below whichever column ended lower.
        $y = min($y, $qrBottom - 34) - 6;
        $ops .= $this->rule($left, $y, $contentWidth, 0.75);
        $y -= 20;

        [$block] = $this->block(
            $left,
            $y,
            9,
            false,
            $contentWidth,
            'Tunjukkan QR di atas kepada petugas saat check-in. Satu kode hanya berlaku '
            .'untuk satu check-in. Berkas ini bukan bukti pembayaran dan tidak dapat '
            .'dialihkan kepada orang lain.',
            self::MUTED,
            1.4,
        );
        $ops .= $block;

        $ops .= $this->text(
            $left,
            64,
            8,
            false,
            'Diterbitkan '.$ticket->issued_at->translatedFormat('d M Y, H:i').' WIB oleh '
            .config('app.name').'.',
            self::MUTED,
        );

        return $this->assemble($ops);
    }

    /**
     * Filename offered to the participant and used for the mail attachment.
     */
    public function fileName(Ticket $ticket): string
    {
        $ticket->loadMissing('order');

        $reference = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $ticket->order->order_reference);

        return 'e-ticket-'.($reference === '' ? 'tiket' : $reference).'.pdf';
    }

    /**
     * Label and value pairs printed on the ticket.
     *
     * Everything is read from the order and the ticket, never from the event's
     * current price or quota: the ticket describes this participant's purchase,
     * which is already frozen in the order snapshot.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function fields(Ticket $ticket): array
    {
        $order = $ticket->order;
        $event = $order->event;

        return [
            ['Kode Pesanan', (string) $order->order_reference],
            ['Nama Peserta', (string) $order->participant_name],
            ['Waktu', $event->starts_at->translatedFormat('d M Y, H:i').' WIB'],
            ['Lokasi', (string) $event->location],
            ['Status Tiket', $ticket->status->label()],
            ['Diterbitkan', $ticket->issued_at->translatedFormat('d M Y, H:i').' WIB'],
        ];
    }

    /**
     * Draw the QR matrix as filled rectangles.
     *
     * Modules are merged into horizontal runs, which turns a 37x37 symbol into a
     * few hundred path segments instead of one per module. The 4-module quiet
     * zone required by the QR specification is included inside the box.
     */
    private function qr(float $x, float $y, float $size, string $token): string
    {
        $matrix = Encoder::encode($token, ErrorCorrectionLevel::M())->getMatrix();
        $modules = $matrix->getWidth();

        $quietZone = 4;
        $scale = $size / ($modules + 2 * $quietZone);
        $originX = $x + $quietZone * $scale;
        $originY = $y + $quietZone * $scale;

        // Overlap each module by a hair: floating point rounding otherwise
        // leaves hairline gaps between runs, which scanners read as noise.
        $bleed = 0.03;

        $ops = "0 0 0 rg\n";

        for ($row = 0; $row < $modules; $row++) {
            $column = 0;

            while ($column < $modules) {
                if ($matrix->get($column, $row) !== 1) {
                    $column++;

                    continue;
                }

                $runStart = $column;

                while ($column < $modules && $matrix->get($column, $row) === 1) {
                    $column++;
                }

                // PDF user space grows upwards, while row 0 is the top of the
                // symbol, so the row index is mirrored.
                $ops .= $this->num($originX + $runStart * $scale).' '
                    .$this->num($originY + ($modules - 1 - $row) * $scale).' '
                    .$this->num(($column - $runStart) * $scale + $bleed).' '
                    .$this->num($scale + $bleed)." re\n";
            }
        }

        return $ops."f\n";
    }

    /**
     * Wrap a value and emit it line by line.
     *
     * @return array{0: string, 1: float} The operators and the next baseline.
     */
    private function block(
        float $x,
        float $y,
        float $size,
        bool $bold,
        float $maxWidth,
        string $value,
        float $gray,
        float $leading,
    ): array {
        $ops = '';

        foreach ($this->wrap($value, $maxWidth, $size, $bold) as $line) {
            $ops .= $this->text($x, $y, $size, $bold, $line, $gray);
            $y -= $size * $leading;
        }

        return [$ops, $y];
    }

    /**
     * Break text into lines that fit the given width.
     *
     * Values arrive as UTF-8 from the database and leave as Windows-1252, so the
     * byte-by-byte fallback below can never split a multi-byte character in
     * half.
     *
     * @return array<int, string>
     */
    private function wrap(string $value, float $maxWidth, float $size, bool $bold): array
    {
        $value = $this->sanitize($value);

        if ($value === '') {
            return [''];
        }

        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/', trim($value)) ?: [] as $word) {
            if ($word === '') {
                continue;
            }

            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($this->measure($candidate, $size, $bold) <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            // A single word wider than the column (a long location name, an
            // unbroken reference) is split rather than allowed to overflow.
            while ($word !== '' && $this->measure($word, $size, $bold) > $maxWidth) {
                $cut = 1;

                while ($cut < strlen($word)
                    && $this->measure(substr($word, 0, $cut + 1), $size, $bold) <= $maxWidth) {
                    $cut++;
                }

                $lines[] = substr($word, 0, $cut);
                $word = substr($word, $cut);
            }

            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    /**
     * Approximate rendered width of a line, in points.
     */
    private function measure(string $value, float $size, bool $bold): float
    {
        $total = 0;

        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            $code = ord($value[$index]);

            $total += $code >= 32 && $code <= 126
                ? self::WIDTHS[$code - 32]
                : self::FALLBACK_WIDTH;
        }

        // Helvetica-Bold is roughly 5% wider than the regular face; measuring
        // bold text with the regular table and this factor keeps it inside the
        // column instead of letting it spill past the margin.
        return $total / 1000 * $size * ($bold ? 1.05 : 1.0);
    }

    /**
     * One line of text at a baseline position.
     */
    private function text(float $x, float $y, float $size, bool $bold, string $line, ?float $gray = null): string
    {
        return $this->gray($gray ?? self::INK)
            .'BT /'.($bold ? 'F2' : 'F1').' '.$this->num($size).' Tf '
            .$this->num($x).' '.$this->num($y).' Td ('.$this->escape($line).") Tj ET\n";
    }

    /**
     * One line of text ending at a right edge.
     */
    private function textRight(float $right, float $y, float $size, bool $bold, string $line, ?float $gray = null): string
    {
        $line = $this->sanitize($line);

        return $this->text($right - $this->measure($line, $size, $bold), $y, $size, $bold, $line, $gray);
    }

    /**
     * A hairline rule drawn as a filled rectangle, which every reader renders
     * identically regardless of line width settings.
     */
    private function rule(float $x, float $y, float $width, float $gray): string
    {
        return $this->gray($gray).$this->num($x).' '.$this->num($y).' '
            .$this->num($width)." 0.6 re f\n";
    }

    /**
     * Set the non-stroking (fill) colour used by text and rules.
     */
    private function gray(float $value): string
    {
        return $this->num($value)." g\n";
    }

    /**
     * Convert UTF-8 to the only character set base-14 fonts address.
     *
     * iconv with //TRANSLIT keeps "é" as "e" instead of a box or a question
     * mark; //IGNORE drops whatever has no Windows-1252 equivalent.
     */
    private function sanitize(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);

        // Guards the document against a broken byte sequence stored in the
        // database: an invalid string inside a PDF literal would corrupt it.
        if (preg_match('//u', $value) !== 1) {
            return '';
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);

            if ($converted !== false) {
                return $converted;
            }
        }

        return (string) mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
    }

    /**
     * Escape the three characters that are special inside a PDF string.
     */
    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    /**
     * Format a coordinate without trailing zeros.
     */
    private function num(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }

    /**
     * Wrap the content stream in a complete PDF document.
     */
    private function assemble(string $content): string
    {
        $pageWidth = $this->num(self::PAGE_WIDTH);
        $pageHeight = $this->num(self::PAGE_HEIGHT);

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$pageWidth.' '.$pageHeight.'] '
                .'/Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        $file = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $offsets[] = strlen($file);
            $file .= ($index + 1)." 0 obj\n".$body."\nendobj\n";
        }

        // The xref table lists the byte offset of every object, so it can only
        // be written after those offsets are known.
        $xrefOffset = strlen($file);
        $size = count($objects) + 1;

        $file .= "xref\n0 ".$size."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $file .= sprintf("%010d 00000 n \n", $offset);
        }

        $file .= "trailer\n<< /Size ".$size." /Root 1 0 R >>\n"
            .'startxref'."\n".$xrefOffset."\n%%EOF\n";

        return $file;
    }
}
