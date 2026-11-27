<?php

declare(strict_types=1);

namespace App\Domain\Offers\Services;

use App\Domain\Booking\Enums\DealStatus;
use App\Domain\Booking\ServiceType;
use App\Models\Deal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DealCatalogService
{
    /**
     * Retrieve all active, published deals with optional service and destination filters.
     *
     * @return Collection<int, Deal>
     */
    public function getPublishedDeals(?ServiceType $serviceType = null, ?string $destination = null): Collection
    {
        $now = Carbon::now('Asia/Colombo');

        $query = Deal::query()
            ->with(['promotion'])
            ->where('status', DealStatus::Published)
            ->where(function (Builder $sub) use ($now): void {
                $sub->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->where(function (Builder $sub) use ($now): void {
                $sub->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });

        if ($serviceType !== null) {
            $query->where('service_type', $serviceType->value);
        }

        if ($destination !== null && trim($destination) !== '') {
            $term = '%'.trim($destination).'%';
            $query->where(function (Builder $sub) use ($term): void {
                $sub->where('title', 'like', $term)
                    ->orWhere('summary', 'like', $term)
                    ->orWhereRaw("json_extract(preset_payload, '$.destination') like ?", [$term])
                    ->orWhereRaw("json_extract(preset_payload, '$.destination_text') like ?", [$term]);
            });
        }

        return $query->orderByDesc('priority')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Find a single published deal by slug.
     */
    public function findPublishedBySlug(string $slug): ?Deal
    {
        $now = Carbon::now('Asia/Colombo');

        return Deal::query()
            ->with(['promotion'])
            ->where('slug', $slug)
            ->where('status', DealStatus::Published)
            ->where(function (Builder $sub) use ($now): void {
                $sub->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->where(function (Builder $sub) use ($now): void {
                $sub->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->first();
    }

    /**
     * Retrieve featured published deals for homepage showcase.
     *
     * @return Collection<int, Deal>
     */
    public function getFeaturedDeals(int $limit = 6): Collection
    {
        $now = Carbon::now('Asia/Colombo');

        return Deal::query()
            ->with(['promotion'])
            ->where('status', DealStatus::Published)
            ->where('is_featured', true)
            ->where(function (Builder $sub) use ($now): void {
                $sub->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->where(function (Builder $sub) use ($now): void {
                $sub->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderByDesc('priority')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Group active published deals across all four services for homepage & catalog coverage.
     *
     * @return array<string, Collection<int, Deal>>
     */
    public function getPublishedDealsByService(int $perService = 3): array
    {
        $all = $this->getPublishedDeals();
        $grouped = [];

        foreach (ServiceType::cases() as $serviceType) {
            $grouped[$serviceType->value] = $all
                ->where('service_type', $serviceType)
                ->take($perService)
                ->values();
        }

        return $grouped;
    }
}
