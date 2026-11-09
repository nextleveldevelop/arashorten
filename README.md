# AraShorten

**A lightweight, secure, and self-hosted URL shortener built with PHP and MySQL.**

AraShorten allows users to create short links, customize their short codes, manage URLs, and monitor click activity through a clean dashboard. It also includes an administration panel, SEO tools, content pages, and privacy-conscious analytics.

---

# القسم الأول: العربية 🇸🇦

## نبذة عن المشروع

**AraShorten** هو تطبيق مفتوح المصدر لاختصار الروابط، مبني باستخدام PHP وMySQL، ويمكن استضافته على خوادم الاستضافة التقليدية التي تدعم PHP وقواعد بيانات MySQL.

يتيح التطبيق تحويل الروابط الطويلة إلى روابط قصيرة وسهلة المشاركة، مع إمكانية تخصيص الرموز المختصرة وإدارة الروابط ومتابعة إحصائيات النقرات.

يهدف المشروع إلى توفير نظام مستقل لاختصار الروابط، يمكن تثبيته وتشغيله على الاستضافة الخاصة بالمستخدم دون الاعتماد على خدمات اختصار الروابط الخارجية.

## المميزات

### اختصار الروابط
- إنشاء روابط مختصرة تلقائياً.
- إمكانية اختيار رمز مخصص للرابط.
- روابط قصيرة ونظيفة دون معاملات URL ظاهرة.
- دعم الرموز المخصصة من 3 إلى 32 حرفاً.
- دعم الأحرف الإنجليزية والأرقام والشرطة والشرطة السفلية.
- منع استخدام الكلمات المحجوزة الخاصة بالنظام.

### إدارة المستخدمين
- إنشاء حسابات جديدة.
- تسجيل الدخول والخروج.
- لوحة تحكم خاصة بكل مستخدم.
- إدارة الروابط المختصرة.
- حذف الروابط.
- إدارة الملف الشخصي.

### الإحصائيات
- عرض إجمالي الروابط.
- عرض إجمالي النقرات.
- إحصائيات النقرات لكل رابط.
- متابعة النشاط اليومي للنقرات.
- إحصائيات تراعي خصوصية الزوار.

**الخصوصية:** لا يجمع نظام تحليلات النقرات عناوين IP أو معرّفات الزوار. يتم تسجيل عدد النقرات وتوقيتها فقط. وقد تحتفظ شركة الاستضافة بسجلات خادم مستقلة عن التطبيق.

### لوحة الإدارة
- لوحة تحكم إدارية.
- إدارة المستخدمين.
- إدارة الروابط.
- تفعيل وتعطيل الروابط.
- إدارة الصفحات.
- إدارة رسائل التواصل.
- سجل العمليات الإدارية.
- التحكم في إعدادات التسجيل واختصار الروابط للزوار.

### إدارة المحتوى وتحسين محركات البحث
- إنشاء وتعديل الصفحات.
- محرر محتوى غني.
- إدارة عناوين الصفحات وأوصافها.
- إعدادات SEO.
- دعم Open Graph.
- إعدادات التحقق من Google وBing.
- إنشاء Sitemap تلقائياً.
- إنشاء robots.txt تلقائياً.

### الأمان
- استخدام PDO والاستعلامات المجهزة.
- حماية CSRF.
- تخزين كلمات المرور باستخدام password_hash.
- إدارة الجلسات بشكل آمن.
- تحديد معدلات الطلبات.
- حماية مسارات وملفات التطبيق الحساسة.
- منع الوصول المباشر إلى المجلدات الداخلية.
- التحقق من الروابط قبل اختصارها.
- تسجيل العمليات الإدارية.

## أمثلة على الروابط

| النوع | مثال |
|---|---|
| رابط تلقائي | `https://example.com/aB7xK9p` |
| رابط مخصص | `https://example.com/my-link` |
| رابط مخصص | `https://example.com/portfolio` |

## المتطلبات

- PHP 8.1 أو أحدث.
- MySQL أو MariaDB.
- امتداد PDO MySQL.
- امتداد OpenSSL.
- امتداد Mbstring.
- خادم Apache أو LiteSpeed مع دعم `.htaccess` و`mod_rewrite`.
- شهادة SSL موصى بها، وضرورية عند تفعيل HTTPS.
- استضافة تدعم إنشاء قواعد بيانات MySQL.

## طريقة التثبيت

### 1. تحميل المشروع

قم بتحميل المشروع من GitHub أو استنساخه:

```bash
git clone https://github.com/nextleveldevelop/arashorten.git
```
أو تحميله من https://github.com/nextleveldevelop/arashorten

### 2. رفع الملفات

ارفع ملفات المشروع إلى مجلد موقعك، مثلاً:

```text
public_html/
```

أو إلى مجلد فرعي:

```text
public_html/arashorten/
```

### 3. إنشاء قاعدة البيانات

من لوحة تحكم الاستضافة cPanel:

1. افتح MySQL Databases.
2. أنشئ قاعدة بيانات جديدة.
3. أنشئ مستخدم قاعدة بيانات.
4. امنح المستخدم جميع الصلاحيات المطلوبة على قاعدة البيانات.

