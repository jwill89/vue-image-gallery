<?php

namespace Routes\Internal;

use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Collection\ImageCollection;
use Gallery\Core\DuplicateScanner;

/**
 * DuplicatesController class
 * This class handles duplicate image detection and management.
 */
class DuplicatesController extends AbstractController
{
    private ImageCollection $image_collection;

    // Path to the dupes directory (relative to project root, called from api/)
    private const string DUPES_DIRECTORY = '../dupes/';

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->image_collection = new ImageCollection();
    }

    /**
     * getLatestReport - Returns the latest duplicates report JSON.
     * @throws \JsonException
     */
    public function getLatestReport(Request $request, Response $response, array $args): Response
    {
        $dupes_dir = self::DUPES_DIRECTORY;

        // Check if directory exists
        if (!is_dir($dupes_dir)) {
            return $this->error($response, 'NoDupesDirectory', 404);
        }

        // Find all dupes JSON files
        $files = glob($dupes_dir . 'dupes-*.json');

        if (empty($files)) {
            return $this->error($response, 'NoReportsFound', 404);
        }

        // Sort by modification time descending to get the latest
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

        // Read the latest file
        $latest_file = $files[0];
        $content = file_get_contents($latest_file);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if ($data === null) {
            return $this->error($response, 'InvalidReport', 500);
        }

        // Enrich the matches with image data (filenames for thumbnails)
        $enriched_matches = [];
        if (isset($data['matches']) && is_array($data['matches'])) {
            foreach ($data['matches'] as $match) {
                $id1 = $match[0];
                $id2 = $match[1];
                $distance = $match[2] ?? null;

                try {
                    $img1 = $this->image_collection->get($id1);
                    $img2 = $this->image_collection->get($id2);

                    // Skip if either image no longer exists in DB
                    if ($img1 === null || $img2 === null) {
                        continue;
                    }

                    $enriched_matches[] = [
                        'image_1' => [
                            'image_id' => $id1,
                            'file_name' => $img1->getFileName(),
                            'hash' => $img1->getHash(),
                        ],
                        'image_2' => [
                            'image_id' => $id2,
                            'file_name' => $img2->getFileName(),
                            'hash' => $img2->getHash(),
                        ],
                        'distance' => $distance,
                    ];
                } catch (\Throwable $e) {
                    // One or both images may have been deleted since the report was generated
                    continue;
                }
            }
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
     * runScan - Executes the duplicate scanner directly and returns the result.
     */
    public function runScan(Request $request, Response $response, array $args): Response
    {
        $this->logger->info('Duplicate scan initiated');

        try {
            $scanner = new DuplicateScanner();
            $result = $scanner->run();

            $this->logger->info('Duplicate scan completed', $result);
            return $this->success($response, [
                'success' => true,
                'message' => 'Scan completed successfully.',
                'images_compared' => $result['images_compared'],
                'duplicates_found' => $result['duplicates_found'],
                'execution_time' => $result['execution_time_seconds'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Duplicate scan failed', ['error' => $e->getMessage()]);
            return $this->error($response, 'ScanFailed', 500);
        }
    }

    /**
     * deleteImages - Deletes one or more images from the database by ID.
     * The cron script will clean up orphaned files on disk.
     */
    public function deleteImages(Request $request, Response $response, array $args): Response
    {
        $params = $request->getParsedBody();
        $image_ids = $params['image_ids'] ?? [];

        if (empty($image_ids) || !is_array($image_ids)) {
            return $this->error($response, 'InvalidInput', 400);
        }

        $this->logger->info('Delete images requested', ['image_ids' => $image_ids]);

        $deleted = [];
        $failed = [];

        foreach ($image_ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                $failed[] = $id;
                continue;
            }

            try {
                $image = $this->image_collection->get($id);
                if ($image !== null && $this->image_collection->delete($image)) {
                    $deleted[] = $id;
                } else {
                    $failed[] = $id;
                }
            } catch (\Throwable $e) {
                $failed[] = $id;
            }
        }

        return $this->success($response, [
            'deleted' => $deleted,
            'failed' => $failed,
            'total_deleted' => count($deleted),
        ]);
    }
}
