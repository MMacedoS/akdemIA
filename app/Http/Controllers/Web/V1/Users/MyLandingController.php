<?php

namespace App\Http\Controllers\Web\V1\Users;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Landing\UserMediaAsset;
use App\Models\Landing\UserPost;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MyLandingController extends Controller
{
    private const PROFESSIONAL_IMAGE_LIMIT = 15;

    private const PROFESSIONAL_VIDEO_LIMIT = 4;

    private array $compressionStats = [];

    public function edit(Request $request): View
    {
        $user = $this->resolveAllowedUser($request);
        $isProfessional = $this->isProfessionalUser($user);

        $profile = $user->publicProfile()->first();
        $mediaAssets = $user->mediaAssets()->orderBy('sort_order')->orderBy('id')->get();
        $posts = $user->posts()->orderByDesc('published_at')->orderByDesc('id')->get();
        $imageCount = $user->mediaAssets()->where('media_type', 'image')->count();
        $videoCount = $user->mediaAssets()->where('media_type', 'video')->count();

        return view('web.v1.users.my-landing.edit', [
            'profile' => $profile,
            'mediaAssets' => $mediaAssets,
            'posts' => $posts,
            'isProfessional' => $isProfessional,
            'mediaImageCount' => $imageCount,
            'mediaVideoCount' => $videoCount,
            'professionalImageLimit' => self::PROFESSIONAL_IMAGE_LIMIT,
            'professionalVideoLimit' => self::PROFESSIONAL_VIDEO_LIMIT,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->compressionStats = [];
        $user = $this->resolveAllowedUser($request);
        $profileId = $user->publicProfile()->value('id');

        $payload = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:user_public_profiles,slug,' . ((string) ($profileId ?? 0))],
            'headline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:4000'],
            'skills' => ['nullable', 'string', 'max:4000'],
            'service_section_title' => ['nullable', 'string', 'max:255'],
            'service_one_label' => ['nullable', 'string', 'max:80'],
            'service_one_title' => ['nullable', 'string', 'max:255'],
            'service_one_description' => ['nullable', 'string', 'max:2000'],
            'service_one_link_label' => ['nullable', 'string', 'max:80'],
            'service_one_link_url' => ['nullable', 'string', 'max:255'],
            'service_two_label' => ['nullable', 'string', 'max:80'],
            'service_two_title' => ['nullable', 'string', 'max:255'],
            'service_two_description' => ['nullable', 'string', 'max:2000'],
            'service_two_link_label' => ['nullable', 'string', 'max:80'],
            'service_two_link_url' => ['nullable', 'string', 'max:255'],
            'service_three_label' => ['nullable', 'string', 'max:80'],
            'service_three_title' => ['nullable', 'string', 'max:255'],
            'service_three_description' => ['nullable', 'string', 'max:2000'],
            'service_three_link_label' => ['nullable', 'string', 'max:80'],
            'service_three_link_url' => ['nullable', 'string', 'max:255'],
            'contact_whatsapp' => ['nullable', 'string', 'max:255'],
            'contact_instagram' => ['nullable', 'string', 'max:255'],
            'hero_image_url' => ['nullable', 'url', 'max:2000'],
            'hero_video_url' => ['nullable', 'url', 'max:2000'],
            'theme_preset' => ['nullable', 'in:myhra_bordeaux,sage_serene,graphite_noir,ocean_mist'],
            'hero_image_file' => ['nullable', 'image', 'max:3072'],
            'hero_video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:25600'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $existingProfile = $user->publicProfile()->first();
        $heroImageUrl = $payload['hero_image_url'] ?? null;
        $heroVideoUrl = $payload['hero_video_url'] ?? null;

        if ($request->hasFile('hero_image_file')) {
            if ($existingProfile instanceof \App\Models\Landing\UserPublicProfile) {
                $this->deleteIfLocalStorageUrl($existingProfile->hero_image_url);
            }

            $heroImageUrl = $this->storeUploadedFile($request->file('hero_image_file'), 'landings/users/heroes');
        }

        if ($request->hasFile('hero_video_file')) {
            if ($existingProfile instanceof \App\Models\Landing\UserPublicProfile) {
                $this->deleteIfLocalStorageUrl($existingProfile->hero_video_url);
            }

            $heroVideoUrl = $this->storeUploadedFile($request->file('hero_video_file'), 'landings/users/heroes');
        }

        $user->publicProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'slug' => Str::lower((string) $payload['slug']),
                'headline' => $payload['headline'] ?? null,
                'bio' => $payload['bio'] ?? null,
                'skills' => $payload['skills'] ?? null,
                'service_section_title' => $payload['service_section_title'] ?? null,
                'service_one_label' => $payload['service_one_label'] ?? null,
                'service_one_title' => $payload['service_one_title'] ?? null,
                'service_one_description' => $payload['service_one_description'] ?? null,
                'service_one_link_label' => $payload['service_one_link_label'] ?? null,
                'service_one_link_url' => $payload['service_one_link_url'] ?? null,
                'service_two_label' => $payload['service_two_label'] ?? null,
                'service_two_title' => $payload['service_two_title'] ?? null,
                'service_two_description' => $payload['service_two_description'] ?? null,
                'service_two_link_label' => $payload['service_two_link_label'] ?? null,
                'service_two_link_url' => $payload['service_two_link_url'] ?? null,
                'service_three_label' => $payload['service_three_label'] ?? null,
                'service_three_title' => $payload['service_three_title'] ?? null,
                'service_three_description' => $payload['service_three_description'] ?? null,
                'service_three_link_label' => $payload['service_three_link_label'] ?? null,
                'service_three_link_url' => $payload['service_three_link_url'] ?? null,
                'contact_whatsapp' => $payload['contact_whatsapp'] ?? null,
                'contact_instagram' => $payload['contact_instagram'] ?? null,
                'hero_image_url' => $heroImageUrl,
                'hero_video_url' => $heroVideoUrl,
                'theme_preset' => $payload['theme_preset'] ?? 'myhra_bordeaux',
                'is_published' => (bool) ($payload['is_published'] ?? false),
            ]
        );

        $response = redirect()->route('my-landing.edit')->with('status', 'Landing pessoal atualizada com sucesso.');

        if (! empty($this->compressionStats)) {
            $response->with('compression_status', $this->buildCompressionSummary());
        }

        return $response;
    }

    public function storeMedia(Request $request): RedirectResponse
    {
        $this->compressionStats = [];
        $user = $this->resolveAllowedUser($request);
        $isProfessional = $this->isProfessionalUser($user);

        $payload = $request->validate([
            'media_type' => ['required', 'in:image,video'],
            'media_url' => ['nullable', 'url', 'max:2000'],
            'media_file' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime', 'max:30720'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $mediaFile = $request->file('media_file');
        $mediaUrl = $payload['media_url'] ?? null;

        if ($isProfessional) {
            $currentImageCount = $user->mediaAssets()->where('media_type', 'image')->count();
            $currentVideoCount = $user->mediaAssets()->where('media_type', 'video')->count();

            if ($payload['media_type'] === 'image' && $currentImageCount >= self::PROFESSIONAL_IMAGE_LIMIT) {
                return redirect()->route('my-landing.edit')->withErrors([
                    'media_file' => 'Limite de fotos atingido. Profissional pode cadastrar no maximo ' . self::PROFESSIONAL_IMAGE_LIMIT . ' fotos.',
                ])->withInput();
            }

            if ($payload['media_type'] === 'video' && $currentVideoCount >= self::PROFESSIONAL_VIDEO_LIMIT) {
                return redirect()->route('my-landing.edit')->withErrors([
                    'media_file' => 'Limite de videos atingido. Profissional pode cadastrar no maximo ' . self::PROFESSIONAL_VIDEO_LIMIT . ' videos.',
                ])->withInput();
            }
        }

        if (! is_string($mediaUrl) && ! $mediaFile instanceof UploadedFile) {
            return redirect()->route('my-landing.edit')->withErrors([
                'media_file' => 'Informe uma URL ou envie um arquivo.',
            ])->withInput();
        }

        if ($mediaFile instanceof UploadedFile) {
            $mimeType = (string) $mediaFile->getMimeType();

            if ($payload['media_type'] === 'image' && ! str_starts_with($mimeType, 'image/')) {
                return redirect()->route('my-landing.edit')->withErrors([
                    'media_file' => 'O arquivo deve ser uma imagem para o tipo selecionado.',
                ])->withInput();
            }

            if ($payload['media_type'] === 'video' && ! str_starts_with($mimeType, 'video/')) {
                return redirect()->route('my-landing.edit')->withErrors([
                    'media_file' => 'O arquivo deve ser um video para o tipo selecionado.',
                ])->withInput();
            }

            $mediaUrl = $this->storeUploadedFile($mediaFile, 'landings/users/media');
        }

        $user->mediaAssets()->create([
            'media_type' => $payload['media_type'],
            'media_url' => $mediaUrl,
            'title' => $payload['title'] ?? null,
            'description' => $payload['description'] ?? null,
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ]);

        $response = redirect()->route('my-landing.edit')->with('status', 'Midia adicionada com sucesso.');

        if (! empty($this->compressionStats)) {
            $response->with('compression_status', $this->buildCompressionSummary());
        }

        return $response;
    }

    public function destroyMedia(Request $request, int $mediaId): RedirectResponse
    {
        $user = $this->resolveAllowedUser($request);

        $media = UserMediaAsset::query()
            ->where('id', $mediaId)
            ->where('user_id', $user->id)
            ->first();

        if ($media instanceof UserMediaAsset) {
            $this->deleteIfLocalStorageUrl($media->media_url);
            $media->delete();
        }

        return redirect()->route('my-landing.edit')->with('status', 'Midia removida com sucesso.');
    }

    public function storePost(Request $request): RedirectResponse
    {
        $user = $this->resolveAllowedUser($request);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string', 'max:20000'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $user->posts()->create([
            'title' => $payload['title'],
            'slug' => Str::slug((string) $payload['title']) . '-' . Str::lower(Str::random(6)),
            'excerpt' => $payload['excerpt'] ?? null,
            'content' => $payload['content'],
            'is_published' => (bool) ($payload['is_published'] ?? false),
            'published_at' => (bool) ($payload['is_published'] ?? false) ? now() : null,
        ]);

        return redirect()->route('my-landing.edit')->with('status', 'Post salvo com sucesso.');
    }

    public function updatePost(Request $request, int $postId): RedirectResponse
    {
        $user = $this->resolveAllowedUser($request);

        $payload = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string', 'max:20000'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $post = UserPost::query()
            ->where('id', $postId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $post->fill([
            'title' => $payload['title'],
            'excerpt' => $payload['excerpt'] ?? null,
            'content' => $payload['content'],
            'is_published' => (bool) ($payload['is_published'] ?? false),
            'published_at' => (bool) ($payload['is_published'] ?? false)
                ? ($post->published_at ?? now())
                : null,
        ]);
        $post->save();

        return redirect()->route('my-landing.edit')->with('status', 'Post atualizado com sucesso.');
    }

    public function destroyPost(Request $request, int $postId): RedirectResponse
    {
        $user = $this->resolveAllowedUser($request);

        UserPost::query()
            ->where('id', $postId)
            ->where('user_id', $user->id)
            ->delete();

        return redirect()->route('my-landing.edit')->with('status', 'Post removido com sucesso.');
    }

    private function resolveAllowedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user !== null, 401);
        abort_if($user->isSystemAdmin(), 403, 'Perfil nao permitido.');

        $isTrainerProfile = $user->profileType() === Role::TRAINER;
        $isTenantTrainer = $user->tenants()->wherePivot('role', Role::TRAINER->value)->exists();

        abort_unless($user->isTrainee() || $isTrainerProfile || $isTenantTrainer, 403, 'Apenas trainee ou profissional pode editar landing propria.');

        return $user;
    }

    private function isProfessionalUser(User $user): bool
    {
        return $user->profileType() === Role::TRAINER
            || $user->tenants()->wherePivot('role', Role::TRAINER->value)->exists();
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
