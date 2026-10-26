<?php

return [
    'criteria' => [
        'rating_accuracy' => 'دقة التوصيات / النتائج',
        'rating_transparency' => 'الشفافية والمصداقية',
        'rating_support' => 'التواصل والدعم',
        'rating_value' => 'القيمة مقابل السعر',
    ],

    'pros' => [
        'accurate' => 'توصيات دقيقة',
        'transparent' => 'نتائج موثقة وشفافة',
        'risk_mgmt' => 'إدارة مخاطر واضحة',
        'fast_support' => 'رد سريع على الاستفسارات',
        'education' => 'محتوى تعليمي مفيد',
        'fair_price' => 'سعر مناسب',
        'consistent' => 'التزام ونشاط مستمر',
        'profitable' => 'أرباح فعلية',
    ],

    'cons' => [
        'losses' => 'خسائر متكررة',
        'deleted_trades' => 'حذف الصفقات الخاسرة',
        'fake_results' => 'نتائج مضللة أو مبالغ فيها',
        'no_stoploss' => 'بدون وقف خسارة',
        'slow_support' => 'دعم ضعيف أو بطيء',
        'expensive' => 'سعر مبالغ فيه',
        'pressure' => 'ضغط للاشتراك أو الإيداع',
        'inactive' => 'قلة النشاط',
        'scam' => 'شبهة احتيال',
    ],

    'experience_durations' => [
        'lt_1m' => 'أقل من شهر',
        '1_3m' => '1 - 3 أشهر',
        '3_12m' => '3 - 12 شهر',
        'gt_1y' => 'أكثر من سنة',
    ],

    'pricing_options' => [
        'free' => 'مجاني',
        'paid' => 'مدفوع',
        'freemium' => 'مجاني + VIP مدفوع',
        'commission' => 'عمولة على الأرباح',
    ],

    'socials' => [
        'telegram_url' => 'تيليجرام',
        'website_url' => 'الموقع',
        'twitter_url' => 'X / تويتر',
        'instagram_url' => 'إنستغرام',
        'youtube_url' => 'يوتيوب',
        'tiktok_url' => 'تيك توك',
        'facebook_url' => 'فيسبوك',
    ],

    'relations' => [
        'owner' => 'أنا صاحب الخدمة',
        'user' => 'أنا مستخدم/مشترك وأرغب بإضافتها',
    ],

    'contact_subjects' => [
        'general' => 'استفسار عام',
        'review_issue' => 'بلاغ عن تقييم (مزيف أو مسيء)',
        'provider_listing' => 'بخصوص صفحة مقدم خدمة',
        'account_deletion' => 'طلب حذف حسابي وبياناتي',
        'other' => 'أخرى',
    ],

    'account_platforms' => [
        'telegram' => 'تيليجرام',
        'instagram' => 'إنستغرام',
        'x' => 'X / تويتر',
        'tiktok' => 'تيك توك',
        'youtube' => 'يوتيوب',
        'facebook' => 'فيسبوك',
        'whatsapp' => 'واتساب',
        'website' => 'موقع إلكتروني',
        'other' => 'أخرى',
    ],

    'report_reasons' => [
        'fake' => 'تقييم مزيّف أو مدفوع',
        'abusive' => 'محتوى مسيء أو شتائم',
        'personal_info' => 'يحتوي على معلومات شخصية',
        'spam' => 'إعلان أو رسائل مزعجة',
        'other' => 'سبب آخر',
    ],

    'review_sorts' => [
        'helpful' => 'الأكثر فائدة',
        'newest' => 'الأحدث',
        'highest' => 'الأعلى تقييماً',
        'lowest' => 'الأقل تقييماً',
    ],

    'review_statuses' => [
        'pending' => 'بانتظار المراجعة',
        'approved' => 'منشور',
        'rejected' => 'مرفوض',
    ],

    'rating_labels' => [
        'excellent' => 'ممتاز',
        'great' => 'جيد جداً',
        'average' => 'جيد',
        'poor' => 'ضعيف',
        'bad' => 'سيئ',
        'none' => 'لا توجد تقييمات',
    ],

    'analysis' => [
        'empty' => 'لا توجد تقييمات بعد لهذا المزود. كن أول من يشارك تجربته.',
        'overall' => 'التقييم الحالي :avg من 5 (:label) بناءً على :count تقييم: :positive% إيجابية و:negative% سلبية.',
        'best_worst' => 'أقوى نقطة حسب المقيّمين هي «:best» (:best_avg/5)، وأضعفها «:worst» (:worst_avg/5).',
        'balanced' => 'تقييمات المعايير متقاربة، أعلاها «:best» بمعدل :best_avg/5.',
        'top_pros' => 'أكثر ما أشاد به المستخدمون: :list.',
        'top_cons' => 'أبرز الانتقادات: :list.',
        'red_flags' => '⚠️ تنبيه: نسبة ملحوظة من المقيّمين أشارت إلى مؤشرات خطيرة (حذف صفقات/نتائج مضللة/شبهة احتيال). تحقّق جيداً قبل الاشتراك.',
        'trend_up' => 'التقييمات الأخيرة (آخر 90 يوماً) في تحسّن بمعدل :avg/5.',
        'trend_down' => 'التقييمات الأخيرة (آخر 90 يوماً) في تراجع بمعدل :avg/5.',
        'trend_stable' => 'التقييمات الأخيرة مستقرة بمعدل :avg/5.',
        'few_reviews' => 'عدد التقييمات قليل، لذلك قد لا يعكس التقييم الصورة الكاملة بعد.',
        'list_separator' => '، ',
    ],
];
