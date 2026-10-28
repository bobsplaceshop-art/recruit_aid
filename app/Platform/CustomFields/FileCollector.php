<?php
declare(strict_types=1);

namespace CorporateAid\Platform\CustomFields;

/**
 * Extracts custom field file uploads from the global $_FILES array.
 * Looks for inputs named "cf_{fieldKey}_file".
 */
final class FileCollector
{
    /**
     * @return array<string, array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    public static function collect(): array
    {
        $files = [];

        if (empty($_FILES)) {
            return $files;
        }

        foreach ($_FILES as $inputName => $fileData) {
            if (!is_string($inputName)) {
                continue;
            }
            if (!preg_match('/^cf_(.+)_file$/', $inputName, $matches)) {
                continue;
            }

            $fieldKey = $matches[1];
            if (!is_array($fileData)) {
                continue;
            }

            /** @var array{error: int} $fileData */
            $error = $fileData['error'];
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            /** @var array{name:string,type:string,tmp_name:string,error:int,size:int} $fileData */
            $files[$fieldKey] = $fileData;
        }

        return $files;
    }
}
