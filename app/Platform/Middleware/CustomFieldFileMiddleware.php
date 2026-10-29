<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Middleware;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Row;
use CorporateAid\Platform\Repositories\CustomFieldRepository;
use CorporateAid\Platform\Services\FileService;
use CorporateAid\Platform\Support\Logger;

final class CustomFieldFileMiddleware
{
    public function __construct(private Container $container) {}

    public function handle(Request $request, callable $next): Response
    {
        $method = $request->method();
        $hasBody = in_array($method, ['POST', 'PUT', 'PATCH'], true);
        if (!$hasBody || empty($_FILES)) {
            return $next($request);
        }

        $pending = [];
        foreach ($_FILES as $inputName => $fileData) {
            if (!is_string($inputName)) continue;
            if (!preg_match('/^cf_(.+)_file$/', $inputName, $m)) continue;
            if (!is_array($fileData)) continue;
            if (($fileData['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;

            /** @var array{name:string,type:string,tmp_name:string,error:int,size:int} $fileData */
            $pending[$m[1]] = $fileData;
        }

        if (empty($pending)) {
            return $next($request);
        }

        $fieldRepo = $this->container->get(CustomFieldRepository::class);
        $fileService = $this->container->get(FileService::class);
        /** @var Auth $auth */
        $auth = $this->container->get(Auth::class);
        $userId = $auth->user()?->id;

        // Get the existing cf array from the request body
        $currentCf = $request->input('cf', []);
        if (!is_array($currentCf)) {
            $currentCf = [];
        }

        foreach ($pending as $fieldKey => $fileData) {
            // Find field definition for max_size constraint
            $maxMb = FileService::DEFAULT_MAX_MB;
            foreach (['organisation', 'contact', 'engagement', 'vacancy'] as $entityType) {
                $fieldDef = $fieldRepo->findByKey($entityType, $fieldKey);
                if ($fieldDef !== null) {
                    $maxMb = max(1, Row::int($fieldDef->constraints, 'max_size_mb', FileService::DEFAULT_MAX_MB));
                    break;
                }
            }
            $result = $fileService->store($fileData, $userId, $fieldKey, $maxMb);

            if (is_array($result)) {
                Logger::warning('Custom field file upload failed (middleware)', [
                    'field_key' => $fieldKey,
                    'errors'    => $result,
                ]);
                continue;
            }

            // Inject the UUID string into the cf array
            $currentCf[$fieldKey] = $result->uuid;
        }

        // Update BOTH $_POST (for controllers that read superglobals directly)
        // AND the Request object (for controllers that use $request->input)
        $_POST['cf'] = $currentCf;
        return $next($request->withBody(['cf' => $currentCf]));
    }
}
