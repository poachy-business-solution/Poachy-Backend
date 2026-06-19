<?php

namespace App\Enums\Central;

enum TrackEvent: string
{
    case PageView               = 'page_view';
    case ProductView            = 'product_view';
    case Search                 = 'search';
    case FilterUsed             = 'filter_used';
    case AddToCart              = 'add_to_cart';
    case RemoveFromCart         = 'remove_from_cart';
    case AddToWishlist          = 'add_to_wishlist';
    case CheckoutStarted        = 'checkout_started';
    case CheckoutStepCompleted  = 'checkout_step_completed';
    case Purchase               = 'purchase';
    case ReviewWritten          = 'review_written';
    case MerchantFollowed       = 'merchant_followed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function deduplicatedEvents(): array
    {
        return [
            self::AddToCart,
            self::AddToWishlist,
            self::CheckoutStarted,
        ];
    }

    public function shouldDeduplicate(): bool
    {
        return in_array($this, self::deduplicatedEvents());
    }

    public function label(): string
    {
        return match($this) {
            self::PageView              => 'Page View',
            self::ProductView           => 'Product View',
            self::Search                => 'Search',
            self::FilterUsed            => 'Filter Used',
            self::AddToCart             => 'Add to Cart',
            self::RemoveFromCart        => 'Remove from Cart',
            self::AddToWishlist         => 'Add to Wishlist',
            self::CheckoutStarted       => 'Checkout Started',
            self::CheckoutStepCompleted => 'Checkout Step Completed',
            self::Purchase              => 'Purchase',
            self::ReviewWritten         => 'Review Written',
            self::MerchantFollowed      => 'Merchant Followed',
        };
    }
}