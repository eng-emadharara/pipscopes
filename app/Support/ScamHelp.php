<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * "I was scammed, what now?": the kinds of scam and ways of paying the visitor picks from, the steps
 * we suggest for each, and which case (one per account, wallet or broker) a report joins.
 * The advice is general and never promises the money back; it is not legal advice.
 */
class ScamHelp
{
    /** Confirmed reports a case needs before the team acts on it (the admin is told when it gets there) */
    public const ACT_AT = 3;

    /** Card disputes are usually accepted within about this many days of paying */
    public const CARD_DAYS = 120;

    /** Wallet disputes (PayPal and the like) */
    public const WALLET_DAYS = 180;

    public const TYPES = ['subscription', 'broker', 'manager', 'impersonator', 'recovery'];

    public const PAYMENTS = ['card', 'bank', 'crypto', 'ewallet', 'cash'];

    /** Approximate US dollars per unit, to add up losses (the pegged Gulf and Jordanian currencies are exact) */
    public const CURRENCIES = [
        'USD' => 1, 'USDT' => 1, 'EUR' => 1.08, 'GBP' => 1.27, 'SAR' => 1 / 3.75, 'AED' => 1 / 3.6725,
        'QAR' => 1 / 3.64, 'KWD' => 3.25, 'JOD' => 1 / 0.709, 'EGP' => 0.02, 'MAD' => 0.1, 'IQD' => 0.00076,
    ];

    /** What the team can do with a case; shown to its reporters */
    public const ACTIONS = ['telegram', 'regulator', 'exchange', 'warning', 'contacted', 'result', 'other'];

    /** @return array<string, array{0: string, 1: string, 2: string}> key => [emoji, label, hint] */
    public static function types(): array
    {
        return [
            'subscription' => ['💳', __('دفعت اشتراكاً ولم أحصل على شيء'), __('اختفت القناة، أو توقفت التوصيات، أو حظروني بعد الدفع.')],
            'broker' => ['🏦', __('وسيط لا يسمح لي بالسحب'), __('سجّلت عند وسيط نصحتني به القناة، والآن لا أستطيع سحب أموالي.')],
            'manager' => ['🧑‍💼', __('حوّلت لـ«مدير حساب» ليدير أموالي'), __('وعدني بأرباح ثابتة مقابل إدارة حسابي أو استثمار أموالي.')],
            'impersonator' => ['🎭', __('حساب انتحل اسم قناة معروفة'), __('راسلني على الخاص باسم قناة أعرفها وأخذ مني المال.')],
            'recovery' => ['🔁', __('شخص وعدني باسترجاع أموالي مقابل رسوم'), __('بعد أن خسرت، تواصل معي من يدّعي أنه يسترجع الأموال.')],
        ];
    }

    /** @return array<string, string> */
    public static function payments(): array
    {
        return [
            'card' => __('بطاقة بنكية (فيزا أو ماستركارد)'),
            'bank' => __('تحويل بنكي'),
            'crypto' => __('عملات رقمية (USDT أو غيرها)'),
            'ewallet' => __('محفظة إلكترونية (PayPal، Skrill…)'),
            'cash' => __('نقداً أو طريقة أخرى'),
        ];
    }

    /** @return array<string, string> */
    public static function actions(): array
    {
        return [
            'telegram' => __('أبلغنا تيليجرام'),
            'regulator' => __('راسلنا الجهة الرقابية'),
            'exchange' => __('أبلغنا منصة التحويل'),
            'warning' => __('نشرنا تحذيراً عاماً'),
            'contacted' => __('تواصلنا مع الجهة'),
            'result' => __('نتيجة'),
            'other' => __('إجراء آخر'),
        ];
    }

    public static function validType(?string $type): ?string
    {
        return in_array($type, self::TYPES, true) ? $type : null;
    }

    public static function validPayment(?string $payment): ?string
    {
        return in_array($payment, self::PAYMENTS, true) ? $payment : null;
    }

