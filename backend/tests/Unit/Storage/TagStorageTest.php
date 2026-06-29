<?php

declare(strict_types=1);

namespace Gallery\Tests\Unit\Storage;

use Gallery\Collection\TagCategoryCollection;
use Gallery\Storage\TagCategoryStorage;
use Gallery\Storage\TagStorage;
use Gallery\Structure\Media;
use Gallery\Structure\Tag;
use Gallery\Tests\Support\DatabaseTestCase;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TagStorage::class)]
#[CoversClass(Tag::class)]
final class TagStorageTest extends DatabaseTestCase
{
    private function makeStorage(PDO $db): TagStorage
    {
        return new TagStorage($db, new TagCategoryCollection(new TagCategoryStorage($db)));
    }

    private function makeCategory(PDO $db): int
    {
        $db->exec("INSERT INTO tag_categories (category_name, category_short) VALUES ('General', 'gen')");
        return (int) $db->lastInsertId();
    }

    private function makeMedia(PDO $db, string $name = 'a.jpg', string $hash = 'h1'): Media
    {
        $db->prepare("INSERT INTO media (media_type, file_name, file_time, hash) VALUES ('image', :n, 1, :h)")
            ->execute([':n' => $name, ':h' => $hash]);
        return (new Media())->setMediaId((int) $db->lastInsertId());
    }

    public function testStoreInsertsAndAssignsId(): void
    {
        $db = self::makeDb();
        $storage = $this->makeStorage($db);
        $catId = $this->makeCategory($db);

        $tag = (new Tag())->setCategoryId($catId)->setTagName('forest');
        $id = $storage->store($tag);

        $this->assertGreaterThan(0, $id);
        $this->assertSame($id, $tag->getTagId());
    }

    public function testRetrieveByIdAndByName(): void
    {
        $db = self::makeDb();
        $storage = $this->makeStorage($db);
        $catId = $this->makeCategory($db);
        $tag = (new Tag())->setCategoryId($catId)->setTagName('ocean');
        $storage->store($tag);

        $this->assertSame('ocean', $storage->retrieve($tag->getTagId())->getTagName());
        $this->assertSame($tag->getTagId(), $storage->retrieveByName('ocean')->getTagId());
        $this->assertNull($storage->retrieveByName('missing'));
    }

    public function testRetrieveIdsByNamesIsCaseInsensitiveAndSkipsBlanksAndUnmatched(): void
    {
        $db = self::makeDb();
        $storage = $this->makeStorage($db);
        $catId = $this->makeCategory($db);
        $a = (new Tag())->setCategoryId($catId)->setTagName('forest');
        $storage->store($a);
        $b = (new Tag())->setCategoryId($catId)->setTagName('river');
        $storage->store($b);

        $ids = $storage->retrieveIdsByNames([' FOREST ', 'River', 'unknown', '']);
        sort($ids);
        $expected = [$a->getTagId(), $b->getTagId()];
        sort($expected);

        $this->assertSame($expected, $ids);
        $this->assertSame([], $storage->retrieveIdsByNames([]));
    }

    public function testTagExistsIsCaseInsensitive(): void
    {
        $db = self::makeDb();
        $storage = $this->makeStorage($db);
        $catId = $this->makeCategory($db);
        $storage->store((new Tag())->setCategoryId($catId)->setTagName('sky'));

        $this->assertTrue($storage->tagExists('sky'));
        $this->assertTrue($storage->tagExists('SKY'));
        $this->assertFalse($storage->tagExists('ground'));
    }

    public function testStoreUpdatesExistingTagInPlace(): void
    {
        $db = self::makeDb();
        $storage = $this->makeStorage($db);
        $catId = $this->makeCategory($db);
        $tag = (new Tag())->setCategoryId($catId)->setTagName('old');
        $storage->store($tag);

        $tag->setTagName('renamed');
        $storage->store($tag);

        $this->assertSame('renamed', $storage->retrieve($tag->getTagId())->getTagName());
        $this->assertSame(1, $storage->retrieveTotalTagCount());
    }

    public function testAddRetrieveAndRemoveTagsForMedia(): void
    {
        $db = self::makeDb();
        $storage = $this->makeStorage($db);
        $catId = $this->makeCategory($db);
        $tagA = (new Tag())->setCategoryId($catId)->setTagName('alpha');
        $storage->store($tagA);
        $tagB = (new Tag())->setCategoryId($catId)->setTagName('beta');
        $storage->store($tagB);
        $media = $this->makeMedia($db);

        $this->assertTrue($storage->addTagsToMedia($media, [$tagA->getTagId(), $tagB->getTagId()]));

        $names = array_map(static fn(Tag $t) => $t->getTagName(), $storage->retrieveTagsForMedia($media));
        $this->assertCount(2, $names);
        $this->assertContains('alpha', $names);
        $this->assertContains('beta', $names);

        // INSERT OR IGNORE: re-adding does not create duplicates.
        $storage->addTagsToMedia($media, [$tagA->getTagId()]);
        $this->assertCount(2, $storage->retrieveTagsForMedia($media));

        $this->assertTrue($storage->removeTagFromMedia($media, $tagA));
        $remaining = $storage->retrieveTagsForMedia($media);
        $this->assertCount(1, $remaining);
        $this->assertSame('beta', $remaining[0]->getTagName());
    }

    public function testAddTagsResolvesImplicationsTransitively(): void
    {
        $db = self::makeDb();
        $storage = $this->makeStorage($db);
        $catId = $this->makeCategory($db);
        $cat = (new Tag())->setCategoryId($catId)->setTagName('cat');
        $storage->store($cat);
        $animal = (new Tag())->setCategoryId($catId)->setTagName('animal');
        $storage->store($animal);

        // Applying "cat" should also apply the implied "animal".
        $storage->addImplication($cat->getTagId(), $animal->getTagId());
        $media = $this->makeMedia($db, 'c.jpg', 'hc');
        $storage->addTagsToMedia($media, [$cat->getTagId()]);

        $names = array_map(static fn(Tag $t) => $t->getTagName(), $storage->retrieveTagsForMedia($media));
        $this->assertContains('cat', $names);
        $this->assertContains('animal', $names);
    }

    public function testDeleteRemovesTag(): void
    {
        $db = self::makeDb();
        $storage = $this->makeStorage($db);
        $catId = $this->makeCategory($db);
        $tag = (new Tag())->setCategoryId($catId)->setTagName('temp');
        $storage->store($tag);

        $this->assertTrue($storage->delete($tag));
        $this->assertNull($storage->retrieve($tag->getTagId()));
        $this->assertSame(0, $storage->retrieveTotalTagCount());
    }
}
