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
