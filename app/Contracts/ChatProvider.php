<?php

namespace App\Contracts;

interface ChatProvider
{
    /** @param array<int, array{role: string, content: string}> $messages */
    public function reply(array $messages): string;

    /**
     * Summarise the opening exchange into a short conversation name.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function title(array $messages): string;
}
