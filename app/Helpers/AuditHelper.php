<?php

namespace App\Helpers;

class AuditHelper
{
    public static function log(
        string $action,
        string $description,
        mixed $userId = null,
        mixed $recordId = null,
        ?string $module = null
    ): void {
        // Audit implementation will be added here.
    }
}