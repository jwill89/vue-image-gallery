<?php

namespace Gallery\Collection;

use Imagick;
use ImagickException;
use OutOfBoundsException;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\DifferenceHash;
use Gallery\Core\Configuration;
use Gallery\Storage\ImageStorage;
use Gallery\Structure\Image;

/**
 * ImageCollection class
 * This class is responsible for managing a collection of images and interacting with the database and filesystem.
 * It provides methods to retrieve, save, delete images, and create thumbnails.
 */
class ImageCollection
{
    // Directory where Images are stored
    public const string IMAGE_DIRECTORY = 'images/';
    public const string IMAGE_DIRECTORY_FULL = 'images/full/';
    public const string IMAGE_DIRECTORY_THUMBNAILS = 'images/thumbs/';

    // Image Database Storage Object
    private ImageStorage $storage;

    /**
     * ImageCollection constructor.
     * Initializes the ImageStorage object.
     */
    public function __construct()
    {
        if (!isset($this->storage)) {
            $this->storage = new ImageStorage();
        }
    }

    /**
     * Gets an image based on supplied image ID.
     *
     * @param int $image_id The ID of the image to retrieve.
     *
     * @return Image|null The Image object corresponding to the supplied ID, or null if not found.
     */
    public function get(int $image_id): ?Image
    {
        return $this->storage->retrieve($image_id);
    }

    /**
     * Gets all images.
     *
     * @return Image[] An array of Image objects.
     */
    public function getAll(): array
    {
        return $this->storage->retrieve();
    }

    /**
     * Gets a lightweight summary of all images (only ID, file_name, and hash).
     * Used by cron.php for orphan detection and deduplication without loading full objects.
     *
     * @return array[] Array of associative arrays with image_id, file_name, and hash.
     */
    public function getAllSummary(): array
    {
        return $this->storage->retrieveSummary();
    }

    /**
     * Gets a number of images based on the supplied page number.
     *
     * @param int $page_number The page number to retrieve.
     * @param int|null $items_per_page Optional. The number of items per page. Defaults to the number set in the Configuration.
     *
     * @return Image[] An array of Image objects for the specified page.
     */
    public function getForPage(int $page_number, ?int $items_per_page = null): array
    {
        // Default to the number set in the Configuration if not provided
        $items_per_page = $items_per_page ?? Configuration::DEFAULT_PER_PAGE;

        return $this->storage->retrieveForPage($page_number, $items_per_page);
    }

    /**
     * Gets a number of images based on the supplied page number and tag ID.
     *
     * @param array $tag_ids - The tag IDs to filter images by.
     * @param int $page_number - The page number to retrieve.
     * @param int|null $items_per_page Optional. The number of items per page. Defaults to the number set in the Configuration.
     *
     * @return Image[] An array of Image objects for the specified page and tags.
     */
    public function getWithTags(array $tag_ids, int $page_number, ?int $items_per_page = null): array
    {
        // Default to the number set in the Configuration if not provided
        $items_per_page = $items_per_page ?? Configuration::DEFAULT_PER_PAGE;

        return $this->storage->retrieveWithTags($tag_ids, $page_number, $items_per_page);
    }

    /**
     * Gets the total number of images in the database.
     *
     * @return int The total number of images.
     */
    public function totalImages(): int
    {
        return $this->storage->retrieveTotalImageCount();
    }

    /**
     * Gets the total number of images with the supplied tags.
     *
     * @param array $tag_ids The tag IDs to filter images by.
     *
     * @return int The total number of images with the specified tags.
     */
    public function totalImagesWithTags(array $tag_ids): int
    {
        return $this->storage->retrieveTotalImageWithTagsCount($tag_ids);
    }

    /**
     * Creates a thumbnail for the supplied image object.
     * The thumbnail is resized to a maximum width or height of 200 pixels, maintaining the aspect ratio.
     *
     * @param Image $image_obj The image object for which to create a thumbnail.
     *
     * @throws ImagickException
     */
    public function createThumbnail(Image $image_obj): void
    {
        // Max Width/Height of Thumbnail
        $max_size = 200;

        // Start New Thumbnail
        $image = new Imagick(self::IMAGE_DIRECTORY . $image_obj->getFileName());

        // If the image is wider
        if ($image->getImageHeight() <= $image->getImageWidth()) {
            // Resize image using the lanczos resampling algorithm based on width
            $image->resizeImage($max_size, 0, Imagick::FILTER_LANCZOS, 1);

            // If the image is taller
        } else {
            // Resize image using the lanczos resampling algorithm based on height
            $image->resizeImage(0, $max_size, Imagick::FILTER_LANCZOS, 1);
        }

        // Set to use jpeg compression
        $image->setImageCompression(Imagick::COMPRESSION_JPEG);

        // Set compression level (1 lowest quality, 100 highest quality)
        $image->setImageCompressionQuality(75);

        // Strip out unneeded metadata
        $image->stripImage();

        // Start Thumbnail Write
        $image_file_name = pathinfo($image->getImageFilename());

        // Extension fis the same as the original
        $ext = $image_file_name['extension'];

        // Write Thumbnail
        $image->writeImage(self::IMAGE_DIRECTORY_THUMBNAILS . $image_file_name['filename'] . '.' . $ext);

        $image->clear();
    }

    /**
     * Generates a fingerprint for the image using the DifferenceHash algorithm and stores as bits.
     *
     * @param Image $image_obj The image object for which to create a fingerprint.
     *
     * @return string The generated fingerprint in bit format.
     */
    public function createFingerprint(Image $image_obj): string
    {
        // Get the Hasher based on DifferenceHash
        $hasher = new ImageHash(new DifferenceHash());

        // Generate the Hash Fingerprint
        $hash = $hasher->hash(self::IMAGE_DIRECTORY . $image_obj->getFileName());

        // Store the fingerprint in Bits format
        $image_obj->setBitsFingerprint($hash->toBits());

        return $hash;
    }

    /**
     * Saves an image to the database and calls the function to generate a fingerprint and thumbnail.
     *
     * @param Image $image The image object to save.
     *
     * @return int The ID of the newly saved image.
     * @throws ImagickException
     */
    public function save(Image $image): int
    {
        // Check for fingerprint and generate if not set
        if (empty($image->getBitsFingerprint())) {
            $this->createFingerprint($image);
        }

        // Save the image to the database
        $image_id = $this->storage->store($image);

        // If we have an ID, we can assume it was successful and generate a thumbnail
        if ($image_id > 0) {
            // Set the ID of the image object to the ID returned from the database
            $image->setImageId($image_id);

            // Create a thumbnail for the image
            $this->createThumbnail($image);
        }

        return $image->getImageId();
    }

    /**
     * Deletes an image from the database and the filesystem.
     *
     * @param Image $image The image object to delete.
     *
     * @return bool True if the image was successfully deleted, false otherwise.
     */
    public function delete(Image $image): bool
    {
        // Delete the image from the database
        $success = $this->storage->delete($image);

        // Delete the image and thumbnail from the filesystem
        if ($success) {
            $image_path = self::IMAGE_DIRECTORY . $image->getFileName();
            $thumbnail_path = self::IMAGE_DIRECTORY_THUMBNAILS . $image->getFileName();

            // Delete the image file
            if (file_exists($image_path)) {
                unlink($image_path);
            }

            // Delete the thumbnail file
            if (file_exists($thumbnail_path)) {
                unlink($thumbnail_path);
            }
        } else {
            throw new OutOfBoundsException('Image not found in database.');
        }

        return true;
    }
}
