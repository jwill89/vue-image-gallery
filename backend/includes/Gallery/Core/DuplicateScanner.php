<?php

namespace Gallery\Core;

use PDO;
use Jenssegers\ImageHash\Hash;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;
use Gallery\Collection\MediaCollection;

/**
 * DuplicateScanner class
 *
 * Scans the media database for duplicate images using perceptual hashing.
 * Uses Locality-Sensitive Hashing (LSH) for efficient candidate finding,
 * then a second-pass SSIM calculation for verification.
 * Only compares image-type media (videos don't have fingerprints).
 */
class DuplicateScanner
{
    private int $maxDistance;
    private string $reportDirectory;

    /** Number of bits per band for LSH. 8 bits = 8 bands over a 64-bit hash. */
    private int $bandBits;

    public function __construct(int $maxDistance = 2, string $reportDirectory = 'dupes/', int $bandBits = 8)
    {
        $this->maxDistance = $maxDistance;
        $this->reportDirectory = $reportDirectory;
        $this->bandBits = $bandBits;
    }

    /**
     * Run the duplicate scan on image media items.
     */
    public function run(): array
    {
        // Temporarily raise limits for the scan — this is a heavy batch operation
        $previousMemoryLimit = ini_get('memory_limit');
        $previousTimeLimit = ini_get('max_execution_time');
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes

        $start_time = microtime(true);

        // Load dismissed pairs from DB
        $dismissed = $this->loadDismissedPairs();

        // ──────────────────────────────────────────────
        // Load only the fields we need — no full Media objects
        // ──────────────────────────────────────────────
        $db = DatabaseConnection::getInstance();
        $stmt = $db->query(
            "SELECT media_id, file_name, bits_fingerprint
             FROM media
             WHERE media_type = 'image' AND bits_fingerprint != ''
             ORDER BY media_id"
        );

        $bitStrings = [];   // media_id => bit string
        $fileNames  = [];   // media_id => file_name

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id   = (int) $row['media_id'];
            $bits = $row['bits_fingerprint'];

            if (empty($bits)) {
                continue;
            }

            $bitStrings[$id] = $bits;
            $fileNames[$id]  = $row['file_name'];
        }
        unset($stmt);

        $hash_count = count($bitStrings);

        // ──────────────────────────────────────────────
        // Phase 1: LSH — band the fingerprints to find candidate pairs
        // ──────────────────────────────────────────────
        $candidates = $this->lshCandidates($bitStrings);
        $lsh_candidate_count = count($candidates);

        // ──────────────────────────────────────────────
        // Phase 2: Hamming distance filter on candidates
        // Build Hash objects only for IDs that appear in candidates
        // ──────────────────────────────────────────────
        $neededIds = [];
        foreach ($candidates as [$id1, $id2]) {
            $neededIds[$id1] = true;
            $neededIds[$id2] = true;
        }

        $hashes = [];
        foreach ($neededIds as $id => $_) {
            if (isset($bitStrings[$id])) {
                try {
                    $hashes[$id] = Hash::fromBits($bitStrings[$id]);
                } catch (\Exception $e) {
                    // skip
                }
            }
        }
        unset($neededIds, $bitStrings); // free the big arrays

        $hasher = new ImageHash(new PerceptualHash());
        $verified = [];

        foreach ($candidates as [$id1, $id2]) {
            // Skip dismissed pairs
            $pairKey = $this->pairKey($id1, $id2);
            if (isset($dismissed[$pairKey])) {
                continue;
            }

            if (!isset($hashes[$id1], $hashes[$id2])) {
                continue;
            }

            $distance = $hasher->distance($hashes[$id1], $hashes[$id2]);

            if ($distance <= $this->maxDistance) {
                $verified[] = [$id1, $id2, $distance];
            }
        }
        unset($candidates, $hashes, $dismissed); // free before SSIM phase

        // ──────────────────────────────────────────────
        // Phase 3: SSIM second pass on verified candidates
        // Use @2x thumbnails (already small WebP files) instead of
        // full-size originals to avoid GD memory spikes.
        // ──────────────────────────────────────────────
        $matches = [];
        $thumbDir = MediaCollection::getThumbDirectory();

