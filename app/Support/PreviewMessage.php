<?php

namespace App\Support;

/** Stand-in for Illuminate\Mail\Message when previewing an e-mail in the browser. */
class PreviewMessage
{
    public function embedData(string $data, string $name, ?string $contentType = null): string
    {
        return 'data:'.($contentType ?? 'application/octet-stream').';base64,'.base64_encode($data);
    }
}
