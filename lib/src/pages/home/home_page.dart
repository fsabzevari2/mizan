// lib/src/pages/home/home_page.dart
// صفحه اصلی ساده (پس از ورود) — فایل جدا
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});
  @override
  Widget build(BuildContext context) {
    final auth = Provider.of<AuthProvider>(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mizan - خانه'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await auth.logout();
              if (context.mounted)
                Navigator.of(context).pushReplacementNamed('/login');
            },
          ),
        ],
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            children: [
              const SizedBox(height: 24),
              Text(
                'خوش آمدید',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 12),
              Text(
                'وضعیت لایسنس محلی: ${auth.licenseToken != null ? "فعال" : "غیرفعال"}',
              ),
              const SizedBox(height: 12),
              if (auth.licensePayload != null)
                Text(
                  'انقضا: ${auth.licensePayload?['expires_at'] != null ? DateTime.fromMillisecondsSinceEpoch(auth.licensePayload!['expires_at'] * 1000).toLocal().toString() : "نامشخص"}',
                ),
            ],
          ),
        ),
      ),
    );
  }
}