        foreach ($verified as [$id1, $id2, $distance]) {
            $fn1 = $fileNames[$id1] ?? null;
            $fn2 = $fileNames[$id2] ?? null;

            $ssim = null;
            if ($fn1 && $fn2) {
                $base1 = pathinfo($fn1, PATHINFO_FILENAME);
                $base2 = pathinfo($fn2, PATHINFO_FILENAME);
                $thumb1 = $thumbDir . $base1 . '@2x.webp';
                $thumb2 = $thumbDir . $base2 . '@2x.webp';
                $ssim = $this->calculateSSIM($thumb1, $thumb2);
            }

            $matches[] = [$id1, $id2, $distance, $ssim];
        }
        unset($verified, $fileNames);

        // Sort by SSIM descending (most similar first), then by distance ascending
        usort($matches, function ($a, $b) {
            if ($a[3] !== null && $b[3] !== null) {
                $ssimCmp = $b[3] <=> $a[3];
                if ($ssimCmp !== 0) return $ssimCmp;
            }
            return $a[2] <=> $b[2];
        });

        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);

        $result = [
            'generated_at' => date('Y-m-d H:i:s'),
            'images_compared' => $hash_count,
            'lsh_candidates' => $lsh_candidate_count,
            'duplicates_found' => count($matches),
            'execution_time_seconds' => $execution_time,
            'matches' => $matches,
        ];

        $this->saveReport($result);

        // Restore original limits
        ini_set('memory_limit', $previousMemoryLimit);
        set_time_limit((int) $previousTimeLimit);

        return $result;
    }

    /**
     * LSH banding: split each fingerprint into bands of $bandBits bits,
     * and hash each band. Pairs that share at least one band bucket are candidates.
     *
     * For a 64-bit hash with bandBits=4, we get 16 bands.
     * This reduces comparison from O(n^2) to roughly O(n * bucket_size).
     *
     * @param array<int, string> $bitStrings media_id => bit string
     * @return array<array{int, int}> Unique candidate pairs [id1, id2] where id1 < id2
     */
    private function lshCandidates(array $bitStrings): array
    {
        // Determine band count from the first fingerprint length
        $sampleBits = reset($bitStrings);
        if ($sampleBits === false) {
            return [];
        }
        $totalBits = strlen($sampleBits);
        $numBands = intdiv($totalBits, $this->bandBits);

        // Collect candidate pairs directly — process one band at a time
        // to avoid keeping the full bucket structure in memory
        $seen = [];

        for ($band = 0; $band < $numBands; $band++) {
            $offset = $band * $this->bandBits;

            // Build buckets for this single band
            $buckets = [];
            foreach ($bitStrings as $mediaId => $bits) {
                $segment = substr($bits, $offset, $this->bandBits);
                $buckets[$segment][] = $mediaId;
            }

            // Extract pairs from this band's buckets
            // Skip oversized buckets (>100 items) — they indicate a common
            // hash segment and would generate O(n^2) spurious candidates
            foreach ($buckets as $ids) {
                $count = count($ids);
                if ($count < 2 || $count > 100) continue;

                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $a = min($ids[$i], $ids[$j]);
                        $b = max($ids[$i], $ids[$j]);
                        $key = ($a << 32) | $b; // integer key — cheaper than string concat
                        $seen[$key] = ($a << 32) | $b;
                    }
                }
            }
            // $buckets for this band is freed here
        }

        // Convert to pair arrays
        $candidates = [];
        foreach ($seen as $packed) {
            $candidates[] = [$packed >> 32, $packed & 0xFFFFFFFF];
        }
        unset($seen);

        return $candidates;
    }

    /**
     * Calculate SSIM between two images.
     * Uses GD library to compare luminance channels at a reduced resolution
     * for performance. Returns a value between 0.0 (totally different) and
     * 1.0 (identical).
     */
    private function calculateSSIM(string $path1, string $path2): ?float
    {
        if (!file_exists($path1) || !file_exists($path2)) {
            return null;
        }

        try {
            $img1 = $this->loadImage($path1);
            if ($img1 === null) {
                return null;
            }

            $img2 = $this->loadImage($path2);
            if ($img2 === null) {
                imagedestroy($img1);
                return null;
            }

            // Resize both to a common small size for fast SSIM comparison
            $size = 64;
            $r1 = imagecreatetruecolor($size, $size);
            $r2 = imagecreatetruecolor($size, $size);

            imagecopyresampled($r1, $img1, 0, 0, 0, 0, $size, $size, imagesx($img1), imagesy($img1));
            imagedestroy($img1); // free immediately

            imagecopyresampled($r2, $img2, 0, 0, 0, 0, $size, $size, imagesx($img2), imagesy($img2));
            imagedestroy($img2); // free immediately

            // Extract luminance arrays
            $lum1 = $this->extractLuminance($r1, $size);
            imagedestroy($r1);

            $lum2 = $this->extractLuminance($r2, $size);
            imagedestroy($r2);

            return $this->ssimFromLuminance($lum1, $lum2);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Load an image file into a GD resource.
     */
    private function loadImage(string $path): ?\GdImage
    {
        $info = @getimagesize($path);
        if ($info === false) return null;

        $type = $info[2];

        // AVIF support: constant exists in PHP 8.1+ but GD may lack the function
        if (defined('IMAGETYPE_AVIF') && $type === IMAGETYPE_AVIF) {
            return function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : null;
        }

        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_BMP  => @imagecreatefrombmp($path),
            default        => null,
        };
    }

    /**
     * Extract luminance values from a square GD image.
     *
     * @return float[] Flat array of luminance values [0..255]
     */
    private function extractLuminance(\GdImage $img, int $size): array
    {
        $lum = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                // ITU-R BT.601 luminance
                $lum[] = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            }
        }
        return $lum;
    }

    /**
     * Compute SSIM from two luminance arrays.
     * Uses 8x8 sliding windows with the standard SSIM formula.
     *
     * Constants: C1 = (0.01*255)^2, C2 = (0.03*255)^2
     */
    private function ssimFromLuminance(array $lum1, array $lum2): float
    {
        $size = (int) sqrt(count($lum1));
        $windowSize = 8;
        $c1 = 6.5025;   // (0.01 * 255)^2
        $c2 = 58.5225;  // (0.03 * 255)^2

        $ssimSum = 0.0;
        $windowCount = 0;

        for ($y = 0; $y <= $size - $windowSize; $y += $windowSize) {
            for ($x = 0; $x <= $size - $windowSize; $x += $windowSize) {
                $w1 = [];
                $w2 = [];

                for ($wy = 0; $wy < $windowSize; $wy++) {
                    for ($wx = 0; $wx < $windowSize; $wx++) {
                        $idx = ($y + $wy) * $size + ($x + $wx);
                        $w1[] = $lum1[$idx];
                        $w2[] = $lum2[$idx];
                    }
                }

                $n = count($w1);
                $mu1 = array_sum($w1) / $n;
                $mu2 = array_sum($w2) / $n;

                $sigma1Sq = 0.0;
                $sigma2Sq = 0.0;
                $sigma12 = 0.0;

                for ($i = 0; $i < $n; $i++) {
                    $d1 = $w1[$i] - $mu1;
                    $d2 = $w2[$i] - $mu2;
                    $sigma1Sq += $d1 * $d1;
                    $sigma2Sq += $d2 * $d2;
                    $sigma12 += $d1 * $d2;
                }

                $sigma1Sq /= $n;
                $sigma2Sq /= $n;
                $sigma12 /= $n;

                $numerator = (2.0 * $mu1 * $mu2 + $c1) * (2.0 * $sigma12 + $c2);
                $denominator = ($mu1 * $mu1 + $mu2 * $mu2 + $c1) * ($sigma1Sq + $sigma2Sq + $c2);

                $ssimSum += $numerator / $denominator;
                $windowCount++;
            }
        }

        return $windowCount > 0 ? round($ssimSum / $windowCount, 4) : 0.0;
    }

    /**
     * Load all dismissed duplicate pairs from the database.
     *
     * @return array<string, true> Keys are "smallerId:largerId"
     */
    private function loadDismissedPairs(): array
    {
        $dismissed = [];

        try {
            $db = DatabaseConnection::getInstance();
            $stmt = $db->query('SELECT media_id_1, media_id_2 FROM dismissed_duplicates');

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = $this->pairKey((int)$row['media_id_1'], (int)$row['media_id_2']);
                $dismissed[$key] = true;
            }
        } catch (\Throwable $e) {
            // Table may not exist yet — treat as no dismissed pairs
        }

        return $dismissed;
    }

    /**
     * Create a canonical key for a pair of media IDs.
     */
    private function pairKey(int $id1, int $id2): string
    {
        return min($id1, $id2) . ':' . max($id1, $id2);
    }

    private function saveReport(array $result): void
    {
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
