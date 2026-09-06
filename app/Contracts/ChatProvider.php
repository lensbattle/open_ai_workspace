<?php

namespace App\Contracts;

interface ChatProvider
{
    /** @param array<int, array{role: string, content: string}> $messages */
    public function reply(array $messages): string;
}
