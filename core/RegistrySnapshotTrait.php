<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Roll back registration data while preserving registry object identities. */
trait RegistrySnapshotTrait
{
    public function registrationRollback(): \Closure
    {
        $snapshot = get_object_vars($this);
        return function () use ($snapshot): void {
            foreach ($snapshot as $property => $value) {
                if ($this->{$property} !== $value) {
                    $this->{$property} = $value;
                }
            }
        };
    }
}
