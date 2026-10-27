<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /** Creates each default page only if it doesn't exist, so admin edits are never overwritten. */
    public function run(): void
    {
        $this->createIfMissing([
            'slug' => 'about',
            'title' => 'من نحن',
            'subtitle' => 'منصة مستقلة وُجدت لسبب واحد: أن يعرف المتداول حقيقة قنوات التوصيات ومقدمي الخدمات قبل أن يدفع، من تجارب أشخاص حقيقيين مثله.',
            'show_in_header' => true,
            'show_in_footer' => true,
            'sort_order' => 0,
            'translations' => ['en' => [
                'title' => 'About us',
                'subtitle' => 'An independent platform built for one reason: so traders can learn the truth about signal channels and service providers before they pay, from the experiences of real people like them.',
                'content' => $this->aboutEn(),
            ]],
            'content' => $this->aboutAr(),
        ]);

        $this->createIfMissing([
            'slug' => 'contact',
            'title' => 'تواصل معنا',
            'subtitle' => 'لديك سؤال، أو ملاحظة على تقييم، أو تريد إضافة مقدم خدمة أو حذف حسابك؟ راسلنا وسنرد عليك في أقرب وقت.',
            'show_in_header' => true,
            'show_in_footer' => true,
            'sort_order' => 1,
            'translations' => ['en' => [
                'title' => 'Contact us',
                'subtitle' => 'Have a question, a concern about a review, want a provider listed or your account deleted? Message us and we will get back to you as soon as possible.',
                'content' => $this->contactEn(),
            ]],
            'content' => $this->contactAr(),
        ]);

        $this->createIfMissing([
            'slug' => 'privacy',
            'title' => 'سياسة الخصوصية',
            'subtitle' => 'كيف نجمع بياناتك ونستخدمها ونحميها، وكيف تطلب حذف حسابك. آخر تحديث: 24 أكتوبر 2026.',
            'show_in_header' => false,
            'show_in_footer' => true,
            'sort_order' => 2,
            'translations' => ['en' => [
                'title' => 'Privacy policy',
                'subtitle' => 'How we collect, use and protect your data, and how to request deletion of your account. Last updated: 24 October 2026.',
                'content' => $this->privacyEn(),
            ]],
            'content' => $this->privacyAr(),
        ]);

        $this->createIfMissing([
            'slug' => 'terms',
            'title' => 'شروط الاستخدام',
            'subtitle' => 'القواعد التي تنظّم استخدامك للمنصة وكتابة التقييمات. آخر تحديث: 27 أكتوبر 2026.',
            'show_in_header' => false,
            'show_in_footer' => true,
            'sort_order' => 3,
            'translations' => ['en' => [
                'title' => 'Terms of service',
                'subtitle' => 'The rules for using the platform and writing reviews. Last updated: 27 October 2026.',
                'content' => $this->termsEn(),
            ]],
            'content' => $this->termsAr(),
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function createIfMissing(array $attributes): void
    {
        if (! Page::where('slug', $attributes['slug'])->exists()) {
            Page::create($attributes);
        }
    }

    protected function contactAr(): string
    {
        return <<<'HTML'
<h2>متى نرد؟</h2>
<p>نراجع الرسائل يومياً، ونرد عادةً خلال <strong>2 إلى 3 أيام عمل</strong>.</p>
<h2>للبلاغ عن تقييم</h2>
<p>اختر «بلاغ عن تقييم» وأرفق رابط صفحة مقدم الخدمة واذكر عنوان التقييم وسبب البلاغ.</p>
<h2>لحذف حسابك</h2>
<p>اختر «طلب حذف حسابي وبياناتي» وأرسل الطلب من بريدك المسجّل. التفاصيل في <a href="/page/privacy">سياسة الخصوصية</a>.</p>
HTML;
    }

    protected function contactEn(): string
    {
        return <<<'HTML'
<h2>When do we reply?</h2>
<p>We check messages daily and usually reply within <strong>2 to 3 business days</strong>.</p>
<h2>Reporting a review</h2>
<p>Choose "Report a review" and include the provider page link, the review title and the reason for your report.</p>
<h2>Deleting your account</h2>
<p>Choose "Delete my account and data" and send the request from your registered email. Details are in our <a href="/page/privacy">privacy policy</a>.</p>
HTML;
    }

    protected function privacyAr(): string
    {
        return <<<'HTML'
<p>نحترم خصوصيتك ونجمع أقل قدر ممكن من البيانات اللازمة لتشغيل المنصة. توضح هذه السياسة ما نجمعه، ولماذا، وما هي حقوقك.</p>

<h2>البيانات التي نجمعها</h2>
<ul>
<li><strong>بيانات الحساب:</strong> الاسم والبريد الإلكتروني، وكلمة المرور مخزّنة بشكل مشفّر ولا يمكن لأحد قراءتها.</li>
<li><strong>التقييمات:</strong> النجوم والنص والأسباب التي تختارها، وتفضيلك بإظهار اسمك أو إخفائه.</li>
<li><strong>رسائل التواصل وطلبات إضافة مقدمي الخدمة:</strong> الاسم ووسيلة التواصل ومحتوى الرسالة.</li>
<li><strong>بيانات تقنية:</strong> عنوان IP ونوع المتصفح، تُستخدم للحماية من الإساءة والرسائل المزعجة.</li>
<li><strong>عند تسجيل الدخول عبر Google أو Facebook أو Apple:</strong> نستلم اسمك وبريدك الإلكتروني فقط من تلك الخدمة، ولا نحصل على كلمة مرورك أو أي بيانات أخرى.</li>
</ul>

<h2>كيف نستخدم بياناتك</h2>
<ul>
<li>لتشغيل حسابك وتمكينك من كتابة التقييمات وتعديلها.</li>
<li>لمنع التقييمات المزيفة والمكررة، فكل حساب يقيّم مقدم الخدمة مرة واحدة.</li>
<li>للرد على رسائلك وطلباتك.</li>
</ul>
<p><strong>لا نبيع بياناتك ولا نشاركها مع مقدمي الخدمات أو أي جهة إعلانية.</strong></p>

<h2>ما الذي يظهر للعامة؟</h2>
<p>التقييمات منشورة للجميع. إذا اخترت إخفاء اسمك، يظهر التقييم باسم «مستخدم مجهول»، ولا يرى هويتك أحد سوى فريق إدارة المنصة. بريدك الإلكتروني لا يظهر للعامة أبداً.</p>

<h2>ملفات تعريف الارتباط (Cookies)</h2>
<p>نستخدم ملفات ضرورية فقط: لإبقائك مسجّلاً الدخول، ولحماية النماذج، ولتذكّر اللغة التي اخترتها. لا نستخدم ملفات تتبع إعلانية. يتم تحميل خطوط الموقع من Google Fonts، مما يعني أن Google قد يستلم عنوان IP الخاص بك عند تحميل الصفحة.</p>

<h2>مدة الاحتفاظ بالبيانات</h2>
<p>نحتفظ ببيانات حسابك وتقييماتك طالما حسابك موجود. عند حذف الحساب نحذف بياناتك الشخصية وتقييماتك نهائياً، ونحتفظ فقط برسالة طلب الحذف نفسها كسجل لتنفيذ الطلب.</p>

<h2>حقوقك</h2>
<ul>
<li>الاطلاع على بياناتك أو طلب نسخة منها.</li>
<li>تعديل تقييمك أو حذفه في أي وقت من صفحة مقدم الخدمة.</li>
<li>طلب حذف حسابك وجميع بياناتك نهائياً.</li>
</ul>

<h2>تعليمات حذف الحساب والبيانات</h2>
<p>لحذف حسابك وجميع بياناتك المرتبطة به، بما فيها البيانات التي استلمناها عبر تسجيل الدخول بـ Google أو Facebook أو Apple:</p>
<ol>
<li>افتح صفحة <a href="/contact?subject=account_deletion">تواصل معنا</a>.</li>
<li>اختر الموضوع «طلب حذف حسابي وبياناتي».</li>
<li>أرسل الطلب <strong>من نفس البريد الإلكتروني المسجّل في حسابك</strong> حتى نتمكن من التحقق منه.</li>
<li>سننفّذ الطلب خلال <strong>30 يوماً كحد أقصى</strong> ونبلغك بالبريد عند الانتهاء.</li>
</ol>
<blockquote>عند الحذف تُحذف بيانات حسابك وجميع تقييماتك نهائياً، ويُعاد حساب تقييم مقدمي الخدمات بدونها. لا يمكن التراجع عن هذه العملية.</blockquote>
<p>إذا سجلت الدخول عبر Facebook، يمكنك أيضاً إزالة صلاحية موقعنا من إعدادات حسابك في Facebook ضمن «التطبيقات والمواقع»، ثم إرسال طلب الحذف كما هو موضح أعلاه.</p>

<h2>التغييرات على هذه السياسة</h2>
<p>قد نحدّث هذه السياسة عند إضافة ميزات جديدة، وسنعدّل تاريخ «آخر تحديث» أعلى الصفحة. لأي سؤال حول خصوصيتك <a href="/contact">تواصل معنا</a>.</p>
HTML;
    }

    protected function termsAr(): string
    {
        return <<<'HTML'
<p>مرحباً بك. باستخدامك هذه المنصة أو إنشائك حساباً فيها، فإنك توافق على هذه الشروط وعلى <a href="/page/privacy">سياسة الخصوصية</a>. إذا كنت لا توافق عليها، فيُرجى عدم استخدام المنصة.</p>

<h2>1. ما هي المنصة؟</h2>
<p>منصة مستقلة تجمع تقييمات المستخدمين لقنوات التوصيات على تيليجرام، وخدمات نسخ الصفقات، ومقدمي خدمات التداول.</p>
<ul>
<li><strong>لسنا مستشاراً مالياً:</strong> لا نقدّم توصيات تداول ولا نصائح استثمارية، ولا يُعد أي محتوى على المنصة دعوة للاشتراك في خدمة معينة أو تجنّبها.</li>
<li><strong>لسنا وسيطاً:</strong> لا نبيع اشتراكات، ولا نستلم أموالاً نيابةً عن أي مقدم خدمة، ولا نتدخل في أي اتفاق بينك وبينه.</li>
<li><strong>لا نتقاضى مقابلاً من مقدمي الخدمات</strong> لرفع تقييمهم أو إخفاء أي تقييم.</li>
</ul>

<h2>2. حسابك</h2>
<ul>
<li>يجب أن يكون عمرك 18 عاماً على الأقل لإنشاء حساب أو كتابة تقييم.</li>
<li>عليك تقديم بريد إلكتروني صحيح وتأكيده، فلا يمكن نشر تقييمات قبل التأكيد.</li>
<li>حساب واحد لكل شخص. إنشاء حسابات متعددة للتأثير على التقييمات مخالفة تؤدي إلى حذفها جميعاً.</li>
<li>أنت مسؤول عن سرية كلمة مرورك وعن كل ما يُنشر من حسابك.</li>
</ul>

<h2>3. قواعد كتابة التقييمات</h2>
<p>التقييم يجب أن يعبّر عن <strong>تجربتك الشخصية الحقيقية</strong> مع مقدم الخدمة. يُسمح لكل حساب بتقييم واحد لكل مقدم خدمة، ويمكنك تعديله أو حذفه في أي وقت.</p>
<p>يُمنع نشر:</p>
<ul>
<li>تقييمات مزيفة أو مدفوعة، أو مكتوبة مقابل أي منفعة أو خصم أو اشتراك مجاني.</li>
<li>تقييمات يكتبها مقدم الخدمة لنفسه، أو منافسوه، أو أي شخص يعمل لديهم.</li>
<li>الشتائم أو التهديد أو التحريض أو أي محتوى عنصري أو مسيء.</li>
<li>بيانات شخصية لأي شخص: أرقام هواتف، عناوين، صور خاصة، أو بيانات حسابات بنكية.</li>
<li>اتهامات بارتكاب جرائم بلا أساس من تجربتك. اذكر ما حدث معك بالوقائع.</li>
<li>روابط تسويقية، أو دعوة للاشتراك في قنوات أخرى، أو أي إعلان.</li>
</ul>

<h2>4. حقوقك على ما تنشره</h2>
<p>يبقى تقييمك ملكاً لك. بنشره تمنحنا ترخيصاً غير حصري ومجانياً لعرضه على المنصة، ومشاركته في صور المشاركة وعلى حسابات المنصة في مواقع التواصل، وترجمته أو اقتباس أجزاء منه دون تغيير معناه. ينتهي هذا الترخيص عند حذفك للتقييم، باستثناء ما سبق نشره خارج المنصة.</p>

<h2>5. المراجعة والبلاغات</h2>
<ul>
<li>نراجع التقييمات، ونحتفظ بحق إخفاء أو حذف أي تقييم يخالف هذه الشروط، دون أن نكون ملزمين بمراجعة كل محتوى قبل نشره.</li>
<li>يمكن لأي زائر الإبلاغ عن تقييم مخالف، ويمكن لصاحب القناة الإبلاغ عن التقييمات المنشورة على قناته.</li>
<li><strong>لا نحذف تقييماً لمجرد أنه سلبي</strong>، ولا مقابل أي مبلغ.</li>
</ul>

<h2>6. أصحاب القنوات</h2>
<ul>
<li>تُنشأ حسابات أصحاب القنوات من قِبل إدارة المنصة بعد التحقق من الملكية، ولا يوجد تسجيل ذاتي لها.</li>
<li>يحق لصاحب القناة الرد علناً على التقييمات وتعديل بيانات قناته. كل تعديل مسجّل، وتغيير الروابط الرسمية يخضع للمراجعة.</li>
<li>لا يحق لصاحب القناة حذف أي تقييم أو تعديله، ولا كتابة تقييمات لأي قناة، ولا التصويت على التقييمات.</li>
<li>أي محاولة للتلاعب بالتقييمات، أو الضغط على المقيّمين أو تهديدهم أو مكافأتهم، تؤدي إلى إيقاف الحساب، وقد نذكر ذلك علناً في صفحة القناة.</li>
</ul>

<h2>7. إضافة القنوات وبياناتها</h2>
<ul>
<li>يمكن لأي زائر اقتراح إضافة قناة. نقبل القنوات العامة فقط، ونحتفظ بحق رفض أي طلب دون ذكر السبب.</li>
<li>اسم القناة ووصفها وصورتها وعدد مشتركيها ومتوسط مشاهداتها تُقرأ تلقائياً من صفحاتها العامة على تيليجرام، وتُحدَّث دورياً.</li>
<li>هذه الأرقام <strong>مؤشرات وليست أحكاماً</strong>: قد تتأخر أو تكون غير دقيقة، ولا نضمن صحتها.</li>
</ul>

<h2>8. أداة التحقق من الحسابات والتنبيهات</h2>
<p>تعتمد نتائج أداة «هل هذا الحساب حقيقي؟» وقائمة الحسابات المنتحلة على ما لدينا من بيانات وبلاغات مؤكدة فقط. نتيجة «غير معروف» لا تعني أن الحساب آمن. وقسم التنبيهات يعرض ما أشار إليه المقيّمون، ولا يُعد حكماً نهائياً على أي قناة.</p>

<h2>9. الاستخدام الممنوع</h2>
<ul>
<li>جمع بيانات المنصة آلياً أو نسخها بكميات كبيرة دون إذن مكتوب منا.</li>
<li>محاولة اختراق المنصة أو تعطيلها أو تجاوز أي حماية فيها.</li>
<li>انتحال صفة شخص آخر أو صفة مقدم خدمة، أو انتحال صفة المنصة نفسها.</li>
<li>استخدام المنصة لإرسال رسائل مزعجة أو للترويج لأي خدمة.</li>
</ul>

<h2>10. إخلاء المسؤولية</h2>
<blockquote>التداول في الفوركس والذهب والعملات الرقمية والأسهم ينطوي على مخاطر عالية، وقد تخسر جزءاً من رأس مالك أو كله. الأداء السابق لأي قناة لا يضمن نتائجها المستقبلية.</blockquote>
<ul>
<li>التقييمات تعبّر عن آراء أصحابها، ولا تمثّل رأي المنصة.</li>
<li>تُقدَّم المنصة «كما هي»، ولا نضمن دقة كل المحتوى أو اكتماله أو استمرار عمل الموقع دون انقطاع.</li>
<li>أي قرار تتخذه بالاشتراك في خدمة أو التداول هو قرارك ومسؤوليتك وحدك.</li>
</ul>

<h2>11. حدود مسؤوليتنا</h2>
<p>في الحدود التي يسمح بها القانون، لا نتحمّل مسؤولية أي خسارة مالية أو ضرر ينتج عن اعتمادك على محتوى المنصة، أو عن تعاملك مع أي مقدم خدمة أو أي حساب، بما في ذلك الحسابات المنتحلة.</p>

<h2>12. إيقاف الحساب وحذفه</h2>
<p>يمكنك طلب حذف حسابك في أي وقت كما هو موضح في <a href="/page/privacy">سياسة الخصوصية</a>. ويحق لنا إيقاف أي حساب أو حذفه إذا خالف هذه الشروط، وحذف تقييماته المخالفة.</p>

<h2>13. التعديلات على الشروط</h2>
<p>قد نعدّل هذه الشروط من وقت لآخر، وسنحدّث تاريخ «آخر تحديث» أعلى الصفحة. استمرارك في استخدام المنصة بعد التعديل يعني موافقتك على الشروط الجديدة.</p>

<h2>14. التواصل</h2>
<p>لأي سؤال حول هذه الشروط، أو للإبلاغ عن مخالفة، <a href="/contact">تواصل معنا</a>.</p>
HTML;
    }

    protected function termsEn(): string
    {
        return <<<'HTML'
<p>Welcome. By using this platform or creating an account, you agree to these terms and to our <a href="/page/privacy">privacy policy</a>. If you do not agree, please do not use the platform.</p>

<h2>1. What the platform is</h2>
<p>An independent platform that collects user reviews of Telegram signal channels, copy-trading services and trading service providers.</p>
<ul>
<li><strong>We are not a financial adviser:</strong> we give no trading signals or investment advice, and nothing on the platform invites you to join or avoid any service.</li>
<li><strong>We are not a broker:</strong> we sell no subscriptions, take no money on behalf of any provider, and play no part in any agreement between you and them.</li>
<li><strong>Providers cannot pay us</strong> to raise their rating or hide any review.</li>
</ul>

<h2>2. Your account</h2>
<ul>
<li>You must be at least 18 to create an account or write a review.</li>
<li>You must give a valid email address and confirm it; reviews cannot be published before that.</li>
<li>One account per person. Creating several accounts to influence ratings is a violation, and all their reviews will be removed.</li>
<li>You are responsible for keeping your password secret and for everything posted from your account.</li>
</ul>

<h2>3. Rules for reviews</h2>
<p>A review must reflect <strong>your own real experience</strong> with the provider. Each account may review a provider once, and you can edit or delete your review at any time.</p>
<p>You may not post:</p>
<ul>
<li>Fake or paid reviews, or reviews written in exchange for any benefit, discount or free subscription.</li>
<li>Reviews by a provider about themselves, by their competitors, or by anyone working for them.</li>
<li>Insults, threats, incitement, or any racist or abusive content.</li>
<li>Anyone's personal data: phone numbers, addresses, private photos or bank details.</li>
<li>Accusations of crimes that your experience does not support. Describe what happened to you, factually.</li>
<li>Promotional links, invitations to other channels, or any advertising.</li>
</ul>

<h2>4. Your rights in what you post</h2>
<p>Your review remains yours. By posting it you give us a free, non-exclusive licence to show it on the platform, share it in share images and on the platform's social accounts, and translate or quote parts of it without changing its meaning. The licence ends when you delete the review, except for what was already published outside the platform.</p>

<h2>5. Moderation and reports</h2>
<ul>
<li>We review content and may hide or remove any review that breaks these terms, without being obliged to check everything before it is published.</li>
<li>Any visitor can report a review, and channel owners can report reviews of their own channel.</li>
<li><strong>We never remove a review just because it is negative</strong>, and never for money.</li>
</ul>

<h2>6. Channel owners</h2>
<ul>
<li>Channel owner accounts are created by the platform's team after verifying ownership; there is no self sign-up.</li>
<li>Owners may reply publicly to reviews and edit their channel's details. Every edit is logged, and changes to official links are reviewed.</li>
<li>Owners cannot delete or edit any review, write reviews of any channel, or vote on reviews.</li>
<li>Any attempt to manipulate ratings, or to pressure, threaten or reward reviewers, leads to suspension, and we may say so publicly on the channel's page.</li>
</ul>

<h2>7. Adding channels and their data</h2>
<ul>
<li>Any visitor may suggest a channel. We accept public channels only and may refuse any request without giving a reason.</li>
<li>A channel's name, description, photo, subscriber count and average views are read automatically from its public Telegram pages and refreshed regularly.</li>
<li>These numbers are <strong>indicators, not verdicts</strong>: they may be delayed or inaccurate, and we do not guarantee them.</li>
</ul>

<h2>8. The account checker and alerts</h2>
<p>Results of the "Is this account real?" tool and the impersonator list rely only on the data and confirmed reports we hold. An "unknown" result does not mean an account is safe. The alerts section shows what reviewers reported and is not a final judgement on any channel.</p>

<h2>9. Prohibited use</h2>
<ul>
<li>Scraping or copying the platform's data in bulk without our written permission.</li>
<li>Trying to hack, disrupt or bypass any protection of the platform.</li>
<li>Impersonating another person, a provider, or the platform itself.</li>
<li>Using the platform to send spam or promote any service.</li>
</ul>

<h2>10. Disclaimer</h2>
<blockquote>Trading forex, gold, cryptocurrencies and stocks carries high risk, and you may lose some or all of your capital. A channel's past performance does not guarantee future results.</blockquote>
<ul>
<li>Reviews reflect their authors' opinions, not the platform's.</li>
<li>The platform is provided "as is". We do not guarantee that all content is accurate or complete, or that the site will run without interruption.</li>
<li>Any decision to subscribe to a service or to trade is yours alone.</li>
</ul>

<h2>11. Limitation of liability</h2>
<p>To the extent the law allows, we are not liable for any financial loss or damage resulting from your reliance on the platform's content, or from your dealings with any provider or account, including impersonator accounts.</p>

<h2>12. Suspension and deletion</h2>
<p>You can ask for your account to be deleted at any time as described in the <a href="/page/privacy">privacy policy</a>. We may suspend or delete any account that breaks these terms, and remove its violating reviews.</p>

<h2>13. Changes to these terms</h2>
<p>We may update these terms from time to time and will change the "Last updated" date at the top of the page. Continuing to use the platform after a change means you accept the new terms.</p>

<h2>14. Contact</h2>
<p>For any question about these terms, or to report a violation, <a href="/contact">contact us</a>.</p>
HTML;
    }

    protected function privacyEn(): string
    {
        return <<<'HTML'
<p>We respect your privacy and collect only the data needed to run the platform. This policy explains what we collect, why, and what your rights are.</p>

<h2>Data we collect</h2>
<ul>
<li><strong>Account data:</strong> your name and email address. Your password is stored hashed and cannot be read by anyone.</li>
<li><strong>Reviews:</strong> the stars, text and reasons you choose, and whether you want your name shown or hidden.</li>
<li><strong>Contact messages and provider submissions:</strong> your name, contact details and message.</li>
<li><strong>Technical data:</strong> IP address and browser type, used to protect the site from abuse and spam.</li>
<li><strong>If you sign in with Google, Facebook or Apple:</strong> we receive only your name and email address from that service, never your password or any other data.</li>
</ul>

<h2>How we use your data</h2>
<ul>
<li>To run your account and let you write and edit reviews.</li>
<li>To prevent fake and duplicate reviews: each account can review a provider once.</li>
<li>To reply to your messages and requests.</li>
</ul>
<p><strong>We do not sell your data or share it with service providers or advertisers.</strong></p>

<h2>What is public?</h2>
<p>Reviews are public. If you choose to hide your name, your review appears as "Anonymous user" and only the platform's admin team can see who you are. Your email address is never shown publicly.</p>

<h2>Cookies</h2>
<p>We use essential cookies only: to keep you logged in, to protect forms, and to remember your language. We do not use advertising trackers. The site's fonts are loaded from Google Fonts, which means Google may receive your IP address when a page loads.</p>

<h2>How long we keep data</h2>
<p>We keep your account data and reviews for as long as your account exists. When an account is deleted we permanently delete its personal data and reviews, keeping only the deletion request itself as a record that it was carried out.</p>

<h2>Your rights</h2>
<ul>
<li>Access your data or request a copy of it.</li>
<li>Edit or delete your review at any time from the provider page.</li>
<li>Request permanent deletion of your account and all of your data.</li>
</ul>

<h2>Account and data deletion instructions</h2>
<p>To delete your account and all associated data, including any data we received when you signed in with Google, Facebook or Apple:</p>
<ol>
<li>Open the <a href="/contact?subject=account_deletion">Contact us</a> page.</li>
<li>Choose the subject "Delete my account and data".</li>
<li>Send the request <strong>from the same email address registered on your account</strong> so we can verify it.</li>
<li>We will complete the request within <strong>30 days at most</strong> and email you when it is done.</li>
</ol>
<blockquote>Deletion permanently removes your account data and all of your reviews, and providers' ratings are recalculated without them. This cannot be undone.</blockquote>
<p>If you signed in with Facebook, you can also remove our site's access from your Facebook settings under "Apps and websites", then send the deletion request as described above.</p>

<h2>Changes to this policy</h2>
<p>We may update this policy when we add new features, and we will change the "Last updated" date at the top of the page. For any privacy question, <a href="/contact">contact us</a>.</p>
HTML;
    }

    protected function aboutAr(): string
    {
        return <<<'HTML'
<p>في السنوات الأخيرة امتلأت منصات التواصل وتيليجرام بقنوات التوصيات وخدمات نسخ الصفقات وإدارة الحسابات. بعضها يقدّم قيمة حقيقية، لكن كثيراً منها يديره أشخاص <strong>بلا خبرة حقيقية في التداول</strong>، وكل ما يملكونه هو <strong>مهارة عالية في التسويق</strong>.</p>

<h2>لماذا أنشأنا هذه المنصة؟</h2>
<p>رأينا القصة نفسها تتكرر مرة بعد مرة: متداول مبتدئ يرى صور أرباح ضخمة وإعلانات مقنعة ووعوداً بدخل سريع، فيشترك ويدفع ويتبع التوصيات، ثم يخسر جزءاً كبيراً من رأس ماله. وغالباً لا يجد أي مكان يعرف فيه مسبقاً تجارب من سبقوه.</p>
<ul>
<li>صور أرباح منتقاة، بينما تُحذف الصفقات الخاسرة بصمت.</li>
<li>توصيات بلا وقف خسارة ولا إدارة مخاطر واضحة.</li>
<li>ضغط مستمر للاشتراك في VIP أو للإيداع لدى وسيط معين.</li>
<li>تعليقات وشهادات إيجابية مصطنعة، وحذف أي رأي ينتقد.</li>
</ul>
<p>المشكلة لم تكن في قلة القنوات، بل في <strong>غياب مكان محايد</strong> يجمع آراء الناس الحقيقية عنها. لذلك أنشأنا هذه المنصة.</p>

<h2>ما الذي نقدمه؟</h2>
<ul>
<li><strong>صفحة لكل مقدم خدمة</strong> فيها نوع الخدمة ورابط قناة التيليجرام الرسمي وحساباته على السوشيال ميديا، حتى تتأكد أنك تتعامل مع الجهة الصحيحة.</li>
<li><strong>تقييمات من مستخدمين حقيقيين</strong>: تقييم عام، وتقييم لكل معيار (دقة التوصيات، الشفافية، الدعم، القيمة مقابل السعر).</li>
<li><strong>تحليل لسبب التقييم</strong>: لا نكتفي بعدد النجوم، بل نوضح أكثر ما أشاد به الناس وأكثر ما انتقدوه، ونطلق تنبيهاً واضحاً عندما يتكرر الحديث عن حذف الصفقات أو النتائج المضللة.</li>
</ul>

<h2>كيف نحافظ على الشفافية والمصداقية؟</h2>
<ul>
<li>كل مستخدم يستطيع تقييم مقدم الخدمة <strong>مرة واحدة فقط</strong>، ويمكنه تعديل تقييمه لاحقاً إذا تغيّرت تجربته.</li>
<li>يمكنك نشر تقييمك <strong>باسمك أو كمستخدم مجهول</strong>، حتى تقول رأيك بحرية دون خوف. هويتك لا يعرفها إلا فريق الإدارة.</li>
<li><strong>مقدم الخدمة لا يستطيع حذف أي تقييم أو تعديله</strong>، لكن يحق له الرد عليه علناً.</li>
<li>لا نقبل أي مقابل لحذف التقييمات السلبية أو لرفع التقييم. التقييم يعكس آراء المستخدمين فقط.</li>
<li>نراجع التقييمات ونحذف فقط ما يحتوي على إساءة أو معلومات شخصية أو ما يتبيّن أنه مزيّف.</li>
</ul>

<h2>نصيحتنا قبل أن تشترك في أي خدمة</h2>
<ul>
<li>اقرأ التقييمات السلبية قبل الإيجابية، وانتبه لتكرار نفس الشكوى.</li>
<li>لا تثق بصور الأرباح وحدها. اطلب سجل نتائج كاملاً وموثقاً يشمل الصفقات الخاسرة.</li>
<li>أي توصية بدون وقف خسارة علامة خطر.</li>
<li>لا ترسل أموالاً لأي شخص يتواصل معك بشكل خاص مدعياً أنه مقدم الخدمة.</li>
<li>ابدأ بمبلغ صغير أو بحساب تجريبي، ولا تتداول بأموال لا تستطيع تحمّل خسارتها.</li>
</ul>

<h2>رسالتنا</h2>
<blockquote>أن يتخذ كل متداول قراره بناءً على تجارب حقيقية، لا على قوة الإعلان. كل تقييم صادق تكتبه قد يحمي شخصاً آخر من خسارة كان يمكن تجنبها.</blockquote>
<p>المنصة لا تقدّم توصيات ولا نصائح مالية، ولا تملك أو تدير أياً من الخدمات المعروضة. نحن فقط نجمع أصوات المستخدمين ونعرضها بوضوح.</p>
HTML;
    }

    protected function aboutEn(): string
    {
        return <<<'HTML'
<p>In recent years, social media and Telegram have filled up with signal channels, copy-trading services and account managers. Some offer real value, but many are run by people with <strong>no real trading experience</strong> whose main skill is <strong>very effective marketing</strong>.</p>

<h2>Why we built this platform</h2>
<p>We kept seeing the same story: a beginner sees huge profit screenshots, convincing ads and promises of quick income, subscribes, pays, follows the signals, and loses a large part of their capital. Usually there was nowhere to learn beforehand what happened to the people who came before them.</p>
<ul>
<li>Cherry-picked profit screenshots, while losing trades are quietly deleted.</li>
<li>Signals with no stop loss and no clear risk management.</li>
<li>Constant pressure to buy VIP or deposit with a specific broker.</li>
<li>Fake testimonials, and any critical comment removed.</li>
</ul>
<p>The problem was never a shortage of channels. It was the <strong>lack of a neutral place</strong> that collects what real people think of them. So we built one.</p>

<h2>What we offer</h2>
<ul>
<li><strong>A page for every provider</strong> with the type of service, the official Telegram channel and social media accounts, so you know you're dealing with the right one.</li>
<li><strong>Reviews from real users</strong>: an overall rating plus ratings per criterion (signal accuracy, transparency, support, value for money).</li>
<li><strong>Why the rating is what it is</strong>: beyond the stars, we show what people praise and criticise most, and raise a clear warning when deleted trades or misleading results come up repeatedly.</li>
</ul>

<h2>How we keep it transparent and honest</h2>
<ul>
<li>Each user can review a provider <strong>only once</strong>, and can update the review later if their experience changes.</li>
<li>You can post <strong>under your name or anonymously</strong>, so you can speak freely. Only our admin team knows who you are.</li>
<li><strong>Providers cannot delete or edit any review</strong>, but they may reply publicly.</li>
<li>We never accept payment to remove negative reviews or boost a rating. Ratings reflect users' opinions only.</li>
<li>We moderate reviews and remove only those containing abuse, personal information, or that turn out to be fake.</li>
</ul>

<h2>Our advice before you subscribe</h2>
<ul>
<li>Read the negative reviews before the positive ones, and watch for the same complaint repeating.</li>
<li>Don't trust profit screenshots alone. Ask for a complete, verified track record that includes losing trades.</li>
<li>Any signal without a stop loss is a red flag.</li>
<li>Never send money to anyone who contacts you privately claiming to be the provider.</li>
<li>Start small or on a demo account, and never trade money you can't afford to lose.</li>
</ul>

<h2>Our mission</h2>
<blockquote>For every trader to decide based on real experiences, not on the strength of the advertising. Every honest review you write could protect someone else from a loss that could have been avoided.</blockquote>
<p>This platform does not provide signals or financial advice, and does not own or operate any of the listed services. We simply collect users' voices and present them clearly.</p>
HTML;
    }
}
