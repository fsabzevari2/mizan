// lib/src/core/db/database.dart
// دیتابیس sqlite محلی با sqflite_common_ffi — اکنون با پشتیبانی از ستون status و توابع مدیریت رکورد
// همه متدهای قبلی حفظ شده و متدهای جدید برای update/status اضافه شده‌اند.
// کامنت‌های مختصر فارسی برای هر بخش قرار دارد.

import 'dart:async';
import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart';
import 'package:path_provider/path_provider.dart';
import 'dart:io';

class AppDatabase {
  static Database? _db;

  // مقداردهی و ساخت فایل دیتابیس محلی
  static Future<void> init() async {
    if (_db != null) return;
    final documents = await getApplicationDocumentsDirectory();
    final path = join(documents.path, 'mizan_app.db');

    _db = await openDatabase(
      path,
      version: 1,
      onCreate: (db, v) async {
        // جدول درخواست‌های pending/records (اضافه کردن ستون status از ابتدا)
        await db.execute('''
          CREATE TABLE requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT,
            first_name TEXT,
            last_name TEXT,
            username TEXT,
            phone TEXT,
            store_name TEXT,
            device_hash TEXT,
            status TEXT DEFAULT 'pending',
            created_at INTEGER
          )
        ''');
        // جدول ساده برای ذخیره لایسنس محلی (برای نمایش)
        await db.execute('''
          CREATE TABLE local_license (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            license_key TEXT,
            license_token TEXT,
            issued_at INTEGER,
            expires_at INTEGER
          )
        ''');
      },
      onOpen: (db) async {
        // در صورتی که پایگاه قبلاً ایجاد شده باشد ولی ستون status را نداشته باشد،
        // آن را اضافه می‌کنیم (ALTER TABLE) — این تضمین سازگاری با نسخه‌های قدیمی است.
        final info = await db.rawQuery("PRAGMA table_info(requests)");
        final hasStatus = info.any((row) => row['name'] == 'status');
        if (!hasStatus) {
          try {
            await db.execute(
                "ALTER TABLE requests ADD COLUMN status TEXT DEFAULT 'pending'");
          } catch (e) {
            // ignore اگر ستون از قبل اضافه شده یا خطا را ثبت نمی‌کنیم
          }
        }
      },
    );
  }

  // درج رکورد pending (حاوی status = 'pending')
  static Future<int> insertPendingRequest(Map<String, dynamic> item) async {
    if (_db == null) await init();
    // مطمئن شویم فیلد created_at و status وجود دارد
    final insertItem = Map<String, dynamic>.from(item);
    if (!insertItem.containsKey('created_at')) {
      insertItem['created_at'] = DateTime.now().millisecondsSinceEpoch;
    }
    if (!insertItem.containsKey('status')) {
      insertItem['status'] = 'pending';
    }
    return await _db!.insert('requests', insertItem);
  }

  // گرفتن رکوردها با فیلتر اختیاری وضعیت
  static Future<List<Map<String, dynamic>>> getRequests(
      {String? status}) async {
    if (_db == null) await init();
    if (status != null && status.isNotEmpty) {
      return await _db!.query('requests',
          where: 'status = ?', whereArgs: [status], orderBy: 'created_at DESC');
    }
    return await _db!.query('requests', orderBy: 'created_at DESC');
  }

  // گرفتن یک رکورد بر اساس ایمیل یا device_hash (اولویت device_hash)
  static Future<Map<String, dynamic>?> getRequestByEmailOrDevice(
      {String? email, String? deviceHash}) async {
    if (_db == null) await init();
    String where = '';
    List args = [];
    if (deviceHash != null && deviceHash.isNotEmpty) {
      where = 'device_hash = ?';
      args = [deviceHash];
    } else if (email != null && email.isNotEmpty) {
      where = 'email = ?';
      args = [email];
    } else {
      return null;
    }
    final rows =
        await _db!.query('requests', where: where, whereArgs: args, limit: 1);
    if (rows.isEmpty) return null;
    return rows.first;
  }

  // به‌روزرسانی وضعیت رکوردها بر اساس ایمیل یا device_hash
  // برمی‌گرداند تعداد ردیف‌های بروزرسانی‌شده
  static Future<int> updateRequestStatusByEmailOrDevice(
      {String? email, String? deviceHash, required String status}) async {
    if (_db == null) await init();
    String where = '';
    List args = [];
    if (deviceHash != null && deviceHash.isNotEmpty) {
      where = 'device_hash = ?';
      args.add(deviceHash);
    }
    if (email != null && email.isNotEmpty) {
      if (where.isNotEmpty) where += ' OR ';
      where += 'email = ?';
      args.add(email);
    }
    if (where.isEmpty) return 0;
    return await _db!
        .update('requests', {'status': status}, where: where, whereArgs: args);
  }

  // حذف رکورد(ها) بر اساس ایمیل یا device_hash (برای پاکسازی تست)
  static Future<int> deleteRequestsByEmailOrDevice(
      {String? email, String? deviceHash}) async {
    if (_db == null) await init();
    String where = '';
    List args = [];
    if (deviceHash != null && deviceHash.isNotEmpty) {
      where = 'device_hash = ?';
      args.add(deviceHash);
    }
    if (email != null && email.isNotEmpty) {
      if (where.isNotEmpty) where += ' OR ';
      where += 'email = ?';
      args.add(email);
    }
    if (where.isEmpty) return 0;
    return await _db!.delete('requests', where: where, whereArgs: args);
  }

  // ذخیره لایسنس محلی (جدید یا جایگزین)
  static Future<int> saveLocalLicense(Map<String, dynamic> item) async {
    if (_db == null) await init();
    // پاک کن و ذخیره کن (نسخه ساده)
    await _db!.delete('local_license');
    return await _db!.insert('local_license', item);
  }

  // گرفتن لایسنس محلی
  static Future<Map<String, dynamic>?> getLocalLicense() async {
    if (_db == null) await init();
    final rows = await _db!.query('local_license', limit: 1);
    if (rows.isEmpty) return null;
    return rows.first;
  }

  // حذف لایسنس محلی (در صورت rejected یا نامعتبر شدن)
  static Future<int> deleteLocalLicense() async {
    if (_db == null) await init();
    return await _db!.delete('local_license');
  }
}
