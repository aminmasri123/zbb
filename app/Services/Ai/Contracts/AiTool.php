<?php

namespace App\Services\Ai\Contracts;

use App\Models\User;
use App\Services\Ai\AiRunContext;

interface AiTool
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(User $user, AiRunContext $context, array $arguments): array;
}
