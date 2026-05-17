<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitialSchema extends AbstractMigration
{
    public function change(): void
    {
        // Images table
        $this->table('images', ['id' => false, 'primary_key' => ['image_id']])
            ->addColumn('image_id', 'integer', ['identity' => true])
            ->addColumn('file_name', 'text', ['null' => false])
            ->addColumn('file_time', 'integer', ['null' => false])
            ->addColumn('hash', 'text', ['null' => false])
            ->addColumn('bits_fingerprint', 'text', ['null' => false])
            ->addIndex(['file_name'], ['unique' => true])
            ->addIndex(['hash'])
            ->create();

        // Videos table
        $this->table('videos', ['id' => false, 'primary_key' => ['video_id']])
            ->addColumn('video_id', 'integer', ['identity' => true])
            ->addColumn('file_name', 'text', ['null' => false])
            ->addColumn('file_time', 'integer', ['null' => false])
            ->addColumn('hash', 'text', ['null' => false])
            ->addIndex(['file_name'], ['unique' => true])
            ->addIndex(['hash'])
            ->create();

        // Tag categories table
        $this->table('tag_categories', ['id' => false, 'primary_key' => ['category_id']])
            ->addColumn('category_id', 'integer', ['identity' => true])
            ->addColumn('category_name', 'text', ['null' => false])
            ->addColumn('category_short', 'text', ['null' => false])
            ->addIndex(['category_name'], ['unique' => true])
            ->addIndex(['category_short'], ['unique' => true])
            ->create();

        // Tags table
        $this->table('tags', ['id' => false, 'primary_key' => ['tag_id']])
            ->addColumn('tag_id', 'integer', ['identity' => true])
            ->addColumn('category_id', 'integer', ['null' => false, 'default' => 1])
            ->addColumn('tag_name', 'text', ['null' => false])
            ->addIndex(['tag_name'], ['unique' => true])
            ->addForeignKey('category_id', 'tag_categories', 'category_id')
            ->create();

        // Image tags junction table
        $this->table('image_tags', ['id' => false, 'primary_key' => ['image_id', 'tag_id']])
            ->addColumn('image_id', 'integer', ['null' => false])
            ->addColumn('tag_id', 'integer', ['null' => false])
            ->addForeignKey('image_id', 'images', 'image_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('tag_id', 'tags', 'tag_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Video tags junction table
        $this->table('video_tags', ['id' => false, 'primary_key' => ['video_id', 'tag_id']])
            ->addColumn('video_id', 'integer', ['null' => false])
            ->addColumn('tag_id', 'integer', ['null' => false])
            ->addForeignKey('video_id', 'videos', 'video_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('tag_id', 'tags', 'tag_id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        // Rate limits table
        $this->table('rate_limits', ['id' => false])
            ->addColumn('ip', 'text', ['null' => false])
            ->addColumn('requested_at', 'integer', ['null' => false])
            ->addIndex(['ip', 'requested_at'])
            ->create();

        // Auth tokens table
        $this->table('auth_tokens', ['id' => false, 'primary_key' => ['token']])
            ->addColumn('token', 'text', ['null' => false])
            ->addColumn('created_at', 'integer', ['null' => false])
            ->create();

        // Seed default tag categories
        $this->table('tag_categories')->insert([
            ['category_id' => 1, 'category_name' => 'General', 'category_short' => 'g'],
            ['category_id' => 2, 'category_name' => 'Artist', 'category_short' => 'a'],
            ['category_id' => 3, 'category_name' => 'Character', 'category_short' => 'c'],
            ['category_id' => 4, 'category_name' => 'Source', 'category_short' => 's'],
            ['category_id' => 5, 'category_name' => 'Personal List', 'category_short' => 'p'],
        ])->saveData();
    }
}
