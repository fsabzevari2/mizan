// lib/src/core/license/license_manager.dart
// اعتبارسنجی JWT RS256 به‌صورت آفلاین با استفاده از کتابخانه jose
import 'dart:convert';
import 'package:jose/jose.dart';
import 'package:flutter/services.dart' show rootBundle;

class LicenseManager {
  // public key را از فایل می‌خوانیم (public.pem در مسیر داده شده)
  static Future<JsonWebKey> _loadPublicKey() async {
    final pem = await rootBundle.loadString('lib/src/core/security/public.pem');
    final jwk = JsonWebKey.fromPem(pem);
    return jwk;
  }

  // اعتبارسنجی آفلاین JWT — برمی‌گرداند payload اگر معتبر باشد، در غیر این صورت null
  static Future<Map<String, dynamic>?> verifyJwtOffline(String token) async {
    try {
      final jwk = await _loadPublicKey();
      final jws = JsonWebSignature.fromCompactSerialization(token);
      final verified = jws.verify(jwk);
      if (!verified) return null;
      final payloadJson = jsonDecode(utf8.decode(jws.unverifiedPayload));
      // اگر expires_at وجود داشت بررسی کن (timestamp ثانیه)
      if (payloadJson is Map &&
          payloadJson.containsKey('expires_at') &&
          payloadJson['expires_at'] != null) {
        final exp = int.parse(payloadJson['expires_at'].toString());
        if (DateTime.now().toUtc().millisecondsSinceEpoch ~/ 1000 > exp) {
          return null;
        }
      }
      return Map<String, dynamic>.from(payloadJson);
    } catch (e) {
      return null;
    }
  }
}
