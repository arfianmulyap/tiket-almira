<?php

namespace App\Services;

use App\Models\PaymentMethod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores and replaces QR merchant images for payment methods (Phase 12B).
 *
 * Mirrors EventPosterService deliberately: the QR image has to be publicly
 * readable, because a participant must load it in order to scan it, so it goes
 * on the configured public disk rather than the private one used for payment
 * proofs. Keeping the two upload flows shaped the same way means there is one
 * pattern to understand, and one place to change when the disk moves to object
 * storage.
 *
 * The image is not an instruction: it is an asset that can be replaced. The
 * path is stored on the method, and each order snapshots the URL it was shown,
 * so replacing an image never rewrites an order that already showed the old one.
 */
class PaymentMethodImageService
{
    /**
     * Directory inside the disk.
     */
    private const DIRECTORY = 'payment-methods';

    /**
     * Store a new QR image for the method and remove the previous file.
     *
     * The filename is randomised and namespaced per method, so one method can
     * never overwrite another method's image and a guessed filename cannot
     * replace an existing one.
     */
    public function store(PaymentMethod $method, UploadedFile $image): string
    {
        $previousPath = $method->qr_image_path;

        $filename = $method->getKey().'-'.Str::random(24).'.'.$image->extension();

        $path = $image->storeAs(self::DIRECTORY, $filename, $this->disk());

        $method->qr_image_path = $path;
        $method->save();

        // Only delete the old file once the new one is safely stored, so a
        // failed upload never leaves the method without a QR image.
        if (filled($previousPath) && $previousPath !== $path) {
            $this->deleteFile((string) $previousPath);
        }

        return $path;
    }

    /**
     * Delete a QR image file, ignoring a path that is already gone.
     *
     * Kept idempotent so repeated calls — a replaced image, a removed image,
     * and a deleted method — cannot fail.
     */
    public function deleteFile(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        $disk = Storage::disk($this->disk());

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    /**
     * Disk that stores and serves QR merchant images.
     *
     * Read from config so this service, PaymentMethod::qrImageUrl(), and the
     * validation rules all share one definition. Falls back to `public` rather
     * than to the private proof disk: storing the image privately would leave
     * the participant with a URL they cannot load.
     */
    public function disk(): string
    {
        return (string) config('payments.qr_image.disk', 'public');
    }
}
