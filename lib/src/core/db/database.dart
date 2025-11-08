// lib/src/core/db/database.dart
// دیتابیس sqlite محلی با sqflite_common_ffi — ساختار ساده برای ذخیره درخواست‌های ارسالی و تنظیمات
import 'dart:async';
import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart';
import 'package:path_provider/path_provider.dart';
import 'dart:io';

class AppDatabase {
  static Database? _db;
  static Future<void> init() async {
    if (_db != null) return;
    final documents = await getApplicationDocumentsDirectory();
    final path = join(documents.path, 'mizan_app.db');
    _db = await openDatabase(
      path,
      version: 1,
      onCreate: (db, v) async {
        // جدول درخواست‌های pending/records
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
    );
  }

  // درج رکورد pending
  static Future<int> insertPendingRequest(Map<String, dynamic> item) async {
    return await _db!.insert('requests', item);
  }

  // ذخیره لایسنس محلی (جدید یا جایگزین)
  static Future<int> saveLocalLicense(Map<String, dynamic> item) async {
    // پاک کن و ذخیره کن (نسخه ساده)
    await _db!.delete('local_license');
    return await _db!.insert('local_license', item);
  }

  // گرفتن لایسنس محلی
  static Future<Map<String, dynamic>?> getLocalLicense() async {
    final rows = await _db!.query('local_license', limit: 1);
    if (rows.isEmpty) return null;
    return rows.first;
  }
}