### 4. تشغيل المثبت

افتح الرابط التالي في المتصفح:

```text
https://example.com/install.php
```

أدخل البيانات المطلوبة:

- اسم التطبيق.
- رابط التطبيق الأساسي.
- عنوان خادم قاعدة البيانات.
- اسم قاعدة البيانات.
- اسم مستخدم قاعدة البيانات.
- كلمة مرور قاعدة البيانات.
- اسم المدير.
- البريد الإلكتروني للمدير.
- كلمة مرور المدير.

سيقوم المثبت بإنشاء جداول قاعدة البيانات وإعداد حساب المدير وإنشاء ملف الإعدادات تلقائياً.

**ملاحظة:** لا يتطلب المثبت إدخال رقم منفذ قاعدة البيانات.

### 5. إكمال التثبيت

بعد نجاح التثبيت:

- سجّل الدخول بحساب المدير.
- افتح لوحة الإدارة.
- راجع إعدادات التطبيق.
- تأكد من عمل الروابط المختصرة.
- تأكد من تفعيل SSL وإعدادات الروابط النظيفة.

يقوم المثبت بإنشاء ملف `config.local.php` وقفل التثبيت لمنع تشغيله مرة أخرى.

## بنية المشروع

```text
arashorten/
│
├── .htaccess
├── index.php
├── install.php
├── redirect.php
├── login.php
├── register.php
├── dashboard.php
├── analytics.php
├── profile.php
│
├── admin/
│   ├── index.php
│   ├── users.php
│   ├── links.php
│   ├── settings.php
│   ├── pages.php
│   ├── page-edit.php
│   ├── messages.php
│   └── audit.php
│
├── actions/
│   ├── shorten.php
│   └── delete-url.php
│
├── includes/
│   ├── bootstrap.php
│   ├── config.php
│   ├── database.php
│   ├── security.php
│   ├── csrf.php
│   ├── auth.php
│   ├── functions.php
│   ├── url-service.php
│   ├── url-validator.php
│   ├── analytics.php
│   ├── seo.php
│   └── ...
│
├── database/
│   └── schema.sql
│
├── storage/
│
├── cron/
│
└── assets/
    ├── css/
    ├── js/
    └── vendor/
```

## إعدادات إضافية

يمكن تخصيص بعض حدود التطبيق من خلال قسم `limits` في ملف الإعدادات.

تشمل الخيارات:

- طول الرموز المختصرة التلقائية.
- الحد الأقصى لعدد الروابط لكل مستخدم.
- عدد عمليات الاختصار المسموح بها في الساعة.
- حدود محاولات تسجيل الدخول.
- مدة الجلسات.
- مدة الاحتفاظ بسجلات النقرات.
- مدة الاحتفاظ برسائل التواصل.

## مهمة التنظيف الدورية

يوفر المشروع سكربت تنظيف اختياري:

```text
cron/cleanup.php
```

يمكن تشغيله يومياً من خلال Cron Jobs في cPanel لتنظيف سجلات تحديد معدل الطلبات والبيانات القديمة وفق إعدادات الاحتفاظ.

## الأمان والخصوصية

- لا ترفع ملف `config.local.php` إلى GitHub.
- استخدم كلمات مرور قوية.
- استخدم HTTPS.
- احتفظ بنسخ احتياطية من قاعدة البيانات.
- لا تمنح صلاحيات الكتابة للمجلدات إلا عند الحاجة.
- راجع سياسة الخصوصية قبل نشر الخدمة للجمهور.

## المساهمة

المساهمات والاقتراحات وتحسينات الكود مرحب بها.

يمكنك فتح Issue للإبلاغ عن مشكلة أو اقتراح ميزة جديدة، أو إرسال Pull Request.

## الترخيص والحقوق

السكريبت مجاني مفتوح المصدر يمكنك التعديل عليه وتطويره وحمايته 
---

# Part Two: English 🇬🇧

## About AraShorten

**AraShorten** is a lightweight, secure, and self-hosted URL shortening application built with PHP and MySQL.

It allows users to transform long URLs into short, shareable links, create custom short codes, manage their links, and monitor click activity through a user dashboard.

AraShorten is designed to run on traditional web hosting environments, including shared hosting with cPanel, without requiring Node.js or a dedicated VPS.

The project also includes an administration panel, content management features, SEO tools, and privacy-conscious click analytics.

## Features

### URL Shortening
- Automatically generated short codes.
- Custom short URL aliases.
- Clean URLs without visible query parameters.
- Custom codes between 3 and 32 characters.
- Support for letters, numbers, hyphens, and underscores.
- Reserved application routes protection.
- Destination URL validation.

### User Management
- User registration.
- Secure login and logout.
- Individual user dashboards.
- URL management.
- URL deletion.
- User profile management.

### Analytics
- Total URL count.
- Total click count.
- Individual URL statistics.
- Daily click activity.
- Privacy-conscious click tracking.

**Privacy:** The application's click analytics do not collect visitor IP addresses or visitor identifiers. They record click counts and timestamps only. Hosting providers may maintain separate server logs.

