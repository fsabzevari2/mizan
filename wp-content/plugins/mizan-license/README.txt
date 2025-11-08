Mizan License Manager - راه‌انداز سریع (نسخه JWT RS256)

مراحل نصب و راه‌اندازی:
1) فولدر mizan-license را در مسیر wp-content/plugins/ قرار دهید.
2) افزونه را از منوی افزونه‌ها در وردپرس فعال کنید.
3) مطمئن شوید SSL (HTTPS) فعال است.
4) فایل‌های keys/private.pem و keys/public.pem داخل پوشه افزونه قرار دارند. توصیهٔ امنیتی: فایل private.pem را به مسیر امن خارج از webroot انتقال دهید و سپس constant MIZAN_LICENSE_PRIVATE_KEY_PATH در فایل اصلی افزونه را به مسیر جدید تغییر دهید.
5) اپ باید از public.pem برای اعتبارسنجی آفلاین JWT استفاده کند (به اپ بدهید).

Endpoints (مثال مصرف از اپ):
- ثبت درخواست: POST https://cofeclick.ir/wp-json/mizan/v1/register
  body JSON: { "email","first_name","last_name","username","phone","store_name","device_hash" }

- بررسی وضعیت و گرفتن توکن: POST https://cofeclick.ir/wp-json/mizan/v1/check
  body JSON: { "device_hash": "..." } یا { "email": "..." }
  response (در صورت فعال بودن): { license_key, license_token, expires_at, issued_at }

- اعتبارسنجی آنلاین JWT (اختیاری): POST https://cofeclick.ir/wp-json/mizan/v1/validate
  body JSON: { "license_token": "..." }

جریان کار پیشنهادی:
- کاربر در اپ ثبت‌نام می‌کند -> درخواست به /register ارسال می‌شود (status = pending)
- در وردپرس به منوی "Mizan Licenses" بروید، درخواست را تأیید کنید (دکمه تأیید)
- افزونه یک کلید لایسنس و یک توکن JWT امضاشده تولید و به ایمیل کاربر ارسال می‌کند؛ اعتبار پیش‌فرض 14 روز است
- اپ توکن را دریافت و با public key داخل اپ صحت‌سنجی (آفلاین) می‌کند و فعال می‌شود.

نکات امنیتی:
- private.pem را هرگز در مسیر عمومی قرار ندهید؛ آن را در فولدر خارج از public_html قرار دهید و مسیر را در MIZAN_LICENSE_PRIVATE_KEY_PATH تنظیم کنید.
- public.pem را داخل اپ قرار دهید (lib/src/core/security/public.pem) و در ساخت production از obfuscation استفاده کنید.
- این نسخه برای شروع و تست مناسب است؛ برای مصونیت بیشتر از کرک در آینده بخش‌های حساس (مثل خواندن device_id و بررسی امضا) را به native plugin منتقل کنید.