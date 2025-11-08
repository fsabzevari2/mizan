// lib/src/providers/auth_provider.dart
// Provider مدیریت احراز هویت و لایسنس — جدا و کامل
import 'dart:convert';
import 'package:flutter/material.dart';
import '../core/api/api_service.dart';
import '../core/device/device_id_windows.dart';
import '../core/license/license_manager.dart';
import '../core/db/database.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class _Result {
  final bool success;
  final String message;
  _Result(this.success, this.message);
}

class AuthProvider extends ChangeNotifier {
  final FlutterSecureStorage _secure = const FlutterSecureStorage();
  String? licenseToken;
  Map<String, dynamic>? licensePayload;

  AuthProvider() {
    _loadFromStorage();
  }

  Future<void> _loadFromStorage() async {
    licenseToken = await _secure.read(key: 'license_token');
    if (licenseToken != null) {
      final payload = await LicenseManager.verifyJwtOffline(licenseToken!);
      if (payload != null) {
        licensePayload = payload;
      } else {
        // توکن محلی نامعتبر شده
        licenseToken = null;
        await _secure.delete(key: 'license_token');
      }
    }
    notifyListeners();
  }

  // ثبت‌نام: ارسال درخواست به سرور (وردپرس)
  Future<_Result> registerUser({
    required String email,
    required String firstName,
    required String lastName,
    required String username,
    required String phone,
    required String storeName,
  }) async {
    final deviceHash = await DeviceId.getDeviceHash();
    final res = await ApiService.register(
      email: email,
      firstName: firstName,
      lastName: lastName,
      username: username,
      phone: phone,
      storeName: storeName,
      deviceHash: deviceHash,
    );
    if (res['success'] == true) {
      // ذخیره محلی رکورد درخواست برای نمایش آفلاین
      await AppDatabase.insertPendingRequest({
        'email': email,
        'first_name': firstName,
        'last_name': lastName,
        'username': username,
        'phone': phone,
        'store_name': storeName,
        'device_hash': deviceHash,
        'created_at': DateTime.now().millisecondsSinceEpoch,
      });
      return _Result(true, res['message'] ?? 'درخواست ارسال شد.');
    }
    return _Result(false, res['message']?.toString() ?? 'خطا در ثبت‌نام');
  }

  // ورود/بررسی لایسنس با ایمیل یا deviceHash
  Future<_Result> loginWithEmail(String email) async {
    final deviceHash = await DeviceId.getDeviceHash();
    final res = await ApiService.check(deviceHash: deviceHash, email: email);
    if (res['success'] == true && res['license_token'] != null) {
      final token = res['license_token'] as String;
      // اعتبارسنجی آفلاین توکن با public key
      final payload = await LicenseManager.verifyJwtOffline(token);
      if (payload != null) {
        // ذخیره امن توکن
        await _secure.write(key: 'license_token', value: token);
        licenseToken = token;
        licensePayload = payload;
        notifyListeners();
        return _Result(true, 'ورود موفق. لایسنس معتبر است.');
      } else {
        return _Result(false, 'توکن دریافت شده نامعتبر یا منقضی است.');
      }
    } else {
      final msg = res['message']?.toString() ?? 'لایسنس فعال یافت نشد.';
      return _Result(false, msg);
    }
  }

  Future<void> logout() async {
    licenseToken = null;
    licensePayload = null;
    await _secure.delete(key: 'license_token');
    notifyListeners();
  }
}
