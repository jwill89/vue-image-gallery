<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * The per-file outcome of an upload (POST /media). Fields other than
 * `file_name`/`status` are present depending on the outcome. Doc-only schema.
 */
#[OA\Schema(schema: 'UploadResult', description: 'Per-file upload outcome.')]
class UploadResult
{
    #[OA\Property(type: 'string')]
    public string $file_name = '';
    #[OA\Property(type: 'string', enum: ['success', 'duplicate', 'error'])]
    public string $status = '';
    #[OA\Property(type: 'integer', description: 'New media ID (on success).')]
    public int $id = 0;
    #[OA\Property(type: 'integer', description: 'Existing media ID (on duplicate).')]
    public int $existing_id = 0;
    #[OA\Property(type: 'string', description: 'MD5 hash of the file.')]
    public string $hash = '';
    #[OA\Property(type: 'string', description: 'Failure reason (on error).')]
    public string $message = '';
    #[OA\Property(type: 'boolean', description: 'Whether Danbooru tags were found.')]
    public bool $tags_found = false;
    #[OA\Property(type: 'integer', description: 'Number of tags applied from Danbooru.')]
    public int $tags_applied = 0;
    #[OA\Property(type: 'string', description: 'Danbooru lookup method used (md5/iqdb).')]
    public string $tags_method = '';
}
