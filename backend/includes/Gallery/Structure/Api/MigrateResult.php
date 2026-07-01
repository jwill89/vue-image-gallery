<?php

namespace Gallery\Structure\Api;

use OpenApi\Attributes as OA;

/**
 * Result of migrating one tag into another (POST /tags/{tag_id}/migrate).
 * Doc-only schema.
 */
#[OA\Schema(schema: 'MigrateResult', description: 'Outcome of a tag migration.')]
class MigrateResult
{
    #[OA\Property(type: 'boolean')]
    public bool $migrated = true;
    #[OA\Property(type: 'integer', description: 'The source tag (now deleted).')]
    public int $source_tag_id = 0;
    #[OA\Property(type: 'integer', description: 'The target tag that received the media.')]
    public int $target_tag_id = 0;
}
