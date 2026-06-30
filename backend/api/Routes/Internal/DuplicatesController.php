<?php

namespace Routes\Internal;

use PDO;
use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Collection\MediaCollection;
use Gallery\Core\CacheGroup;
use Gallery\Core\DatabaseConnection;
use Gallery\Core\DuplicateScanner;

/**
 * DuplicatesController class
 * Handles duplicate image detection, management, and dismissal.
 * Duplicates are image-only (based on perceptual fingerprinting).
 */
class DuplicatesController extends AbstractController
{
    private MediaCollection $media_collection;

    private const string DUPES_DIRECTORY = 'dupes/';

    public function __construct(MediaCollection $media_collection)
    {
        parent::__construct();
        $this->media_collection = $media_collection;
    }

    /**
     * GET /duplicates/report — The latest duplicate-scan report, with dismissed
     * pairs filtered out and each pair enriched with media file names/hashes.
     */
    public function getLatestReport(Request $request, Response $response, array $args): Response
    {
        $dupes_dir = self::DUPES_DIRECTORY;

        if (!is_dir($dupes_dir)) {
            return $this->error($response, 'NoDupesDirectory', 404, 'No duplicate scan has been run yet.');
        }

        $files = glob($dupes_dir . 'dupes-*.json');

        if (empty($files)) {
            return $this->error($response, 'NoReportsFound', 404, 'No duplicate reports found. Run a scan first.');
        }

        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

        $latest_file = $files[0];
        $content = (string) file_get_contents($latest_file);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Duplicate report is corrupted', ['file' => basename($latest_file), 'error' => $e->getMessage()]);
            return $this->error($response, 'InvalidReport', 500, 'The latest report file is corrupted or unreadable.');
        }

