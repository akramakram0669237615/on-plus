# OnPlus Backend Pro

## ما الذي يحتويه؟
- PHP 8.3 + PostgreSQL
- REST API JSON
- لوحة تحكم عربية RTL
- CRUD للتصنيفات والقنوات والمباريات والإشعارات والتحديثات والقائمة والإعدادات
- استيراد التصنيفات والمباريات من المصدر المحدد
- Docker و Render

## مهم جداً بخصوص بيانات قاعدة البيانات
لا تضع كلمة مرور PostgreSQL داخل المشروع أو GitHub أو ملف ZIP.
في Render:
1. افتح Web Service.
2. Environment.
3. أضف `DATABASE_URL`.
4. انسخ **Internal Database URL** من خدمة PostgreSQL نفسها مباشرة إلى متغير البيئة.

بما أن كلمة مرور قاعدة البيانات تم إرسالها سابقاً في المحادثة، قم بعمل **Rotate Password** في Render قبل النشر.

## إعداد القاعدة
بعد إنشاء PostgreSQL استورد:
```bash
psql "$DATABASE_URL" -f database/database.sql
```

## إنشاء المدير
```bash
php scripts/create_admin.php admin admin@example.com "كلمة-مرور-قوية"
```

## Render
- New Web Service
- اختر GitHub repository
- Environment: Docker
- أضف DATABASE_URL كمتغير سري
- Deploy

## API
- GET /api/health
- GET /api/app/config
- GET /api/sidebar
- GET /api/home
- GET /api/categories
- GET /api/categories/{id}/channels
- GET /api/channels
- GET /api/channels/{id}
- GET /api/matches
- GET /api/notifications/startup
- GET /api/update/check?version_code=1

## لوحة الإدارة
- /admin/login
- /admin

## الاستيراد الخارجي
المشروع يستورد من المصدر الذي طلبته:
- التصنيفات وبيانات القنوات الوصفية
- مباريات اليوم وبياناتها الوصفية

لا يقوم المستورد تلقائياً بنسخ روابط تشغيل محمية أو مفاتيح DRM أو بيانات تجاوز الحماية. أضف فقط روابط البث التي تملك حق استخدامها من لوحة التحكم.

## Import behavior
The importer copies editable public metadata and direct playback URLs present in the configured source. Extra non-secret fields are preserved in `raw_data` JSONB for review. DRM keys, license credentials, authorization headers, passwords, tokens, and similar secrets are deliberately not imported.
