<?php

declare(strict_types=1);

use App\Domain\Booking\Enums\DealStatus;
use App\Domain\Booking\Enums\PromotionDiscountType;
use App\Domain\Booking\Enums\PromotionScope;
use App\Domain\Booking\Enums\PromotionStatus;
use App\Domain\Booking\Enums\ReviewStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Domain\Identity\Role;
use App\Filament\Resources\Offers\Deals\Pages\ManageDeals;
use App\Filament\Resources\Offers\Promotions\Pages\ManagePromotions;
use App\Filament\Resources\Offers\Reviews\Pages\ManageReviews;
use App\Models\Booking;
use App\Models\Deal;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->owner = User::factory()->create([
        'token_version' => 1,
    ]);
    $this->owner->assignRole(Role::OwnerAdmin->value);

    $this->staff = User::factory()->create([
        'token_version' => 1,
    ]);
    $this->staff->assignRole(Role::Staff->value);
    // Staff role does not have Permission::OffersManage ('offers.manage')
});

test('unprivileged staff cannot access deals, promotions, or reviews management', function (): void {
    $this->actingAs($this->staff);

    Livewire::test(ManageDeals::class)
        ->assertForbidden();

    Livewire::test(ManagePromotions::class)
        ->assertForbidden();

    Livewire::test(ManageReviews::class)
        ->assertForbidden();
});

test('authorized owner can access and manage deals', function (): void {
    $this->actingAs($this->owner);

    Livewire::test(ManageDeals::class)
        ->assertSuccessful()
        ->callAction('create', data: [
            'title' => 'Ella Scenic Train & Private Return',
            'slug' => 'ella-scenic-train-private-return',
            'service_type' => ServiceType::Ride->value,
            'summary' => 'Chauffeured pickup from Ella station and scenic highway return.',
            'description_markdown' => 'Detailed itinerary covering Ella gap and Ravana falls.',
            'preset_payload' => ['destination' => 'Ella'],
            'status' => DealStatus::Published->value,
            'is_featured' => true,
            'priority' => 10,
        ])
        ->assertHasNoActionErrors();

    expect(Deal::where('slug', 'ella-scenic-train-private-return')->exists())->toBeTrue();
});

test('authorized owner can access and manage promotions', function (): void {
    $this->actingAs($this->owner);

    Livewire::test(ManagePromotions::class)
        ->assertSuccessful()
        ->callAction('create', data: [
            'name' => 'Early Bird 10%',
            'explanation' => 'Advance booking promotion for early travelers.',
            'scope' => PromotionScope::Global->value,
            'discount_type' => PromotionDiscountType::PercentageBasisPoints->value,
            'discount_value' => 1000,
            'currency' => 'USD',
            'status' => PromotionStatus::Active->value,
            'priority' => 5,
        ])
        ->assertHasNoActionErrors();

    expect(Promotion::where('name', 'Early Bird 10%')->exists())->toBeTrue();
});

test('authorized owner can moderate reviews and toggle featured status', function (): void {
    $this->actingAs($this->owner);

    $booking = Booking::factory()->create();
    $review = Review::factory()->create([
        'booking_id' => $booking->id,
        'traveler_name' => 'Oliver Queen',
        'rating' => 5,
        'review_text' => 'Outstanding service from start to finish.',
        'status' => ReviewStatus::PendingModeration,
        'is_featured' => false,
    ]);

    Livewire::test(ManageReviews::class)
        ->assertSuccessful()
        ->callTableAction('approve', $review)
        ->assertHasNoTableActionErrors();

    $review->refresh();
    expect($review->status)->toBe(ReviewStatus::Published)
        ->and($review->moderated_by_user_id)->toBe($this->owner->id)
        ->and($review->moderated_at)->not->toBeNull();

    // Toggle featured
    Livewire::test(ManageReviews::class)
        ->callTableAction('toggle_featured', $review)
        ->assertHasNoTableActionErrors();

    $review->refresh();
    expect($review->is_featured)->toBeTrue();
});
