// lib/main.dart
// ورودی اصلی اپ؛ فقط bootstrap و تنظیم Provider
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'src/app.dart';
import 'src/providers/license_provider.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(
    MultiProvider(
      providers: [ChangeNotifierProvider(create: (_) => LicenseProvider())],
      child: MyApp(),
    ),
  );
}
