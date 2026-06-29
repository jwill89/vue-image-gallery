<?php

declare(strict_types=1);

namespace Gallery\Tests\Unit\Storage;

use Gallery\Storage\TagCategoryStorage;
use Gallery\Structure\TagCategory;
use Gallery\Tests\Support\DatabaseTestCase;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TagCategoryStorage::class)]
#[CoversClass(TagCategory::class)]
final class TagCategoryStorageTest extends DatabaseTestCase
{
    private function newCategory(string $name, string $short, int $sort = 0, string $color = 'white'): TagCategory
    {
        return (new TagCategory())
            ->setCategoryName($name)
            ->setCategoryShort($short)
            ->setColor($color)
            ->setSortOrder($sort);
    }

    public function testStoreInsertsAndAssignsId(): void
    {
        $storage = new TagCategoryStorage(self::makeDb());

        $cat = $this->newCategory('Character', 'char', 1, 'teal');
        $id = $storage->store($cat);

        $this->assertGreaterThan(0, $id);
        $this->assertSame($id, $cat->getCategoryId());
    }

    public function testRetrieveByIdReturnsStoredCategory(): void
    {
        $storage = new TagCategoryStorage(self::makeDb());
        $id = $storage->store($this->newCategory('Meta', 'meta', 0, 'purple'));

        $fetched = $storage->retrieve($id);

        $this->assertInstanceOf(TagCategory::class, $fetched);
        $this->assertSame('Meta', $fetched->getCategoryName());
        $this->assertSame('purple', $fetched->getColor());
    }

    public function testRetrieveUnknownIdReturnsNull(): void
    {
        $storage = new TagCategoryStorage(self::makeDb());
        $this->assertNull($storage->retrieve(999));
    }

    public function testRetrieveAllReturnsCategoriesOrderedBySortOrder(): void
    {
        $storage = new TagCategoryStorage(self::makeDb());
        $storage->store($this->newCategory('Bravo', 'b', 2));
        $storage->store($this->newCategory('Alpha', 'a', 1));

        $all = $storage->retrieve();

        $this->assertCount(2, $all);
        $this->assertSame('Alpha', $all[0]->getCategoryName());
        $this->assertSame('Bravo', $all[1]->getCategoryName());
    }

    public function testStoreUpdatesExistingRowInPlace(): void
    {
        $db = self::makeDb();
        $storage = new TagCategoryStorage($db);
        $cat = $this->newCategory('Old', 'old');
        $id = $storage->store($cat);

        $cat->setCategoryName('New')->setColor('amber');
        $storage->store($cat);

        $fetched = $storage->retrieve($id);
        $this->assertSame('New', $fetched->getCategoryName());
        $this->assertSame('amber', $fetched->getColor());
        $this->assertCount(1, $storage->retrieve());
    }

    public function testRetrieveByShortcode(): void
    {
        $storage = new TagCategoryStorage(self::makeDb());
        $storage->store($this->newCategory('Artist', 'art'));

        $this->assertSame('Artist', $storage->retrieveByShortcode('art')->getCategoryName());
        $this->assertNull($storage->retrieveByShortcode('nope'));
    }

    public function testRetrieveAllIds(): void
    {
        $storage = new TagCategoryStorage(self::makeDb());
        $id1 = $storage->store($this->newCategory('One', 'o'));
        $id2 = $storage->store($this->newCategory('Two', 't'));

        $this->assertSame([$id1, $id2], $storage->retrieveAllIds());
    }

    public function testCountTagsInCategory(): void
    {
        $db = self::makeDb();
        $storage = new TagCategoryStorage($db);
        $catId = $storage->store($this->newCategory('Cat', 'c'));

        $insert = $db->prepare('INSERT INTO tags (category_id, tag_name) VALUES (:c, :n)');
        $insert->execute([':c' => $catId, ':n' => 'tag1']);
        $insert->execute([':c' => $catId, ':n' => 'tag2']);

        $this->assertSame(2, $storage->countTagsInCategory($catId));
        $this->assertSame(0, $storage->countTagsInCategory(999));
    }
}
