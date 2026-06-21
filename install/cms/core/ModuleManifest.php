<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

final readonly class ModuleManifest
{
    /**
     * @param list<string> $requiredModules
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $type,
        public string $author,
        public string $phpConstraint,
        public string $miniportalConstraint,
        public array $requiredModules,
        public bool $protected,
        public ?string $factoryFile,
        public ?string $installFile,
        public ?string $uninstallFile,
        public string $originType,
        public string $originUrl,
        public ?string $signatureKeyId,
        public string $signatureStatus,
        public string $directory,
    ) {
    }
}
