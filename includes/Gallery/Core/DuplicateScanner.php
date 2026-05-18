<?php

namespace Gallery\Core;

use Jenssegers\ImageHash\Hash;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\DifferenceHash;
use Gallery\Collection\ImageCollection;

/**
 * DuplicateScanner class
 *
 * Scans the image database for duplicate images using perceptual hashing.
 * This replaces the need to shell out to dupes.php via exec().
 */
class DuplicateScanner
{
    /** Maximum hamming distance to consider images as duplicates */
    private int $maxDistance;

    /** Directory to store reports */
    private string $reportDirectory;

    public function __construct(int $maxDistance = 2, string $reportDirectory = '../dupes/')
    {
        $this->maxDistance = $maxDistance;
        $this->reportDirectory = $reportDirectory;
    }

    /**
     * Run the duplicate scan.
     *
     * @return array{images_compared: int, pairs_checked: int, duplicates_found: int, execution_time_seconds: float, matches: array}
     */
    public function run(): array
    {
        $start_time = microtime(true);

        // Image Comparison Hasher
        $hasher = new ImageHash(new DifferenceHash());

        // Get all images from the database
        $image_collection = new ImageCollection();
        $images_in_database = $image_collection->getAll();

        // Pre-compute all hashes once (avoid repeated Hash::fromBits calls)
        $hashes = [];
        foreach ($images_in_database as $img) {
            try {
                $hashes[$img->getImageId()] = Hash::fromBits($img->getBitsFingerprint());
            } catch (\Exception $e) {
                // Skip images with invalid fingerprints
                continue;
            }
        }

        // Matches Array
        $matches = [];

        // Compare each unique pair only once: i < j
        $image_ids = array_keys($hashes);
        $hash_count = count($image_ids);

        for ($i = 0; $i < $hash_count; $i++) {
            for ($j = $i + 1; $j < $hash_count; $j++) {
                $id1 = $image_ids[$i];
                $id2 = $image_ids[$j];

                $distance = $hasher->distance($hashes[$id1], $hashes[$id2]);

                if ($distance <= $this->maxDistance) {
                    $matches[] = [$id1, $id2, $distance];
                }
            }
        }

        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);
        $pairs_checked = ($hash_count * ($hash_count - 1)) / 2;

        $result = [
            'generated_at' => date('Y-m-d H:i:s'),
            'images_compared' => $hash_count,
            'pairs_checked' => (int) $pairs_checked,
            'duplicates_found' => count($matches),
            'execution_time_seconds' => $execution_time,
            'matches' => $matches,
        ];

        // Save report to file
        $this->saveReport($result);

        return $result;
    }

    /**
     * Save the scan report to a JSON file.
     */
    private function saveReport(array $result): void
    {
        if (empty($result['matches'])) {
            return;
        }

        // Ensure dupes directory exists
        if (!is_dir($this->reportDirectory)) {
            if (!mkdir($this->reportDirectory, 0755, true) && !is_dir($this->reportDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->reportDirectory));
            }
        }

        file_put_contents(
            $this->reportDirectory . 'dupes-' . date('Y-m-d') . '.json',
            json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );
    }
}

