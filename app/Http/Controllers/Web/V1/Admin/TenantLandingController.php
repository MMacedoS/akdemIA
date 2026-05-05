<?php

namespace App\Http\Controllers\Web\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Landing\TenantProfessionalMedia;
use App\Models\Tenant\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantLandingController extends Controller
{
    private const PROFESSIONAL_IMAGE_LIMIT = 15;

    private const PROFESSIONAL_VIDEO_LIMIT = 4;

    private array $compressionStats = [];

    public function edit(Request $request): View
    {
        $tenant = $this->resolveTenant($request);

        $landing = $tenant->landingPage()->first();
        $professionals = $tenant->users()
            ->wherePivot('role', Role::TRAINER->value)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email']);

        $professionalMedia = $tenant->professionalMedia()
            ->with('professional:id,name')
            ->latest()
            ->get();

        return view('web.v1.admin.landing.edit', [
            'landing' => $landing,
            'professionals' => $professionals,
            'professionalMedia' => $professionalMedia,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->compressionStats = [];
        $tenant = $this->resolveTenant($request);

        $payload = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'hero_image_url' => ['nullable', 'url', 'max:2000'],
            'hero_video_url' => ['nullable', 'url', 'max:2000'],
            'theme_preset' => ['nullable', 'in:myhra_bordeaux,sage_serene,graphite_noir,ocean_mist,custom_brand'],
            'hero_image_file' => ['nullable', 'image', 'max:3072'],
            'hero_video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:25600'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'cta_text' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'url', 'max:2000'],
            'instagram_username' => ['nullable', 'string', 'max:100'],
            'instagram_access_token' => ['nullable', 'string', 'max:5000'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $existingLanding = $tenant->landingPage()->first();
        $heroImageUrl = $payload['hero_image_url'] ?? null;
        $heroVideoUrl = $payload['hero_video_url'] ?? null;

        if ($request->hasFile('hero_image_file')) {
            if ($existingLanding instanceof \App\Models\Landing\TenantLandingPage) {
                $this->deleteIfLocalStorageUrl($existingLanding->hero_image_url);
            }

            $heroImageUrl = $this->storeUploadedFile($request->file('hero_image_file'), 'landings/tenants/heroes');
        }

        if ($request->hasFile('hero_video_file')) {
            if ($existingLanding instanceof \App\Models\Landing\TenantLandingPage) {
                $this->deleteIfLocalStorageUrl($existingLanding->hero_video_url);
            }

            $heroVideoUrl = $this->storeUploadedFile($request->file('hero_video_file'), 'landings/tenants/heroes');
        }

        $instagramAccessToken = $payload['instagram_access_token'] ?? null;

        if (
            (! is_string($instagramAccessToken) || trim($instagramAccessToken) === '')
            && $existingLanding instanceof \App\Models\Landing\TenantLandingPage
        ) {
            $instagramAccessToken = $existingLanding->instagram_access_token;
        }

        $tenant->landingPage()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'title' => $payload['title'] ?? null,
                'headline' => $payload['headline'] ?? null,
                'description' => $payload['description'] ?? null,
                'hero_image_url' => $heroImageUrl,
                'hero_video_url' => $heroVideoUrl,
                'theme_preset' => $payload['theme_preset'] ?? 'myhra_bordeaux',
                'primary_color' => $payload['primary_color'] ?? '#0f6eaf',
                'secondary_color' => $payload['secondary_color'] ?? '#13a9bf',
                'cta_text' => $payload['cta_text'] ?? null,
                'cta_url' => $payload['cta_url'] ?? null,
                'instagram_username' => $payload['instagram_username'] ?? null,
                'instagram_access_token' => $instagramAccessToken,
                'is_published' => (bool) ($payload['is_published'] ?? false),
            ]
        );

        $response = redirect()->route('admin.landing.edit')->with('status', 'Landing do contratante atualizada com sucesso.');

        if (! empty($this->compressionStats)) {
            $response->with('compression_status', $this->buildCompressionSummary());
        }

        return $response;
    }

    public function storeProfessionalMedia(Request $request): RedirectResponse
    {
        $this->compressionStats = [];
        $tenant = $this->resolveTenant($request);

        $payload = $request->validate([
            'professional_user_id' => ['required', 'integer'],
            'media_type' => ['required', 'in:image,video'],
            'media_url' => ['nullable', 'url', 'max:2000'],
            'media_file' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime', 'max:30720'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $mediaFile = $request->file('media_file');
        $mediaUrl = $payload['media_url'] ?? null;

        if (! is_string($mediaUrl) && ! $mediaFile instanceof UploadedFile) {
            return redirect()->route('admin.landing.edit')->withErrors([
                'media_file' => 'Informe uma URL ou envie um arquivo.',
            ])->withInput();
        }

        if ($mediaFile instanceof UploadedFile) {
            $mimeType = (string) $mediaFile->getMimeType();

            if ($payload['media_type'] === 'image' && ! str_starts_with($mimeType, 'image/')) {
                return redirect()->route('admin.landing.edit')->withErrors([
                    'media_file' => 'O arquivo deve ser uma imagem para o tipo selecionado.',
                ])->withInput();
            }

            if ($payload['media_type'] === 'video' && ! str_starts_with($mimeType, 'video/')) {
                return redirect()->route('admin.landing.edit')->withErrors([
                    'media_file' => 'O arquivo deve ser um video para o tipo selecionado.',
                ])->withInput();
            }

            $mediaUrl = $this->storeUploadedFile($mediaFile, 'landings/tenants/professionals');
        }

        $isValidProfessional = $tenant->users()
            ->where('users.id', (int) $payload['professional_user_id'])
            ->wherePivot('role', Role::TRAINER->value)
            ->exists();

        if (! $isValidProfessional) {
            return redirect()->route('admin.landing.edit')->withErrors([
                'professional_user_id' => 'Profissional invalido para este contratante.',
            ]);
        }

        $currentImageCount = $tenant->professionalMedia()
            ->where('professional_user_id', (int) $payload['professional_user_id'])
            ->where('media_type', 'image')
            ->count();

        $currentVideoCount = $tenant->professionalMedia()
            ->where('professional_user_id', (int) $payload['professional_user_id'])
            ->where('media_type', 'video')
            ->count();

        if ($payload['media_type'] === 'image' && $currentImageCount >= self::PROFESSIONAL_IMAGE_LIMIT) {
            return redirect()->route('admin.landing.edit')->withErrors([
                'media_file' => 'Limite de fotos atingido para este profissional. Maximo de ' . self::PROFESSIONAL_IMAGE_LIMIT . ' fotos.',
            ])->withInput();
        }

        if ($payload['media_type'] === 'video' && $currentVideoCount >= self::PROFESSIONAL_VIDEO_LIMIT) {
            return redirect()->route('admin.landing.edit')->withErrors([
                'media_file' => 'Limite de videos atingido para este profissional. Maximo de ' . self::PROFESSIONAL_VIDEO_LIMIT . ' videos.',
            ])->withInput();
        }

        $tenant->professionalMedia()->create([
            'professional_user_id' => (int) $payload['professional_user_id'],
            'media_type' => $payload['media_type'],
            'media_url' => $mediaUrl,
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
        ]);

        $response = redirect()->route('admin.landing.edit')->with('status', 'Midia profissional adicionada com sucesso.');

        if (! empty($this->compressionStats)) {
            $response->with('compression_status', $this->buildCompressionSummary());
        }

        return $response;
    }

    public function destroyProfessionalMedia(Request $request, int $mediaId): RedirectResponse
    {
        $tenant = $this->resolveTenant($request);

        $media = TenantProfessionalMedia::query()
            ->where('id', $mediaId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($media instanceof TenantProfessionalMedia) {
            $this->deleteIfLocalStorageUrl($media->media_url);
            $media->delete();
        }

        return redirect()->route('admin.landing.edit')->with('status', 'Midia profissional removida com sucesso.');
    }

    private function resolveTenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            abort(409, 'Tenant not identified.');
        }

        return $tenant;
    }

    private function storeUploadedFile(?UploadedFile $file, string $directory): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $mimeType = (string) $file->getMimeType();

        if (str_starts_with($mimeType, 'image/')) {
            return $this->storeOptimizedImage($file, $directory);
        }

        $path = $file->store($directory, 'public');

        return Storage::disk('public')->url($path);
    }

    private function storeOptimizedImage(UploadedFile $file, string $directory): ?string
    {
        $originalSize = (int) $file->getSize();

        if (
            ! function_exists('imagecreatetruecolor')
            || ! function_exists('imagecopyresampled')
            || ! function_exists('getimagesize')
        ) {
            $fallbackPath = $file->store($directory, 'public');
            $this->compressionStats[] = [
                'optimized' => false,
                'original' => $originalSize,
                'final' => $originalSize,
            ];
            return Storage::disk('public')->url($fallbackPath);
        }

        $imageInfo = @getimagesize($file->getPathname());

        if (! is_array($imageInfo) || ! isset($imageInfo['mime'], $imageInfo[0], $imageInfo[1])) {
            $fallbackPath = $file->store($directory, 'public');
            $this->compressionStats[] = [
                'optimized' => false,
                'original' => $originalSize,
                'final' => $originalSize,
            ];
            return Storage::disk('public')->url($fallbackPath);
        }

        $sourceImage = $this->createImageResource((string) $imageInfo['mime'], $file->getPathname());

        if ($sourceImage === null) {
            $fallbackPath = $file->store($directory, 'public');
            $this->compressionStats[] = [
                'optimized' => false,
                'original' => $originalSize,
                'final' => $originalSize,
            ];
            return Storage::disk('public')->url($fallbackPath);
        }

        $sourceWidth = (int) $imageInfo[0];
        $sourceHeight = (int) $imageInfo[1];
        $maxDimension = 1600;
        $scale = min(1, $maxDimension / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        $extension = $this->resolveImageExtension((string) $imageInfo['mime']);
        $path = $directory . '/' . Str::uuid()->toString() . '.' . $extension;
        $binary = $this->encodeImageBinary($targetImage, $extension);

        imagedestroy($sourceImage);
        imagedestroy($targetImage);

        if (! is_string($binary)) {
            $fallbackPath = $file->store($directory, 'public');
            $this->compressionStats[] = [
                'optimized' => false,
                'original' => $originalSize,
                'final' => $originalSize,
            ];
            return Storage::disk('public')->url($fallbackPath);
        }

        Storage::disk('public')->put($path, $binary);

        $this->compressionStats[] = [
            'optimized' => true,
            'original' => $originalSize,
            'final' => strlen($binary),
        ];

        return Storage::disk('public')->url($path);
    }

    private function createImageResource(string $mimeType, string $path): mixed
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            'image/gif' => @imagecreatefromgif($path),
            default => null,
        };
    }

    private function resolveImageExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }

    private function encodeImageBinary(mixed $image, string $extension): ?string
    {
        ob_start();

        $encoded = match ($extension) {
            'png' => imagepng($image, null, 7),
            'webp' => function_exists('imagewebp') ? imagewebp($image, null, 78) : imagejpeg($image, null, 78),
            'gif' => imagegif($image),
            default => imagejpeg($image, null, 78),
        };

        $output = ob_get_clean();

        if (! $encoded || ! is_string($output)) {
            return null;
        }

        return $output;
    }

    private function deleteIfLocalStorageUrl(?string $url): void
    {
        if (! is_string($url) || $url === '') {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
            return;
        }

        $relativePath = Str::after($path, '/storage/');

        if ($relativePath !== '') {
            Storage::disk('public')->delete($relativePath);
        }
    }

    private function buildCompressionSummary(): string
    {
        $optimizedCount = 0;
        $savedBytes = 0;

        foreach ($this->compressionStats as $stat) {
            if ((bool) ($stat['optimized'] ?? false)) {
                $optimizedCount++;
                $savedBytes += max(0, (int) ($stat['original'] ?? 0) - (int) ($stat['final'] ?? 0));
            }
        }

        if ($optimizedCount === 0) {
            return 'Upload concluido sem compressao de imagem (arquivo ja otimizado ou formato nao suportado).';
        }

        $savedMb = number_format($savedBytes / 1024 / 1024, 2, ',', '.');

        return "Compressao aplicada em {$optimizedCount} imagem(ns). Economia estimada: {$savedMb} MB.";
    }
}
