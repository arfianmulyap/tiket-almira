<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores and replaces event posters.
 *
 * Posters are shown on the public ordering page (PRD FR-03), so they live on
 * the `public` disk. The disk is named explicitly rather than relying on the
 * default: `FILESYSTEM_DISK` is `local` (private), which is the correct
 * default for payment proofs in Phase 05 and must stay that way.
 */
class EventPosterService
{
    /**
     * Disk that serves poster images.
     */
    private const DISK = 'public';

    /**
     * Directory inside the disk.
     */
    private const DIRECTORY = 'posters';

    /**
     * Store a new poster for the event and remove the previous file.
     *
     * The filename is randomised and namespaced per event so one event can
     * never overwrite another event's poster (PRD FR-03).
     */
    public function store(Event $event, UploadedFile $poster): string
    {
        $previousPath = $event->poster_path;

        $filename = $event->id.'-'.Str::random(24).'.'.$poster->extension();

        $path = $poster->storeAs(self::DIRECTORY, $filename, self::DISK);

        $event->poster_path = $path;
        $event->save();

        // Only delete the old file once the new one is safely stored, so a
        // failed upload never leaves the event without a poster.
        if (filled($previousPath) && $previousPath !== $path) {
            $this->deleteFile($previousPath);
        }

        return $path;
    }

    /**
     * Delete a poster file, ignoring a path that is already gone.
     *
     * Kept idempotent so repeated calls cannot fail.
     */
    public function deleteFile(string $path): void
    {
        $disk = Storage::disk(self::DISK);

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
