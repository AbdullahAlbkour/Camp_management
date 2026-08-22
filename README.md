# نظام إدارة مخيمات لجوء محلي

نظام ويب إداري مبني بـ Laravel و MySQL و Blade HTML/CSS/JS، اعتمادًا على ملف التحليل المرفق.

## التشغيل

1. انسخ ملف البيئة:

```bash
cp .env.example .env
```

2. عدّل بيانات MySQL داخل `.env`:

```env
DB_DATABASE=camps_management
DB_USERNAME=root
DB_PASSWORD=
```

3. ثبّت اعتماديات Laravel وشغّل الهجرة والبيانات الأولية:

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

4. افتح:

```text
http://localhost:8000
```

## حسابات تجريبية

كل الحسابات تستخدم كلمة المرور:

```text
password
```

- `admin@camp.local`
- `manager@camp.local`
- `registration@camp.local`
- `housing@camp.local`
- `aid@camp.local`
- `medical@camp.local`
- `security@camp.local`

## الموديولات

- تسجيل الدخول والأدوار RBAC.
- إدارة المستخدمين.
- إدارة المخيمات والوحدات السكنية.
- تسجيل اللاجئين مع فحص تكرار أولي.
- ملف موحد للاجئ.
- إدارة الأسر.
- السكن والانتقالات مع سجل `residency_transfers`.
- الجهات الداعمة وأنواع وتوزيع المساعدات.
- الخدمات والسجلات الطبية.
- نقاط التفتيش وحركة الدخول والخروج.
- التقارير الأمنية.
- Dashboard و Charts.
- تقارير مع تصدير Excel/CSV وطباعة PDF من المتصفح.
- تنبيهات داخلية.
- سجل تدقيق Audit Logs.

## ملاحظات تقنية

- الواجهة عربية RTL بالكامل.
- السكن اختياري عند تسجيل اللاجئ، لكن المخيم الحالي مطلوب.
- رقم الوثيقة فريد إذا كان موجودًا، ويمكن تركه فارغًا.
- المساعدة ترتبط إما بلاجئ أو بأسرة، وليس الاثنين معًا.
- الحركة Entry/Exit لا تغير المخيم أو السكن، بل تغير حالة الوجود فقط.
- تفاصيل الطب والأمن محمية حسب الدور من جهة الواجهة والمسارات.
