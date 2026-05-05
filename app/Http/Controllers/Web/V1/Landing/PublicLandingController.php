<?php

namespace App\Http\Controllers\Web\V1\Landing;

use App\Models\Landing\UserPost;
use App\Models\Landing\UserPublicProfile;
use App\Models\Tenant\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicLandingController
{
    public function subdomain(Request $request, string $slug, \App\Services\Landing\InstagramFeedService $instagramFeedService): View
    {
        $tenant = Tenant::query()
            ->with([
                'landingPage',
                'users' => fn($query) => $query->select('users.id', 'users.name', 'users.goal'),
                'professionalMedia' => fn($query) => $query->with('professional:id,name')->latest(),
            ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($tenant instanceof Tenant) {
            $instagramFeed = $instagramFeedService->forTenantLanding($tenant->landingPage);

            return view('landing.tenant', [
                'tenant' => $tenant,
                'landing' => $tenant->landingPage,
                'professionals' => $tenant->users,
                'professionalMedia' => $tenant->professionalMedia,
                'instagramFeed' => $instagramFeed,
            ]);
        }

        $profile = UserPublicProfile::query()
            ->with([
                'user' => fn($query) => $query->select('id', 'name', 'goal'),
                'user.mediaAssets' => fn($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $posts = $profile->user->posts()
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(6)
            ->withQueryString();

        $highlightedPost = $this->resolveHighlightedPost($request, $profile->user->id);

        return view('landing.user', [
            'profile' => $profile,
            'user' => $profile->user,
            'mediaAssets' => $profile->user->mediaAssets,
            'posts' => $posts,
            'highlightedPost' => $highlightedPost,
        ]);
    }

    public function user(Request $request, string $slug): View
    {
        $profile = UserPublicProfile::query()
            ->with([
                'user' => fn($query) => $query->select('id', 'name', 'goal'),
                'user.mediaAssets' => fn($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $posts = $profile->user->posts()
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(6)
            ->withQueryString();

        $highlightedPost = $this->resolveHighlightedPost($request, $profile->user->id);

        return view('landing.user', [
            'profile' => $profile,
            'user' => $profile->user,
            'mediaAssets' => $profile->user->mediaAssets,
            'posts' => $posts,
            'highlightedPost' => $highlightedPost,
        ]);
    }

    private function resolveHighlightedPost(Request $request, int $userId): ?UserPost
    {
        $postSlug = trim((string) $request->query('post', ''));

        if ($postSlug === '') {
            return null;
        }

        return UserPost::query()
            ->where('user_id', $userId)
            ->where('is_published', true)
            ->where('slug', $postSlug)
            ->first();
    }
}