### Administration Panel
- Administrative dashboard.
- User management.
- URL management.
- Enable and disable short links.
- Content page management.
- Contact message management.
- Administrative activity logs.
- Registration and guest-shortening controls.

### Content Management and SEO
- Create and edit content pages.
- Rich text editor.
- Custom page titles and descriptions.
- SEO configuration.
- Open Graph metadata.
- Google and Bing verification settings.
- Automatic XML sitemap generation.
- Automatic robots.txt generation.

### Security
- PDO prepared statements.
- CSRF protection.
- Secure password hashing.
- Secure session management.
- Request rate limiting.
- Protection of sensitive application files.
- Internal directory access restrictions.
- Destination URL validation.
- Administrative audit logging.

## Example Short URLs

| Type | Example |
|---|---|
| Automatically generated | `https://example.com/aB7xK9p` |
| Custom short code | `https://example.com/my-link` |
| Custom short code | `https://example.com/portfolio` |

Short URLs are internally resolved through Apache rewrite rules while keeping the clean URL visible in the visitor's browser.

## Requirements

- PHP 8.1 or newer.
- MySQL or MariaDB.
- PDO MySQL extension.
- OpenSSL extension.
- Mbstring extension.
- Apache or LiteSpeed with `.htaccess` and `mod_rewrite` support.
- SSL certificate recommended and required when HTTPS enforcement is enabled.
- Web hosting with MySQL database support.

## Installation

### 1. Download the Project

Clone the repository:

```bash
git clone https://github.com/nextleveldevelop/arashorten.git
```

Alternatively, download the ZIP archive from GitHub. From this page: https://github.com/nextleveldevelop/arashorten

### 2. Upload the Files

Upload the application files to your hosting account.

For example:

```text
public_html/
```

Or install it inside a subdirectory:

```text
public_html/arashorten/
```

### 3. Create a MySQL Database

Using cPanel:

1. Open MySQL Databases.
2. Create a new database.
3. Create a database user.
4. Assign the user the required privileges on the database.

### 4. Run the Installer

Open:

```text
https://example.com/install.php
```

Complete the installation form:

- Application name.
- Application URL.
- Database host.
- Database name.
- Database username.
- Database password.
- Administrator name.
- Administrator email.
- Administrator password.

The installer automatically creates the database tables, initializes application settings, creates the administrator account, and generates the local configuration file.

**Note:** The installer does not require a database port field.

### 5. Complete Installation

After successful installation:

- Log in using your administrator account.
- Open the administration panel.
- Review application settings.
- Test automatic and custom short URLs.
- Verify HTTPS and clean URL rewriting.

The installer creates `config.local.php` and an installation lock to prevent unauthorized reinstallation.

## Project Structure

```text
arashorten/
│
├── .htaccess
├── index.php
├── install.php
├── redirect.php
├── login.php
├── register.php
├── dashboard.php
├── analytics.php
├── profile.php
│
├── admin/
│   ├── index.php
│   ├── users.php
│   ├── links.php
│   ├── settings.php
│   ├── pages.php
│   ├── page-edit.php
│   ├── messages.php
│   └── audit.php
│
├── actions/
│   ├── shorten.php
│   └── delete-url.php
│
├── includes/
│   ├── bootstrap.php
│   ├── config.php
│   ├── database.php
│   ├── security.php
│   ├── csrf.php
│   ├── auth.php
│   ├── functions.php
│   ├── url-service.php
│   ├── url-validator.php
│   ├── analytics.php
│   ├── seo.php
│   └── ...
│
├── database/
│   └── schema.sql
│
├── storage/
│
├── cron/
│
└── assets/
    ├── css/
    ├── js/
    └── vendor/
```

## Configuration

Additional application limits can be customized through the `limits` section in the local configuration file.

Available configuration options include:

- Automatic short code length.
- Maximum URLs per user.
- User and guest shortening rate limits.
- Login attempt limits.
- Session expiration.
- Click analytics retention.
- Contact message retention.

For manual configuration, use `config.sample.php` as a reference and create `config.local.php`.

Never commit your local configuration file or database credentials to a public repository.

## Scheduled Maintenance

AraShorten includes an optional cleanup script:

```text
cron/cleanup.php
```

It can be scheduled through cPanel Cron Jobs to periodically clean expired rate-limit records and optionally remove old click analytics and contact messages according to the configured retention periods.

## Security and Privacy Recommendations

- Never publish `config.local.php`.
- Use strong administrator passwords.
- Enable HTTPS.
- Maintain regular database backups.
- Restrict write permissions to directories that require them.
- Review your privacy policy before operating a public URL-shortening service.

## Contributing

Contributions, bug reports, and feature suggestions are welcome.

You can:

- Open an Issue to report a problem.
- Suggest improvements.
- Submit a Pull Request.

Please ensure that proposed changes follow the existing security and coding practices.

## License

MIT License

Copyright (c) 2026 NEXT LeVeL Develop

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.




Made by NEXT LeVel Develop

https://github.com/nextleveldevelop/

---

**AraShorten — Your links, your platform, your control.**