        // Load dismissed pairs so we can filter the report in real time
        $dismissed = [];
        try {
            $db = DatabaseConnection::getInstance();
            $stmt = $db->query('SELECT media_id_1, media_id_2 FROM dismissed_duplicates');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $a = min((int)$row['media_id_1'], (int)$row['media_id_2']);
                $b = max((int)$row['media_id_1'], (int)$row['media_id_2']);
                $dismissed[$a . ':' . $b] = true;
            }
        } catch (\Throwable $e) {
            // Table may not exist yet — skip filtering
        }

        // Filter out dismissed pairs first, then batch-load all referenced media
        // in a single query to avoid an N+1 lookup (2 queries per match pair).
        $pairs = [];
        $neededIds = [];
        if (isset($data['matches']) && is_array($data['matches'])) {
            foreach ($data['matches'] as $match) {
                $id1 = (int) $match[0];
                $id2 = (int) $match[1];

                // Skip dismissed pairs
                $a = min($id1, $id2);
                $b = max($id1, $id2);
                if (isset($dismissed[$a . ':' . $b])) {
                    continue;
                }

                $pairs[] = [$id1, $id2, $match[2] ?? null, $match[3] ?? null];
                $neededIds[$id1] = true;
                $neededIds[$id2] = true;
            }
        }

        // Single batched fetch, indexed by media_id for O(1) lookup
        $mediaById = [];
        if (!empty($neededIds)) {
            foreach ($this->media_collection->getByIds(array_keys($neededIds)) as $media) {
                $mediaById[$media->media_id] = $media;
            }
        }

        $enriched_matches = [];
        foreach ($pairs as [$id1, $id2, $distance, $ssim]) {
            $media1 = $mediaById[$id1] ?? null;
            $media2 = $mediaById[$id2] ?? null;

            if ($media1 === null || $media2 === null) {
                continue;
            }

            $enriched_matches[] = [
                'media_1' => [
                    'media_id' => $id1,
                    'file_name' => $media1->file_name,
                    'hash' => $media1->hash,
                ],
                'media_2' => [
                    'media_id' => $id2,
                    'file_name' => $media2->file_name,
                    'hash' => $media2->hash,
                ],
                'distance' => $distance,
                'ssim' => $ssim,
            ];
        }

        $result = [
            'report_file' => basename($latest_file),
            'generated_at' => $data['generated_at'] ?? null,
            'images_compared' => $data['images_compared'] ?? null,
            'duplicates_found' => count($enriched_matches),
            'matches' => $enriched_matches,
        ];

        return $this->success($response, $result);
    }

    /**
     * POST /duplicates/scan — Run the perceptual-hash duplicate scanner and
     * return summary stats (images compared, candidates, duplicates found).
     */
    public function runScan(Request $request, Response $response, array $args): Response
    {
        $this->logger->info('Duplicate scan initiated');

        try {
            $scanner = new DuplicateScanner($this->getConnection());
            $result = $scanner->run();

            $this->logger->info('Duplicate scan completed', $result);
            return $this->success($response, [
                'success' => true,
                'message' => 'Scan completed successfully.',
                'images_compared' => $result['images_compared'],
                'lsh_candidates' => $result['lsh_candidates'],
                'duplicates_found' => $result['duplicates_found'],
                'execution_time' => $result['execution_time_seconds'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Duplicate scan failed', ['error' => $e->getMessage()]);
            return $this->error($response, 'ScanFailed', 500, 'The duplicate scan encountered an error. Check the server logs for details.');
        }
    }

    /**
     * POST /duplicates/dismiss/ — Dismiss a pair of media items as not-duplicates.
     * Expects { media_id_1: int, media_id_2: int }
     */
    public function dismissPair(Request $request, Response $response, array $args): Response
    {
        $params = $this->parsedBody($request);
        $id1 = (int)($params['media_id_1'] ?? 0);
        $id2 = (int)($params['media_id_2'] ?? 0);

        if ($id1 <= 0 || $id2 <= 0 || $id1 === $id2) {
            return $this->error($response, 'InvalidInput', 400, 'Two different, valid media IDs are required.');
        }

        // Always store with smaller ID first for consistency
        $a = min($id1, $id2);
        $b = max($id1, $id2);

        $db = DatabaseConnection::getInstance();
        $stmt = $db->prepare('INSERT OR IGNORE INTO dismissed_duplicates (media_id_1, media_id_2, dismissed_at) VALUES (:id1, :id2, :ts)');
        $stmt->execute([
            ':id1' => $a,
            ':id2' => $b,
            ':ts' => time(),
        ]);

        $this->logger->info('Duplicate pair dismissed', ['media_id_1' => $a, 'media_id_2' => $b]);

        return $this->success($response, [
            'dismissed' => true,
            'media_id_1' => $a,
            'media_id_2' => $b,
        ]);
    }

    /**
     * DELETE /duplicates/media — Delete one or more media items by ID.
     * Body: { media_ids: int[] }. Returns the deleted and failed ID lists.
     */
    public function deleteMedia(Request $request, Response $response, array $args): Response
    {
        $params = $this->parsedBody($request);
        $media_ids = $params['media_ids'] ?? [];

        if (empty($media_ids) || !is_array($media_ids)) {
            return $this->error($response, 'InvalidInput', 400, 'A list of media IDs to delete is required.');
        }

        $this->logger->info('Delete media requested', ['media_ids' => $media_ids]);

        $deleted = [];
        $failed = [];

        foreach ($media_ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                $failed[] = $id;
                continue;
            }

            try {
                $media = $this->media_collection->get($id);
                if ($media !== null && $this->media_collection->delete($media)) {
                    $deleted[] = $id;
                } else {
                    $failed[] = $id;
                }
            } catch (\Throwable $e) {
                $failed[] = $id;
            }
        }

        if (!empty($deleted)) {
            $this->invalidateCache(CacheGroup::Media, CacheGroup::Tags);
        }

        return $this->success($response, [
            'deleted' => $deleted,
            'failed' => $failed,
            'total_deleted' => count($deleted),
        ]);
    }
}
