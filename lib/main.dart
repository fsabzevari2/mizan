// lib/main.dart
// ورودی برنامه؛ تنظیمات اولیه (sqflite_ffi، Provider) و راه‌اندازی اپ
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'src/app.dart';
import 'src/providers/auth_provider.dart';
import 'src/core/db/database.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // اگر روی ویندوز اجرا می‌شود، sqflite_ffi را فعال کن
  if (Platform.isWindows || Platform.isLinux || Platform.isMacOS) {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
  }

  // باز کردن دیتابیس محلی
  await AppDatabase.init();

  runApp(
    MultiProvider(
      providers: [ChangeNotifierProvider(create: (_) => AuthProvider())],
      child: const MyApp(),
    ),
  );
}
