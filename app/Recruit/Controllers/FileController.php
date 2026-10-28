<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Platform\Repositories\EntityFileRepository;
use CorporateAid\Platform\Repositories\FileRepository;
use CorporateAid\Platform\Services\FileService;
use CorporateAid\Platform\Support\Logger;

/**
 * Serves stored files through PHP with permission checks derived
 * from the file's linked entities.
 *
 * Model:
 *   - Files are linked to entities via core_entity_files.
 *   - Permission to download is derived from the linked entity's
 *     view permission (ENTITY_VIEW_PERMISSION map below).
 *   - Zero links => orphan => 404 (fail closed).
 *   - No matching permission => 403.
 *   - Missing / soft-deleted file => 404.
 *
 * The route requires 'auth' — the fine-grained check happens here
 * because permission depends on which entity the file is attached to.
 */
final class FileController
{
    /**
     * entity_type => required view permission slug.
     *
     * When new entity types get file support, add them here. If an
     * entity_type has no mapping, its files are not servable (404),
     * by design — every file attachment should be a deliberate grant.
     */
    private const ENTITY_VIEW_PERMISSION = [
        'organisation' => 'recruit.organisations.view',
        'contact'      => 'recruit.contacts.view',
        'engagement'   => 'recruit.engagements.view',
        'vacancy'      => 'recruit.vacancies.view',
    ];

    public function __construct(private Container $container) {}

    /** @param array<string, string> $params */
    public function serve(Request $request, array $params = []): Response
    {
        $uuid = (string) ($params['uuid'] ?? '');
        if ($uuid === '') {
            return Response::text('Not found', 404);
        }

        /** @var FileRepository $files */
        $files = $this->container->get(FileRepository::class);
        /** @var EntityFileRepository $links */
        $links = $this->container->get(EntityFileRepository::class);
        /** @var FileService $fileService */
        $fileService = $this->container->get(FileService::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);

        $file = $files->findByUuid($uuid);
        if ($file === null || $file->isDeleted()) {
            Logger::warning('FileController 404: File not found or deleted by UUID', ['uuid' => $uuid]);
            return Response::text('Not found', 404);
        }

        // Derive permission from linked entities. Zero links => orphan => 404.
        $entityLinks = $links->linksForFile($file->id);
        if (empty($entityLinks)) {
            Logger::warning('FileController 404: No entity links found for file', ['file_id' => $file->id]);
            return Response::text('Not found', 404);
        }

        $allowed = false;
        foreach ($entityLinks as $link) {
            $perm = self::ENTITY_VIEW_PERMISSION[$link->entityType] ?? null;
            if ($perm !== null && $auth->can($perm)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            Logger::warning('FileController 403: Permission denied based on links', ['file_id' => $file->id, 'links' => array_map(fn($l) => $l->entityType, $entityLinks)]);
            return Response::text('Forbidden', 403);
        }

        $absolute = $fileService->absolutePath($file);
        if (!is_file($absolute) || !is_readable($absolute)) {
            Logger::warning('FileController 404: Physical file missing on disk', ['file_id' => $file->id, 'path' => $absolute]);
            return Response::text('Not found', 404);
        }

        return Response::file($file, $absolute);
    }
}
