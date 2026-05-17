<?php

namespace Routes\Internal;

use Psr\Container\ContainerInterface;
use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;
use Gallery\Collection\ImageCollection;

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
     */
    public function getLatestReport(Request $request, Response $response, array $args): Response
    {
        $dupes_dir = self::DUPES_DIRECTORY;

        // Check if directory exists
        if (!is_dir($dupes_dir)) {
            return $response->withJson(['error' => 'NoDupesDirectory', 'message' => 'No duplicates directory found.'], 404);
        }

        // Find all dupes JSON files
        $files = glob($dupes_dir . 'dupes-*.json');

        if (empty($files)) {
            return $response->withJson(['error' => 'NoReportsFound', 'message' => 'No duplicate reports found. Run a scan first.'], 404);
        }

        // Sort by modification time descending to get the latest
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

        // Read the latest file
        $latest_file = $files[0];
        $content = file_get_contents($latest_file);
        $data = json_decode($content, true);

        if ($data === null) {
            return $response->withJson(['error' => 'InvalidReport', 'message' => 'Could not parse report file.'], 500);
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

                    // Skip if either image no longer exists in DB (get() returns array when not found)
                    if (!($img1 instanceof \Gallery\Structure\Image) || !($img2 instanceof \Gallery\Structure\Image)) {
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

        return $response->withJson($result, 200);
    }

    /**
     * runScan - Executes the dupes.php script and returns the result.
     */
    public function runScan(Request $request, Response $response, array $args): Response
    {
        $dupes_script = realpath(__DIR__ . '/../../../dupes.php');

        if (!$dupes_script || !file_exists($dupes_script)) {
            return $response->withJson(['error' => 'ScriptNotFound', 'message' => 'dupes.php not found.'], 500);
        }

        // Execute the script in the project root directory
        $project_root = realpath(__DIR__ . '/../../../');
        $command = sprintf('cd %s && php dupes.php 2>&1', escapeshellarg($project_root));

        $output = [];
        $return_code = 0;
        exec($command, $output, $return_code);

        if ($return_code !== 0) {
            return $response->withJson([
                'error' => 'ScanFailed',
                'message' => 'Duplicate scan failed.',
                'output' => implode("\n", $output),
            ], 500);
        }

        return $response->withJson([
            'success' => true,
            'message' => 'Scan completed successfully.',
            'output' => implode("\n", $output),
        ], 200);
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
            return $response->withJson(['error' => 'InvalidInput', 'message' => 'Provide an array of image_ids.'], 400);
        }

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
                if (($image instanceof \Gallery\Structure\Image) && $this->image_collection->delete($image)) {
                    $deleted[] = $id;
                } else {
                    $failed[] = $id;
                }
            } catch (\Throwable $e) {
                $failed[] = $id;
            }
        }

        return $response->withJson([
            'deleted' => $deleted,
            'failed' => $failed,
            'total_deleted' => count($deleted),
        ], 200);
    }
}