    /**
     * The steps for one situation, most urgent first.
     *
     * @return list<array{key: string, icon: string, title: string, text: string, urgent: bool}>
     */
    public static function advice(?string $type, ?string $payment, ?Carbon $paidAt = null): array
    {
        $days = $paidAt ? max(0, (int) $paidAt->copy()->startOfDay()->diffInDays(now()->startOfDay())) : null;
        $steps = [];
        $add = function (string $key, string $icon, string $title, string $text, bool $urgent = false) use (&$steps) {
            $steps[] = compact('key', 'icon', 'title', 'text', 'urgent');
        };

        $add('stop', '🛑', __('أوقف أي تحويل جديد فوراً'),
            $type === 'broker'
                ? __('إذا طلبوا منك «رسوم سحب» أو «ضريبة» أو «إيداعاً إضافياً» لفتح السحب، فهذه خطوة ثانية من النصب نفسه. لا تدفع شيئاً.')
                : __('مهما وعدوك بأن المبلغ سيعود أو أن الربح قريب، لا ترسل أي مبلغ آخر.'), true);

        if ($type === 'recovery') {
            $add('recovery', '⚠️', __('هذا غالباً نصب ثانٍ'),
                __('لا توجد جهة حقيقية تطلب رسوماً مقدماً لاسترجاع أموالك. «شركات الاسترداد» و«المحامون» و«الهاكرز» الذين يراسلونك بعد الخسارة يستهدفون الضحايا أنفسهم. أوقف التواصل معهم.'), true);
        }

        if ($type === 'manager') {
            $add('access', '🔐', __('اسحب صلاحياتهم على حساباتك'),
                __('غيّر كلمة مرور حساب التداول والبريد، واحذف أي برنامج تحكم عن بعد ثبّتوه لك (AnyDesk أو TeamViewer)، ولا ترسل لهم أي رمز تحقق.'), true);
        }

        $add('evidence', '📸', __('احفظ الأدلة الآن قبل أن تُحذف'),
            __('صوّر المحادثات مع اسم الحساب ورابطه، ومنشورات القناة، وإيصالات الدفع، ورقم العملية. القنوات والحسابات النصابة تحذف كل شيء بسرعة.'), true);

        match ($payment) {
            'card' => $add('card', '💳', __('اتصل ببنكك اليوم واطلب استرجاع المبلغ (Chargeback)'),
                ($days !== null && $days > self::CARD_DAYS
                    ? __('مر :days يوماً على الدفع، وهذا قد يتجاوز المهلة المعتادة، لكن اسأل البنك على أي حال.', ['days' => $days])
                    : ($days !== null ? __('مر :days يوماً على الدفع. ', ['days' => $days]) : '')
                        .__('البنوك تقبل الاعتراض عادةً خلال مدة محدودة من تاريخ الدفع (كثير منها نحو :n يوماً)، فكلما أسرعت كانت فرصتك أكبر.', ['n' => self::CARD_DAYS]))
                .' '.__('قل للبنك إنها عملية احتيال، وأرسل لهم الأدلة.'), true),
            'bank' => $add('bank', '🏦', __('اطلب من بنكك استرجاع الحوالة فوراً'),
                __('أحياناً يمكن إيقاف الحوالة أو استرجاعها إذا تحركت خلال ساعات أو أيام قليلة. اطلب تقديم بلاغ احتيال رسمي، واحتفظ برقمه.'), true),
            'crypto' => $add('crypto', '🪙', __('أبلغ المنصة التي حوّلت منها'),
                __('تحويلات العملات الرقمية لا يمكن عكسها، لكن إذا حوّلت من منصة مثل Binance أو OKX فراسل دعمها برقم العملية (TXID) وعنوان المحفظة المستلمة. المنصات قد تجمّد الحساب المستلم إذا كان لديها.'), true),
            'ewallet' => $add('ewallet', '👛', __('افتح نزاعاً من داخل المحفظة'),
                __('محافظ مثل PayPal تسمح بفتح نزاع على العملية لفترة محدودة (غالباً حتى :n يوماً). افتحه الآن واختر أنها عملية احتيال.', ['n' => self::WALLET_DAYS]), true),
            default => null,
        };

        if ($type === 'broker') {
            $add('regulator', '🏛', __('اشتكِ للجهة الرقابية للوسيط'),
                __('ابحث في موقع الوسيط عن رقم ترخيصه والجهة التي تراقبه، وتحقق منه في موقع الجهة نفسها. إذا كان مرخصاً فقدّم شكوى رسمية لها، وإن لم يكن مرخصاً فأبلغها أيضاً، فهي تنشر تحذيرات عن هذه الشركات.'));
        }

        $add('telegram', '🚩', __('أبلغ تيليجرام عن الحساب أو القناة'),
            $type === 'impersonator'
                ? __('من صفحة الحساب اختر «إبلاغ» ثم «انتحال»، وأخبر القناة الحقيقية حتى تحذّر مشتركيها. يمكنك أيضاً مراسلة حساب تيليجرام الرسمي ‎@notoscam‎.')
                : __('من صفحة الحساب أو القناة اختر «إبلاغ» ثم «احتيال». يمكنك أيضاً مراسلة حساب تيليجرام الرسمي ‎@notoscam‎ بالتفاصيل.'));

        $add('police', '👮', __('قدّم بلاغاً للجرائم الإلكترونية في بلدك'),
            __('معظم الدول لديها وحدة للجرائم الإلكترونية تستقبل البلاغات. خذ معك الأدلة، والملف الجماعي من PipScopes إن صار لبلاغك ملف.'));

        if ($type !== 'recovery') {
            $add('next', '⚠️', __('احذر من النصبة التالية'),
                __('بعد الخسارة سيتواصل معك من يعدك باسترجاع أموالك مقابل رسوم. لا أحد يسترجع الأموال مقابل دفعة مقدمة.'));
        }

        return $steps;
    }

    /**
     * The case a report belongs to: the account it names, else the wallet, else the broker.
     *
     * @return array{type: string, key: string, display: string}|null
     */
    public static function target(?string $account, ?string $wallet, ?string $broker): ?array
    {
        if (filled($account) && ($parsed = AccountHandle::parse($account))) {
            return ['type' => $parsed['platform'], 'key' => $parsed['key'], 'display' => $parsed['display']];
        }

        if (filled($wallet) && ($address = self::wallet($wallet))) {
            return ['type' => 'wallet', 'key' => 'wallet:'.Str::lower($address), 'display' => $address];
        }

        if (filled($broker)) {
            $name = trim(preg_replace('/\s+/u', ' ', $broker));

            return ['type' => 'broker', 'key' => 'broker:'.Str::lower(preg_replace('/[^\pL\pN]+/u', '', $name)), 'display' => $name];
        }

        return null;
    }

    /** A wallet address as written, without spaces; null when it can't be one */
    public static function wallet(?string $input): ?string
    {
        $address = preg_replace('/\s+/', '', (string) $input);

        return preg_match('/^[A-Za-z0-9]{20,100}$/', $address) ? $address : null;
    }

    public static function toUsd(?float $amount, ?string $currency): ?float
    {
        if ($amount === null || ! isset(self::CURRENCIES[$currency])) {
            return null;
        }

        return round($amount * self::CURRENCIES[$currency], 2);
    }
}
