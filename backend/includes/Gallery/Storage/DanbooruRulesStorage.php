<?php

namespace Gallery\Storage;

use PDO;
use Gallery\Core\DatabaseConnection;

/**
 * DanbooruRulesStorage Class
 * Manages danbooru_category_map and danbooru_tag_map tables.
 */
class DanbooruRulesStorage
{
    private const string CATEGORY_MAP_TABLE = 'danbooru_category_map';
    private const string TAG_MAP_TABLE = 'danbooru_tag_map';

    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance();
    }

    // ========================================================================
    // Category Map
    // ========================================================================

    /**
     * Retrieve all Danbooru category mappings (joined with gallery category name).
     */
    public function retrieveCategoryMappings(): array
    {
        $sql = "SELECT m.danbooru_category_id, m.danbooru_category_name, m.gallery_category_id,
                       c.category_name AS gallery_category_name
                FROM " . self::CATEGORY_MAP_TABLE . " m
                LEFT JOIN tag_categories c ON c.category_id = m.gallery_category_id
                ORDER BY m.danbooru_category_id";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add or replace a category mapping.
     */
    public function storeCategoryMapping(int $danbooruCategoryId, string $danbooruCategoryName, int $galleryCategoryId): bool
    {
        $sql = "INSERT OR REPLACE INTO " . self::CATEGORY_MAP_TABLE
             . " (danbooru_category_id, danbooru_category_name, gallery_category_id)"
             . " VALUES (:dcid, :dcname, :gcid)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':dcid' => $danbooruCategoryId,
            ':dcname' => $danbooruCategoryName,
            ':gcid' => $galleryCategoryId,
        ]);
    }

    /**
     * Delete a category mapping.
     */
    public function deleteCategoryMapping(int $danbooruCategoryId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM " . self::CATEGORY_MAP_TABLE . " WHERE danbooru_category_id = :dcid");
        return $stmt->execute([':dcid' => $danbooruCategoryId]);
    }

    // ========================================================================
    // Tag Name Map
    // ========================================================================

    /**
     * Retrieve all tag name mappings.
     */
    public function retrieveTagMappings(): array
    {
        $sql = "SELECT id, danbooru_tag, gallery_tag FROM " . self::TAG_MAP_TABLE . " ORDER BY danbooru_tag";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a tag name mapping.
     */
    public function storeTagMapping(string $danbooruTag, string $galleryTag): int
    {
        $sql = "INSERT INTO " . self::TAG_MAP_TABLE . " (danbooru_tag, gallery_tag) VALUES (:dt, :gt)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':dt' => $danbooruTag, ':gt' => $galleryTag]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a tag name mapping.
     */
    public function updateTagMapping(int $id, string $danbooruTag, string $galleryTag): bool
    {
        $sql = "UPDATE " . self::TAG_MAP_TABLE . " SET danbooru_tag = :dt, gallery_tag = :gt WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':dt' => $danbooruTag, ':gt' => $galleryTag, ':id' => $id]);
    }

    /**
     * Delete a tag name mapping.
     */
    public function deleteTagMapping(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM " . self::TAG_MAP_TABLE . " WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Check if a danbooru tag mapping already exists (optionally excluding an ID).
     */
    public function tagMappingExists(string $danbooruTag, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM " . self::TAG_MAP_TABLE . " WHERE danbooru_tag = :dt AND id != :eid"
        );
        $stmt->execute([':dt' => $danbooruTag, ':eid' => $excludeId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Build the category map as danbooru_category_id => gallery_category_id.
     */
    public function getCategoryMapAsArray(): array
    {
        $stmt = $this->db->query(
            "SELECT danbooru_category_id, gallery_category_id FROM " . self::CATEGORY_MAP_TABLE
        );
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int) $row['danbooru_category_id']] = (int) $row['gallery_category_id'];
        }
        return $map;
    }

    /**
     * Build the full category map as danbooru_category_id => ['gallery_category_id' => int, 'field' => string].
     * The 'field' key is the Danbooru API field name derived from the category name (e.g. "tag_string_general").
     */
    public function getCategoryMapWithFields(): array
    {
        $stmt = $this->db->query(
            "SELECT danbooru_category_id, danbooru_category_name, gallery_category_id FROM " . self::CATEGORY_MAP_TABLE
        );
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int) $row['danbooru_category_id']] = [
                'gallery_category_id' => (int) $row['gallery_category_id'],
                'field' => 'tag_string_' . strtolower($row['danbooru_category_name']),
            ];
        }
        return $map;
    }

    /**
     * Build the tag name map as danbooru_tag => gallery_tag.
     */
    public function getTagMapAsArray(): array
    {
        $stmt = $this->db->query("SELECT danbooru_tag, gallery_tag FROM " . self::TAG_MAP_TABLE);
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[$row['danbooru_tag']] = $row['gallery_tag'];
        }
        return $map;
    }
}
