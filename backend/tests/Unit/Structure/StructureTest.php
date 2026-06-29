<?php

declare(strict_types=1);

namespace Gallery\Tests\Unit\Structure;

use Gallery\Structure\AbstractStructure;
use Gallery\Structure\Media;
use Gallery\Structure\Tag;
use Gallery\Structure\TagCategory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractStructure::class)]
#[CoversClass(Media::class)]
#[CoversClass(Tag::class)]
#[CoversClass(TagCategory::class)]
final class StructureTest extends TestCase
{
    public function testMediaConstructorPopulatesProperties(): void
    {
        $media = new Media([
            'media_id'   => 7,
            'media_type' => 'video',
            'file_name'  => 'clip.mp4',
            'file_time'  => 1700000000,
            'hash'       => 'abc123',
            'width'      => 1920,
            'height'     => 1080,
            'duration'   => 12.5,
            'file_size'  => 2048,
        ]);

        $this->assertSame(7, $media->getMediaId());
        $this->assertSame('video', $media->getMediaType());
        $this->assertSame('clip.mp4', $media->getFileName());
        $this->assertSame(1700000000, $media->getFileTime());
        $this->assertSame('abc123', $media->getHash());
        $this->assertSame(1920, $media->getWidth());
        $this->assertSame(1080, $media->getHeight());
        $this->assertSame(12.5, $media->getDuration());
        $this->assertSame(2048, $media->getFileSize());
        $this->assertTrue($media->isVideo());
        $this->assertFalse($media->isImage());
    }

    public function testMediaDefaults(): void
    {
        $media = new Media();

        $this->assertSame(0, $media->getMediaId());
        $this->assertSame('image', $media->getMediaType());
        $this->assertSame('', $media->getFileName());
        $this->assertSame(0.0, $media->getDuration());
        $this->assertSame('', $media->getBitsFingerprint());
        $this->assertTrue($media->isImage());
        $this->assertFalse($media->isVideo());
    }

    public function testMediaFluentSetters(): void
    {
        $media = (new Media())
            ->setMediaType('image')
            ->setFileName('pic.png')
            ->setHash('deadbeef')
            ->setWidth(800)
            ->setHeight(600)
            ->setFileSize(1234)
            ->setBitsFingerprint('1010');

        $this->assertSame('pic.png', $media->getFileName());
        $this->assertSame('deadbeef', $media->getHash());
        $this->assertSame(800, $media->getWidth());
        $this->assertSame(600, $media->getHeight());
        $this->assertSame(1234, $media->getFileSize());
        $this->assertSame('1010', $media->getBitsFingerprint());
    }

    public function testUnknownConstructorKeysAreIgnored(): void
    {
        $tag = new Tag(['tag_id' => 5, 'does_not_exist' => 'x']);

        $this->assertSame(5, $tag->getTagId());
        $this->assertObjectNotHasProperty('does_not_exist', $tag);
    }

    public function testTagAccessors(): void
    {
        $tag = (new Tag())->setTagId(3)->setCategoryId(2)->setTagName('forest');

        $this->assertSame(3, $tag->getTagId());
        $this->assertSame(2, $tag->getCategoryId());
        $this->assertSame('forest', $tag->getTagName());
    }

    public function testTagCategoryDefaultsAndAccessors(): void
    {
        $cat = new TagCategory();
        $this->assertSame('white', $cat->getColor());
        $this->assertSame(0, $cat->getSortOrder());

        $cat->setCategoryName('Character')
            ->setCategoryShort('char')
            ->setColor('teal')
            ->setSortOrder(2)
            ->setDescription('People');

        $this->assertSame('Character', $cat->getCategoryName());
        $this->assertSame('char', $cat->getCategoryShort());
        $this->assertSame('teal', $cat->getColor());
        $this->assertSame(2, $cat->getSortOrder());
        $this->assertSame('People', $cat->getDescription());
    }

    public function testJsonSerializeReturnsAllDeclaredProperties(): void
    {
        $cat = new TagCategory(['category_id' => 1, 'category_name' => 'Meta', 'color' => 'purple']);
        $data = $cat->jsonSerialize();

        $this->assertSame(
            ['category_id', 'category_name', 'category_short', 'color', 'description', 'sort_order'],
            array_keys($data)
        );
        $this->assertSame(1, $data['category_id']);
        $this->assertSame('Meta', $data['category_name']);
        $this->assertSame('purple', $data['color']);
    }

    public function testJsonEncodeRoundTrip(): void
    {
        $media = new Media(['media_id' => 9, 'file_name' => 'a.jpg']);
        $decoded = json_decode(json_encode($media, JSON_THROW_ON_ERROR), true);

        $this->assertSame(9, $decoded['media_id']);
        $this->assertSame('a.jpg', $decoded['file_name']);
        $this->assertArrayHasKey('bits_fingerprint', $decoded);
    }

    public function testSetPropertiesUpdatesExistingInstance(): void
    {
        $tag = new Tag(['tag_id' => 1, 'tag_name' => 'old']);
        $tag->setProperties(['tag_name' => 'new', 'category_id' => 4]);

        $this->assertSame('new', $tag->getTagName());
        $this->assertSame(4, $tag->getCategoryId());
        $this->assertSame(1, $tag->getTagId());
    }
}
