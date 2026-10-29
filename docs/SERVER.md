# أوامر السيرفر — PipScopes

مرجع لكل ما يلزم تشغيله على السيرفر: الكرون، أوامر أول رفع، أوامر كل تحديث، الأوامر اليدوية، وملفات الـ seed.

---

## 1. الكرون (Cron Job) — سطر واحد فقط

```bash
* * * * * cd /path/to/pipscopes && php artisan schedule:run >> /dev/null 2>&1
```

غيّر `/path/to/pipscopes` لمسار المشروع على السيرفر. هذا السطر يشغّل كل المهام التالية تلقائياً، فلا تضف لها كرونات منفصلة:

| المهمة | متى | ماذا تفعل |
|---|---|---|
| `queue:work --stop-when-empty --max-time=55 --tries=3` | كل دقيقة | يرسل الإيميلات ورسائل تيليجرام المنتظرة (إشعارات الأعضاء، الإعلانات). بدونه لا يصل أي إشعار. |
| `announcements:send-due` | كل دقيقة | يرسل الإعلانات المجدولة عند وقتها. |
| `telegram:refresh` | كل ساعة | يحدّث عدد المشتركين ومتوسط المشاهدات لدفعة من القنوات (كل قناة تقريباً مرة يومياً). |
| `telegram:compact` | يوم 1 من كل شهر 03:30 | يدمج سجلات تيليجرام اليومية القديمة في متوسطات أسبوعية وشهرية. |
| `signup:update-disposable` | كل اثنين 04:10 | يحدّث قائمة نطاقات البريد المؤقت المرفوضة عند التسجيل. |

للتأكد أن المهام مسجّلة:

```bash
php artisan schedule:list
```

> إذا كان السيرفر VPS وتريد إرسالاً أسرع للإعلانات الكبيرة، يمكن تشغيل `php artisan queue:work --tries=3` دائماً عبر Supervisor. مهمة الكرون تبقى كما هي ولا تتعارض معه.

---

## 2. أول رفع على السيرفر

بالترتيب:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env        # ثم عدّل القيم (القسم 5)
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=PageSeeder --force
php artisan db:seed --class=SiteSettingsSeeder --force
php artisan storage:link
php artisan signup:update-disposable
php artisan optimize
php artisan filament:optimize
```

ثم أنشئ حساب الأدمن (القسم 4 ← «حساب الأدمن على السيرفر»).

> ⚠️ لا تشغّل `php artisan db:seed` بدون `--class` على السيرفر الحقيقي، فهو يضيف بيانات تجريبية (اقرأ القسم 4).

---

## 3. مع كل تحديث (بعد رفع كود جديد)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
php artisan queue:restart
```

- `migrate --force` ضروري عند وجود جداول أو أعمدة جديدة، وآمن إذا لم يكن هناك جديد.
- `optimize:clear` ثم `optimize` يحدّثان كاش الإعدادات والمسارات والصفحات.
- `queue:restart` يضمن أن أي عامل قائمة شغّال يأخذ الكود الجديد.

---

## 4. ملفات الـ Seed

### `PageSeeder` — الصفحات الثابتة

```bash
php artisan db:seed --class=PageSeeder --force
```

- ينشئ 4 صفحات بالعربي والإنجليزي: **من نحن** (`about`)، **تواصل معنا** (`contact`)، **سياسة الخصوصية** (`privacy`)، **الشروط والأحكام** (`terms`).
- ينشئ الصفحة فقط إذا لم تكن موجودة، فتعديلاتك من لوحة التحكم لا تُمسح أبداً.
- آمن للتشغيل على السيرفر وفي أي وقت.

### `SiteSettingsSeeder` — إعدادات الموقع الافتراضية

```bash
php artisan db:seed --class=SiteSettingsSeeder --force
```

يملأ «إعدادات الموقع» بالقيم الافتراضية التالية، **فقط للحقول الفارغة**، فلا يغيّر شيئاً عدّلته:

- اسم الموقع (ar/en).
- وصف الموقع والكلمات المفتاحية لمحركات البحث (ar/en).
- نبذة التذييل وإخلاء المسؤولية (ar/en).
- إظهار الاسم بجانب الشعار: مفعّل.
- لون الثيم: `#191919`.
- رابط التواصل على تيليجرام: `https://t.me/pipscopes?direct`.
- منع الأرشفة (noindex): مطفأ.

آمن للتشغيل على السيرفر وفي أي وقت.

### `DatabaseSeeder` — للتجربة المحلية فقط

```bash
php artisan db:seed
```

يشغّل `PageSeeder` و`SiteSettingsSeeder`، ثم:

1. **حساب أدمن**: البريد وكلمة المرور من `ADMIN_EMAIL` و`ADMIN_PASSWORD` في `.env`. إذا لم تُضبطا يستخدم القيم المحلية المكتوبة في الملف، فلا تعتمد عليها على السيرفر.
2. **6 تصنيفات**: قنوات توصيات، نسخ الصفقات، توصيات كريبتو، تعليم وتدريب، إدارة محافظ، أدوات وبوتات. ⚠️ يعيد كتابة اسمها ووصفها إذا عدّلتها من لوحة التحكم.
3. **بيانات تجريبية** (فقط إذا لم يكن في الموقع أي قناة):
   - 25 عضواً وهمياً.
   - 8 قنوات تجريبية بروابط `t.me/demo_channel_…` وأعداد مشتركين وهمية.
   - بين 6 و18 تقييماً وهمياً لكل قناة.
   - طلب إضافة قناة تجريبي واحد.

> الأسواق (ذهب، فوركس، كريبتو…) لا تأتي من الـ seed، بل تُنشأ تلقائياً مع `migrate`.

### حساب الأدمن على السيرفر

بدل `DatabaseSeeder`، أنشئه بهذا الأمر (ضع بريدك وكلمة مرور قوية):

```bash
php artisan tinker --execute="App\Models\User::updateOrCreate(['email' => 'you@example.com'], ['name' => 'مدير المنصة', 'password' => 'كلمة-مرور-قوية', 'is_admin' => true, 'email_verified_at' => now()]);"
```

> الأمر يبقى في سجل الطرفية (history)، فغيّر كلمة المرور بعدها من الموقع، أو امسح السجل.

---

## 5. أهم قيم `.env` على السيرفر

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pipscopes.com
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=43200          # الجلسة 30 يوماً، والعضو يبقى مسجّلاً بعدها بكوكي «تذكرني»
ADMIN_SESSION_DAYS=7            # لوحة التحكم تطلب تسجيل دخول جديد كل 7 أيام
CACHE_STORE=database
MAIL_MAILER=...            # مع بيانات مزود البريد
REVIEWS_REQUIRE_APPROVAL=false   # true = كل تقييم ينتظر موافقة الأدمن
```

إعدادات تيليجرام وتسجيل الدخول الاجتماعي والإشعارات وحماية التسجيل تُضبط من لوحة التحكم، وليس من `.env`.

---

## 6. أوامر يدوية مفيدة

| الأمر | الاستخدام |
|---|---|
| `php artisan telegram:refresh --provider=ID` | تحديث أرقام قناة معيّنة الآن (ID من لوحة التحكم). |
| `php artisan telegram:refresh --limit=50 --delay=3` | تحديث دفعة أكبر من القنوات يدوياً. |
| `php artisan telegram:compact` | دمج السجلات القديمة يدوياً. |
| `php artisan providers:reindex` | إعادة بناء فهرس البحث لكل القنوات (إذا ظهر البحث ناقصاً). |
| `php artisan signup:update-disposable` | تحديث قائمة البريد المؤقت الآن (أو من زر صفحة «حماية التسجيل»). |
| `php artisan queue:failed` | عرض الرسائل التي فشل إرسالها نهائياً. |
| `php artisan queue:retry all` | إعادة محاولة كل الرسائل الفاشلة. |
| `php artisan schedule:list` | عرض المهام المجدولة ومواعيدها القادمة. |
| `php artisan telegram:demo` / `--remove` | **محلي فقط**: إضافة أو حذف أرقام تيليجرام تجريبية لتجربة الرسم البياني. |
| `php artisan down` / `php artisan up` | وضع الصيانة أثناء تحديث كبير. |
