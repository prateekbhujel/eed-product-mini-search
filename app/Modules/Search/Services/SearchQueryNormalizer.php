<?php

namespace App\Modules\Search\Services;

use Illuminate\Support\Str;

class SearchQueryNormalizer
{
    public function normalize(?string $value): string
    {
        $value = Str::ascii((string) $value);
        $value = Str::lower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    public function compact(?string $value): string
    {
        return str_replace(' ', '', $this->normalize($value));
    }

    /**
     * @return array<int, string>
     */
    public function tokens(?string $value): array
    {
        return collect(explode(' ', $this->normalize($value)))
            ->filter(fn (string $token): bool => strlen($token) >= 2)
            ->values()
            ->all();
    }
}
