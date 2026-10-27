<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores and replaces event posters.
 *
 * Posters are shown on the public ordering page (PRD FR-03), so they live on a
 * publicly readable disk. The disk is read from `filesystems.poster_disk`
 * rather than from `FILESYSTEM_DISK`, which is `local` (private) and is the
 * correct default for payment proofs in Phase 05 and must stay that way.
 *
 * Resolving it through config also makes the disk swappable per environment:
 * it stays `public` locally and becomes `s3` on a serverless host, where there
 * is no persistent local filesystem to write to.
 */
class EventPosterService
{
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

        $path = $poster->storeAs(self::DIRECTORY, $filename, $this->disk());

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
        $disk = Storage::disk($this->disk());

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    /**
     * Disk that stores and serves poster images.
     *
     * Read from config so this service, Event::posterUrl(), and the tests all
     * share one definition. Falls back to `public` rather than to
     * `FILESYSTEM_DISK`: defaulting to the private disk would store posters
     * somewhere with no public URL and silently break the ordering page.
     */
    public function disk(): string
    {
        return (string) config('filesystems.poster_disk', 'public');
    }
}
