<?php

namespace Database\Seeders\Concerns;

use App\Modules\AdminAuth\Models\StaffUser;
use App\Modules\Media\Models\Media;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

trait SeedsDemoData
{
    protected function ensureDemoSeedingAllowed(): void
    {
        $allowedInProduction = filter_var(env('ZENNA_ALLOW_DEMO_QA_SEED', false), FILTER_VALIDATE_BOOLEAN);

        if (app()->environment('production') && ! $allowedInProduction) {
            throw new RuntimeException('DemoQaSeeder is disabled in production. Set ZENNA_ALLOW_DEMO_QA_SEED=true only for an intentional QA seed.');
        }
    }

    protected function demoStaffId(): ?int
    {
        return StaffUser::query()->orderBy('id')->value('id');
    }

    protected function demoMedia(string $key, string $title, string $accent = '#b88a2f', int $width = 1200, int $height = 900): Media
    {
        $filename = $key.'.svg';
        $directory = 'demo';
        $svg = $this->demoSvg($title, $accent, $width, $height);

        Storage::disk('public')->put($directory.'/'.$filename, $svg);

        return Media::updateOrCreate(
            ['directory' => $directory, 'filename' => $filename],
            [
                'disk' => 'public',
                'original_name' => $filename,
                'mime_type' => 'image/svg+xml',
                'extension' => 'svg',
                'size' => strlen($svg),
                'width' => $width,
                'height' => $height,
                'alt_text' => $title,
                'uploaded_by' => $this->demoStaffId(),
            ]
        );
    }

    protected function demoSvg(string $title, string $accent, int $width, int $height): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $innerWidth = $width - 144;
        $innerHeight = $height - 144;
        $titleY = $height - 150;
        $labelY = $height - 94;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" role="img" aria-label="{$safeTitle}">
  <defs>
    <linearGradient id="bg" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0%" stop-color="#fff8ec"/>
      <stop offset="55%" stop-color="#f4dfb8"/>
      <stop offset="100%" stop-color="#1d2147"/>
    </linearGradient>
    <pattern id="stitch" width="36" height="36" patternUnits="userSpaceOnUse">
      <path d="M0 18h12m12 0h12" stroke="{$accent}" stroke-width="2" stroke-linecap="round" opacity=".45"/>
    </pattern>
  </defs>
  <rect width="{$width}" height="{$height}" fill="url(#bg)"/>
  <rect width="{$width}" height="{$height}" fill="url(#stitch)" opacity=".32"/>
  <circle cx="{$width}" cy="0" r="{$height}" fill="{$accent}" opacity=".14"/>
  <rect x="72" y="72" width="{$innerWidth}" height="{$innerHeight}" rx="34" fill="none" stroke="#1d2147" stroke-width="3" opacity=".42"/>
  <text x="72" y="{$titleY}" fill="#1d2147" font-family="Georgia, serif" font-size="48" font-weight="700">{$safeTitle}</text>
  <text x="74" y="{$labelY}" fill="#5e4b2f" font-family="Arial, sans-serif" font-size="22" font-weight="700" letter-spacing="4">ZENNA CRAFT DEMO QA</text>
</svg>
SVG;
    }
}
