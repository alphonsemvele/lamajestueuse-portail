<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Televersement d'un visuel : fichier envoye, suppression demandee, ou valeur
 * saisie a la main (URL externe / visuel livre avec le projet).
 */
trait HandlesMediaUploads
{
    /**
     * @param  string|null  $current  Valeur actuellement enregistree.
     */
    protected function resolveMedia(Request $request, ?string $current, string $field, string $folder): ?string
    {
        if ($request->hasFile($field.'_file')) {
            $this->deleteUploaded($current);

            return $request->file($field.'_file')->store($folder, 'public');
        }

        if ($request->boolean('remove_'.$field)) {
            $this->deleteUploaded($current);

            return null;
        }

        return $request->input($field) ?: $current;
    }

    /**
     * N'efface que les fichiers televerses : ni les URL externes, ni les
     * visuels du depot (public/images/...).
     */
    protected function deleteUploaded(?string $path): void
    {
        if (blank($path)
            || str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, 'images/')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
