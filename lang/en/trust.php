<?php

return [
    'criteria' => [
        'rating_accuracy' => 'Signal / result accuracy',
        'rating_transparency' => 'Transparency & honesty',
        'rating_support' => 'Communication & support',
        'rating_value' => 'Value for money',
    ],

    'pros' => [
        'accurate' => 'Accurate signals',
        'transparent' => 'Documented, transparent results',
        'risk_mgmt' => 'Clear risk management',
        'fast_support' => 'Fast replies',
        'education' => 'Useful educational content',
        'fair_price' => 'Fair price',
        'consistent' => 'Consistent and active',
        'profitable' => 'Real profits',
    ],

    'cons' => [
        'losses' => 'Frequent losses',
        'deleted_trades' => 'Deletes losing trades',
        'fake_results' => 'Misleading or inflated results',
        'no_stoploss' => 'No stop loss',
        'slow_support' => 'Poor or slow support',
        'expensive' => 'Overpriced',
        'pressure' => 'Pressure to subscribe or deposit',
        'inactive' => 'Low activity',
        'scam' => 'Suspected scam',
    ],

    'experience_durations' => [
        'lt_1m' => 'Less than a month',
        '1_3m' => '1 - 3 months',
        '3_12m' => '3 - 12 months',
        'gt_1y' => 'More than a year',
    ],

    'pricing_options' => [
        'free' => 'Free',
        'paid' => 'Paid',
        'freemium' => 'Free + paid VIP',
        'commission' => 'Profit commission',
    ],

    'socials' => [
        'telegram_url' => 'Telegram',
        'website_url' => 'Website',
        'twitter_url' => 'X / Twitter',
        'instagram_url' => 'Instagram',
        'youtube_url' => 'YouTube',
        'tiktok_url' => 'TikTok',
        'facebook_url' => 'Facebook',
    ],

    'relations' => [
        'owner' => 'I own this service',
        'user' => "I'm a user/subscriber and want it listed",
    ],

    'contact_subjects' => [
        'general' => 'General question',
        'review_issue' => 'Report a review (fake or abusive)',
        'provider_listing' => 'About a provider page',
        'account_deletion' => 'Delete my account and data',
        'other' => 'Other',
    ],

    'account_platforms' => [
        'telegram' => 'Telegram',
        'instagram' => 'Instagram',
        'x' => 'X / Twitter',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'facebook' => 'Facebook',
        'whatsapp' => 'WhatsApp',
        'website' => 'Website',
        'other' => 'Other',
    ],

    'report_reasons' => [
        'fake' => 'Fake or paid review',
        'abusive' => 'Abusive or offensive',
        'personal_info' => 'Contains personal information',
        'spam' => 'Advertising or spam',
        'other' => 'Other reason',
    ],

    'review_sorts' => [
        'helpful' => 'Most helpful',
        'newest' => 'Newest',
        'highest' => 'Highest rated',
        'lowest' => 'Lowest rated',
    ],

    'review_statuses' => [
        'pending' => 'Pending review',
        'approved' => 'Published',
        'rejected' => 'Rejected',
    ],

    'rating_labels' => [
        'excellent' => 'Excellent',
        'great' => 'Great',
        'average' => 'Average',
        'poor' => 'Poor',
        'bad' => 'Bad',
        'none' => 'No reviews yet',
    ],

    'analysis' => [
        'empty' => 'No reviews for this provider yet. Be the first to share your experience.',
        'overall' => 'Current rating is :avg out of 5 (:label) based on :count reviews: :positive% positive and :negative% negative.',
        'best_worst' => 'Reviewers rate «:best» highest (:best_avg/5) and «:worst» lowest (:worst_avg/5).',
        'balanced' => 'Criteria ratings are close to each other; the highest is «:best» at :best_avg/5.',
        'top_pros' => 'Most praised: :list.',
        'top_cons' => 'Main complaints: :list.',
        'red_flags' => '⚠️ Warning: a notable share of reviewers reported serious red flags (deleted trades / misleading results / suspected scam). Check carefully before subscribing.',
        'trend_up' => 'Recent reviews (last 90 days) are improving, averaging :avg/5.',
        'trend_down' => 'Recent reviews (last 90 days) are declining, averaging :avg/5.',
        'trend_stable' => 'Recent reviews are stable, averaging :avg/5.',
        'few_reviews' => 'There are only a few reviews, so the rating may not reflect the full picture yet.',
        'list_separator' => ', ',
    ],
];
